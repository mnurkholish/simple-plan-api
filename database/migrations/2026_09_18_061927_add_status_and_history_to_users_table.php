<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status_user')->default('Aktif')->after('status');
            $table->string('alasan_nonaktif')->nullable()->after('status_user');
            $table->json('riwayat_status_akun')->nullable()->after('alasan_nonaktif');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status_user', 'alasan_nonaktif', 'riwayat_status_akun']);
        });
    }
};
