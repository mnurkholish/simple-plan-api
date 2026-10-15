<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('migration memindahkan merek, membuat UID, dan menyelaraskan tahun kode lama', function (): void {
    $originalConnection = DB::getDefaultConnection();
    config()->set('database.connections.inventory_legacy_test', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'foreign_key_constraints' => true,
    ]);
    DB::setDefaultConnection('inventory_legacy_test');

    try {
        Schema::create('users', fn (Blueprint $table) => $table->id());
        Schema::create('asset_classifications', function (Blueprint $table): void {
            $table->id();
            $table->integer('code');
        });
        Schema::create('asset_locations', function (Blueprint $table): void {
            $table->id();
            $table->integer('code');
        });
        Schema::create('asset_items', function (Blueprint $table): void {
            $table->id();
            $table->integer('code');
            $table->string('brand')->nullable();
        });
        Schema::create('asset_codes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asset_classification_id');
            $table->unsignedBigInteger('asset_location_id');
            $table->unsignedBigInteger('asset_item_id');
            $table->integer('sequence_number');
            $table->integer('year');
            $table->string('full_code')->unique();
            $table->boolean('is_assigned');
            $table->softDeletes();
        });
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asset_code_id')->nullable();
            $table->string('brand', 100)->nullable();
            $table->integer('purchase_year')->nullable();
        });

        DB::table('asset_classifications')->insert(['id' => 1, 'code' => 5]);
        DB::table('asset_locations')->insert(['id' => 1, 'code' => 10]);
        DB::table('asset_items')->insert(['id' => 1, 'code' => 20, 'brand' => 'Dell']);
        DB::table('asset_codes')->insert([
            'id' => 1, 'asset_classification_id' => 1, 'asset_location_id' => 1,
            'asset_item_id' => 1, 'sequence_number' => 1, 'year' => 2020,
            'full_code' => '5/10/1/20/2020', 'is_assigned' => true,
        ]);
        DB::table('assets')->insert(['id' => 1, 'asset_code_id' => 1, 'purchase_year' => 2021]);
        DB::table('assets')->insert(['id' => 2, 'asset_code_id' => null, 'brand' => 'Legacy']);

        $migration = require base_path('database/migrations/2026_10_15_000000_update_inventory_identity_and_history.php');
        $migration->up();

        $asset = DB::table('assets')->where('id', 1)->first();
        expect($asset->brand)->toBe('Dell')
            ->and($asset->purchase_year)->toBe(2021)
            ->and($asset->asset_uid)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/')
            ->and(DB::table('assets')->where('id', 2)->value('asset_uid'))->not->toBeNull()
            ->and(DB::table('asset_codes')->where('id', 1)->value('full_code'))->toBe('5/10/1/20/2021')
            ->and(Schema::hasColumn('asset_items', 'brand'))->toBeFalse()
            ->and(Schema::hasColumn('asset_codes', 'year'))->toBeFalse()
            ->and(Schema::hasColumn('asset_codes', 'is_assigned'))->toBeFalse();

        $history = DB::table('asset_histories')->first();
        expect(json_decode($history->old_values, true)['full_code'])->toBe('5/10/1/20/2020')
            ->and(json_decode($history->new_values, true)['full_code'])->toBe('5/10/1/20/2021');

        $migration->down();
        expect(Schema::hasColumn('asset_codes', 'year'))->toBeTrue()
            ->and(DB::table('asset_codes')->where('id', 1)->value('year'))->toBe(2021)
            ->and(Schema::hasColumn('asset_items', 'brand'))->toBeTrue();
    } finally {
        DB::setDefaultConnection($originalConnection);
        DB::purge('inventory_legacy_test');
    }
});
