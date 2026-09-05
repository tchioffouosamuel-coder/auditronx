<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AccessibleEnseignants;
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
        $presencesDuJour = Presence::whereDate('date', $date->toDateString())
            ->whereIn('enseignant_id', $enseignants->pluck('id'))
            ->get()
            ->keyBy('enseignant_id');

        $presents = 0;
        $retardataires = 0;

        foreach ($enseignants as $enseignant) {
            $presence = $presencesDuJour->get($enseignant->id);

            if ($presence?->heure_arrivee) {
                $presents++;

                if ($retards->estEnRetard($enseignant, $presence)) {
                    $retardataires++;
                }
            }
        }

        return response()->json([
            'date' => $date->toDateString(),
            'effectif' => $enseignants->count(),
            'presents' => $presents,
            'absents' => $enseignants->count() - $presents,
            'retardataires' => $retardataires,
            'classement_par_section' => $this->classementParSection($enseignants, $presencesDuJour, $retards),
        ]);
    }

    private function classementParSection($enseignants, $presencesDuJour, RetardCalculator $retards)
    {
        return $enseignants->groupBy('section')
            ->map(function ($groupe) use ($presencesDuJour, $retards) {
                $presents = $groupe->filter(fn (Enseignant $e) => $presencesDuJour->get($e->id)?->heure_arrivee);
                $retardsCount = $presents->filter(fn (Enseignant $e) => $retards->estEnRetard($e, $presencesDuJour->get($e->id)));

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
