<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * « J'ai oublié mon code établissement » : index haché numéro → établissement.
 *
 * Aucun numéro en clair, aucun nom : la table ne permet pas de reconstituer
 * l'annuaire des clients. Un même numéro peut pointer plusieurs
 * établissements (un vacataire qui enseigne dans deux lycées), d'où l'unicité
 * sur le couple et non sur le seul haché.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::create('annuaire_entrees', function (Blueprint $table) {
            $table->id();
            $table->string('tel_hash', 64)->index();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->timestamp('vu_le')->nullable();
            $table->timestamps();

            $table->unique(['tel_hash', 'etablissement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annuaire_entrees');
    }
};
