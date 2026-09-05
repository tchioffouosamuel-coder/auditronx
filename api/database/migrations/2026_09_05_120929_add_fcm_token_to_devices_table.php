<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // Token FCM du téléphone (push, §notifications) — remplacé à chaque
            // rafraîchissement par Firebase ; nullable, un device peut ne jamais
            // avoir accepté les notifications.
            $table->string('fcm_token')->nullable()->after('device_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('fcm_token');
        });
    }
};
