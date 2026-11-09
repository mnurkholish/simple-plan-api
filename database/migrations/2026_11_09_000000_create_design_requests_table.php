<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('request_number', 20)->unique();
            $table->string('title');
            $table->string('design_type');
            $table->text('description');
            $table->timestamp('needed_at');
            $table->foreignId('requester_id');
            $table->foreignId('unit_id');
            $table->string('status', 30)->default('baru');
            $table->string('priority', 20)->nullable();
            $table->foreignId('assigned_officer_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->text('rejection_note')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('reference_file_object_key', 500)->nullable();
            $table->string('reference_file_original_name')->nullable();
            $table->string('reference_file_mime_type', 100)->nullable();
            $table->unsignedBigInteger('reference_file_size')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['requester_id', 'created_at']);
            $table->index(['assigned_officer_id', 'status']);
            $table->index('needed_at');
            $table->index('unit_id');

            $table->foreign('requester_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();
            $table->foreign('assigned_officer_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_requests');
    }
};
