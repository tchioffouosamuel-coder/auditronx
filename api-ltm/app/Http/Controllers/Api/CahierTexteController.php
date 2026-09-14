<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CahierTexteEntree;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Http\Request;

class CahierTexteController extends Controller
{
    /**
     * GET /api/cahier-texte/{enseignant} — historique du cahier de texte d'un enseignant.
     * Un enseignant ne peut consulter que son propre historique ; l'admin (User) a un accès
     * complet en lecture (l'admin n'a plus qu'un rôle de visualisation sur ce module).
     */
    public function index(Request $request, Enseignant $enseignant)
    {
        $user = $request->user();
        abort_if($user instanceof Enseignant && $user->id !== $enseignant->id, 403);

        return response()->json(
            $enseignant->cahierTexteEntrees()
                ->with(['emploiDuTemps.classe', 'emploiDuTemps.discipline'])
                ->orderByDesc('date')
                ->paginate(30)
        );
    }

    /**
     * POST /api/cahier-texte — enregistre une entrée de cahier de texte (§4.2, §4.3).
     * Seul l'enseignant du créneau peut saisir une entrée, pour lui-même : l'admin n'a plus
     * qu'un rôle de visualisation sur le cahier de texte (saisie réservée aux enseignants).
     */
    public function store(Request $request)
    {
        $enseignant = $request->user();
        abort_unless($enseignant instanceof Enseignant, 403, 'Authentification enseignant requise.');

        $data = $request->validate([
            'emploi_du_temps_id' => ['required', 'exists:emploi_du_temps,id'],
            'date' => ['required', 'date'],
            'contenu' => ['required', 'string'],
            'reference_programme' => ['nullable', 'string', 'max:255'],
        ]);

        $cours = EmploiDuTemps::where('id', $data['emploi_du_temps_id'])
            ->where('enseignant_id', $enseignant->id)
            ->firstOrFail();

        $entree = CahierTexteEntree::create([
            ...$data,
            'enseignant_id' => $enseignant->id,
            'emploi_du_temps_id' => $cours->id,
            'created_by' => null,
        ]);

        return response()->json($entree, 201);
    }
}
