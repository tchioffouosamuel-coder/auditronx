<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maquette d'une classe : les modules qu'elle suit réellement, avec leurs
 * crédits, leurs heures, l'enseignant qui signe et les trimestres où chacun
 * est programmé.
 *
 * C'est cette table — et non `modules` — que parcourt un bulletin. Le
 * référentiel dit ce qu'une spécialité comporte ; la maquette dit ce que
 * *cette* classe fait cette année. Les deux divergent : un module peut n'être
 * ouvert qu'à partir du deuxième trimestre, un stage peut n'être pas
 * programmé du tout (le bulletin l'imprime alors avec son crédit entre
 * parenthèses), et deux classes d'une même spécialité peuvent ne pas avoir le
 * même enseignant.
 *
 * `credit` et `volume_horaire` sont **recopiés** du référentiel à
 * l'initialisation plutôt que lus à travers lui : la maquette d'une année
 * close doit rester ce qu'elle était, même si le référentiel est corrigé
 * ensuite.
 *
 * `trimestres` porte le tiret du bulletin annuel. Un module absent du
 * trimestre 1 n'y a pas une case vide à zéro : il a un tiret, et il sort du
 * dénominateur de sa moyenne annuelle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classe_module', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->unsignedSmallInteger('credit')->nullable();
            $table->unsignedSmallInteger('volume_horaire')->nullable();
            $table->foreignId('enseignant_id')->nullable()->constrained('enseignants')->nullOnDelete();
            $table->json('trimestres')->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();

            $table->unique(['classe_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classe_module');
    }
};
