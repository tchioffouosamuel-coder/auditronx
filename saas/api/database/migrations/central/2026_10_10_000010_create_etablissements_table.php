<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les locataires de la plateforme. Une ligne ici = une base de données
 * `auditron_<code>` + un dossier de stockage `tenants/<code>`.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::create('etablissements', function (Blueprint $table) {
            $table->id();

            // Identifiant public envoyé par les clients dans `X-Tenant`, et
            // suffixe du nom de base. Court et stable : il est tapé à la main
            // par les enseignants à l'activation de l'app.
            $table->string('code', 32)->unique();

            $table->string('nom');
            $table->string('nom_court', 64)->nullable();
            $table->string('ville')->nullable();
            $table->string('pays', 64)->default('Cameroun');
            $table->string('fuseau', 64)->default('Africa/Douala');

            // Branding servi à l'exécution : une seule app, N identités.
            $table->string('logo_path')->nullable();
            $table->string('couleur_primaire', 16)->nullable();
            $table->string('couleur_secondaire', 16)->nullable();

            // Renseigné seulement si la base ne suit pas la convention de
            // nommage (reprise d'une base existante, hébergement mutualisé qui
            // impose un préfixe de compte).
            $table->string('db_name')->nullable();

            // Identifiants MySQL propres à cette base.
            //
            // Sur un hébergement mutualisé (cPanel, hPanel Hostinger), on ne
            // crée pas un utilisateur ayant droit sur toutes les bases : chaque
            // base vient avec son propre utilisateur, qui n'a de privilèges que
            // sur elle. Sans ces deux colonnes, la plateforme ne pourrait pas
            // reprendre des bases déjà en production ailleurs.
            //
            // Laissés vides, les identifiants globaux (DB_TENANT_USERNAME /
            // DB_TENANT_PASSWORD, à défaut DB_USERNAME / DB_PASSWORD) sont
            // utilisés — le cas d'un serveur dont on maîtrise MySQL.
            $table->string('db_username')->nullable();
            $table->text('db_password')->nullable(); // chiffré par le modèle

            $table->string('statut', 32)->default('en_attente'); // en_attente | actif | suspendu | archive

            $table->string('contact_nom')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_tel')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('provisionne_le')->nullable();
            $table->timestamp('suspendu_le')->nullable();
            $table->timestamps();

            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etablissements');
    }
};
