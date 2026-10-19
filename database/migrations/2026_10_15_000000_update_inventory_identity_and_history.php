<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->string('event_type', 50);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->boolean('is_location_change')->default(false);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['asset_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_histories');
    }
};
