<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve une route aux comptes administrateurs (User : backoffice web et
 * espace admin mobile). `auth:sanctum` seul ne suffit pas : les enseignants
 * (Enseignant) s'authentifient aussi par token Sanctum depuis l'app mobile et
 * pourraient sinon lire les OTP des autres enseignants (§otp-approval).
 */
class EnsureBackofficeUser
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() instanceof User, 403, 'Réservé aux administrateurs.');

        return $next($request);
    }
}
