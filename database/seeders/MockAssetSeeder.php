<?php

namespace Database\Seeders;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetClassification;
use App\Models\AssetCode;
use App\Models\AssetItem;
use App\Models\AssetLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MockAssetSeeder extends Seeder
{
    public function run(): void
    {
        $assets = [
            [3, 'Sarana dan Prasarana', 1, 'IGD', 1, 'Meja', 1, 2020, 'Meja'],
            [5, 'Alat IT', 2, 'Ruang IT', 31, 'AC 2PK', 1, 2024, 'Samsung'],
            [3, 'Sarana dan Prasarana', 61, 'Rosalina 3', 10, 'Lampu', 607, 2023, 'Philips'],
        ];

        foreach ($assets as [$classCode, $className, $locationCode, $locationName, $itemCode, $itemName, $sequence, $year, $brand]) {
            $classification = AssetClassification::firstOrCreate(['code' => $classCode], ['name' => $className]);
            $location = AssetLocation::firstOrCreate(['code' => $locationCode], ['name' => $locationName]);
            $item = AssetItem::firstOrCreate(['code' => $itemCode], ['name' => $itemName]);
            $fullCode = "{$classCode}/{$locationCode}/{$sequence}/{$itemCode}/{$year}";

            $assetCode = AssetCode::firstOrCreate(['full_code' => $fullCode], [
                'asset_classification_id' => $classification->id,
                'asset_location_id' => $location->id,
                'asset_item_id' => $item->id,
                'sequence_number' => $sequence,
            ]);

            $asset = Asset::firstOrNew(['asset_code_id' => $assetCode->id]);
            if (! $asset->exists) {
                $asset->forceFill([
                    'asset_uid' => (string) Str::ulid(),
                    'brand' => $brand,
                    'purchase_year' => $year,
                    'status' => AssetStatus::InUse->value,
                    'condition' => AssetCondition::Good->value,
                ])->save();
            }
        }
    }
}
