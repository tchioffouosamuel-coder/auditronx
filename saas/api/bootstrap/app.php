<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function (): void {
            /*
             * Deux familles de routes, deux bases :
             *
             *  - /api/central/*  → base centrale (abonnés, abonnements,
             *    facturation), personnel Auditron ;
             *  - /api/*          → base de l'établissement désigné par
             *    l'en-tête X-Tenant, personnel de l'établissement.
             *
             * Les routes métier sont déclarées ici plutôt que via le paramètre
             * `api:` de withRouting, afin d'y attacher `tenant` sans toucher à
             * routes/api.php : ce fichier reste identique à celui de l'API
             * mono-établissement, ce qui garde les futures évolutions
             * métier lisibles dans l'historique.
             */
            Route::middleware('api')
                ->prefix('api/central')
                ->group(base_path('routes/central.php'));

            Route::middleware(['api', 'tenant'])
                ->prefix('api')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => \App\Http\Middleware\IdentifieEtablissement::class,
            'platform' => \App\Http\Middleware\EnsurePlatformUser::class,
            'backoffice' => \App\Http\Middleware\EnsureBackofficeUser::class,
            'accreditation-admin' => \App\Http\Middleware\EnsureAccreditationAdministrator::class,
        ]);

        /*
         * L'identification de l'établissement doit précéder l'authentification
         * ET la résolution des liaisons de route : le token Sanctum comme le
         * `{enseignant}` d'une URL sont lus dans la base du client. Sans cette
         * priorité, `SubstituteBindings` (présent dans le groupe `api`, donc
         * exécuté avant les intergiciels de route) chercherait l'enseignant
         * dans la base centrale et répondrait 404 sur des fiches existantes.
         */
        $middleware->prependToPriorityList(
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \App\Http\Middleware\IdentifieEtablissement::class,
        );

        // Journal d'audit (§4.2) : appliqué à toutes les routes API, y compris
        // la connexion et les tentatives refusées, afin qu'aucune action ne
        // puisse être menée hors trace. Sans contexte établissement, il se
        // retire de lui-même (voir JournaliseAction).
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
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
