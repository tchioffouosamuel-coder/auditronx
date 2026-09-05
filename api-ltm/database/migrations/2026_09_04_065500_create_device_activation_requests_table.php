<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_activation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enseignant_id')->constrained('enseignants')->cascadeOnDelete();
            $table->string('device_uuid');
            $table->enum('device_type', ['mobile', 'kiosk_facial'])->default('mobile');
            $table->dateTime('requested_at');
            $table->dateTime('fulfilled_at')->nullable();
            $table->foreignId('otp_id')->nullable()->constrained('otps')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_activation_requests');
    }
};
