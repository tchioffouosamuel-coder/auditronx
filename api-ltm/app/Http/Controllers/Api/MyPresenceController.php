<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enseignant;
use App\Services\RetardCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Historique personnel (§4.1) — l'enseignant consulte ses propres présences/retards. */
class MyPresenceController extends Controller
{
    /** GET /api/mes-presences?debut=&fin= — par défaut le mois en cours. */
    public function index(Request $request, RetardCalculator $retards)
    {
        $enseignant = $request->user();

        if (! $enseignant instanceof Enseignant) {
            abort(403, 'Authentification enseignant requise.');
        }

        $debut = Carbon::parse($request->query('debut', now()->startOfMonth()));
        $fin = Carbon::parse($request->query('fin', now()->endOfMonth()));

        $presences = $enseignant->presences()
            ->whereBetween('date', [$debut->toDateString(), $fin->toDateString()])
            ->orderByDesc('date')
            ->get()
            ->map(function ($presence) use ($enseignant, $retards) {
                return [
                    'id' => $presence->id,
                    'date' => $presence->date->toDateString(),
                    'heure_arrivee' => $presence->heure_arrivee?->format('H:i'),
                    'heure_depart' => $presence->heure_depart?->format('H:i'),
                    'source' => $presence->source,
                    'minutes_retard' => $retards->minutesDeRetard($enseignant, $presence),
                ];
            });

        return response()->json($presences);
    }
}
