<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\Etablissement;
use Illuminate\Http\Request;

/**
 * Liste publique des établissements abonnés — c'est elle qui rend l'URL unique
 * utilisable : l'écran de connexion du portail et l'écran d'activation de
 * l'app proposent un choix au lieu d'exiger un code appris par cœur.
 *
 * Ne renvoie que ce qu'il faut pour choisir (code, nom, ville, logo,
 * couleurs) : aucun contact, aucun effectif, aucune donnée d'abonnement. Et
 * seulement les établissements actifs — un abonné suspendu n'apparaît pas dans
 * la liste, mais son code continue de répondre avec un 402 explicite.
 *
 * `auditron.catalogue_public` permet de couper cette liste : la saisie du code
 * devient alors obligatoire, pour un éditeur qui préfère ne pas publier qui
 * sont ses clients.
 */
class CatalogueController extends Controller
{
    public function index(Request $request)
    {
        if (! config('auditron.catalogue_public')) {
            return response()->json(['data' => [], 'liste_publique' => false]);
        }

        $recherche = trim((string) $request->query('recherche'));

        $etablissements = Etablissement::query()
            ->where('statut', Etablissement::STATUT_ACTIF)
            ->whereNotNull('provisionne_le')
            ->when($recherche !== '', function ($q) use ($recherche) {
                $q->where(fn ($q) => $q->where('nom', 'like', "%{$recherche}%")
                    ->orWhere('nom_court', 'like', "%{$recherche}%")
                    ->orWhere('code', 'like', "%{$recherche}%")
                    ->orWhere('ville', 'like', "%{$recherche}%"));
            })
            ->orderBy('nom')
            ->limit(200)
            ->get()
            ->map(fn (Etablissement $e) => $e->branding());

        return response()->json(['data' => $etablissements, 'liste_publique' => true]);
    }
}
