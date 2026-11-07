<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lignes de facture (libellé, montant) — la facturation n'émettait jusqu'ici
 * qu'un montant global. Les factures déjà émises sont reconstituées avec une
 * ligne unique reprenant leur montant, pour rester affichables sur le nouveau
 * modèle sans perte de données.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::create('facture_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facture_id')->constrained('factures')->cascadeOnDelete();
            $table->string('libelle');
            $table->decimal('montant', 12, 2);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });

        $factures = DB::connection('central')->table('factures')
            ->leftJoin('abonnements', 'abonnements.id', '=', 'factures.abonnement_id')
            ->leftJoin('plans', 'plans.id', '=', 'abonnements.plan_id')
            ->select('factures.id', 'factures.montant', 'plans.nom as plan_nom')
            ->get();

        foreach ($factures as $facture) {
            DB::connection('central')->table('facture_lignes')->insert([
                'facture_id' => $facture->id,
                'libelle' => 'Abonnement' . ($facture->plan_nom ? ' — ' . $facture->plan_nom : ''),
                'montant' => $facture->montant,
                'ordre' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('facture_lignes');
    }
};
