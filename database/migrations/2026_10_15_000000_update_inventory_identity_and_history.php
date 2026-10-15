<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('asset_items')->whereNotNull('brand')->select(['id', 'brand'])->cursor() as $item) {
            if (mb_strlen($item->brand) > 100) {
                throw new RuntimeException('Asset item brand exceeds the 100 character asset brand limit. Resolve it before migrating.');
            }
        }

        Schema::table('assets', function (Blueprint $table): void {
            $table->char('asset_uid', 26)->nullable()->unique();
            $table->string('model_specification', 100)->nullable();
            $table->string('location_detail', 100)->nullable();
        });

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

        DB::table('assets')->orderBy('id')->chunkById(500, function ($assets): void {
            foreach ($assets as $asset) {
                $changes = ['asset_uid' => (string) Str::ulid()];
                if ($asset->asset_code_id !== null) {
                    $code = DB::table('asset_codes')->where('id', $asset->asset_code_id)->first();
                    if ($code !== null) {
                        if ($asset->brand === null) {
                            $changes['brand'] = DB::table('asset_items')->where('id', $code->asset_item_id)->value('brand');
                        }
                        if ($asset->purchase_year === null) {
                            $changes['purchase_year'] = $code->year;
                        }
                    }
                }
                DB::table('assets')->where('id', $asset->id)->update($changes);
            }
        });

        // Older records can have a code year that differs from the asset's purchase year.
        DB::table('assets')->whereNotNull('asset_code_id')->orderBy('id')->chunkById(500, function ($assets): void {
            foreach ($assets as $asset) {
                $code = DB::table('asset_codes')->where('id', $asset->asset_code_id)->first();
                if ($code === null || $asset->purchase_year === null || (int) $code->year === (int) $asset->purchase_year) {
                    continue;
                }

                $classification = DB::table('asset_classifications')->where('id', $code->asset_classification_id)->value('code');
                $location = DB::table('asset_locations')->where('id', $code->asset_location_id)->value('code');
                $item = DB::table('asset_items')->where('id', $code->asset_item_id)->value('code');
                $sequence = (int) $code->sequence_number;
                do {
                    $fullCode = "{$classification}/{$location}/{$sequence}/{$item}/{$asset->purchase_year}";
                    $used = DB::table('asset_codes')->where('full_code', $fullCode)->exists()
                        || DB::table('asset_histories')->where('old_values->full_code', $fullCode)->exists();
                    if ($used) {
                        $sequence++;
                    }
                } while ($used);

                DB::table('asset_codes')->where('id', $code->id)->update([
                    'sequence_number' => $sequence,
                    'full_code' => $fullCode,
                ]);
                DB::table('asset_histories')->insert([
                    'asset_id' => $asset->id,
                    'event_type' => 'purchase_year_migrated',
                    'old_values' => json_encode(['full_code' => $code->full_code]),
                    'new_values' => json_encode(['full_code' => $fullCode]),
                    'is_location_change' => false,
                    'changed_by' => null,
                    'created_at' => now(),
                ]);
            }
        });

        Schema::table('assets', fn (Blueprint $table) => $table->char('asset_uid', 26)->nullable(false)->change());
        Schema::table('asset_items', fn (Blueprint $table) => $table->dropColumn('brand'));
        Schema::table('asset_codes', function (Blueprint $table): void {
            $table->dropColumn(['year', 'is_assigned']);
        });
    }

    public function down(): void
    {
        Schema::table('asset_codes', function (Blueprint $table): void {
            $table->integer('year')->nullable();
            $table->boolean('is_assigned')->default(true);
        });
        DB::table('asset_codes')->orderBy('id')->chunkById(500, function ($codes): void {
            foreach ($codes as $code) {
                $year = DB::table('assets')->where('asset_code_id', $code->id)->value('purchase_year');
                DB::table('asset_codes')->where('id', $code->id)->update([
                    'year' => $year ?? (int) substr(strrchr($code->full_code, '/'), 1),
                ]);
            }
        });
        Schema::table('asset_items', fn (Blueprint $table) => $table->string('brand')->nullable());
        Schema::dropIfExists('asset_histories');
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropUnique(['asset_uid']);
            $table->dropColumn(['asset_uid', 'model_specification', 'location_detail']);
        });
    }
};
