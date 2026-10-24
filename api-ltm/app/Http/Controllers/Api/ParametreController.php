<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HoraireAttendu;
use Illuminate\Http\Request;

/** Configuration (§4.2) — horaire fixe du personnel administratif, identité de l'établissement. */
class ParametreController extends Controller
{
    /**
     * GET /api/parametres/etablissement
     *
     * Identité officielle imprimée en entête des documents générés. Les PDF
     * la lisent directement dans la configuration ; les impressions pilotées
     * par le navigateur (planches de QR codes) passent par cette route pour
     * porter le même entête que les PDF.
     */
    public function etablissement()
    {
        $logo = config('etablissement.logo');

        return response()->json(config('etablissement') + [
            // Les PDF embarquent le fichier par `public_path()` ; un navigateur
            // a besoin d'une URL, et `null` quand le logo n'est pas déployé.
            'logo_url' => file_exists(public_path($logo)) ? asset($logo) : null,
        ]);
    }

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
