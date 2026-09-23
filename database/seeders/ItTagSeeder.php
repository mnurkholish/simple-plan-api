<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItTagSeeder extends Seeder
{
    public function run(): void
    {
        $timestamp = now();

        DB::table('it_tags')->upsert([
            [
                'name' => 'Lain-lain',
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ], ['name'], ['is_active', 'updated_at']);
    }
}
