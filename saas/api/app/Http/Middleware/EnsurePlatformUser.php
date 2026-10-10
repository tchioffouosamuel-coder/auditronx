<?php

namespace App\Http\Middleware;

use App\Models\Central\PlatformUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve une route au personnel Auditron (comptes de la base centrale).
 *
 * `auth:sanctum` ne suffirait pas : les tokens d'établissement (User,
 * Enseignant) sont eux aussi des tokens Sanctum valides. Sans ce contrôle, la
 * direction d'un lycée pourrait lire la liste des autres lycées abonnés.
 *
 * Le paramètre optionnel exige en plus le rôle super-admin, pour tout ce qui
 * crée, provisionne ou supprime un client.
 */
class EnsurePlatformUser
{
    public function handle(Request $request, Closure $next, ?string $exigence = null): Response
    {
        $utilisateur = $request->user();

        abort_unless(
            $utilisateur instanceof PlatformUser && $utilisateur->actif,
            403,
            'Réservé au personnel Auditron.'
        );

        if ($exigence === 'admin') {
            abort_unless($utilisateur->peutAdministrer(), 403, 'Réservé au super-administrateur.');
        }

        return $next($request);
    }
}
