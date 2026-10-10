<?php

namespace App\Console\Commands;

use App\Models\Central\Etablissement;
use App\Tenancy\ProvisionneurEtablissement;
use Illuminate\Console\Command;
use Throwable;

/**
 * Joue les migrations métier dans la base de chaque établissement.
 *
 * C'est l'opération de déploiement de la plateforme : une seule base de code,
 * N bases à mettre à niveau. À lancer après chaque `git pull` en production.
 * Idempotent, et sans effet sur un établissement déjà à jour.
 */
class TenantsMigrate extends Command
{
    protected $signature = 'tenants:migrate
        {--etablissement=* : Limiter à ces codes}
        {--inclure-suspendus : Migrer aussi les établissements suspendus}';

    protected $description = 'Met à niveau le schéma de tous les établissements abonnés.';

    public function handle(ProvisionneurEtablissement $provisionneur): int
    {
        $codes = array_map(
            fn ($code) => Etablissement::normaliseCode($code),
            (array) $this->option('etablissement')
        );

        $etablissements = Etablissement::query()
            ->whereNotNull('provisionne_le')
            ->when($codes !== [], fn ($q) => $q->whereIn('code', $codes))
            ->when($codes === [] && ! $this->option('inclure-suspendus'),
                fn ($q) => $q->where('statut', '!=', Etablissement::STATUT_ARCHIVE))
            ->orderBy('code')
            ->get();

        $echecs = [];

        foreach ($etablissements as $etablissement) {
            $this->line("→ [{$etablissement->code}] migrations…");

            try {
                $provisionneur->migre($etablissement);
            } catch (Throwable $e) {
                $echecs[$etablissement->code] = $e->getMessage();
                $this->error("  ✗ {$etablissement->code} : {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info(count($etablissements) - count($echecs) . ' établissement(s) à jour.');

        return $echecs === [] ? self::SUCCESS : self::FAILURE;
    }
}
