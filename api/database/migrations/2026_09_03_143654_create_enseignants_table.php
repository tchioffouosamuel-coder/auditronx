<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enseignants', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('matricule')->unique();
            $table->string('email')->nullable()->unique();
            $table->string('fonction')->nullable();
            $table->string('section')->nullable();
            $table->string('grade')->nullable();
            $table->string('tel')->nullable();
            $table->string('poste')->nullable();
            // Legacy: identifiant carte/empreinte RFID, conservé pendant la coexistence puis décommissionné
            $table->string('rfid_uid')->nullable()->unique();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enseignants');
    }
};
