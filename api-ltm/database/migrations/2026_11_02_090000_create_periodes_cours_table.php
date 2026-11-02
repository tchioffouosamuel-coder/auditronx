<?php

use App\Models\EmploiDuTemps;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Périodes de cours de l'établissement (§4.2). Les heures proposées à la
 * saisie d'un créneau étaient figées dans trois fichiers — constantes du
 * modèle, de l'écran mobile et de la page web — et deux listes indépendantes
 * laissaient composer des créneaux absurdes (début 15h30, fin 08h10). Une
 * période porte désormais son couple début/fin, et la grille horaire devient
 * propre à l'établissement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodes_cours', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('ordre');
            $table->string('libelle', 60)->nullable();
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->timestamps();

            $table->unique(['heure_debut', 'heure_fin']);
        });

        // Grille historique reprise telle quelle : sans elle, les listes
        // déroulantes des établissements déjà en service seraient vides au
        // premier déploiement et aucun créneau existant ne tomberait sur une
        // période connue.
        $maintenant = now();
        $lignes = [];

        foreach (EmploiDuTemps::HEURES_DEBUT as $index => $debut) {
            $fin = EmploiDuTemps::HEURES_FIN[$index] ?? null;

            if ($fin === null) {
                continue;
            }

            $lignes[] = [
                'ordre' => $index + 1,
                'libelle' => 'P'.($index + 1),
                'heure_debut' => $debut,
                'heure_fin' => $fin,
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ];
        }

        DB::table('periodes_cours')->insert($lignes);
    }

    public function down(): void
    {
        Schema::dropIfExists('periodes_cours');
    }
};
