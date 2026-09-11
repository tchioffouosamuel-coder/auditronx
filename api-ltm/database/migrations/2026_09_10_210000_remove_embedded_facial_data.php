<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('visages_embeddings');

        if (Schema::hasColumn('enseignants', 'photo_path')) {
            Schema::table('enseignants', function (Blueprint $table): void {
                $table->dropColumn(['photo_path', 'photo_updated_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('enseignants', function (Blueprint $table): void {
            $table->string('photo_path')->nullable();
            $table->dateTime('photo_updated_at')->nullable();
        });
    }
};