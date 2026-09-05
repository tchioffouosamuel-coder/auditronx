<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->enum('source', ['app_mobile', 'admin_proxy', 'reconnaissance_faciale', 'manuel'])
                ->default('manuel')->after('heure_depart');
            $table->foreignId('access_point_id')->nullable()->after('source')
                ->constrained('access_points')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->after('access_point_id')
                ->constrained('devices')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->after('device_id')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('on_behalf_of')->nullable()->after('recorded_by')
                ->constrained('enseignants')->nullOnDelete();
            $table->string('reason')->nullable()->after('on_behalf_of');
        });
    }

    public function down(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('access_point_id');
            $table->dropConstrainedForeignId('device_id');
            $table->dropConstrainedForeignId('recorded_by');
            $table->dropConstrainedForeignId('on_behalf_of');
            $table->dropColumn(['source', 'reason']);
        });
    }
};
