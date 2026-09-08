<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enseignants', function (Blueprint $table) {
            // Photo de référence pour l'enrôlement facial (§5), chargée depuis la
            // plateforme web. photo_updated_at permet aux bornes de détecter une
            // photo nouvelle/modifiée sans comparer le fichier lui-même.
            $table->string('photo_path')->nullable()->after('poste');
            $table->dateTime('photo_updated_at')->nullable()->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('enseignants', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'photo_updated_at']);
        });
    }
};
