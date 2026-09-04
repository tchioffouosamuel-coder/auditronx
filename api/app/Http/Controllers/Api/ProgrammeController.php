<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Programme;
use Illuminate\Http\Request;

/** Programmes officiels (§6.3) — base de la fiche de progression. */
class ProgrammeController extends Controller
{
    public function index()
    {
        return response()->json(Programme::with(['classe', 'discipline'])->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'discipline_id' => ['required', 'exists:disciplines,id'],
            'classe_id' => ['required', 'exists:classes,id'],
            'annee_scolaire' => ['required', 'string', 'max:20'],
            'nb_seances_prevues' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json(Programme::create($data), 201);
    }

    public function update(Request $request, Programme $programme)
    {
        $data = $request->validate([
            'nb_seances_prevues' => ['sometimes', 'integer', 'min:1'],
            'annee_scolaire' => ['sometimes', 'string', 'max:20'],
        ]);

        $programme->update($data);

        return response()->json($programme);
    }

    public function destroy(Programme $programme)
    {
        $programme->delete();

        return response()->json(status: 204);
    }
}
