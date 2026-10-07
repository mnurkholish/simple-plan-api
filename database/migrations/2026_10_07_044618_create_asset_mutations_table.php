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
        Schema::create('asset_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('asset_id')->constrained('assets');
            $table->foreignId('master_ruang_id_lama')->nullable()->constrained('master_ruangs');
            $table->foreignId('master_ruang_id_baru')->constrained('master_ruangs');
            $table->dateTime('waktu_perubahan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_mutations');
    }
};
