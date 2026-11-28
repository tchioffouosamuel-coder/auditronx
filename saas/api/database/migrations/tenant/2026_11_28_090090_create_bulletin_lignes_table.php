<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lignes d'un bulletin : un module, tel qu'il a été imprimé.
 *
 * **Tout est recopié.** Code, intitulé, bloc, crédit, heures, notes, mention,
 * signataire : un bulletin signé et remis aux parents ne doit pas changer
 * parce que quelqu'un a corrigé un crédit trois semaines plus tard.
 * `classe_module_id` et `module_id` ne sont là que pour retrouver l'origine ;
 * ils passent à NULL si elle disparaît, la ligne reste.
 *
 * `notes_trimestres` (json, `{"1": 87.0, "2": null}`) plutôt que trois
 * colonnes `note_t1..t3` : le nombre de trimestres est un réglage de
 * l'établissement (voir BaremeApc), et `trimestres` recopie la programmation
 * pour que l'impression sache distinguer le tiret (non programmé) d'une case
 * vide.
 *
 * `hors_totaux` : le module est imprimé mais ne pèse ni sur la moyenne, ni
 * sur les crédits, ni sur les heures — crédit entre parenthèses au bulletin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulletin_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bulletin_id')->constrained('bulletins')->cascadeOnDelete();
            $table->foreignId('classe_module_id')->nullable()->constrained('classe_module')->nullOnDelete();
            $table->foreignId('module_id')->nullable()->constrained('modules')->nullOnDelete();
            $table->unsignedSmallInteger('ordre')->default(0);

            $table->string('code');
            $table->string('intitule');
            $table->string('bloc');
            $table->unsignedSmallInteger('credit')->nullable();
            $table->unsignedSmallInteger('volume_horaire')->nullable();
            $table->json('trimestres')->nullable();
            $table->boolean('hors_totaux')->default(false);

            $table->decimal('moyenne_uea', 5, 2)->nullable();
            $table->decimal('note_integratrice', 5, 2)->nullable();
            $table->json('notes_trimestres')->nullable();
            $table->decimal('note_finale', 5, 2)->nullable();
            $table->boolean('note_manquante')->default(false);

            $table->string('mention', 2)->nullable();
            $table->string('sanction', 2)->nullable();
            $table->string('appreciation')->nullable();
            $table->string('signataire')->nullable();
            $table->timestamps();

            $table->index(['bulletin_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulletin_lignes');
    }
};
