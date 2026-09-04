<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accreditations', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            // '*' = accès total (direction/admin) ; sinon nom de section/groupe
            $table->string('groupe')->nullable();
            // 1 à 4 : niveau d'accès aux classes/disciplines ; null = pas de restriction
            $table->unsignedTinyInteger('niveau')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accreditations');
    }
};
