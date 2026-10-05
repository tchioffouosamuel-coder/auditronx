<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Journal d'audit (§4.2) : trace immuable de toute action modifiant les
        // données, pour pouvoir établir les responsabilités. Des fiches de
        // personnel disparaissaient (suppression logique) sans qu'on puisse
        // dire qui les avait supprimées ni quand.
        //
        // L'auteur est dénormalisé (nom, email, accréditation recopiés) et non
        // une simple clé étrangère : un journal doit rester lisible même après
        // la suppression ou le renommage du compte qui a agi.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->string('auteur_type')->nullable();   // user | enseignant | borne | systeme
            $table->unsignedBigInteger('auteur_id')->nullable();
            $table->string('auteur_nom')->nullable();
            $table->string('auteur_email')->nullable();
            $table->string('auteur_accreditation')->nullable();

            $table->string('action');                    // ex. enseignant.supprime
            $table->string('sujet_type')->nullable();    // classe du modèle visé
            $table->unsignedBigInteger('sujet_id')->nullable();
            $table->string('sujet_libelle')->nullable(); // nom lisible au moment de l'action

            // {champ: {avant, apres}} — valeurs sensibles remplacées par «•••».
            $table->json('changements')->nullable();
            $table->json('contexte')->nullable();        // payload nettoyé, paramètres de route

            $table->string('methode', 10)->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('route')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->unsignedSmallInteger('statut')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index('action');
            $table->index('created_at');
            $table->index(['sujet_type', 'sujet_id']);
            $table->index(['auteur_type', 'auteur_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
