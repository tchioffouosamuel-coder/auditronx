<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `parametres.valeur` était un VARCHAR(255) : il suffisait tant qu'un
 * paramètre tenait en une ligne. L'en-tête de l'établissement y range
 * désormais ses deux colonnes de lignes en JSON — une vingtaine de lignes de
 * 150 caractères au plus — et MySQL refusait l'enregistrement (1406, « Data
 * too long »). Un TEXT laisse la place sans rien changer à la lecture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            $table->text('valeur')->change();
        });
    }

    public function down(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            $table->string('valeur')->change();
        });
    }
};
