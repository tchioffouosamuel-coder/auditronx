<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deux colonnes de la liste officielle des élèves (« MATRICULE NATIONAL »,
 * « TÉLÉPHONE ») que la fiche ne portait pas.
 *
 * Le matricule national n'est pas la clé : il manque sur une bonne part des
 * listes (élèves pas encore immatriculés). La clé reste `matricule`, le
 * matricule interne, présent sur chaque ligne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->string('matricule_national', 50)->nullable()->after('matricule');
            $table->string('telephone', 30)->nullable()->after('lieu_naissance');

            $table->index('matricule_national');
        });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->dropIndex(['matricule_national']);
            $table->dropColumn(['matricule_national', 'telephone']);
        });
    }
};
