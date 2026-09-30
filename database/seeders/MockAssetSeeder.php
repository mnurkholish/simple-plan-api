<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MockAssetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('assets')->insert([
            [
                'asset_number' => '3/1/1/1/2020',
                'name' => 'Meja IGD 1',
                'brand' => 'Meja',
                'unit_id' => 1,
                'location' => 'IGD',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'asset_number' => '5/1/1/31/2024',
                'name' => 'AC IGD 2PK 1',
                'brand' => 'Samsung',
                'unit_id' => 1,
                'location' => 'Ruang IT',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'asset_number' => '3/61/607/10/2023',
                'name' => 'Lampu (Rosalina 3) 1',
                'brand' => 'Philips',
                'unit_id' => 2,
                'location' => 'Rosalina 3',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
