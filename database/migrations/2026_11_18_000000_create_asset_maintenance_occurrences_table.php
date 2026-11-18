<?php

use App\Enums\MaintenanceStatus;
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
        Schema::create('asset_maintenance_occurrences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_maintenance_id')
                ->constrained('asset_maintenances')
                ->cascadeOnDelete();
            $table->foreignId('asset_code_id')
                ->constrained('asset_codes')
                ->restrictOnDelete();
            $table->foreignId('asset_maintenance_log_id')
                ->nullable()
                ->constrained('asset_maintenance_logs')
                ->nullOnDelete();
            $table->string('period', 30);
            $table->date('scheduled_date');
            $table->string('status', 30)->default(MaintenanceStatus::Terjadwal->value);
            $table->dateTime('realized_at')->nullable();
            $table->boolean('is_late')->default(false);
            $table->timestamps();

            $table->unique(['asset_maintenance_id', 'scheduled_date'], 'amo_maintenance_scheduled_unique');
            $table->unique('asset_maintenance_log_id', 'amo_log_unique');
            $table->index(['status', 'scheduled_date'], 'amo_status_scheduled_idx');
            $table->index(['asset_code_id', 'scheduled_date'], 'amo_asset_code_scheduled_idx');
            $table->index('scheduled_date', 'amo_scheduled_date_idx');
            $table->index('realized_at', 'amo_realized_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_maintenance_occurrences');
    }
};
