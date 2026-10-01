<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Firmwares OTA des bornes relais (§hardware). Un firmware vise UNE
        // borne : son token API (RELAY_API_TOKEN) est compilé dans le binaire,
        // le même .bin ne peut donc pas servir à deux bornes.
        Schema::create('firmwares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('version');           // semver ex: "1.2.0", comparé à FIRMWARE_VERSION côté borne
            $table->string('path');              // disque "local" (privé : le binaire contient le token)
            $table->string('sha256', 64);        // calculé côté serveur, vérifié par la borne avant flash
            $table->unsignedInteger('size_bytes');
            $table->text('release_notes')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['device_id', 'version']);
        });

        Schema::table('devices', function (Blueprint $table) {
            // Version rapportée par la borne à chaque vérification OTA.
            $table->string('firmware_version')->nullable()->after('device_type');
            $table->timestamp('firmware_checked_at')->nullable()->after('firmware_version');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['firmware_version', 'firmware_checked_at']);
        });
        Schema::dropIfExists('firmwares');
    }
};
