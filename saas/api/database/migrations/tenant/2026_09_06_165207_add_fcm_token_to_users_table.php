<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Token FCM du navigateur (backoffice) : permet de pousser les
            // notifications de validation OTP (§otp-approval) à l'admin même
            // quand l'onglet n'est pas au premier plan.
            $table->string('fcm_token')->nullable()->after('accreditation_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('fcm_token');
        });
    }
};
