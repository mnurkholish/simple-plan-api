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
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropUnique('assets_asset_number_unique');
            $table->dropColumn(['asset_number', 'name', 'location']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->string('asset_number', 50)->nullable()->unique();
            $table->string('name', 100)->nullable();
            $table->string('location', 150)->nullable();
        });
    }
};
