<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_alert_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enseignant_id')->constrained('enseignants')->cascadeOnDelete();
            $table->foreignId('absence_checkpoint_id')->nullable()->constrained('absence_checkpoints')->nullOnDelete();
            $table->dateTime('sent_at');
            $table->string('canal')->default('mail');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_alert_logs');
    }
};
