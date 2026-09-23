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
        Schema::table('tickets', function (Blueprint $table): void {
            $table->string('initial_evidence_object_key')->nullable();
            $table->string('initial_evidence_original_name')->nullable();
            $table->string('initial_evidence_mime_type', 100)->nullable();
            $table->unsignedBigInteger('initial_evidence_size')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn([
                'initial_evidence_object_key',
                'initial_evidence_original_name',
                'initial_evidence_mime_type',
                'initial_evidence_size',
            ]);
        });
    }
};
