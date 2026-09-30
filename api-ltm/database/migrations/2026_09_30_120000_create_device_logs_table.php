<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lignes du moniteur série des bornes relais (§hardware), remontées
        // par la borne elle-même pour diagnostic à distance depuis le
        // backoffice. Volatiles : purgées au-delà de DeviceLog::RETENTION_DAYS.
        Schema::create('device_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->text('message');
            // Horodatage estimé de la ligne côté borne (reconstitué à partir
            // de son uptime, la borne n'ayant pas forcément l'heure NTP au
            // moment où la ligne est imprimée — ex. pendant le boot).
            $table->dateTime('logged_at');
            $table->unsignedBigInteger('uptime_ms')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['device_id', 'id']);
            $table->index('logged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_logs');
    }
};
