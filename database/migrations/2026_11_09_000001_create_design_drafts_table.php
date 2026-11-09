<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('design_request_id');
            $table->string('file_object_key', 500);
            $table->string('file_original_name');
            $table->string('file_mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by_id');
            $table->timestamps();

            $table->index(['design_request_id', 'created_at']);
            $table->index('uploaded_by_id');
            $table->foreign('design_request_id')->references('id')->on('design_requests')->restrictOnDelete();
            $table->foreign('uploaded_by_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_drafts');
    }
};
