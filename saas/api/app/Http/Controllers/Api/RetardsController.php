<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AccessibleEnseignants;
use App\Models\Enseignant;
use App\Services\BilanIndividuel;
use App\Services\HoraireAttendu;
use App\Services\PonctualiteCalculator;
use App\Services\RetardCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Retards & bilans (§4.2) — seuils de tolérance, bilans PDF. */
class RetardsController extends Controller
{
    use AccessibleEnseignants;

    public function parametres(RetardCalculator $retards, PonctualiteCalculator $ponctualite)
    {
        return response()->json([
            'tolerance_minutes' => $retards->toleranceMinutes(),
            'coefficient_minutes' => $ponctualite->coefficientMinutes(),
        ]);
    }

    public function definirParametres(Request $request, RetardCalculator $retards, PonctualiteCalculator $ponctualite)
    {
        $data = $request->validate([
            'tolerance_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'coefficient_minutes' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);
        $retards->definirTolerance($data['tolerance_minutes']);

        if (isset($data['coefficient_minutes'])) {
            $ponctualite->definirCoefficientMinutes($data['coefficient_minutes']);
        }

        return response()->json([
            'tolerance_minutes' => $retards->toleranceMinutes(),
            'coefficient_minutes' => $ponctualite->coefficientMinutes(),
        ]);
    }

    /** GET /api/retards?debut=&fin=&section= — liste des retards sur une période. */
    public function index(Request $request, RetardCalculator $retards, HoraireAttendu $horaires, PonctualiteCalculator $ponctualite)
    {
        [$debut, $fin, $enseignants] = $this->periodeEtEnseignants($request);

        $lignes = $enseignants
            ->map(fn (Enseignant $enseignant) => $this->ligneBilanCumule($enseignant, $debut, $fin, $retards, $horaires, $ponctualite))
            ->sortBy(fn (array $ligne) => mb_strtolower($ligne['nom']), SORT_NATURAL)
            ->values();

        return response()->json($lignes);
    }

    /** GET /api/retards/classement-ponctualite?debut=&fin=&section= — classement des enseignants par ponctualité. */
    public function classementPonctualite(Request $request, RetardCalculator $retards, HoraireAttendu $horaires, PonctualiteCalculator $ponctualite)
    {
        [$debut, $fin, $enseignants] = $this->periodeEtEnseignants($request);

        if (mb_strtolower(trim((string) $request->query('section'))) !== HoraireAttendu::SECTION_ADMINISTRATIVE) {
            $enseignants = $enseignants->reject(fn (Enseignant $enseignant) => $horaires->estAdministratif($enseignant));
        }

        $classement = $enseignants
            ->map(fn (Enseignant $enseignant) => $this->ligneBilanCumule($enseignant, $debut, $fin, $retards, $horaires, $ponctualite))
            ->sortBy(fn (array $ligne) => [-$ligne['score_ponctualite'], mb_strtolower($ligne['nom'])])
            ->values();

        return response()->json($classement);
    }

    /** GET /api/retards/bilan-cumule?debut=&fin=&section=&tri= — bilan PDF de tous les enseignants du périmètre. */
    public function bilanCumule(Request $request, RetardCalculator $retards, HoraireAttendu $horaires, PonctualiteCalculator $ponctualite)
    {
        [$debut, $fin, $enseignants] = $this->periodeEtEnseignants($request);

        // Le personnel administratif n'apparaît pas sur le bilan des enseignants,
        // mais la fiche de la section « Administration » doit le lister en entier.
        if (mb_strtolower(trim((string) $request->query('section'))) !== HoraireAttendu::SECTION_ADMINISTRATIVE) {
            $enseignants = $enseignants->reject(fn(Enseignant $enseignant) => $horaires->estAdministratif($enseignant));
        }

        $lignes = $enseignants->map(fn(Enseignant $enseignant) => $this->ligneBilanCumule($enseignant, $debut, $fin, $retards, $horaires, $ponctualite));

        // Tri par score de ponctualité (classement) si demandé, sinon par nom.
        $data = $request->query('tri') === 'ponctualite'
            ? $lignes->sortBy(fn(array $ligne) => [-$ligne['score_ponctualite'], mb_strtolower($ligne['nom'])])->values()->all()
            : $lignes->sortBy(fn(array $ligne) => mb_strtolower($ligne['nom']), SORT_NATURAL)->values()->all();

        $pdf = Pdf::loadView('pdf.retards-cumule', [
            'data' => $data,
            'mois' => $debut->format('m'),
            'annee' => $debut->year,
            'classement' => $request->query('tri') === 'ponctualite',
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

    private function ligneBilanCumule(Enseignant $enseignant, Carbon $debut, Carbon $fin, RetardCalculator $retards, HoraireAttendu $horaires, PonctualiteCalculator $ponctualite): array
    {
        $emplois = $enseignant->emploiDuTemps()->get();
        $presences = $enseignant->presences()->whereBetween('date', [$debut->toDateString(), $fin->toDateString()])->get()->keyBy(fn($presence) => $presence->date->toDateString());
        $signalements = $enseignant->signalements()->whereDate('date', '<=', $fin->toDateString())->get();
        $result = ['enseignant_id' => $enseignant->id, 'nom' => $enseignant->nom, 'tel' => $enseignant->tel, 'matricule' => $enseignant->matricule, 'specialite' => $enseignant->section, 'section' => $enseignant->section, 'nb_jours_retard' => 0, 'total_retard_minutes' => 0, 'nb_jours_anticipation' => 0, 'total_anticipation_minutes' => 0, 'nb_jours_absence' => 0, 'periodes_absence' => 0, 'periodes_presence' => 0, 'periodes_totales' => 0];
        $joursAttendus = $joursValides = $joursPonctuels = 0;
        for ($date = $debut->copy()->startOfDay(); $date->lte($fin) && !$horaires->estAVenir($date); $date->addDay()) {
            // Enseignant : jours avec cours ; personnel administratif : journée
            // de travail fixe, sans emploi du temps (voir HoraireAttendu).
            $plage = $horaires->plage($enseignant, $date, $emplois);
            if (! $plage) continue;
            $presence = $presences->get($date->toDateString());
            $signale = $signalements->contains(fn($item) => $item->couvre($date));
            $joursAttendus++;
            if ($signale || $presence?->heure_arrivee || $presence?->heure_depart) $joursValides++;
            $periodes = max(1, (int) ceil($plage['minutes'] / 40));
            $finPrevue = Carbon::parse($date->toDateString() . ' ' . $plage['heure_fin']);
            $retard = $presence?->heure_arrivee ? ($retards->minutesDeRetard($enseignant, $presence) ?? 0) : 0;
            $anticipation = $presence?->heure_depart ? max(0, (int) floor(($finPrevue->timestamp - $presence->heure_depart->timestamp) / 60)) : 0;
            $absent = !$signale && (!$presence || (!$presence->heure_arrivee && !$presence->heure_depart));
            if (!$absent && $retard === 0) $joursPonctuels++;
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

        return [...$result, ...$ponctualite->calculer($joursAttendus, $joursPonctuels, $result['total_retard_minutes'])];
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

}
