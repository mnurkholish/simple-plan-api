<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QualityCategorySeeder extends Seeder
{
    public function run(): void
    {
        $timestamp = now();
        $categories = [
            'Kepatuhan Input Operator',
            'Ketidakstabilan System',
            'Ketidaksesuaian Program',
            'Akun dan Hak Akses System',
            'Waktu Tanggap Kerusakan Hardware',
            'Waktu Tanggap Kerusakan Software',
        ];

        DB::table('quality_categories')->upsert(
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
