<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HoraireAttendu;
use Illuminate\Http\Request;

/** Configuration (§4.2) — horaire fixe du personnel administratif. */
class ParametreController extends Controller
{
    /** GET /api/parametres/horaires-administratifs */
    public function horairesAdministratifs(HoraireAttendu $horaires)
    {
        return response()->json($horaires->parametresAdministratifs());
    }

    /** PUT /api/parametres/horaires-administratifs */
    public function definirHorairesAdministratifs(Request $request, HoraireAttendu $horaires)
    {
        $data = $request->validate([
            'jours' => ['required', 'array', 'min:1'],
            'jours.*' => ['integer', 'between:1,7', 'distinct'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
        ]);

        $horaires->definirParametresAdministratifs($data['jours'], $data['heure_debut'], $data['heure_fin']);

        return response()->json($horaires->parametresAdministratifs());
    }
}
