<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Borne ESP32-S3 + caméra OV5640 (§hardware) : photo prise au moment du
     * scan, remontée en base64 dans le paquet relayé, décodée et stockée
     * côté API — preuve visuelle que la présence a été pointée par la bonne
     * personne (anti-fraude), consultable par l'admin sur chaque pointage.
     */
    public function up(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            // Une photo par événement (arrivée/départ), comme heure_arrivee/heure_depart —
            // sinon la photo du départ écraserait la preuve visuelle de l'arrivée.
            $table->string('photo_path_arrivee')->nullable()->after('device_capture_at');
            $table->string('photo_path_depart')->nullable()->after('photo_path_arrivee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->dropColumn(['photo_path_arrivee', 'photo_path_depart']);
        });
    }
};
