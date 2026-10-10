<?php

namespace App\Providers;

use App\Models\Enseignant;
use App\Observers\AnnuaireObserver;
use App\Observers\AuditObserver;
use App\Tenancy\TenantManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         * Un seul TenantManager par requête : il porte l'état « quel
         * établissement est actif » et la configuration à restaurer. Deux
         * instances se marcheraient dessus — l'une restaurerait la
         * configuration que l'autre vient de poser.
         */
        $this->app->singleton(TenantManager::class);
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

        // Index central « numéro → établissement » (§multi-établissement).
        Enseignant::observe(AnnuaireObserver::class);
    }
}
