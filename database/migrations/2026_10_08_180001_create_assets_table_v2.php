<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->foreignId('asset_code_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('asset_uid', 26)->unique();
            $table->string('serial_number')->nullable();
            $table->integer('purchase_year')->nullable();
            $table->string('model_specification', 100)->nullable();
            $table->string('location_detail', 100)->nullable();
            $table->string('condition')->nullable();
            $table->date('warranty_until')->nullable();
            $table->text('notes')->nullable();
            $table->string('photo_object_key', 500)->nullable();
            $table->string('photo_original_name')->nullable();
            $table->string('photo_mime_type', 100)->nullable();
            $table->unsignedBigInteger('photo_size')->nullable();
            $table->string('calibration_object_key', 500)->nullable();
            $table->string('calibration_original_name')->nullable();
            $table->string('calibration_mime_type', 100)->nullable();
            $table->unsignedBigInteger('calibration_size')->nullable();
            $table->unique('asset_code_id');
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->string('asset_number', 50)->nullable()->change();
            $table->string('name', 100)->nullable()->change();
            $table->foreignId('unit_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->string('asset_number', 50)->nullable(false)->change();
            $table->string('name', 100)->nullable(false)->change();
            $table->foreignId('unit_id')->nullable(false)->change();
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->dropUnique(['asset_code_id']);
            $table->dropUnique(['asset_uid']);
            $table->dropForeign(['asset_code_id']);
            $table->dropColumn([
                'asset_code_id', 'asset_uid', 'serial_number', 'purchase_year',
                'model_specification', 'location_detail', 'condition',
                'warranty_until', 'notes', 'photo_object_key', 'photo_original_name',
                'photo_mime_type', 'photo_size', 'calibration_object_key',
                'calibration_original_name', 'calibration_mime_type', 'calibration_size',
            ]);
        });
    }
};
