<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enseignants', function (Blueprint $table) {
            $table->string('password')->nullable()->after('tel');
            // Accès direct à l'app sans passage par un OTP relayé par un tiers admin.
            $table->boolean('est_admin')->default(false)->after('password');
            $table->unique('tel');
        });
    }

    public function down(): void
    {
        Schema::table('enseignants', function (Blueprint $table) {
            $table->dropUnique(['tel']);
            $table->dropColumn(['password', 'est_admin']);
        });
    }
};
