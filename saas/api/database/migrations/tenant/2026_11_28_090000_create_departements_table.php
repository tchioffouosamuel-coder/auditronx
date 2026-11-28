<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Départements d'un établissement d'enseignement technique : Génie Électrique,
 * Industrie de l'Habillement, Enseignement Général…
 *
 * Le département n'existait jusqu'ici que comme texte libre dans
 * `disciplines.departement`, saisi à la main et donc orthographié de plusieurs
 * façons. Cette table en fait une entité : les spécialités s'y rattachent, et
 * c'est elle qui regroupera les bulletins par département.
 *
 * `disciplines.departement` n'est pas touchée : elle sert au périmètre des
 * accréditations (voir AccessiblesDisciplines) et la reprendre ici
 * déplacerait un contrôle d'accès sans nécessité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departements', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('nom');
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departements');
    }
};
