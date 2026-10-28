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
        Schema::create('asset_maintenances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_code_id')->constrained('asset_codes')->restrictOnDelete();
            $table->foreignId('assigned_officer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('creator_id')->constrained('users')->restrictOnDelete();
            $table->string('maintenance_type');
            $table->string('period', 30);
            $table->date('next_scheduled_date');
            $table->string('status', 30)->default('terjadwal');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'next_scheduled_date']);
            $table->index(['assigned_officer_id', 'status']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_maintenances');
    }
};
