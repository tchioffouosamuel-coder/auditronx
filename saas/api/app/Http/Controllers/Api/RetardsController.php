<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AccessibleEnseignants;
use App\Models\Enseignant;
use App\Models\Presence;
use App\Services\BilanIndividuel;
use App\Services\HoraireAttendu;
use App\Services\RetardCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Retards & bilans (§4.2) — seuils de tolérance, bilans PDF. */
class RetardsController extends Controller
{
    use AccessibleEnseignants;

    public function parametres(RetardCalculator $retards)
    {
        return response()->json(['tolerance_minutes' => $retards->toleranceMinutes()]);
    }

    public function definirParametres(Request $request, RetardCalculator $retards)
    {
        $data = $request->validate(['tolerance_minutes' => ['required', 'integer', 'min:0', 'max:120']]);
        $retards->definirTolerance($data['tolerance_minutes']);

        return response()->json(['tolerance_minutes' => $retards->toleranceMinutes()]);
    }

    /** GET /api/retards?debut=&fin=&section= — liste des retards sur une période. */
    public function index(Request $request, RetardCalculator $retards)
    {
        [$debut, $fin, $enseignants] = $this->periodeEtEnseignants($request);

        $lignes = $this->calculerRetards($enseignants, $debut, $fin, $retards);

        return response()->json($lignes->values());
    }

    /** GET /api/retards/bilan-cumule?debut=&fin=&section= — bilan PDF de tous les enseignants du périmètre. */
    public function bilanCumule(Request $request, RetardCalculator $retards, HoraireAttendu $horaires)
    {
        [$debut, $fin, $enseignants] = $this->periodeEtEnseignants($request);

        // Le personnel administratif n'apparaît pas sur le bilan des enseignants,
        // mais la fiche de la section « Administration » doit le lister en entier.
        if (mb_strtolower(trim((string) $request->query('section'))) !== HoraireAttendu::SECTION_ADMINISTRATIVE) {
            $enseignants = $enseignants->reject(fn(Enseignant $enseignant) => $horaires->estAdministratif($enseignant));
        }

        $data = $enseignants->map(fn(Enseignant $enseignant) => $this->ligneBilanCumule($enseignant, $debut, $fin, $retards, $horaires))
            ->sortBy(fn(array $ligne) => mb_strtolower($ligne['nom']), SORT_NATURAL)
            ->values()->all();

        $pdf = Pdf::loadView('pdf.retards-cumule', [
            'data' => $data,
            'mois' => $debut->format('m'),
            'annee' => $debut->year,
        ]);

        return $pdf->download("bilan-retards-{$debut->toDateString()}-{$fin->toDateString()}.pdf");
    }

    /** GET /api/retards/bilan/{enseignant}?debut=&fin= — fiche individuelle PDF. */
    public function bilanIndividuel(Request $request, Enseignant $enseignant, BilanIndividuel $bilan)
    {
        abort_unless($this->peutAccederA($request->user(), $enseignant), 403);

        $debut = Carbon::parse($request->query('debut', now()->startOfMonth()));
        $fin = Carbon::parse($request->query('fin', now()->endOfMonth()));

        $fiche = $bilan->donnees($enseignant, $debut, $fin);

        $pdf = Pdf::loadView('pdf.retards-individuel', [
            ...$fiche,
            'debut' => $debut,
            'fin' => $fin,
        ]);

        return $pdf->download("bilan-retards-{$enseignant->matricule}.pdf");
    }

    private function ligneBilanCumule(Enseignant $enseignant, Carbon $debut, Carbon $fin, RetardCalculator $retards, HoraireAttendu $horaires): array
    {
        $emplois = $enseignant->emploiDuTemps()->get();
        $presences = $enseignant->presences()->whereBetween('date', [$debut->toDateString(), $fin->toDateString()])->get()->keyBy(fn($presence) => $presence->date->toDateString());
        $signalements = $enseignant->signalements()->whereDate('date', '<=', $fin->toDateString())->get();
        $result = ['nom' => $enseignant->nom, 'tel' => $enseignant->tel, 'matricule' => $enseignant->matricule, 'specialite' => $enseignant->section, 'nb_jours_retard' => 0, 'total_retard_minutes' => 0, 'nb_jours_anticipation' => 0, 'total_anticipation_minutes' => 0, 'nb_jours_absence' => 0, 'periodes_absence' => 0, 'periodes_presence' => 0, 'periodes_totales' => 0];
        $joursAttendus = $joursValides = 0;
        for ($date = $debut->copy(); $date->lte($fin) && !$date->isFuture(); $date->addDay()) {
            // Enseignant : jours avec cours ; personnel administratif : journée
            // de travail fixe, sans emploi du temps (voir HoraireAttendu).
            $plage = $horaires->plage($enseignant, $date, $emplois);
            if (! $plage) continue;
            $presence = $presences->get($date->toDateString());
            $signale = $signalements->first(fn($item) => $date->between($item->date, $item->date->copy()->addDays(max(0, $item->duree_jours - 1)))) !== null;
            $joursAttendus++;
            if ($signale || $presence?->heure_arrivee || $presence?->heure_depart) $joursValides++;
            $periodes = max(1, (int) ceil($plage['minutes'] / 40));
            $finPrevue = Carbon::parse($date->toDateString() . ' ' . $plage['heure_fin']);
            $retard = $presence?->heure_arrivee ? ($retards->minutesDeRetard($enseignant, $presence) ?? 0) : 0;
            $anticipation = $presence?->heure_depart ? max(0, (int) floor(($finPrevue->timestamp - $presence->heure_depart->timestamp) / 60)) : 0;
            $absent = !$signale && (!$presence || (!$presence->heure_arrivee && !$presence->heure_depart));
            if ($retard > 0) {
                $result['nb_jours_retard']++;
                $result['total_retard_minutes'] += $retard;
            }
            if ($anticipation > 0) {
                $result['nb_jours_anticipation']++;
                $result['total_anticipation_minutes'] += $anticipation;
            }
            if ($absent) {
                $result['nb_jours_absence']++;
                $result['periodes_absence'] += $periodes;
            } else {
                $result['periodes_presence'] += (int) ceil($anticipation / 40);
            }
        }
        $result['periodes_totales'] = $result['periodes_presence'] + $result['periodes_absence'];
        $result['taux_assiduite'] = $joursAttendus > 0 ? round($joursValides / $joursAttendus * 100, 1) : 0;
        return $result;
    }

    private function periodeEtEnseignants(Request $request): array
    {
        $debut = Carbon::parse($request->query('debut', now()->startOfMonth()));
        $fin = Carbon::parse($request->query('fin', now()->endOfMonth()));

        $enseignants = $this->enseignantsAccessibles($request->user())
            ->when($request->query('section'), function ($q, $section) {
                $normalized = mb_strtolower(trim((string) $section));
                $sections = $normalized === 'générale'
                    ? ['générale', 'enseignement générale', 'enseignement général']
                    : [$normalized];

                return $q->whereRaw(
                    'LOWER(TRIM(section)) IN (' . implode(',', array_fill(0, count($sections), '?')) . ')',
                    $sections,
                );
            })
            ->get();

        return [$debut, $fin, $enseignants];
    }

    private function calculerRetards($enseignants, Carbon $debut, Carbon $fin, RetardCalculator $retards)
    {
        $presences = Presence::whereBetween('date', [$debut->toDateString(), $fin->toDateString()])
            ->whereIn('enseignant_id', $enseignants->pluck('id'))
            ->whereNotNull('heure_arrivee')
            ->get()
            ->groupBy('enseignant_id');

        return $enseignants->map(function (Enseignant $enseignant) use ($presences, $retards) {
            $minutesTotal = 0;
            $joursRetard = 0;

            foreach ($presences->get($enseignant->id, collect()) as $presence) {
                $minutes = $retards->minutesDeRetard($enseignant, $presence);

                if ($minutes) {
                    $minutesTotal += $minutes;
                    $joursRetard++;
                }
            }

            return [
                'enseignant_id' => $enseignant->id,
                'nom' => $enseignant->nom,
                'matricule' => $enseignant->matricule,
                'section' => $enseignant->section,
                'jours_retard' => $joursRetard,
                'minutes_retard_total' => $minutesTotal,
            ];
        });
    }
}
