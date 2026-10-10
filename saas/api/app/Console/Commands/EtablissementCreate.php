<?php

namespace App\Console\Commands;

use App\Models\Central\Abonnement;
use App\Models\Central\Etablissement;
use App\Models\Central\Plan;
use App\Tenancy\ProvisionneurEtablissement;
use Illuminate\Console\Command;

/**
 * Ouverture d'un nouvel abonné : une commande, pas une copie du code.
 *
 *   php artisan etablissement:create LTM "Lycée Technique de Meiganga" \
 *       --ville=Meiganga --plan=standard --direction-email=direction@ltm.cm
 */
class EtablissementCreate extends Command
{
    protected $signature = 'etablissement:create
        {code : Code court de l\'établissement (sert d\'en-tête X-Tenant et de suffixe de base)}
        {nom : Nom complet de l\'établissement}
        {--nom-court= : Nom affiché dans l\'app mobile}
        {--ville=}
        {--fuseau=Africa/Douala}
        {--couleur= : Couleur primaire du branding, ex. #0F766E}
        {--contact-nom=}
        {--contact-email=}
        {--contact-tel=}
        {--plan= : Code du plan à souscrire (abonnement créé et actif)}
        {--direction-email= : E-mail du compte de direction à créer}
        {--direction-password= : Mot de passe imposé (sinon tiré au hasard)}
        {--db= : Nom de base imposé par l\'hébergeur (sinon auditron_<code>)}
        {--db-user= : Utilisateur MySQL propre à cette base (hébergement mutualisé)}
        {--db-password= : Mot de passe de cet utilisateur}
        {--base-existante : Ne pas créer la base, elle existe déjà (reprise, hébergement mutualisé)}
        {--sans-amorcage : Migrer sans créer accréditations ni compte de direction}';

    protected $description = 'Crée un établissement abonné : base dédiée, migrations, compte de direction.';

    public function handle(ProvisionneurEtablissement $provisionneur): int
    {
        $code = Etablissement::normaliseCode($this->argument('code'));

        if ($code === '') {
            $this->error('Code invalide : lettres, chiffres, tiret et souligné uniquement.');

            return self::FAILURE;
        }

        if (Etablissement::where('code', $code)->exists()) {
            $this->error("L'établissement {$code} existe déjà. Utiliser etablissement:provision pour le réparer.");

            return self::FAILURE;
        }

        $etablissement = Etablissement::create([
            'code' => $code,
            'nom' => $this->argument('nom'),
            'nom_court' => $this->option('nom-court'),
            'ville' => $this->option('ville'),
            'fuseau' => $this->option('fuseau'),
            'couleur_primaire' => $this->option('couleur'),
            'contact_nom' => $this->option('contact-nom'),
            'contact_email' => $this->option('contact-email'),
            'contact_tel' => $this->option('contact-tel'),
            'db_name' => $this->option('db'),
            'db_username' => $this->option('db-user'),
            'db_password' => $this->option('db-password'),
            'statut' => Etablissement::STATUT_EN_ATTENTE,
        ]);

        $this->info("Établissement {$code} créé. Provisioning de la base…");

        $provisionneur->provisionne(
            $etablissement,
            direction: array_filter([
                'email' => $this->option('direction-email'),
                'password' => $this->option('direction-password'),
            ]),
            creerLaBase: ! $this->option('base-existante'),
            amorcer: ! $this->option('sans-amorcage'),
        );

        $this->souscrit($etablissement);

        $this->newLine();
        $this->info('--- Établissement prêt ---');
        $this->line("Code (en-tête X-Tenant) : {$etablissement->code}");
        $this->line('Base de données         : ' . app(\App\Tenancy\TenantManager::class)->nomBase($etablissement));

        if ($identifiants = $provisionneur->dernieresIdentifiants()) {
            $this->line("Compte direction        : {$identifiants['email']}");
            $this->line("Mot de passe            : {$identifiants['password']}  (à changer à la première connexion)");
        }

        return self::SUCCESS;
    }

    /** Souscription immédiate au plan demandé, si un plan est passé. */
    private function souscrit(Etablissement $etablissement): void
    {
        $codePlan = $this->option('plan');

        if (! $codePlan) {
            $this->warn('Aucun plan passé : l’établissement n’a pas d’abonnement actif.');

            return;
        }

        $plan = Plan::where('code', $codePlan)->first();

        if (! $plan) {
            $this->warn("Plan « {$codePlan} » inconnu : abonnement non créé.");

            return;
        }

        Abonnement::create([
            'etablissement_id' => $etablissement->id,
            'plan_id' => $plan->id,
            'debut_le' => now()->toDateString(),
            'statut' => Abonnement::STATUT_ACTIF,
            'montant' => $plan->prix,
            'devise' => $plan->devise,
            'periodicite' => $plan->periodicite,
        ]);

        $this->line("Abonnement créé sur le plan {$plan->nom}.");
    }
}
