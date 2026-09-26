<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->timestamp('sla_started_at')->nullable()->after('assigned_at');
            $table->index('sla_started_at');
        });

        DB::table('tickets')
            ->whereNull('sla_started_at')
            ->whereNotNull('sla_deadline')
            ->update(['sla_started_at' => DB::raw('assigned_at')]);
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropIndex(['sla_started_at']);
            $table->dropColumn('sla_started_at');
        });
    }
};
