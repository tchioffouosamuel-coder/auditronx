<?php

namespace App\Console\Commands;

use App\Models\Central\Etablissement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Corrige le code d'un établissement pas encore provisionné.
 *
 * Le code est volontairement immuable partout ailleurs : il nomme la base,
 * nomme le dossier de fichiers, voyage dans l'en-tête `X-Tenant` mémorisé par
 * chaque téléphone activé, et est scellé dans le `config.h` des bornes. Le
 * changer après la mise en service casserait les quatre à la fois.
 *
 * Avant le provisioning, en revanche, rien de tout ça n'existe : une faute de
 * frappe à la déclaration doit pouvoir se corriger sans ressaisir le dossier
 * commercial de l'établissement. D'où cette commande, qui refuse
 * catégoriquement de s'appliquer à un établissement déjà provisionné.
 */
class EtablissementRenomme extends Command
{
    protected $signature = 'etablissement:renomme
        {ancien : Code actuel}
        {nouveau : Code corrigé}
        {--db= : Corriger aussi le nom de la base}
        {--db-user= : Corriger aussi l\'utilisateur MySQL}
        {--db-password= : Corriger aussi le mot de passe MySQL}
        {--nom= : Corriger aussi le nom de l\'établissement}';

    protected $description = 'Corrige le code d’un établissement pas encore provisionné.';

    public function handle(): int
    {
        $ancien = Etablissement::normaliseCode($this->argument('ancien'));
        $nouveau = Etablissement::normaliseCode($this->argument('nouveau'));

        $etablissement = Etablissement::where('code', $ancien)->first();

        if (! $etablissement) {
            $this->error("Établissement {$ancien} inconnu.");

            return self::FAILURE;
        }

        if ($nouveau === '') {
            $this->error('Nouveau code invalide : lettres, chiffres, tiret et souligné uniquement.');

            return self::FAILURE;
        }

        if ($nouveau !== $ancien && Etablissement::where('code', $nouveau)->exists()) {
            $this->error("Le code {$nouveau} est déjà utilisé par un autre établissement.");

            return self::FAILURE;
        }

        if ($etablissement->estProvisionne()) {
            $this->error("L'établissement {$ancien} est déjà provisionné : son code ne peut plus changer.");
            $this->newLine();
            $this->line('Il nomme sa base et son dossier de fichiers, il est mémorisé par chaque');
            $this->line('téléphone activé et scellé dans le config.h de ses bornes. Le corriger');
            $this->line('suppose de renommer la base, déplacer les fichiers, réactiver les');
            $this->line('téléphones et reflasher les bornes — une migration, pas une commande.');

            return self::FAILURE;
        }

        $corrections = array_filter([
            'code' => $nouveau,
            'nom' => $this->option('nom'),
            'db_name' => $this->option('db'),
            'db_username' => $this->option('db-user'),
            'db_password' => $this->option('db-password'),
        ], fn ($valeur) => $valeur !== null && $valeur !== '');

        $etablissement->update($corrections);

        $this->deplaceLesDossiers($ancien, $nouveau);

        $this->info("Établissement {$ancien} renommé en {$nouveau}.");
        $this->line('Base visée : ' . ($etablissement->db_name ?: '(convention de nommage)'));
        $this->newLine();
        $this->line('Vérifier puis provisionner :');
        $this->line("  php artisan etablissement:provision {$nouveau} --tester");

        return self::SUCCESS;
    }

    /**
     * Les dossiers par établissement sont créés dès le début du provisioning,
     * donc avant l'échec qui a révélé la faute de frappe. On les déplace
     * plutôt que d'en laisser une paire vide au nom de l'ancien code.
     */
    private function deplaceLesDossiers(string $ancien, string $nouveau): void
    {
        foreach ([
            storage_path('app/private/tenants/'),
            storage_path('app/public/tenants/'),
            public_path('tenants/'),
        ] as $racine) {
            $source = $racine . strtolower($ancien);
            $cible = $racine . strtolower($nouveau);

            if (! File::isDirectory($source) || File::isDirectory($cible)) {
                continue;
            }

            File::move($source, $cible);
            $this->line("Dossier déplacé : {$source} → {$cible}");
        }
    }
}
