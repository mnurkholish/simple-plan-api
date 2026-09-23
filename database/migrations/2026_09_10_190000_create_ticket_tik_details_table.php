<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_tik_details', function (Blueprint $table): void {
            $table->foreignId('ticket_id')->primary();
            $table->foreignId('quality_category_id');
            $table->foreignId('it_tag_id');
            $table->string('custom_it_tag_text')->nullable();
            $table->timestamps();

            $table->index('quality_category_id');
            $table->index('it_tag_id');
            $table->foreign('ticket_id')->references('id')->on('tickets')->restrictOnDelete();
            $table->foreign('quality_category_id')->references('id')->on('quality_categories')->restrictOnDelete();
            $table->foreign('it_tag_id')->references('id')->on('it_tags')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_tik_details');
    }
};
