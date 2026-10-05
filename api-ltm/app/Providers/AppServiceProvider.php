<?php

namespace App\Providers;

use App\Observers\AuditObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Journal d'audit (§4.2) : chaque modèle métier remonte ses écritures.
        // Branché ici plutôt que par un attribut sur chaque modèle, pour que la
        // liste de ce qui est tracé tienne en un seul endroit relisible.
        foreach (AuditObserver::modelesSurveilles() as $modele) {
            $modele::observe(AuditObserver::class);
        }
    }
}
