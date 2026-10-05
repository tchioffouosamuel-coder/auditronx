<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccreditationAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $accreditation = $user instanceof User ? $user->accreditation : null;

        // Une accréditation volontairement restreinte (surveillant général,
        // exclu de l'administration) a bien le groupe '*', mais la laisser
        // éditer les accréditations lui permettrait de lever sa propre
        // restriction en deux clics. L'accès total ne suffit donc pas.
        $autorise = $user instanceof User
            && (! $accreditation || ($accreditation->estAccesTotal() && ! $accreditation->exclutAdministration()));

        abort_unless(
            $autorise,
            403,
            'La gestion des accréditations est réservée aux administrateurs à accès total.',
        );

        return $next($request);
    }
}
