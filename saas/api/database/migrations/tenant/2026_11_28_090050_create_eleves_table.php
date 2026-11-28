<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Élèves (§bulletins APC).
 *
 * La fiche ne porte que l'identité — ce que l'en-tête du bulletin imprime :
 * nom, matricule, date et lieu de naissance, sexe. La classe, l'année et le
 * statut (régulier / redoublant) n'y sont pas : ils changent d'une année à
 * l'autre et vivent dans `inscriptions`.
 *
 * `matricule` est unique, contrairement à celui du personnel, dont la
 * contrainte a dû être levée (voir
 * 2026_11_21_212641_remove_unique_matricule_from_enseignants). La raison
 * diffère : le matricule d'un élève est la clé de l'import du fichier de
 * classe et s'imprime sur le bulletin. Deux élèves qui le partagent, ce sont
 * deux bulletins qu'on ne sait plus attribuer — mieux vaut un import qui
 * refuse la ligne.
 *
 * Pas de suppression douce : une fiche élève se supprime rarement, et ses
 * bulletins doivent survivre. Le lot « bulletins » posera un
 * `restrictOnDelete` sur `bulletins.inscription_id`, pour qu'une suppression
 * d'élève déjà noté échoue franchement au lieu d'emporter ses bulletins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eleves', function (Blueprint $table) {
            $table->id();
            $table->string('matricule')->unique();
            $table->string('nom');
            $table->string('prenoms')->nullable();
            $table->string('sexe', 1)->nullable();
            $table->date('date_naissance')->nullable();
            $table->string('lieu_naissance')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();

            $table->index('nom');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eleves');
    }
};
