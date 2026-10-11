<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            /*
              Libellé lisible d'une borne relais : « Entrée principale »,
              « Portail Nord ». `device_uuid` identifie, il ne décrit pas — et
              une liste de `borne-lbng-02` ne dit pas où se trouve l'appareil
              qu'on s'apprête à révoquer.

              `DeviceController::provisionRelay()` validait déjà un champ
              `label` qu'il n'enregistrait nulle part, faute de colonne : le
              nom donné à la mise en service était silencieusement perdu.
            */
            $table->string('label')->nullable()->after('device_type');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }
};
