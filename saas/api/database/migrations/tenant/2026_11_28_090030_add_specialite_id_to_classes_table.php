<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattache une classe à une spécialité du référentiel APC.
 *
 * La colonne texte `classes.specialite` est **conservée** : elle s'affiche
 * dans les écrans existants et dans des exports, et la supprimer les casserait
 * pour un gain nul. Elle est désormais tenue à jour en miroir du code de la
 * spécialité (voir ClasseController) — une seule saisie, deux lectures.
 *
 * Aucune reprise automatique n'est tentée : la table `specialites` vient
 * d'être créée et est vide à cet instant. Les classes existantes gardent leur
 * texte libre jusqu'à ce qu'on leur désigne une spécialité à l'écran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('specialite_id')->nullable()->after('specialite')
                ->constrained('specialites')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('specialite_id');
        });
    }
};
