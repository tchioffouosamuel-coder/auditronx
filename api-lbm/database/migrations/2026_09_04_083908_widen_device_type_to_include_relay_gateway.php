<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Passe device_type d'un enum figé à une chaîne libre (validée en amont
     * par les FormRequests/contrôleurs) — évite de dépendre de doctrine/dbal
     * pour élargir un CHECK d'enum à chaque nouveau type de device, et permet
     * d'ajouter 'relay_gateway' (passerelle ESP32, §hardware) sans y revenir.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('device_type_new')->default('mobile')->after('device_type');
        });

        DB::table('devices')->orderBy('id')->chunkById(200, function ($devices) {
            foreach ($devices as $device) {
                DB::table('devices')->where('id', $device->id)->update(['device_type_new' => $device->device_type]);
            }
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('device_type');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->renameColumn('device_type_new', 'device_type');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->enum('device_type_old', ['mobile', 'kiosk_facial'])->default('mobile')->after('device_type');
        });

        DB::table('devices')->orderBy('id')->chunkById(200, function ($devices) {
            foreach ($devices as $device) {
                DB::table('devices')->where('id', $device->id)->update([
                    'device_type_old' => in_array($device->device_type, ['mobile', 'kiosk_facial']) ? $device->device_type : 'mobile',
                ]);
            }
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('device_type');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->renameColumn('device_type_old', 'device_type');
        });
    }
};
