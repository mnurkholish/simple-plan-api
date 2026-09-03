<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            [
                'unit_name' => 'ICU',
                'description' => null,
            ],
        ];

        foreach ($units as $unit) {
            Unit::updateOrCreate(
                ['unit_name' => $unit['unit_name']],
                [
                    ...$unit,
                    'slug' => Str::slug($unit['unit_name']),
                ],
            );
        }
    }
}
