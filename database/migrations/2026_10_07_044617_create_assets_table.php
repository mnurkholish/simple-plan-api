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
        Schema::create('assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('master_klasifikasi_id')->constrained('master_klasifikasis');
            $table->foreignId('master_ruang_id')->constrained('master_ruangs');
            $table->foreignId('master_barang_id')->constrained('master_barangs');
            $table->integer('no_urut');
            $table->string('no_inventaris')->unique();
            $table->string('model_spesifikasi', 100)->nullable();
            $table->string('serial_number', 50)->nullable();
            $table->year('tahun_beli');
            $table->enum('status', ['Digunakan', 'Tidak Digunakan', 'Rusak', 'Dipinjam'])->index();
            $table->enum('kondisi', ['Baik', 'Rusak Ringan', 'Rusak Berat']);
            $table->date('garansi_sd')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('foto_barang')->nullable();
            $table->string('dokumen_kalibrasi')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
