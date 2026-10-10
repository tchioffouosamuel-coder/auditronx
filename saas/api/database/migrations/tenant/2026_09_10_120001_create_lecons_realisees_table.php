<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lecons_realisees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enseignant_id')->constrained('enseignants')->cascadeOnDelete();
            $table->foreignId('emploi_du_temps_id')->constrained('emploi_du_temps')->cascadeOnDelete();
            $table->foreignId('progression_lecon_id')->constrained('progression_lecons')->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();

            $table->unique(['emploi_du_temps_id', 'progression_lecon_id', 'date'], 'lecons_realisees_cours_lecon_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecons_realisees');
    }
};
