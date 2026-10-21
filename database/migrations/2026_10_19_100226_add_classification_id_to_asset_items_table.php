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
        Schema::table('asset_items', function (Blueprint $table): void {
            $table->foreignId('asset_classification_id')->after('id')->constrained('asset_classifications')->cascadeOnDelete();
            $table->dropUnique(['code']);
            $table->unique(['asset_classification_id', 'code']);
            $table->unique(['asset_classification_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('asset_items', function (Blueprint $table): void {
            $table->dropForeign(['asset_classification_id']);
            $table->dropUnique(['asset_classification_id', 'name']);
            $table->dropUnique(['asset_classification_id', 'code']);
            $table->dropColumn('asset_classification_id');
            $table->unique('code');
        });
    }
};
