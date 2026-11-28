<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notes d'un module, pour un élève, à un trimestre.
 *
 * Deux chiffres saisis — la moyenne des UEA et l'épreuve intégratrice — et
 * leur somme. **Saisie agrégée** : c'est le geste du censeur devant son
 * tableau, et non le détail des évaluations. Une table d'évaluations reste
 * possible plus tard ; elle alimenterait `moyenne_uea` sans rien changer ici.
 *
 * `note_finale` est **stockée et non recalculée à l'affichage**. Le barème est
 * réglable par établissement : un bulletin imprimé en décembre doit rester
 * reproductible si la répartition 60/40 change en mars. La valeur est posée à
 * chaque enregistrement par BaremeApc (voir le modèle NoteModule).
 *
 * La note s'accroche à `inscription_id`, jamais à l'élève : un redoublant a
 * deux inscriptions, donc deux jeux de notes, et la seconde année n'écrase pas
 * la première. Elle s'accroche à `classe_module_id`, et non au module du
 * référentiel : c'est la ligne de maquette qui porte le crédit et les
 * trimestres de programmation dont le bulletin a besoin.
 *
 * `verrouillee_at` est posé à la validation d'un bulletin (lot suivant) :
 * une note qui a servi à imprimer un document signé ne se modifie plus sans
 * geste explicite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions')->cascadeOnDelete();
            $table->foreignId('classe_module_id')->constrained('classe_module')->cascadeOnDelete();
            $table->unsignedTinyInteger('trimestre');
            $table->decimal('moyenne_uea', 5, 2)->nullable();
            $table->decimal('note_integratrice', 5, 2)->nullable();
            $table->decimal('note_finale', 5, 2)->nullable();
            $table->string('appreciation')->nullable();
            $table->foreignId('saisie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verrouillee_at')->nullable();
            $table->timestamps();

            $table->unique(['inscription_id', 'classe_module_id', 'trimestre'], 'notes_modules_unique');
            // La grille de saisie et le calcul d'un bulletin lisent toujours
            // par (module de la maquette, trimestre).
            $table->index(['classe_module_id', 'trimestre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes_modules');
    }
};
