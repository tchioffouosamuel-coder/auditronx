<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Lie un compte back-office (User) à sa fiche enseignant (§admin-mobile)
            // — seul moyen de pointer sa propre présence : la table presences
            // n'a qu'un enseignant_id, jamais de user_id.
            $table->foreignId('enseignant_id')->nullable()->after('id')
                ->constrained('enseignants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('enseignant_id');
        });
    }
};
