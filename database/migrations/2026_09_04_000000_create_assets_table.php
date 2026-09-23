<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->string('asset_number', 50)->unique();
            $table->string('name', 100);
            $table->string('brand', 100)->nullable();
            $table->foreignId('unit_id');
            $table->string('location', 150)->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index('unit_id');
            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
