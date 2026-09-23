<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SarprasCategorySeeder extends Seeder
{
    public function run(): void
    {
        $timestamp = now();
        $categories = [
            'Sarpras',
            'Elektronik',
            'Alkes',
        ];

        DB::table('sarpras_categories')->upsert(
            array_map(fn (string $name): array => [
                'name' => $name,
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ], $categories),
            ['name'],
            ['is_active', 'updated_at'],
        );
    }
}
