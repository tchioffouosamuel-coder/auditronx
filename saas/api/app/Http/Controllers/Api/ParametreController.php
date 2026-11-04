<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EnteteEtablissement;
use App\Services\HoraireAttendu;
use Illuminate\Http\Request;

/** Configuration (§4.2) — horaire fixe du personnel administratif et en-tête des documents. */
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

    /** GET /api/parametres/entete-etablissement */
    public function enteteEtablissement(EnteteEtablissement $entete)
    {
        return response()->json($entete->valeurs());
    }

    /**
     * PUT /api/parametres/entete-etablissement
     *
     * Tous les champs sont facultatifs — un en-tête se remplit par étapes, et
     * une administration qui ne dépend d'aucune délégation laisse la ligne
     * vide. La chaîne vide est donc une valeur valide, pas un oubli.
     */
    public function definirEnteteEtablissement(Request $request, EnteteEtablissement $entete)
    {
        $regles = [];
        foreach (array_keys(EnteteEtablissement::CHAMPS) as $champ) {
            $regles[$champ] = ['sometimes', 'nullable', 'string', 'max:150'];
        }

        return response()->json($entete->definir($request->validate($regles)));
    }
}
