<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel APC : les modules d'une spécialité pour un niveau donné,
 * rangés dans les quatre blocs du bulletin de compétences (compétences
 * particulières, compétences générales, enseignements généraux, enseignements
 * complémentaires).
 *
 * Table distincte de `disciplines`, et non une extension de celle-ci :
 * `disciplines` porte l'emploi du temps, le cahier de texte, les progressions
 * et le calcul d'assiduité. Un module APC n'est pas une case d'emploi du temps
 * — il regroupe plusieurs séances, vit sur un niveau et une spécialité, et
 * porte des crédits. `discipline_id` reste disponible pour relier les deux
 * quand c'est utile, sans l'imposer.
 *
 * Clé naturelle `(specialite_id, niveau, code)` : un même code (C1 « Métier et
 * formation ») se retrouve légitimement dans toutes les spécialités, et un
 * module de Seconde n'est pas celui de Première.
 *
 * `credit` est nullable : le classeur de l'organisation pédagogique porte les
 * volumes horaires mais pas les crédits, qui se saisissent ensuite. Mettre 0
 * par défaut ferait disparaître le module de la moyenne générale sans que
 * personne ne le remarque.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('specialite_id')->constrained('specialites')->cascadeOnDelete();
            $table->string('niveau');
            $table->string('code');
            $table->string('intitule');
            $table->string('bloc');
            $table->unsignedSmallInteger('volume_horaire')->nullable();
            $table->unsignedSmallInteger('credit')->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->foreignId('discipline_id')->nullable()->constrained('disciplines')->nullOnDelete();
            $table->timestamps();

            $table->unique(['specialite_id', 'niveau', 'code']);
            $table->index(['specialite_id', 'niveau', 'bloc', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
