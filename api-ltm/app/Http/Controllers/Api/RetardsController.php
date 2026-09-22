<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AccessibleEnseignants;
use App\Models\Enseignant;
use App\Models\Presence;
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
    public function bilanCumule(Request $request, RetardCalculator $retards)
    {
        [$debut, $fin, $enseignants] = $this->periodeEtEnseignants($request);

        $data = $enseignants->map(fn(Enseignant $enseignant) => $this->ligneBilanCumule($enseignant, $debut, $fin, $retards))
            ->sortByDesc('periodes_totales')->values()->all();

        $pdf = Pdf::loadView('pdf.retards-cumule', [
            'data' => $data,
            'mois' => $debut->format('m'),
            'annee' => $debut->year,
        ]);

        return $pdf->download("bilan-retards-{$debut->toDateString()}-{$fin->toDateString()}.pdf");
    }

    /** GET /api/retards/bilan/{enseignant}?debut=&fin= — fiche individuelle PDF. */
    public function bilanIndividuel(Request $request, Enseignant $enseignant, RetardCalculator $retards)
    {
        abort_unless($this->peutAccederA($request->user(), $enseignant), 403);

        $debut = Carbon::parse($request->query('debut', now()->startOfMonth()));
        $fin = Carbon::parse($request->query('fin', now()->endOfMonth()));

        $fiche = $this->donneesFiche($enseignant, $debut, $fin, $retards);

        $pdf = Pdf::loadView('pdf.retards-individuel', [
            ...$fiche,
            'debut' => $debut,
            'fin' => $fin,
        ]);

        return $pdf->download("bilan-retards-{$enseignant->matricule}.pdf");
    }

    private function donneesFiche(Enseignant $enseignant, Carbon $debut, Carbon $fin, RetardCalculator $retards): array
    {
        $emplois = $enseignant->emploiDuTemps()->with(['discipline', 'classe'])->orderBy('jour')->orderBy('heure_debut')->get();
        $emploiParJour = $emplois->groupBy(fn($emploi) => $this->nomJour($emploi->jour));
        $presences = $enseignant->presences()
            ->whereBetween('date', [$debut->toDateString(), $fin->toDateString()])->get()
            ->keyBy(fn($presence) => $presence->date->toDateString());
        $signalements = $enseignant->signalements()
            ->whereDate('date', '<=', $fin->toDateString())->get();
        $details = [];
        $joursAttendus = $presencesValides = 0;
        $totalRetard = $totalAnticipation = $totalPeriodesPresence = $totalPeriodesAbsence = 0;

        for ($date = $debut->copy(); $date->lte($fin); $date->addDay()) {
            $coursDuJour = $emplois->where('jour', $date->isoWeekday())->values();
            if ($coursDuJour->isEmpty()) continue;
            $premierCours = $coursDuJour->first();
            $dernierCours = $coursDuJour->sortBy('heure_fin')->last();
            $minutesPrevues = $coursDuJour->sum(fn($cours) => Carbon::parse($cours->heure_debut)->diffInMinutes(Carbon::parse($cours->heure_fin)));
            $periodesPrevues = max(1, (int) ceil($minutesPrevues / 40));
            $presence = $presences->get($date->toDateString());
            $signalement = $signalements->first(fn($item) => $date->between($item->date, $item->date->copy()->addDays(max(0, $item->duree_jours - 1))));
            $futur = $date->isFuture();
            $heureDebut = Carbon::parse($date->toDateString() . ' ' . $premierCours->heure_debut);
            $heureFin = Carbon::parse($date->toDateString() . ' ' . $dernierCours->heure_fin);
            $retard = $presence?->heure_arrivee ? $retards->minutesDeRetard($enseignant, $presence) ?? 0 : 0;
            $anticipation = $presence?->heure_depart ? max(0, (int) floor(($heureFin->timestamp - $presence->heure_depart->timestamp) / 60)) : 0;
            $signale = $signalement !== null;
            $absent = !$futur && !$signale && (!$presence || (!$presence->heure_arrivee && !$presence->heure_depart));
            $periodesRattraper = $absent ? $periodesPrevues : (int) ceil($anticipation / 40);
            $valide = $signale || (bool) ($presence?->heure_arrivee || $presence?->heure_depart);
            $joursAttendus++;
            if ($valide && !$futur) $presencesValides++;
            if (!$futur) {
                $totalRetard += $retard;
                $totalAnticipation += $anticipation;
                if ($absent) $totalPeriodesAbsence += $periodesPrevues;
                else $totalPeriodesPresence += $periodesRattraper;
            }
            $details[] = [
                'date' => $date->format('d/m/Y'),
                'jour' => $this->nomJour($date->isoWeekday()),
                'nb_cours' => $coursDuJour->count(),
                'heure_debut_prevue' => $premierCours->heure_debut,
                'heure_fin_prevue' => $dernierCours->heure_fin,
                'heure_arrivee' => $presence?->heure_arrivee?->format('H:i'),
                'heure_depart' => $presence?->heure_depart?->format('H:i'),
                'retard_minutes' => $retard,
                'anticipation_minutes' => $anticipation,
                'periodes_a_rattraper' => $periodesRattraper,
                'futur' => $futur,
                'absent' => $absent,
                'est_signale' => $signale,
                'type_signalement' => $signalement?->motif,
            ];
        }

        return [
            'enseignant' => $enseignant,
            'mois' => $debut->format('m'),
            'annee' => $debut->year,
            'emploi_par_jour' => $emploiParJour,
            'details' => $details,
            'total_retard_minutes' => $totalRetard,
            'total_anticipation_minutes' => $totalAnticipation,
            'total_periodes_a_rattraper' => $totalPeriodesPresence,
            'total_periodes_absence' => $totalPeriodesAbsence,
            'jours_attendus' => $joursAttendus,
            'presences_valides' => $presencesValides,
            'taux_assiduite' => $joursAttendus > 0 ? $presencesValides / $joursAttendus * 100 : 0,
        ];
    }

    private function nomJour(int $jour): string
    {
        return [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'][$jour] ?? 'Inconnu';
    }

    private function ligneBilanCumule(Enseignant $enseignant, Carbon $debut, Carbon $fin, RetardCalculator $retards): array
    {
        $emplois = $enseignant->emploiDuTemps()->get();
        $presences = $enseignant->presences()->whereBetween('date', [$debut->toDateString(), $fin->toDateString()])->get()->keyBy(fn($presence) => $presence->date->toDateString());
        $signalements = $enseignant->signalements()->whereDate('date', '<=', $fin->toDateString())->get();
        $result = ['nom' => $enseignant->nom, 'tel' => $enseignant->tel, 'matricule' => $enseignant->matricule, 'specialite' => $enseignant->section, 'nb_jours_retard' => 0, 'total_retard_minutes' => 0, 'nb_jours_anticipation' => 0, 'total_anticipation_minutes' => 0, 'nb_jours_absence' => 0, 'periodes_absence' => 0, 'periodes_presence' => 0, 'periodes_totales' => 0];
        for ($date = $debut->copy(); $date->lte($fin) && !$date->isFuture(); $date->addDay()) {
            $cours = $emplois->where('jour', $date->isoWeekday())->values();
            if ($cours->isEmpty()) continue;
            $presence = $presences->get($date->toDateString());
            $signale = $signalements->first(fn($item) => $date->between($item->date, $item->date->copy()->addDays(max(0, $item->duree_jours - 1)))) !== null;
            $minutesPrevues = $cours->sum(fn($item) => Carbon::parse($item->heure_debut)->diffInMinutes(Carbon::parse($item->heure_fin)));
            $periodes = max(1, (int) ceil($minutesPrevues / 40));
            $dernierCours = $cours->sortBy('heure_fin')->last();
            $finPrevue = Carbon::parse($date->toDateString() . ' ' . $dernierCours->heure_fin);
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
        return $result;
    }

    private function periodeEtEnseignants(Request $request): array
    {
        $debut = Carbon::parse($request->query('debut', now()->startOfMonth()));
        $fin = Carbon::parse($request->query('fin', now()->endOfMonth()));

        $enseignants = $this->enseignantsAccessibles($request->user())
            ->when($request->query('section'), function ($q, $section) {
                $normalized = mb_strtolower((string) $section);
                $sections = $normalized === 'générale'
                    ? ['générale', 'enseignement générale', 'enseignement général']
                    : [$normalized];

                return $q->whereRaw(
                    'LOWER(section) IN (' . implode(',', array_fill(0, count($sections), '?')) . ')',
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
