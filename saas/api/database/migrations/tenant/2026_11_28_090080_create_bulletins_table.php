<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bulletins de compétences (§bulletins APC, lot 5).
 *
 * Un bulletin par inscription et par période — un trimestre, ou l'année.
 * `periode` vaut « 1 », « 2 », « 3 » ou « annuel » : une seule colonne non
 * nulle porte la clé, parce qu'un index unique sur un `trimestre` nullable ne
 * protège rien (deux NULL ne sont jamais égaux en SQL, et deux bulletins
 * annuels passeraient). `type` et `trimestre` sont gardés à côté pour la
 * lecture et l'impression.
 *
 * ## Versions
 *
 * Un bulletin validé n'est jamais réécrit. Le rouvrir le marque remplacé
 * (`remplace_at`, `remplace_par`, `motif_reouverture`) et la génération
 * suivante crée la version n+1 ; l'ancienne reste en base telle qu'elle a été
 * signée. Le bulletin **courant** est celui dont `remplace_at` est nul.
 * Un brouillon, lui, n'a été remis à personne : le régénérer le réécrit.
 *
 * ## Décision
 *
 * `decision_proposee` est ce que produit le barème ; `decision_conseil` ce
 * que le conseil de classe a retenu à la place, avec son auteur et sa date.
 * Nul veut dire « le conseil suit la proposition ». Les garder séparées, c'est
 * ce qui permet au PV de dire où le conseil s'est écarté du calcul.
 *
 * ## Empreinte
 *
 * `empreinte` résume tout ce dont le calcul dépend — notes, maquette, liste
 * de classe, barème — au moment de la génération. La validation la compare à
 * l'état présent : un conseil ne doit pas figer des bulletins calculés avant
 * la dernière correction de notes.
 *
 * `inscription_id` et `classe_id` sont en `restrictOnDelete` : la suppression
 * d'un élève ou d'une classe qui a des bulletins échoue franchement au lieu de
 * les emporter (annoncé par la migration des élèves).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulletins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions')->restrictOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->restrictOnDelete();
            $table->string('annee_scolaire', 20);
            $table->string('periode', 10);
            $table->string('type', 10);
            $table->unsignedTinyInteger('trimestre')->nullable();
            $table->unsignedSmallInteger('version')->default(1);

            // Ce que l'en-tête imprime et qui peut changer ensuite : la
            // classe d'un élève qui en change en cours d'année, son statut.
            $table->string('classe_code')->nullable();
            $table->string('classe_nom')->nullable();
            $table->string('statut_inscription', 20)->nullable();

            $table->decimal('moyenne_generale', 5, 2)->nullable();
            $table->unsignedSmallInteger('total_credits')->default(0);
            $table->unsignedSmallInteger('total_volume_horaire')->nullable();
            $table->unsignedSmallInteger('modules_notes')->default(0);
            $table->unsignedSmallInteger('competences_non_validees')->default(0);
            $table->unsignedSmallInteger('notes_manquantes')->default(0);
            $table->string('mention', 2)->nullable();
            $table->string('validation_competences', 20)->nullable();

            $table->unsignedSmallInteger('rang')->nullable();
            $table->boolean('ex_aequo')->default(false);
            $table->unsignedSmallInteger('effectif')->default(0);
            $table->unsignedSmallInteger('effectif_classe')->default(0);
            $table->string('position_groupe', 20)->nullable();
            $table->decimal('moyenne_classe', 5, 2)->nullable();
            $table->decimal('moyenne_max', 5, 2)->nullable();
            $table->decimal('moyenne_min', 5, 2)->nullable();

            $table->string('decision_proposee', 30)->nullable();
            $table->string('decision_conseil', 30)->nullable();
            $table->foreignId('decision_conseil_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decision_conseil_at')->nullable();
            $table->text('observation')->nullable();

            $table->string('statut', 20)->default('brouillon');
            $table->string('empreinte', 64)->nullable();
            $table->timestamp('genere_at')->nullable();
            $table->foreignId('genere_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_at')->nullable();
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('remplace_at')->nullable();
            $table->foreignId('remplace_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motif_reouverture')->nullable();
            $table->timestamps();

            $table->unique(['inscription_id', 'periode', 'version'], 'bulletins_unique');
            // Le conseil lit toujours une classe, une année, une période.
            $table->index(['classe_id', 'annee_scolaire', 'periode', 'remplace_at'], 'bulletins_conseil');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulletins');
    }
};
