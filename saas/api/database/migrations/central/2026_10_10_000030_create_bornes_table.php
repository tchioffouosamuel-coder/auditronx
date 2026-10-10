<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parc matériel vu depuis l'éditeur. Le `device_uuid` est le même que celui de
 * la table `devices` de la base du client : c'est la clé qui permet de relier
 * une borne physique à son établissement sans ouvrir la base de celui-ci.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::create('bornes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->string('device_uuid');
            $table->string('libelle')->nullable();
            $table->string('emplacement')->nullable();
            $table->string('firmware_version', 32)->nullable();
            $table->timestamp('derniere_vue_le')->nullable();
            $table->timestamps();

            // Un même UUID ne peut pas être revendiqué par deux établissements.
            $table->unique('device_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bornes');
    }
};
