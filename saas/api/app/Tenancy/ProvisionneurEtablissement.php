<?php

namespace App\Tenancy;

use App\Models\Central\Etablissement;
use Database\Seeders\TenantSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Cycle de vie technique d'un établissement : créer sa base, y jouer les
 * migrations métier, l'amorcer, la mettre à jour, la supprimer.
 *
 * C'est le cœur du modèle d'affaires : ouvrir un nouvel abonné doit coûter une
 * commande, pas une copie du code. Tout ce qui s'y passe est idempotent — on
 * peut relancer un provisioning interrompu sans casser l'existant.
 */
class ProvisionneurEtablissement
{
    public const CHEMIN_MIGRATIONS = 'database/migrations/tenant';

    /**
     * Identifiants du compte de direction créés par le dernier amorçage, pour
     * que la commande d'ouverture puisse les afficher une fois — ils ne sont
     * stockés nulle part en clair.
     *
     * @var array{email: string, password: string}|null
     */
    private ?array $dernieresIdentifiants = null;

    /**
     * Dernière information à rapporter à l'opérateur — ce que la plateforme a
     * délibérément laissé en place, et qu'il faudra peut-être traiter à la
     * main.
     */
    private ?string $dernierMessage = null;

    public function __construct(private readonly TenantManager $tenants) {}

    /** @return array{email: string, password: string}|null */
    public function dernieresIdentifiants(): ?array
    {
        return $this->dernieresIdentifiants;
    }

    public function dernierMessage(): ?string
    {
        return $this->dernierMessage;
    }

    /**
     * Crée la base (si autorisé), joue les migrations, amorce les comptes puis
     * marque l'établissement comme provisionné et actif.
     *
     * @param  array{nom?: string, email?: string, password?: string}  $direction
     *                                                                           Compte de direction à créer dans la base du client.
     */
    public function provisionne(
        Etablissement $etablissement,
        array $direction = [],
        bool $creerLaBase = true,
        bool $amorcer = true,
    ): void {
        if ($creerLaBase && config('auditron.provisioning.cree_la_base')) {
            $this->creeLaBase($etablissement);
        }

        $this->creeLesDossiers($etablissement);
        $this->migre($etablissement);

        if ($amorcer) {
            $this->dernieresIdentifiants = $this->amorce($etablissement, $direction);
        }

        $etablissement->forceFill([
            'provisionne_le' => $etablissement->provisionne_le ?? now(),
            'statut' => $etablissement->statut === Etablissement::STATUT_EN_ATTENTE
                ? Etablissement::STATUT_ACTIF
                : $etablissement->statut,
        ])->save();
    }

    /** `CREATE DATABASE` sur le serveur du locataire, ou fichier en SQLite. */
    public function creeLaBase(Etablissement $etablissement): void
    {
        $base = $this->tenants->nomBase($etablissement);

        if (config('database.connections.tenant.driver') === 'sqlite') {
            File::ensureDirectoryExists(dirname($base));

            if (! File::exists($base)) {
                File::put($base, '');
            }

            return;
        }

        $this->verifieNomDeBase($base);

        $charset = config('database.connections.tenant.charset', 'utf8mb4');
        $collation = config('database.connections.tenant.collation', 'utf8mb4_unicode_ci');

        // Exécuté sur la connexion centrale : la connexion `tenant` ne peut pas
        // servir ici, puisque la base qu'elle désigne n'existe pas encore.
        DB::connection('central')->statement(
            "CREATE DATABASE IF NOT EXISTS `{$base}` CHARACTER SET {$charset} COLLATE {$collation}"
        );
    }

    /** Joue les migrations métier dans la base de l'établissement. */
    public function migre(Etablissement $etablissement, bool $force = true): int
    {
        return $this->tenants->execute($etablissement, fn () => Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => self::CHEMIN_MIGRATIONS,
            '--force' => $force,
        ]));
    }

    /**
     * Amorce la base du client : accréditations standard + compte direction.
     *
     * @return array{email: string, password: string} identifiants à remettre au client
     */
    public function amorce(Etablissement $etablissement, array $direction = []): array
    {
        return $this->tenants->execute($etablissement, function () use ($etablissement, $direction) {
            $seeder = app(TenantSeeder::class);
            $seeder->run();

            return $seeder->creeLaDirection($etablissement, $direction);
        });
    }

    /**
     * Supprime définitivement la base et les fichiers d'un établissement.
     * Irréversible : réservé à la commande dédiée, qui demande confirmation.
     */
    public function supprime(Etablissement $etablissement): void
    {
        $base = $this->tenants->nomBase($etablissement);

        /*
         * Une fiche jamais provisionnée ne donne aucun droit sur la base
         * qu'elle désigne : ce peut être une base préexistante déclarée avec
         * `--base-existante`, ou le nom d'une base saisi de travers. Supprimer
         * la fiche ne doit alors surtout pas emporter des données que la
         * plateforme n'a jamais créées — le cas typique étant une déclaration
         * ratée qu'on nettoie.
         */
        if (! $etablissement->estProvisionne()) {
            $this->dernierMessage = "Base « {$base} » laissée intacte : établissement jamais provisionné.";
        } elseif (config('database.connections.tenant.driver') === 'sqlite') {
            File::delete($base);
        } else {
            $this->verifieNomDeBase($base);
            DB::connection('central')->statement("DROP DATABASE IF EXISTS `{$base}`");
        }

        $code = strtolower($etablissement->code);
        File::deleteDirectory(storage_path('app/private/tenants/' . $code));
        File::deleteDirectory(storage_path('app/public/tenants/' . $code));
        File::deleteDirectory(public_path('tenants/' . $code));
    }

    /** Dossiers de stockage par établissement (photos de pointage, firmwares). */
    public function creeLesDossiers(Etablissement $etablissement): void
    {
        $code = strtolower($etablissement->code);

        foreach ([
            storage_path('app/private/tenants/' . $code),
            storage_path('app/public/tenants/' . $code),
            public_path('tenants/' . $code),
        ] as $dossier) {
            File::ensureDirectoryExists($dossier);
        }
    }

    /**
     * Le nom de base part dans une requête SQL non paramétrable (`CREATE
     * DATABASE` n'accepte pas de liaison). Il vient de `code`, déjà filtré par
     * Etablissement::normaliseCode(), mais `db_name` peut avoir été saisi à la
     * main : on revérifie ici plutôt que de faire confiance à l'appelant.
     */
    private function verifieNomDeBase(string $base): void
    {
        if (! preg_match('/^[A-Za-z0-9_]{1,64}$/', $base)) {
            throw new RuntimeException("Nom de base invalide : « {$base} ».");
        }
    }
}
