<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programmes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discipline_id')->constrained('disciplines')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->string('annee_scolaire');
            $table->unsignedInteger('nb_seances_prevues');
            $table->timestamps();

            $table->unique(['discipline_id', 'classe_id', 'annee_scolaire'], 'programmes_discipline_classe_annee_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programmes');
    }
};
