<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // Drop old table if exists just in case
        Schema::dropIfExists('assets');

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_code_id')->constrained()->restrictOnDelete();
            $table->string('serial_number')->nullable();
            $table->integer('purchase_year');
            $table->string('status');
            $table->string('condition');
            $table->date('warranty_until')->nullable();
            $table->text('notes')->nullable();

            // Photo attributes
            $table->string('photo_object_key', 500)->nullable();
            $table->string('photo_original_name')->nullable();
            $table->string('photo_mime_type', 100)->nullable();
            $table->unsignedBigInteger('photo_size')->nullable();

            // Calibration attributes
            $table->string('calibration_object_key', 500)->nullable();
            $table->string('calibration_original_name')->nullable();
            $table->string('calibration_mime_type', 100)->nullable();
            $table->unsignedBigInteger('calibration_size')->nullable();

            $table->timestamps();

            $table->unique('asset_code_id');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('assets');
        Schema::enableForeignKeyConstraints();
    }
};
