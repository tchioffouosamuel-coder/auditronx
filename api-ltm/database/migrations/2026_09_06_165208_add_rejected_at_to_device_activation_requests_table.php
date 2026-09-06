<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_activation_requests', function (Blueprint $table) {
            // Distinct de fulfilled_at : une demande refusée par l'admin (notification
            // de validation, §otp-approval) ne doit pas rester "en attente" ni être
            // confondue avec une demande traitée avec succès.
            $table->dateTime('rejected_at')->nullable()->after('fulfilled_at');
        });
    }

    public function down(): void
    {
        Schema::table('device_activation_requests', function (Blueprint $table) {
            $table->dropColumn('rejected_at');
        });
    }
};
