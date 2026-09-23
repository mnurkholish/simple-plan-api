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
        Schema::create('ticket_handlings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id');
            $table->foreignId('handled_by_id');
            $table->text('notes');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('result_photo_object_key', 500)->nullable();
            $table->string('result_photo_original_name')->nullable();
            $table->string('result_photo_mime_type', 100)->nullable();
            $table->unsignedBigInteger('result_photo_size')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
            $table->index('handled_by_id');
            $table->foreign('ticket_id')->references('id')->on('tickets')->restrictOnDelete();
            $table->foreign('handled_by_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_handlings');
    }
};
