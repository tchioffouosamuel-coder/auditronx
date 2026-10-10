<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_activation_requests', function (Blueprint $table) {
            // Capturé côté app *avant* toute authentification (aucun device
            // Sanctum n'existe encore à ce stade, §otp-approval) : c'est le
            // seul moyen de pousser l'OTP par notification au bon téléphone
            // une fois l'admin d'accord, plutôt que de la remettre en personne.
            $table->string('fcm_token')->nullable()->after('device_type');
        });
    }

    public function down(): void
    {
        Schema::table('device_activation_requests', function (Blueprint $table) {
            $table->dropColumn('fcm_token');
        });
    }
};
