<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoursValidation;
use App\Models\Programme;
use Illuminate\Http\Request;

/**
 * Fiche de progression (§4.2) : taux d'avancement du programme par classe/discipline,
 * calculé à la volée à partir de cours_validation (§6.3 — pas de table dédiée).
 */
class FicheProgressionController extends Controller
{
    /** GET /api/fiche-progression?classe_id=&discipline_id=&annee_scolaire= */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'classe_id' => ['nullable', 'exists:classes,id'],
            'discipline_id' => ['nullable', 'exists:disciplines,id'],
            'annee_scolaire' => ['nullable', 'string'],
        ]);

        $programmes = Programme::with(['classe', 'discipline'])
            ->when($filters['classe_id'] ?? null, fn ($q, $v) => $q->where('classe_id', $v))
            ->when($filters['discipline_id'] ?? null, fn ($q, $v) => $q->where('discipline_id', $v))
            ->when($filters['annee_scolaire'] ?? null, fn ($q, $v) => $q->where('annee_scolaire', $v))
            ->get();

        $fiche = $programmes->map(function (Programme $programme) {
            $seancesRealisees = CoursValidation::where('status', 'fait')
                ->whereHas('emploiDuTemps', function ($q) use ($programme) {
                    $q->where('classe_id', $programme->classe_id)
                        ->where('discipline_id', $programme->discipline_id);
                })
                ->count();

            $taux = $programme->nb_seances_prevues > 0
                ? round($seancesRealisees / $programme->nb_seances_prevues * 100, 1)
                : 0.0;

            return [
                'classe' => $programme->classe->nom,
                'discipline' => $programme->discipline->nom,
                'annee_scolaire' => $programme->annee_scolaire,
                'nb_seances_prevues' => $programme->nb_seances_prevues,
                'nb_seances_realisees' => $seancesRealisees,
                'taux_avancement' => $taux,
                'en_retard' => $taux < 50.0,
            ];
        });

        return response()->json($fiche->values());
    }
}
