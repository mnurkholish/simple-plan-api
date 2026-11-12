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
        Schema::table('asset_maintenances', function (Blueprint $table): void {
            $table->timestamp('last_executed_at')->nullable()->after('next_scheduled_date');
            $table->index(['status', 'last_executed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_maintenances', function (Blueprint $table): void {
            $table->dropIndex(['status', 'last_executed_at']);
            $table->dropColumn('last_executed_at');
        });
    }
};
