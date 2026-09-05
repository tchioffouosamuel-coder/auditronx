<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CahierTexteEntree;
use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Http\Request;

class CahierTexteController extends Controller
{
    /** GET /api/cahier-texte/{enseignant} — historique du cahier de texte d'un enseignant. */
    public function index(Enseignant $enseignant)
    {
        return response()->json(
            $enseignant->cahierTexteEntrees()
                ->with(['emploiDuTemps.classe', 'emploiDuTemps.discipline'])
                ->orderByDesc('date')
                ->paginate(30)
        );
    }

    /** POST /api/cahier-texte — enregistre une entrée de cahier de texte (§4.2, §4.3). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'enseignant_id' => ['required', 'exists:enseignants,id'],
            'emploi_du_temps_id' => ['required', 'exists:emploi_du_temps,id'],
            'date' => ['required', 'date'],
            'contenu' => ['required', 'string'],
            'reference_programme' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $data['created_by'] = $user instanceof User ? $user->id : null;

        $entree = CahierTexteEntree::create($data);

        return response()->json($entree, 201);
    }
}
