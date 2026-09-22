<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AccessibleEnseignants;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\Presence;
use App\Services\RetardCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Tableau de bord (§4.2) — KPIs du jour et classements par section. */
class DashboardController extends Controller
{
    use AccessibleEnseignants;

    public function index(Request $request, RetardCalculator $retards)
    {
        $date = $request->query('date') ? Carbon::parse($request->query('date')) : now();

        $enseignants = $this->enseignantsAccessibles($request->user())->get();
        $emploisDuJour = EmploiDuTemps::with(['classe', 'discipline'])
            ->where('jour', $date->isoWeekday())
            ->whereIn('enseignant_id', $enseignants->pluck('id'))
            ->orderBy('heure_debut')
            ->get()
            ->groupBy('enseignant_id');
        $planifies = $enseignants->filter(fn(Enseignant $e) => $emploisDuJour->has($e->id))->values();
        $presencesDuJour = Presence::where('date', $date->toDateString())
            ->whereIn('enseignant_id', $planifies->pluck('id'))
            ->get()
            ->keyBy('enseignant_id');

        $scannes = [];
        $absents = [];
        $retardataires = [];

        foreach ($planifies as $enseignant) {
            $presence = $presencesDuJour->get($enseignant->id);
            $cours = $emploisDuJour->get($enseignant->id, collect());
            $detail = [
                'enseignant_id' => $enseignant->id,
                'nom' => $enseignant->nom,
                'matricule' => $enseignant->matricule,
                'section' => $enseignant->section,
                'fonction' => $enseignant->fonction,
                'cours' => $cours->map(fn(EmploiDuTemps $emploi) => [
                    'classe' => $emploi->classe?->nom,
                    'discipline' => $emploi->discipline?->nom,
                    'heure_debut' => substr((string) $emploi->heure_debut, 0, 5),
                    'heure_fin' => substr((string) $emploi->heure_fin, 0, 5),
                ])->values()->all(),
            ];

            if ($presence?->heure_arrivee) {
                $minutesRetard = $retards->minutesDeRetard($enseignant, $presence) ?? 0;
                $detail['heure_arrivee'] = $presence->heure_arrivee->format('H:i');
                $detail['heure_depart'] = $presence->heure_depart?->format('H:i');
                $detail['minutes_retard'] = $minutesRetard;
                $scannes[] = $detail;

                if ($minutesRetard > 0) {
                    $retardataires[] = $detail;
                }
            } else {
                $absents[] = $detail;
            }
        }

        return response()->json([
            'date' => $date->toDateString(),
            'effectif' => $enseignants->count(),
            'presents' => count($scannes),
            'absents' => count($absents),
            'retardataires' => count($retardataires),
            'scannes' => $scannes,
            'absents_liste' => $absents,
            'retardataires_liste' => $retardataires,
            'classement_par_section' => $this->classementParSection($planifies, $presencesDuJour, $retards),
        ]);
    }

    /**
     * Regroupement par section normalisée (`LOWER`) : `enseignants.section`
     * porte des variantes de casse pour une même section ("Industrielle" /
     * "industrielle"...), un `groupBy('section')` strict les afficherait à
     * tort comme deux sections distinctes (voir aussi AccessibleEnseignants).
     */
    private function classementParSection($enseignants, $presencesDuJour, RetardCalculator $retards)
    {
        return $enseignants->groupBy(fn(Enseignant $e) => mb_strtolower((string) $e->section))
            ->map(function ($groupe) use ($presencesDuJour, $retards) {
                $presents = $groupe->filter(fn(Enseignant $e) => $presencesDuJour->get($e->id)?->heure_arrivee);
                $retardsCount = $presents->filter(fn(Enseignant $e) => $retards->estEnRetard($e, $presencesDuJour->get($e->id)));

                return [
                    'section' => $groupe->first()->section,
                    'effectif' => $groupe->count(),
                    'presents' => $presents->count(),
                    'retardataires' => $retardsCount->count(),
                    'taux_assiduite' => $groupe->count() > 0
                        ? round($presents->count() / $groupe->count() * 100, 1)
                        : 0.0,
                ];
            })
            ->values();
    }
}
