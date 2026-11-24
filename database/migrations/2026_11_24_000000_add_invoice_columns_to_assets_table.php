<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->string('invoice_object_key', 500)->nullable()->after('calibration_size');
            $table->string('invoice_original_name')->nullable()->after('invoice_object_key');
            $table->string('invoice_mime_type', 100)->nullable()->after('invoice_original_name');
            $table->unsignedBigInteger('invoice_size')->nullable()->after('invoice_mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropColumn([
                'invoice_object_key',
                'invoice_original_name',
                'invoice_mime_type',
                'invoice_size',
            ]);
        });
    }
};
