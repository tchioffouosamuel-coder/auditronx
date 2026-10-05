<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Le groupe '*' (accès total) ne suffisait plus à décrire les rôles
        // réels : un Surveillant Général doit voir tout le personnel
        // enseignant, toutes sections confondues, mais pas les fiches du
        // personnel administratif. Un indicateur dédié sépare l'étendue
        // (groupe) de cette exclusion, plutôt que d'inventer une valeur de
        // groupe que chaque requête aurait dû interpréter.
        Schema::table('accreditations', function (Blueprint $table) {
            $table->boolean('exclut_administration')->default(false)->after('niveau');
        });

        DB::table('accreditations')
            ->whereRaw("LOWER(label) LIKE '%surveillant%g%n%ral%'")
            ->update(['exclut_administration' => true]);
    }

    public function down(): void
    {
        Schema::table('accreditations', function (Blueprint $table) {
            $table->dropColumn('exclut_administration');
        });
    }
};
