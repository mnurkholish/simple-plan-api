<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('migration Inventory menghasilkan skema final dari database kosong', function (): void {
    $originalConnection = DB::getDefaultConnection();
    config()->set('database.connections.inventory_migration_test', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'foreign_key_constraints' => true,
    ]);
    DB::setDefaultConnection('inventory_migration_test');

    try {
        Schema::create('units', fn (Blueprint $table) => $table->id());
        Schema::create('users', fn (Blueprint $table) => $table->id());
        (require base_path('database/migrations/2026_09_04_000000_create_assets_table.php'))->up();
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->nullable()->constrained()->restrictOnDelete();
        });

        foreach ([
            '2026_10_08_170913_create_asset_classifications_table.php',
            '2026_10_08_170914_create_asset_locations_table.php',
            '2026_10_08_170915_create_asset_items_table.php',
            '2026_10_08_180000_create_asset_codes_table_v2.php',
            '2026_10_08_180001_create_assets_table_v2.php',
            '2026_10_11_105254_add_soft_deletes_to_assets_and_asset_codes_tables.php',
            '2026_10_15_000000_update_inventory_identity_and_history.php',
        ] as $file) {
            (require base_path('database/migrations/'.$file))->up();
        }

        expect(Schema::hasColumn('assets', 'asset_uid'))->toBeTrue()
            ->and(Schema::hasColumn('assets', 'asset_code_id'))->toBeTrue()
            ->and(Schema::hasColumn('assets', 'model_specification'))->toBeTrue()
            ->and(Schema::hasColumn('assets', 'location_detail'))->toBeTrue()
            ->and(Schema::hasColumn('assets', 'deleted_at'))->toBeTrue()
            ->and(Schema::hasColumn('asset_codes', 'deleted_at'))->toBeTrue()
            ->and(Schema::hasColumn('asset_codes', 'year'))->toBeFalse()
            ->and(Schema::hasColumn('asset_codes', 'is_assigned'))->toBeFalse()
            ->and(Schema::hasColumn('asset_items', 'brand'))->toBeFalse()
            ->and(Schema::hasTable('asset_histories'))->toBeTrue();

        (require base_path('database/migrations/2026_10_15_000000_update_inventory_identity_and_history.php'))->down();
        (require base_path('database/migrations/2026_10_11_105254_add_soft_deletes_to_assets_and_asset_codes_tables.php'))->down();
        (require base_path('database/migrations/2026_10_08_180001_create_assets_table_v2.php'))->down();

        expect(Schema::hasTable('tickets'))->toBeTrue()
            ->and(Schema::hasColumn('assets', 'asset_uid'))->toBeFalse()
            ->and(Schema::hasColumn('assets', 'deleted_at'))->toBeTrue();
    } finally {
        DB::setDefaultConnection($originalConnection);
        DB::purge('inventory_migration_test');
    }
});
