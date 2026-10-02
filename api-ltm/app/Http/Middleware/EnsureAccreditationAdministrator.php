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

        abort_unless(
            $user instanceof User && (! $accreditation || $accreditation->estAccesTotal()),
            403,
            'La gestion des accréditations est réservée aux administrateurs à accès total.',
        );

        return $next($request);
    }
}
