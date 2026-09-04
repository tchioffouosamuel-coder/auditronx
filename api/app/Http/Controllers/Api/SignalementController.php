<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Signalement;
use Illuminate\Http\Request;

/** Signalements & justificatifs (§4.2). */
class SignalementController extends Controller
{
    public function index(Request $request)
    {
        $query = Signalement::with('enseignant')
            ->when($request->query('enseignant_id'), fn ($q, $v) => $q->where('enseignant_id', $v));

        return response()->json($query->orderByDesc('date')->paginate(30));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'enseignant_id' => ['required', 'exists:enseignants,id'],
            'date' => ['required', 'date'],
            'motif' => ['required', 'string', 'max:255'],
            'duree_jours' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json(Signalement::create($data), 201);
    }

    /** Création multiple/globale (§4.2) : mêmes motif/date/durée pour plusieurs enseignants. */
    public function storeBulk(Request $request)
    {
        $data = $request->validate([
            'enseignant_ids' => ['required', 'array', 'min:1'],
            'enseignant_ids.*' => ['exists:enseignants,id'],
            'date' => ['required', 'date'],
            'motif' => ['required', 'string', 'max:255'],
            'duree_jours' => ['nullable', 'integer', 'min:1'],
        ]);

        $signalements = collect($data['enseignant_ids'])->map(fn ($id) => Signalement::create([
            'enseignant_id' => $id,
            'date' => $data['date'],
            'motif' => $data['motif'],
            'duree_jours' => $data['duree_jours'] ?? 1,
        ]));

        return response()->json($signalements, 201);
    }

    public function show(Signalement $signalement)
    {
        return response()->json($signalement->load('enseignant'));
    }

    public function update(Request $request, Signalement $signalement)
    {
        $data = $request->validate([
            'date' => ['sometimes', 'date'],
            'motif' => ['sometimes', 'string', 'max:255'],
            'duree_jours' => ['nullable', 'integer', 'min:1'],
        ]);

        $signalement->update($data);

        return response()->json($signalement);
    }

    public function destroy(Signalement $signalement)
    {
        $signalement->delete();

        return response()->json(status: 204);
    }
}
