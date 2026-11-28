<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spécialités : SH, F2, MVT, F6-MIPE…
 *
 * Le code est la clé naturelle, et c'est lui que porte le nom d'onglet du
 * classeur d'import du référentiel — c'est par là qu'une spécialité entre
 * dans l'application. Le libellé long est saisi ensuite : un onglet nommé
 * « F2 » ne dit pas s'il s'agit d'électronique ou d'électrotechnique, et
 * l'inventer produirait des bulletins à l'en-tête fausse.
 *
 * Le département est facultatif : une spécialité importée avant que les
 * départements ne soient saisis doit exister quand même, quitte à être
 * rattachée après coup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialites', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('nom');
            $table->foreignId('departement_id')->nullable()->constrained('departements')->nullOnDelete();
            $table->string('cycle')->nullable();
            $table->boolean('actif')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialites');
    }
};
