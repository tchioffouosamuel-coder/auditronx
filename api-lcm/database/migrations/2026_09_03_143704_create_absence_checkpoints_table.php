<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enseignant_id')->constrained('enseignants')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('absences_consecutives')->default(0);
            $table->timestamps();

            $table->unique(['enseignant_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_checkpoints');
    }
};
