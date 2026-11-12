<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pauses de la journée (récréation, pause de midi). Elles vivent dans la même
 * grille que les périodes de cours — ce sont des plages horaires de
 * l'établissement — mais elles n'en sont pas : une pause ne se compte pas
 * comme une période à rattraper, et elle ne se propose pas à la saisie d'un
 * créneau. Le drapeau les distingue ; la colonne vaut `false` pour tout ce qui
 * existe déjà, qui reste donc une période de cours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periodes_cours', function (Blueprint $table) {
            $table->boolean('est_pause')->default(false)->after('libelle');
        });
    }

    public function down(): void
    {
        Schema::table('periodes_cours', function (Blueprint $table) {
            $table->dropColumn('est_pause');
        });
    }
};
