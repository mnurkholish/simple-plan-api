<?php

namespace Database\Seeders;

use App\Models\Asset;
use Illuminate\Database\Seeder;

class InventoryAssetSeeder extends Seeder
{
    private const INITIAL_ASSET_COUNT = 100;

    public function run(): void
    {
        $missingAssets = max(0, self::INITIAL_ASSET_COUNT - Asset::query()->whereHas('assetCode')->count());

        if ($missingAssets > 0) {
            Asset::factory()->count($missingAssets)->create();
        }
    }
}
