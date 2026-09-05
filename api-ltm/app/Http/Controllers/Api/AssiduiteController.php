<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AccessibleEnseignants;
use App\Models\Enseignant;
use App\Models\Presence;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Assiduité & rapports (§4.2) — statistiques par section, journal, personnel inactif. */
class AssiduiteController extends Controller
{
    use AccessibleEnseignants;

    /** GET /api/assiduite/stats?debut=&fin=&section= */
    public function stats(Request $request)
    {
        $debut = Carbon::parse($request->query('debut', now()->startOfMonth()));
        $fin = Carbon::parse($request->query('fin', now()->endOfMonth()));
        $joursOuvres = $debut->diffInWeekdays($fin) + 1;

        $enseignants = $this->enseignantsAccessibles($request->user())
            ->when($request->query('section'), fn ($q, $v) => $q->where('section', $v))
            ->get();

        $presences = Presence::whereBetween('date', [$debut->toDateString(), $fin->toDateString()])
            ->whereIn('enseignant_id', $enseignants->pluck('id'))
            ->whereNotNull('heure_arrivee')
            ->get()
            ->groupBy('enseignant_id');

        $lignes = $enseignants->map(function (Enseignant $enseignant) use ($presences, $joursOuvres) {
            $joursPresents = $presences->get($enseignant->id, collect())->count();

            return [
                'enseignant_id' => $enseignant->id,
                'nom' => $enseignant->nom,
                'section' => $enseignant->section,
                'jours_presents' => $joursPresents,
                'jours_ouvres' => $joursOuvres,
                'taux_assiduite' => $joursOuvres > 0 ? round($joursPresents / $joursOuvres * 100, 1) : 0.0,
            ];
        });

        return response()->json($lignes->sortByDesc('taux_assiduite')->values());
    }

    /** GET /api/assiduite/journal?date=&section= — journal des présences d'un jour donné. */
    public function journal(Request $request)
    {
        $date = Carbon::parse($request->query('date', now()));

        $enseignants = $this->enseignantsAccessibles($request->user())
            ->when($request->query('section'), fn ($q, $v) => $q->where('section', $v))
            ->pluck('id');

        $presences = Presence::with('enseignant')
            ->whereDate('date', $date->toDateString())
            ->whereIn('enseignant_id', $enseignants)
            ->orderBy('heure_arrivee')
            ->get();

        return response()->json($presences);
    }

    /** GET /api/assiduite/personnel-inactif?jours=N — enseignants sans pointage depuis N jours. */
    public function personnelInactif(Request $request)
    {
        $jours = (int) $request->query('jours', 7);
        $seuil = now()->subDays($jours)->toDateString();

        $enseignants = $this->enseignantsAccessibles($request->user())->get();

        $dernieresPresences = Presence::whereIn('enseignant_id', $enseignants->pluck('id'))
            ->selectRaw('enseignant_id, MAX(date) as derniere_date')
            ->groupBy('enseignant_id')
            ->pluck('derniere_date', 'enseignant_id');

        $inactifs = $enseignants->filter(function (Enseignant $enseignant) use ($dernieresPresences, $seuil) {
            $derniere = $dernieresPresences->get($enseignant->id);

            return ! $derniere || $derniere < $seuil;
        })->values();

        return response()->json($inactifs->map(fn (Enseignant $e) => [
            'enseignant_id' => $e->id,
            'nom' => $e->nom,
            'section' => $e->section,
            'derniere_presence' => $dernieresPresences->get($e->id),
        ]));
    }
}
