<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('design_request_id');
            $table->foreignId('design_draft_id');
            $table->text('revision_note');
            $table->foreignId('requested_by_id');
            $table->timestamps();

            $table->index(['design_request_id', 'created_at']);
            $table->index('design_draft_id');
            $table->index('requested_by_id');
            $table->foreign('design_request_id')->references('id')->on('design_requests')->restrictOnDelete();
            $table->foreign('design_draft_id')->references('id')->on('design_drafts')->restrictOnDelete();
            $table->foreign('requested_by_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_revisions');
    }
};
