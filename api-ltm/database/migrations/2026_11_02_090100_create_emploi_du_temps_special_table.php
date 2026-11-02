<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Emploi du temps spécial (§4.2 — Assiduité) : plage de présence attendue
 * d'un membre du personnel dont l'activité est générale et ne se découpe pas
 * en cours. Une ligne par jour travaillé. Dès qu'une personne en possède un,
 * son assiduité se calcule dessus plutôt que sur ses cours ou sur l'horaire
 * administratif global, et aucune période n'est portée à son rattrapage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emploi_du_temps_special', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enseignant_id')->constrained('enseignants')->cascadeOnDelete();
            $table->unsignedTinyInteger('jour'); // 1 (lundi) à 7 (dimanche)
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->timestamps();

            // Une seule plage par jour : deux lignes concurrentes rendraient
            // l'heure d'arrivée attendue ambiguë.
            $table->unique(['enseignant_id', 'jour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emploi_du_temps_special');
    }
};
