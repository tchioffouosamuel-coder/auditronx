<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otps', function (Blueprint $table) {
            $table->string('code', 6)->nullable()->after('teacher_id');
        });

        Schema::table('otps', function (Blueprint $table) {
            $table->dropColumn('code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('otps', function (Blueprint $table) {
            $table->string('code_hash')->nullable()->after('teacher_id');
        });

        DB::table('otps')->whereNotNull('code')->orderBy('id')->get(['id', 'code'])->each(
            fn(object $otp) => DB::table('otps')->where('id', $otp->id)->update([
                'code_hash' => Hash::make($otp->code),
            ])
        );

        Schema::table('otps', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
