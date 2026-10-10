<?php

namespace App\Console\Commands;

use App\Models\Central\Etablissement;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Exécute une commande artisan dans le contexte de chaque établissement.
 *
 * C'est par là que passent les tâches planifiées : une tâche cron unique sur
 * le serveur, rejouée une fois par locataire.
 *
 *   php artisan tenants:run "auditron:detect-absences"
 *   php artisan tenants:run "auditron:detect-absences" --etablissement=LTM
 *
 * Un échec sur un établissement ne doit pas priver les autres de leur
 * traitement : chaque itération est isolée et l'erreur est rapportée en fin de
 * commande, avec un code de sortie non nul pour que le cron le remonte.
 */
class TenantsRun extends Command
{
    protected $signature = 'tenants:run
        {commande : La commande artisan à exécuter, entre guillemets avec ses options}
        {--etablissement=* : Limiter à ces codes (défaut : tous les établissements actifs)}
        {--inclure-suspendus : Inclure aussi les établissements suspendus}';

    protected $description = 'Rejoue une commande artisan pour chaque établissement abonné.';

    public function handle(TenantManager $tenants): int
    {
        $commande = (string) $this->argument('commande');
        $etablissements = $this->etablissements();

        if ($etablissements->isEmpty()) {
            $this->warn('Aucun établissement ne correspond.');

            return self::SUCCESS;
        }

        $echecs = [];

        foreach ($etablissements as $etablissement) {
            $this->line("→ [{$etablissement->code}] {$commande}");

            try {
                $tenants->execute($etablissement, function () use ($commande) {
                    Artisan::call($commande, [], $this->getOutput());
                });
            } catch (Throwable $e) {
                $echecs[$etablissement->code] = $e->getMessage();
                $this->error("  ✗ {$etablissement->code} : {$e->getMessage()}");
            }
        }

        foreach ($echecs as $code => $message) {
            $this->error("Échec {$code} : {$message}");
        }

        return $echecs === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return \Illuminate\Support\Collection<int, Etablissement> */
    private function etablissements()
    {
        $codes = array_map(
            fn ($code) => Etablissement::normaliseCode($code),
            (array) $this->option('etablissement')
        );

        $statuts = $this->option('inclure-suspendus')
            ? [Etablissement::STATUT_ACTIF, Etablissement::STATUT_SUSPENDU]
            : [Etablissement::STATUT_ACTIF];

        return Etablissement::query()
            ->whereNotNull('provisionne_le')
            ->when($codes !== [], fn ($q) => $q->whereIn('code', $codes))
            ->when($codes === [], fn ($q) => $q->whereIn('statut', $statuts))
            ->orderBy('code')
            ->get();
    }
}
