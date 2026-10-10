<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Offres, abonnements et factures — le volet commercial de la plateforme. */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->decimal('prix', 12, 2)->default(0);
            $table->string('devise', 8)->default('XAF');
            $table->string('periodicite', 16)->default('mensuel'); // mensuel | trimestriel | annuel

            // null = illimité, pour ne pas confondre « aucun quota » et « zéro ».
            $table->unsignedInteger('max_personnel')->nullable();
            $table->unsignedInteger('max_bornes')->nullable();

            $table->json('fonctionnalites')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('abonnements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->date('debut_le');
            $table->date('fin_le')->nullable(); // null = sans échéance
            $table->string('statut', 16)->default('actif'); // actif | expire | resilie
            $table->decimal('montant', 12, 2)->nullable();
            $table->string('devise', 8)->default('XAF');
            $table->string('periodicite', 16)->default('mensuel');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['etablissement_id', 'statut']);
        });

        Schema::create('factures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('abonnement_id')->nullable()->constrained('abonnements')->nullOnDelete();
            $table->string('numero', 32)->unique();
            $table->decimal('montant', 12, 2);
            $table->string('devise', 8)->default('XAF');
            $table->date('emise_le')->nullable();
            $table->date('echeance_le')->nullable();
            $table->date('payee_le')->nullable();
            $table->string('statut', 16)->default('brouillon'); // brouillon | emise | payee | annulee
            $table->string('moyen_paiement', 32)->nullable();   // mobile_money | virement | especes
            $table->string('reference_paiement')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['etablissement_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factures');
        Schema::dropIfExists('abonnements');
        Schema::dropIfExists('plans');
    }
};
