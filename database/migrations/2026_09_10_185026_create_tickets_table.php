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
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('ticket_number', 30)->unique();
            $table->string('service', 20);
            $table->foreignId('reporter_id');
            $table->foreignId('unit_id');
            $table->foreignId('asset_id')->nullable();
            $table->text('description');
            $table->string('status', 30)->default('baru');
            $table->string('priority', 20)->nullable();
            $table->foreignId('classified_by_id')->nullable();
            $table->timestamp('classified_at')->nullable();
            $table->foreignId('assigned_officer_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('sla_deadline')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('initial_evidence_object_key', 500)->nullable();
            $table->string('initial_evidence_original_name')->nullable();
            $table->string('initial_evidence_mime_type', 100)->nullable();
            $table->unsignedBigInteger('initial_evidence_size')->nullable();
            $table->timestamps();

            $table->index(['service', 'status']);
            $table->index(['assigned_officer_id', 'status']);
            $table->index(['reporter_id', 'created_at']);
            $table->index('unit_id');
            $table->index('asset_id');
            $table->index('classified_by_id');
            $table->index('sla_deadline');

            $table->foreign('reporter_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();
            $table->foreign('asset_id')->references('id')->on('assets')->restrictOnDelete();
            $table->foreign('classified_by_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('assigned_officer_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
