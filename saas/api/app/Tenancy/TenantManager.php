<?php

namespace App\Tenancy;

use App\Models\Central\Etablissement;
use App\Tenancy\Exceptions\ContexteTenantManquant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Bascule l'application sur la base d'un établissement (une base par
 * locataire) et la remet dans son état central en sortie.
 *
 * Principe : la connexion par défaut vaut `central` hors contexte, et `tenant`
 * dès qu'un établissement est initialisé. Les modèles métier (Enseignant,
 * Presence, ...) ne déclarent donc aucune connexion et suivent la bascule sans
 * une ligne de changement ; seuls les modèles de App\Models\Central épinglent
 * explicitement la connexion `central`.
 *
 * Tout ce qui est dérivé du locataire bascule avec la base : préfixe de cache,
 * racines de disques (photos de pointage, firmwares), fuseau horaire et nom
 * d'application (en-têtes des PDF).
 */
class TenantManager
{
    private ?Etablissement $courant = null;

    /** Configuration à restaurer en sortie de contexte. */
    private array $sauvegarde = [];

    /**
     * Le contexte courant a-t-il été ouvert par la requête HTTP en cours ?
     *
     * Un contexte peut aussi avoir été posé en amont — par une commande
     * artisan qui boucle sur les établissements, ou par la base des tests. Il
     * n'appartient alors pas à la requête, et c'est à celui qui l'a ouvert de
     * le refermer : sinon la fin de la première requête rendrait la connexion
     * à la base centrale au milieu d'un traitement qui n'en a pas fini.
     */
    private bool $ouvertParLaRequete = false;

    public function courant(): ?Etablissement
    {
        return $this->courant;
    }

    public function estInitialise(): bool
    {
        return $this->courant !== null;
    }

    /** L'établissement courant, ou une erreur franche s'il n'y en a pas. */
    public function exigeCourant(): Etablissement
    {
        if (! $this->courant) {
            throw new ContexteTenantManquant();
        }

        return $this->courant;
    }

    /** Retrouve un établissement par son code, sans l'initialiser. */
    public function resoudre(?string $code): ?Etablissement
    {
        $code = Etablissement::normaliseCode($code);

        if ($code === '') {
            return null;
        }

        return Etablissement::where('code', $code)->first();
    }

    /**
     * Ouvre le contexte pour la requête HTTP en cours, en notant que c'est
     * elle qui l'a ouvert — voir $ouvertParLaRequete et termineLaRequete().
     */
    public function initialisePourLaRequete(Etablissement $etablissement): void
    {
        if ($this->courant && $this->courant->code === $etablissement->code) {
            return;
        }

        $this->initialise($etablissement);
        $this->ouvertParLaRequete = true;
    }

    /** Referme le contexte seulement si la requête l'avait ouvert elle-même. */
    public function termineLaRequete(): void
    {
        if (! $this->ouvertParLaRequete) {
            return;
        }

        $this->ouvertParLaRequete = false;
        $this->termine();
    }

    public function initialise(Etablissement $etablissement): void
    {
        if ($this->courant && $this->courant->code === $etablissement->code) {
            return;
        }

        if ($this->courant) {
            $this->termine();
        }

        $this->sauvegarde = [
            'database.default' => config('database.default'),
            'database.connections.tenant.database' => config('database.connections.tenant.database'),
            'database.connections.tenant.username' => config('database.connections.tenant.username'),
            'database.connections.tenant.password' => config('database.connections.tenant.password'),
            'cache.prefix' => config('cache.prefix'),
            'app.name' => config('app.name'),
            'app.timezone' => config('app.timezone'),
            'filesystems.disks.local.root' => config('filesystems.disks.local.root'),
            'filesystems.disks.public.root' => config('filesystems.disks.public.root'),
            'filesystems.disks.public.url' => config('filesystems.disks.public.url'),
            'filesystems.disks.public_direct.root' => config('filesystems.disks.public_direct.root'),
            'filesystems.disks.public_direct.url' => config('filesystems.disks.public_direct.url'),
        ];

        $code = strtolower($etablissement->code);
        $racine = rtrim((string) config('app.url'), '/');

        config([
            'database.default' => 'tenant',
            'cache.prefix' => 'auditron_' . $code . '_',
            'app.name' => $etablissement->nom,
            'app.timezone' => $etablissement->fuseau ?: $this->sauvegarde['app.timezone'],
            // Photos de pointage et binaires de firmware rangés sous un dossier
            // par établissement : supprimer un client = supprimer sa base et
            // son dossier, rien d'autre à chercher ailleurs.
            'filesystems.disks.local.root' => storage_path('app/private/tenants/' . $code),
            'filesystems.disks.public.root' => storage_path('app/public/tenants/' . $code),
            'filesystems.disks.public.url' => $racine . '/storage/tenants/' . $code,
            'filesystems.disks.public_direct.root' => public_path('tenants/' . $code),
            'filesystems.disks.public_direct.url' => $racine . '/tenants/' . $code,
        ]);

        $this->pointeVersLaBase(
            $this->nomBase($etablissement),
            $etablissement->surchargesConnexion(),
        );
        DB::setDefaultConnection('tenant');
        $this->oublieServicesDerives();
        date_default_timezone_set((string) config('app.timezone'));

        $this->courant = $etablissement;
        app()->instance('auditron.etablissement', $etablissement);
    }

    public function termine(): void
    {
        if (! $this->courant) {
            return;
        }

        $sauvegarde = $this->sauvegarde;
        $basePrecedente = $sauvegarde['database.connections.tenant.database'] ?? null;
        $identifiantsPrecedents = [
            'username' => $sauvegarde['database.connections.tenant.username'] ?? null,
            'password' => $sauvegarde['database.connections.tenant.password'] ?? null,
        ];

        unset(
            $sauvegarde['database.connections.tenant.database'],
            $sauvegarde['database.connections.tenant.username'],
            $sauvegarde['database.connections.tenant.password'],
        );

        config($sauvegarde);
        $this->pointeVersLaBase($basePrecedente, $identifiantsPrecedents);
        DB::setDefaultConnection((string) config('database.default'));
        $this->oublieServicesDerives();
        date_default_timezone_set((string) config('app.timezone'));

        $this->sauvegarde = [];
        $this->courant = null;
        app()->forgetInstance('auditron.etablissement');
    }

    /**
     * Exécute un traitement dans le contexte d'un établissement puis restaure
     * le contexte précédent — base des commandes qui bouclent sur tous les
     * établissements (`tenants:run`, `tenants:migrate`).
     *
     * @template T
     *
     * @param  callable(Etablissement): T  $traitement
     * @return T
     */
    public function execute(Etablissement $etablissement, callable $traitement): mixed
    {
        $precedent = $this->courant;
        $this->initialise($etablissement);

        try {
            return $traitement($etablissement);
        } finally {
            $this->termine();

            if ($precedent) {
                $this->initialise($precedent);
            }
        }
    }

    /**
     * Nom de la base du locataire : colonne dédiée si l'hébergeur impose son
     * nommage, sinon préfixe + code. En SQLite (tests, démo locale), une base
     * est un fichier : le « nom » est alors un chemin.
     */
    public function nomBase(Etablissement $etablissement): string
    {
        if ($etablissement->db_name) {
            return $etablissement->db_name;
        }

        $code = strtolower($etablissement->code);

        if (config('database.connections.tenant.driver') === 'sqlite') {
            return database_path('tenants/' . $code . '.sqlite');
        }

        return config('auditron.tenant.prefixe_base') . $code;
    }

    /**
     * Fait pointer la connexion `tenant` vers une base, avec ses éventuels
     * identifiants propres, en ne la purgeant que si quelque chose change
     * réellement.
     *
     * La purge inconditionnelle serait tentante mais coûteuse et surtout
     * destructrice : elle ferme la connexion PDO, donc annule la transaction
     * en cours. Deux requêtes successives sur le même établissement — le cas
     * normal — n'ont aucune raison de rouvrir la connexion.
     *
     * @param  array{username?: string|null, password?: string|null}  $identifiants
     *                                                                              Valeurs laissées de côté : celles du `.env` sont conservées.
     */
    private function pointeVersLaBase(?string $base, array $identifiants = []): void
    {
        $cible = [
            'database' => $base,
            'username' => $identifiants['username'] ?? config('database.connections.tenant.username'),
            'password' => $identifiants['password'] ?? config('database.connections.tenant.password'),
        ];

        $actuel = [
            'database' => config('database.connections.tenant.database'),
            'username' => config('database.connections.tenant.username'),
            'password' => config('database.connections.tenant.password'),
        ];

        if ($actuel === $cible) {
            return;
        }

        config([
            'database.connections.tenant.database' => $cible['database'],
            'database.connections.tenant.username' => $cible['username'],
            'database.connections.tenant.password' => $cible['password'],
        ]);

        DB::purge('tenant');
    }

    /**
     * Vide les services qui ont mémorisé la configuration précédente : sans
     * ça, un disque ou un store de cache déjà résolu continuerait d'écrire
     * dans le dossier de l'établissement précédent.
     */
    private function oublieServicesDerives(): void
    {
        foreach (['local', 'public', 'public_direct'] as $disque) {
            Storage::forgetDisk($disque);
        }

        Cache::forgetDriver((string) config('cache.default'));
    }
}
