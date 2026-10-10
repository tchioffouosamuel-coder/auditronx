<?php

/*
|--------------------------------------------------------------------------
| Plateforme Auditron X — configuration multi-établissement
|--------------------------------------------------------------------------
|
| Une seule API, un seul portail, une seule app : l'établissement est une
| donnée, pas une copie du code. Chaque établissement possède sa propre base
| (isolation forte, sauvegarde et suppression par client) ; la base centrale
| ne contient que l'annuaire commercial de la plateforme.
|
*/

return [

    'tenant' => [
        /*
         * URL unique : l'établissement courant est désigné par cet en-tête
         * HTTP. Le portail web, l'app mobile et les bornes l'envoient à chaque
         * requête après l'avoir mémorisé une fois (connexion / activation).
         */
        'entete' => env('AUDITRON_TENANT_HEADER', 'X-Tenant'),

        /*
         * Repli accepté quand l'en-tête n'est pas disponible (formulaire de
         * connexion, test manuel, webview) : champ de requête ou de corps.
         */
        'champ' => env('AUDITRON_TENANT_FIELD', 'etablissement'),

        /*
         * Repli par sous-domaine, désactivé par défaut : l'architecture
         * retenue est une URL unique. L'activer n'interdit rien — l'en-tête
         * reste prioritaire — et permet d'ouvrir plus tard `ltm.domaine.com`
         * sans toucher au code.
         */
        'sous_domaine' => env('AUDITRON_TENANT_SUBDOMAIN', false),

        /*
         * Domaine de base à retirer de l'hôte pour en extraire le code
         * établissement, quand le repli par sous-domaine est actif.
         */
        'domaine_base' => env('AUDITRON_TENANT_BASE_DOMAIN'),

        /* Préfixe des bases locataires : `auditron_ltm`, `auditron_lcm`, ... */
        'prefixe_base' => env('AUDITRON_TENANT_DB_PREFIX', 'auditron_'),
    ],

    /*
     * Catalogue public (`GET /api/central/catalogue`) : liste réduite
     * (code, nom, logo) consommée par l'écran de connexion du portail unique
     * et par l'écran d'activation mobile. Ne contient aucune donnée métier.
     */
    'catalogue_public' => env('AUDITRON_CATALOGUE_PUBLIC', true),

    /*
     * Annuaire téléphone → établissement : index central alimenté par
     * App\Observers\AnnuaireObserver, qui permet à un enseignant ayant oublié
     * son code établissement de le retrouver depuis son seul numéro. Seul un
     * haché du numéro est stocké, jamais le numéro en clair.
     */
    'annuaire' => [
        'actif' => env('AUDITRON_ANNUAIRE', true),
        'sel' => env('AUDITRON_ANNUAIRE_SALT', ''),
    ],

    /*
     * Documentation d'API (Scramble, servie sur `/docs/api`).
     *
     * Hors environnement local, Scramble refuse l'accès par défaut — d'où le
     * 403 en production. L'ouvrir est un choix délibéré : la page expose la
     * liste complète des routes, leurs paramètres et leurs réponses. Ce n'est
     * pas un secret (l'authentification reste exigée pour toute donnée), mais
     * ça facilite le travail de qui cherche une faille, donc ça se décide.
     */
    'docs' => [
        'exposees' => env('AUDITRON_DOCS_PUBLIC', false),
    ],

    /*
     * Provisioning : la création d'un établissement crée sa base. Sur un
     * hébergement mutualisé où l'utilisateur SQL n'a pas le droit `CREATE
     * DATABASE`, passer à false et créer la base à la main (cPanel) avant de
     * lancer `etablissement:create --base-existante`.
     */
    'provisioning' => [
        'cree_la_base' => env('AUDITRON_CREATE_DATABASE', true),
    ],

];
