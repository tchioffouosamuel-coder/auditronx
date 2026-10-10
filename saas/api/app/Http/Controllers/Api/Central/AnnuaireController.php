<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\AnnuaireEntree;
use App\Models\Central\Etablissement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * « J'ai oublié mon code établissement » : retrouve les établissements d'un
 * numéro de téléphone.
 *
 * Route publique, donc limitée : sans plafond, elle permettrait de tester des
 * numéros en masse pour savoir qui travaille où. Le plafond est volontairement
 * bas — un enseignant qui cherche son établissement fait un essai, pas trente.
 *
 * Ne renvoie jamais de nom de personne, seulement des établissements, et rien
 * du tout quand le numéro est inconnu (sans distinguer « inconnu » de
 * « plusieurs », le message reste le même côté app).
 */
class AnnuaireController extends Controller
{
    public function resolve(Request $request)
    {
        if (! config('auditron.annuaire.actif')) {
            return response()->json(['data' => []]);
        }

        $data = $request->validate(['tel' => ['required', 'string', 'max:32']]);

        $cle = 'annuaire:' . $request->ip();

        if (RateLimiter::tooManyAttempts($cle, maxAttempts: 10)) {
            return response()->json([
                'message' => 'Trop de recherches. Réessayez dans un moment.',
            ], 429);
        }

        RateLimiter::hit($cle, decaySeconds: 600);

        $hash = AnnuaireEntree::hachNumero($data['tel']);

        if ($hash === null) {
            return response()->json(['data' => []]);
        }

        $etablissements = Etablissement::query()
            ->whereIn('id', AnnuaireEntree::where('tel_hash', $hash)->pluck('etablissement_id'))
            ->where('statut', Etablissement::STATUT_ACTIF)
            ->get()
            ->map(fn (Etablissement $e) => $e->branding());

        return response()->json(['data' => $etablissements]);
    }
}
