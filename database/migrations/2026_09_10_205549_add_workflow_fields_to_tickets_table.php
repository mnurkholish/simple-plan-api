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
        Schema::table('tickets', function (Blueprint $table): void {
            $table->text('rejection_reason')->nullable();
            $table->string('priority', 20)->nullable();
            $table->foreignId('assigned_officer_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_officer_id');
            $table->dropColumn(['rejection_reason', 'priority']);
        });
    }
};
