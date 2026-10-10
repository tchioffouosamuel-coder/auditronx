<?php

namespace App\Tenancy\Concerns;

use App\Models\Central\Etablissement;
use App\Tenancy\TenantManager;

/**
 * À utiliser sur tout job mis en file : la file est partagée par tous les
 * établissements, le worker n'a donc aucun contexte au moment du `handle()`.
 * Le trait mémorise le code de l'établissement à la construction et rebascule
 * la connexion avant l'exécution.
 *
 * Usage :
 *
 *     class EnvoieRappelAbsence implements ShouldQueue
 *     {
 *         use ExecuteDansLEtablissement;
 *
 *         public function __construct()
 *         {
 *             $this->initialiseContexteEtablissement();
 *         }
 *
 *         public function handle(): void
 *         {
 *             $this->dansLEtablissement(function () {
 *                 // code métier : connexion déjà basculée
 *             });
 *         }
 *     }
 */
trait ExecuteDansLEtablissement
{
    public ?string $codeEtablissement = null;

    /** À appeler dans le constructeur du job, pendant que le contexte existe. */
    public function initialiseContexteEtablissement(): void
    {
        $this->codeEtablissement = app(TenantManager::class)->courant()?->code;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $traitement
     * @return T
     */
    protected function dansLEtablissement(callable $traitement): mixed
    {
        if ($this->codeEtablissement === null) {
            return $traitement();
        }

        $etablissement = Etablissement::where('code', $this->codeEtablissement)->firstOrFail();

        return app(TenantManager::class)->execute($etablissement, fn () => $traitement());
    }
}
