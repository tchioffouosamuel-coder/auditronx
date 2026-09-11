<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enseignants', function (Blueprint $table): void {
            $table->string('photo_path')->nullable()->after('poste');
            $table->dateTime('photo_updated_at')->nullable()->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('enseignants', function (Blueprint $table): void {
            $table->dropColumn(['photo_path', 'photo_updated_at']);
        });
    }
};
