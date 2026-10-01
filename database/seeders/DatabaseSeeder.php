<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CorePermissionSeeder::class,
            DomainPermissionSeeder::class,
            RoleSeeder::class,
            UnitSeeder::class,
            QualityCategorySeeder::class,
            ItTagSeeder::class,
            SarprasCategorySeeder::class,
            UserSeeder::class,
            UserExcelSeeder::class,
            MockAssetSeeder::class,
        ]);
    }
}
