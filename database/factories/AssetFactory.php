<?php

namespace Database\Factories;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetClassification;
use App\Models\AssetCode;
use App\Models\AssetItem;
use App\Models\AssetLocation;
use App\Services\AssetCodeService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_year' => fake()->numberBetween(2018, (int) now()->year),
            'brand' => fake()->company(),
            'model_specification' => fake()->bothify('Model-####'),
            'status' => AssetStatus::InUse,
            'condition' => AssetCondition::Good,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Asset $asset): void {
            if ($asset->asset_code_id === null) {
                $asset->assetCode()->associate($this->createAssetCode((int) $asset->purchase_year));
                $asset->save();
            }
        });
    }

    private function createAssetCode(int $year): AssetCode
    {
        $classification = AssetClassification::firstOrCreate(['code' => 5], ['name' => 'Alat IT']);
        $location = AssetLocation::firstOrCreate(['code' => 10], ['name' => 'Ruang IT']);
        $item = AssetItem::firstOrCreate(['code' => 20], ['name' => 'Komputer']);

        $number = app(AssetCodeService::class)->generate(
            $classification->id,
            $location->id,
            $item->id,
            $year,
        );

        return AssetCode::create([
            'asset_classification_id' => $classification->id,
            'asset_location_id' => $location->id,
            'asset_item_id' => $item->id,
            ...$number,
        ]);
    }
}
