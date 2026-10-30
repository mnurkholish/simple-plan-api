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
        Schema::create('asset_maintenance_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_maintenance_id')->constrained('asset_maintenances')->cascadeOnDelete();
            $table->date('scheduled_date');
            $table->text('work_description')->nullable();
            $table->text('maintenance_result')->nullable();
            $table->string('post_maintenance_condition')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('photo_path', 500)->nullable();
            $table->timestamps();

            $table->index(['asset_maintenance_id', 'scheduled_date']);
            $table->index('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_maintenance_logs');
    }
};
