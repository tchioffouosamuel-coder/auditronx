<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enseignant;
use App\Services\AssiduiteCalculator;
use App\Services\HoraireAttendu;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Assiduité du mois en cours, vue par l'enseignant lui-même (§4.1).
 *
 * Distinct de `EnseignantController@assiduite`, qui sert le backoffice et
 * exige un compte administrateur : ici l'auteur est l'enseignant, authentifié
 * par le token de l'app mobile, et ne peut consulter que sa propre fiche.
 */
class MonAssiduiteController extends Controller
{
    public function __invoke(Request $request, HoraireAttendu $horaires, AssiduiteCalculator $assiduite)
    {
        $enseignant = $request->user();

        if (! $enseignant instanceof Enseignant) {
            abort(403, 'Authentification enseignant requise.');
        }

        $debut = $request->query('mois')
            ? Carbon::parse($request->query('mois'))->startOfMonth()
            : now()->startOfMonth();
        $fin = $debut->copy()->endOfMonth();

        $bilan = $assiduite->bilan($enseignant, $debut, $fin);

        // Un taux nul a deux causes très différentes : l'enseignant n'est
        // jamais venu, ou bien aucun jour n'était attendu parce que son emploi
        // du temps n'a pas encore été chargé. L'app doit pouvoir le dire, d'où
        // ces deux drapeaux explicites plutôt qu'un simple pourcentage.
        return response()->json([
            'mois' => $debut->toDateString(),
            ...$bilan,
            'emploi_du_temps_charge' => $enseignant->emploiDuTemps->isNotEmpty(),
            'horaire_fixe' => $horaires->estAdministratif($enseignant),
        ]);
    }
}
