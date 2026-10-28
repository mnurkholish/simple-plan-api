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
        Schema::table('asset_maintenance_logs', function (Blueprint $table): void {
            $table->dropColumn('photo_path');
            $table->string('proof_photo_object_key', 500)->nullable();
            $table->string('proof_photo_original_name')->nullable();
            $table->string('proof_photo_mime_type', 100)->nullable();
            $table->unsignedBigInteger('proof_photo_size')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_maintenance_logs', function (Blueprint $table): void {
            $table->dropColumn([
                'proof_photo_object_key',
                'proof_photo_original_name',
                'proof_photo_mime_type',
                'proof_photo_size',
            ]);
            $table->string('photo_path', 500)->nullable();
        });
    }
};
