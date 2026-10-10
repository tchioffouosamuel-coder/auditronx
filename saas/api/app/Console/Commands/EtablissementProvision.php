<?php

namespace App\Console\Commands;

use App\Models\Central\Etablissement;
use App\Tenancy\ProvisionneurEtablissement;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * Reprend le provisioning d'un établissement déjà déclaré, en corrigeant au
 * besoin ses accès à la base.
 *
 * Cas d'usage principal : un `etablissement:create` dont la fiche a bien été
 * créée mais dont la base a refusé la connexion (mot de passe MySQL changé
 * depuis le `.env` d'origine, utilisateur sans privilèges). L'établissement
 * reste alors en `en_attente`, donc invisible pour les clients — ce qui est le
 * bon comportement — et c'est cette commande qui le remet en route une fois
 * les identifiants corrigés, sans ressaisir tout le dossier.
 */
class EtablissementProvision extends Command
{
    protected $signature = 'etablissement:provision
        {code : Code de l\'établissement déjà déclaré}
        {--db= : Corriger le nom de la base}
        {--db-user= : Corriger l\'utilisateur MySQL}
        {--db-password= : Corriger le mot de passe MySQL}
        {--base-existante : Ne pas créer la base, elle existe déjà}
        {--sans-amorcage : Ne pas créer accréditations ni compte de direction}
        {--tester : Tester seulement la connexion, sans rien modifier}';

    protected $description = 'Reprend le provisioning d’un établissement et corrige ses accès à la base.';

    public function handle(ProvisionneurEtablissement $provisionneur, TenantManager $tenants): int
    {
        $code = Etablissement::normaliseCode($this->argument('code'));
        $etablissement = Etablissement::where('code', $code)->first();

        if (! $etablissement) {
            $this->error("Établissement {$code} inconnu. Utiliser etablissement:create.");

            return self::FAILURE;
        }

        $corrections = array_filter([
            'db_name' => $this->option('db'),
            'db_username' => $this->option('db-user'),
            'db_password' => $this->option('db-password'),
        ], fn ($valeur) => $valeur !== null && $valeur !== '');

        if ($corrections !== []) {
            $etablissement->update($corrections);
            $this->line('Accès mis à jour : ' . implode(', ', array_keys($corrections)));
        }

        $this->line('Base visée : ' . $tenants->nomBase($etablissement));
        $this->line('Utilisateur : ' . ($etablissement->db_username ?: '(celui du .env)'));

        // Un test de connexion avant toute écriture : c'est le diagnostic qu'on
        // veut quand les identifiants sont en cause, et il ne touche à rien.
        try {
            $tables = $tenants->execute(
                $etablissement,
                fn () => count(\Illuminate\Support\Facades\DB::connection('tenant')->getSchemaBuilder()->getTableListing())
            );

            $this->info("Connexion établie ({$tables} table(s) dans la base).");
        } catch (Throwable $e) {
            $this->error('Connexion refusée : ' . $e->getMessage());
            $this->newLine();
            $this->line('Pistes : mot de passe MySQL modifié depuis le .env d’origine,');
            $this->line('utilisateur sans privilèges sur cette base, ou base inexistante.');
            $this->line('Corriger dans le panneau de l’hébergeur, puis relancer avec');
            $this->line("  php artisan etablissement:provision {$code} --db-password='…' --base-existante --sans-amorcage");

            return self::FAILURE;
        }

        if ($this->option('tester')) {
            return self::SUCCESS;
        }

        $provisionneur->provisionne(
            $etablissement,
            direction: [],
            creerLaBase: ! $this->option('base-existante'),
            amorcer: ! $this->option('sans-amorcage'),
        );

        $etablissement->refresh();

        $this->newLine();
        $this->info("Établissement {$code} provisionné — statut « {$etablissement->statut} ».");

        if ($identifiants = $provisionneur->dernieresIdentifiants()) {
            $this->line("Compte direction : {$identifiants['email']}");
            $this->line("Mot de passe     : {$identifiants['password']}  (affiché une seule fois)");
        }

        return self::SUCCESS;
    }
}
