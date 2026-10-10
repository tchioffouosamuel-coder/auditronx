<?php

namespace Tests;

use App\Models\Central\Etablissement;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Socle des tests de la plateforme multi-établissement.
 *
 * Chaque test tourne dans le contexte d'un établissement nommé `TEST` : la
 * base centrale est recréée en mémoire, la base locataire est un fichier
 * SQLite réutilisé d'un test à l'autre (migré une fois, puis isolé par
 * transaction comme le fait RefreshDatabase), et toutes les requêtes HTTP
 * partent avec l'en-tête `X-Tenant`.
 *
 * Conséquence voulue : les tests métier héritent du contexte sans une ligne de
 * code, et un test qui oublierait l'en-tête échouerait franchement en 400 —
 * ce qui est exactement le comportement attendu en production.
 *
 * Le fichier locataire étant partagé par le processus, les tests ne sont pas
 * parallélisables en l'état (`--parallel` demanderait un fichier par jeton).
 */
abstract class TestCase extends BaseTestCase
{
    /** Code de l'établissement de test, envoyé dans l'en-tête X-Tenant. */
    protected string $codeEtablissement = 'TEST';

    protected function refreshApplication()
    {
        parent::refreshApplication();

        // Doit avoir lieu avant setUpTraits() — donc avant RefreshDatabase —
        // pour que la connexion par défaut soit déjà celle de l'établissement
        // quand le trait joue les migrations et ouvre sa transaction.
        $this->preparePlateforme();

        return $this;
    }

    protected function tearDown(): void
    {
        // parent::tearDown() d'abord : c'est lui qui annule la transaction
        // ouverte par RefreshDatabase. Refermer le contexte avant purgerait la
        // connexion, l'annulation porterait sur une connexion neuve et les
        // données du test survivraient au test suivant.
        parent::tearDown();

        app(TenantManager::class)?->termine();
    }

    private function preparePlateforme(): void
    {
        config([
            // Base centrale en mémoire : recréée à chaque test, donc aucun
            // abonné résiduel d'un test sur l'autre.
            'database.connections.central.driver' => 'sqlite',
            'database.connections.central.database' => ':memory:',
            'database.connections.central.url' => null,
            'database.default' => 'central',
            'database.connections.tenant.driver' => 'sqlite',
        ]);

        DB::purge('central');
        DB::setDefaultConnection('central');

        Artisan::call('migrate', [
            '--database' => 'central',
            '--path' => 'database/migrations/central',
            '--force' => true,
        ]);

        $fichier = storage_path('framework/testing/tenant-test.sqlite');
        File::ensureDirectoryExists(dirname($fichier));

        if (! File::exists($fichier)) {
            File::put($fichier, '');
        }

        $etablissement = Etablissement::create([
            'code' => $this->codeEtablissement,
            'nom' => 'Établissement de test',
            'nom_court' => 'TEST',
            'fuseau' => 'Africa/Douala',
            'db_name' => $fichier,
            'statut' => Etablissement::STATUT_ACTIF,
            'provisionne_le' => now(),
        ]);

        app(TenantManager::class)->initialise($etablissement);

        /*
         * Déclare les migrations métier auprès du migrateur, plutôt que de
         * redéfinir `migrateFreshUsing()` : cette méthode vient du trait
         * CanConfigureMigrationCommands, et en PHP une méthode de trait prime
         * sur la méthode héritée d'une classe parente — la redéfinir ici
         * n'aurait aucun effet, et RefreshDatabase migrerait le dossier
         * `database/migrations`, vide depuis la séparation central/locataire.
         *
         * Enregistrées ainsi, elles sont jouées par le `migrate:fresh` du
         * trait sur la connexion par défaut, qui est déjà celle du locataire.
         */
        $this->app->make('migrator')->path(database_path('migrations/tenant'));

        $this->withHeader('X-Tenant', $this->codeEtablissement);
    }

    /** Crée un deuxième établissement provisionné — isolation, annuaire, 402. */
    protected function etablissementSupplementaire(string $code, array $attributs = []): Etablissement
    {
        $fichier = storage_path('framework/testing/tenant-' . strtolower($code) . '.sqlite');
        File::ensureDirectoryExists(dirname($fichier));
        File::put($fichier, '');

        $etablissement = Etablissement::create([
            'code' => $code,
            'nom' => 'Établissement ' . $code,
            'fuseau' => 'Africa/Douala',
            'db_name' => $fichier,
            'statut' => Etablissement::STATUT_ACTIF,
            'provisionne_le' => now(),
            ...$attributs,
        ]);

        app(TenantManager::class)->execute($etablissement, fn () => Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]));

        return $etablissement;
    }
}
