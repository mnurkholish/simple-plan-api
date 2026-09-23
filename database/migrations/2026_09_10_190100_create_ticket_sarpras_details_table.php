<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_sarpras_details', function (Blueprint $table): void {
            $table->foreignId('ticket_id')->primary();
            $table->foreignId('sarpras_category_id');
            $table->timestamps();

            $table->index('sarpras_category_id');
            $table->foreign('ticket_id')->references('id')->on('tickets')->restrictOnDelete();
            $table->foreign('sarpras_category_id')->references('id')->on('sarpras_categories')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_sarpras_details');
    }
};
