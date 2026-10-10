<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Migrations de la base centrale.
 *
 * Raccourci volontaire sur `migrate --database=central --path=...` : les deux
 * jeux de migrations (central et locataire) vivant côte à côte, lancer
 * `php artisan migrate` sans drapeau ne doit jamais devenir une habitude.
 * Les dossiers `database/migrations/central` et `database/migrations/tenant`
 * ne sont d'ailleurs pas découverts automatiquement — Laravel ne descend pas
 * dans les sous-dossiers de `database/migrations`.
 */
class CentralMigrate extends Command
{
    protected $signature = 'central:migrate
        {--fresh : Repart d\'une base vide (DESTRUCTIF, refusé en production)}
        {--seed : Joue PlatformSeeder ensuite}
        {--force}';

    protected $description = 'Joue les migrations de la base centrale (plateforme, abonnements).';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            if (app()->isProduction() && ! $this->option('force')) {
                $this->error('--fresh est refusé en production sans --force.');

                return self::FAILURE;
            }

            $this->call('migrate:fresh', [
                '--database' => 'central',
                '--path' => 'database/migrations/central',
                '--force' => true,
            ]);
        } else {
            $this->call('migrate', [
                '--database' => 'central',
                '--path' => 'database/migrations/central',
                '--force' => (bool) $this->option('force'),
            ]);
        }

        if ($this->option('seed')) {
            $this->call('db:seed', [
                '--database' => 'central',
                '--class' => \Database\Seeders\PlatformSeeder::class,
                '--force' => true,
            ]);
        }

        return self::SUCCESS;
    }
}
