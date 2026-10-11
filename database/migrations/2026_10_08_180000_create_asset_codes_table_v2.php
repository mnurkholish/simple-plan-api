<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_classification_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_item_id')->constrained()->restrictOnDelete();
            $table->integer('sequence_number');
            $table->integer('year');
            $table->string('full_code')->unique();
            $table->boolean('is_assigned')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_codes');
    }
};
