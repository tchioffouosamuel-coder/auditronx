<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->nullable()->constrained('enseignants')->cascadeOnDelete();
            $table->string('device_uuid')->unique();
            $table->enum('device_type', ['mobile', 'kiosk_facial'])->default('mobile');
            $table->dateTime('activated_at')->nullable();
            $table->foreignId('otp_id')->nullable()->constrained('otps')->nullOnDelete();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
