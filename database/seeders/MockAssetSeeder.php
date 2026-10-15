<?php

namespace Database\Seeders;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MockAssetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('assets')->insert([
            [
                'asset_uid' => (string) Str::ulid(),
                'asset_number' => '3/1/1/1/2020',
                'name' => 'Meja IGD 1',
                'brand' => 'Meja',
                'unit_id' => 1,
                'location' => 'IGD',
                'status' => AssetStatus::InUse->value,
                'condition' => AssetCondition::Good->value,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'asset_uid' => (string) Str::ulid(),
                'asset_number' => '5/1/1/31/2024',
                'name' => 'AC IGD 2PK 1',
                'brand' => 'Samsung',
                'unit_id' => 1,
                'location' => 'Ruang IT',
                'status' => AssetStatus::InUse->value,
                'condition' => AssetCondition::Good->value,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'asset_uid' => (string) Str::ulid(),
                'asset_number' => '3/61/607/10/2023',
                'name' => 'Lampu (Rosalina 3) 1',
                'brand' => 'Philips',
                'unit_id' => 2,
                'location' => 'Rosalina 3',
                'status' => AssetStatus::InUse->value,
                'condition' => AssetCondition::Good->value,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
