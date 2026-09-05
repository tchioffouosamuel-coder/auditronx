<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cours_validation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enseignant_id')->constrained('enseignants')->cascadeOnDelete();
            $table->foreignId('emploi_du_temps_id')->nullable()->constrained('emploi_du_temps')->nullOnDelete();
            $table->date('date');
            $table->enum('status', ['fait', 'non_fait'])->default('non_fait');
            $table->timestamps();

            $table->unique(['emploi_du_temps_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cours_validation');
    }
};
