<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enseignant;
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
    public function __invoke(Request $request, HoraireAttendu $horaires)
    {
        $enseignant = $request->user();

        if (! $enseignant instanceof Enseignant) {
            abort(403, 'Authentification enseignant requise.');
        }

        $debut = $request->query('mois')
            ? Carbon::parse($request->query('mois'))->startOfMonth()
            : now()->startOfMonth();
        $fin = $debut->copy()->endOfMonth();

        // L'emploi du temps est chargé une fois et réutilisé pour chaque jour :
        // `HoraireAttendu` ferait sinon une requête par jour du mois.
        $emplois = $enseignant->emploiDuTemps()->get();

        $datesAttendues = collect();
        for ($date = $debut->copy(); $date->lte($fin); $date->addDay()) {
            if ($horaires->estAttendu($enseignant, $date, $emplois)) {
                $datesAttendues->push($date->toDateString());
            }
        }

        $datesPresentes = $enseignant->presences()
            ->whereBetween('date', [$debut->toDateString(), $fin->toDateString()])
            ->whereNotNull('heure_arrivee')
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString());

        $joursAttendus = $datesAttendues->unique()->count();
        $joursPresents = $datesPresentes->intersect($datesAttendues)->unique()->count();

        // Un taux nul a deux causes très différentes : l'enseignant n'est
        // jamais venu, ou bien aucun jour n'était attendu parce que son emploi
        // du temps n'a pas encore été chargé. L'app doit pouvoir le dire, d'où
        // ces deux drapeaux explicites plutôt qu'un simple pourcentage.
        return response()->json([
            'mois' => $debut->toDateString(),
            'jours_presents' => $joursPresents,
            'jours_attendus' => $joursAttendus,
            'taux_assiduite' => $joursAttendus > 0
                ? round($joursPresents / $joursAttendus * 100, 1)
                : 0.0,
            'emploi_du_temps_charge' => $emplois->isNotEmpty(),
            'horaire_fixe' => $horaires->estAdministratif($enseignant),
            'jours_scannes' => $datesPresentes->unique()->count(),
        ]);
    }
}
