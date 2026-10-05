<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'backoffice' => \App\Http\Middleware\EnsureBackofficeUser::class,
            'accreditation-admin' => \App\Http\Middleware\EnsureAccreditationAdministrator::class,
        ]);

        // Journal d'audit (§4.2) : appliqué à toutes les routes API, y compris
        // la connexion et les tentatives refusées, afin qu'aucune action ne
        // puisse être menée hors trace.
        $middleware->api(append: [
            \App\Http\Middleware\JournaliseAction::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request): ?string {
            // Les routes API doivent renvoyer 401 JSON, jamais chercher une
            // route web `login` qui n'existe pas dans cette application.
            return $request->is('api/*') ? null : '/login';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
