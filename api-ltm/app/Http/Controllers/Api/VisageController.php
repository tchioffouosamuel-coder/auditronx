<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VisageEmbedding;
use Illuminate\Http\Request;

/**
 * Enrôlement facial (§5.3, §6.4). L'image brute n'est jamais reçue ni stockée ici :
 * seul l'embedding déjà calculé (côté poste ou backoffice) est transmis et chiffré.
 */
class VisageController extends Controller
{
    /** POST /api/visages/enroll */
    public function enroll(Request $request)
    {
        $data = $request->validate([
            'enseignant_id' => ['required', 'exists:enseignants,id'],
            'device_id' => ['nullable', 'exists:devices,id'],
            'embedding' => ['required', 'array'],
        ]);

        // Un seul embedding actif par enseignant : on révoque le précédent avant d'enrôler.
        VisageEmbedding::where('enseignant_id', $data['enseignant_id'])
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $visage = VisageEmbedding::create([
            'enseignant_id' => $data['enseignant_id'],
            'device_id' => $data['device_id'] ?? null,
            'embedding' => $data['embedding'],
            'enrolled_at' => now(),
        ]);

        return response()->json($visage->makeHidden('embedding'), 201);
    }

    /** DELETE /api/visages/{enseignant} — droit de suppression sur demande (§7). */
    public function revoke(int $enseignantId)
    {
        VisageEmbedding::where('enseignant_id', $enseignantId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        return response()->json(['message' => 'Embedding facial supprimé.']);
    }
}
