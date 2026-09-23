<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_escalations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id');
            $table->foreignId('escalated_by_id');
            $table->string('target', 30);
            $table->text('notes')->nullable();
            $table->timestamp('escalated_at');
            $table->timestamps();

            $table->index(['ticket_id', 'escalated_at']);
            $table->index('target');
            $table->index('escalated_by_id');
            $table->foreign('ticket_id')->references('id')->on('tickets')->restrictOnDelete();
            $table->foreign('escalated_by_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_escalations');
    }
};
