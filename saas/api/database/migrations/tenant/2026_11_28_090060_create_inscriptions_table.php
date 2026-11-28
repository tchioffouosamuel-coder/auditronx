<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inscription d'un élève dans une classe, pour une année scolaire.
 *
 * **C'est l'inscription, et non l'élève, qui est l'unité du chantier
 * bulletins.** Les notes et les bulletins s'y accrochent, jamais à
 * `eleve_id` : c'est ce qui permet à un redoublant d'avoir deux années
 * côte à côte, chacune avec ses notes et ses bulletins, sans que la seconde
 * écrase la première.
 *
 * Un élève n'a qu'une inscription par année — d'où la clé naturelle. Changer
 * de classe en cours d'année, c'est modifier l'inscription, pas en créer une
 * seconde : il n'y a qu'un bulletin par trimestre.
 *
 * `actif` distingue l'élève parti en cours d'année de celui qui n'a jamais
 * été inscrit. Il reste dans les effectifs passés et garde ses bulletins, mais
 * sort des listes de saisie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->string('annee_scolaire', 20);
            $table->string('statut', 20)->default('regulier');
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['eleve_id', 'annee_scolaire']);
            $table->index(['classe_id', 'annee_scolaire', 'actif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscriptions');
    }
};
