<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le barème en vigueur au calcul, recopié sur le bulletin (§bulletins APC,
 * lot 6).
 *
 * Le bulletin imprime la légende des mentions et le seuil de réussite. Les
 * lire dans la configuration du jour ferait mentir une réimpression : un
 * bulletin calculé avec « B ≥ 85 » et réimprimé après un passage à « B ≥ 86 »
 * afficherait une mention B à 85,0 sous une légende qui la contredit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulletins', function (Blueprint $table) {
            $table->json('bareme')->nullable()->after('empreinte');
        });
    }

    public function down(): void
    {
        Schema::table('bulletins', function (Blueprint $table) {
            $table->dropColumn('bareme');
        });
    }
};
