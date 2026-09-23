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
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->foreignId('handled_by_id')->constrained('users')->restrictOnDelete();
            $table->text('notes');
            $table->string('status', 30);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('result_photo_object_key')->nullable();
            $table->string('result_photo_original_name')->nullable();
            $table->string('result_photo_mime_type', 100)->nullable();
            $table->unsignedBigInteger('result_photo_size')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
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
