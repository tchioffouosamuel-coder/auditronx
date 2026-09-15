<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progression_lecons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained('programmes')->cascadeOnDelete();
            $table->unsignedInteger('trimestre')->nullable();
            $table->unsignedInteger('semaine_numero')->nullable();
            $table->string('periode')->nullable();
            $table->text('unite_apprentissage')->nullable();
            $table->text('unite_enseignement');
            $table->boolean('theorique')->default(false);
            $table->boolean('pratique')->default(false);
            $table->string('duree')->nullable();
            $table->boolean('digitalisee')->default(false);
            $table->unsignedInteger('ordre');
            $table->timestamps();

            $table->unique(['programme_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progression_lecons');
    }
};
