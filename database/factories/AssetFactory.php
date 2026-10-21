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
use Illuminate\Support\Str;

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
        $purchaseYear = fake()->numberBetween(2018, (int) now()->year);

        return [
            'asset_uid' => (string) Str::ulid(),
            'serial_number' => fake()->unique()->bothify('SN-####-????'),
            'purchase_year' => $purchaseYear,
            'brand' => fake()->randomElement([
                'Acer', 'Asus', 'Canon', 'Daikin', 'Dell', 'Epson', 'GE', 'HP',
                'Lenovo', 'LG', 'Panasonic', 'Philips', 'Samsung', 'Sharp', 'Siemens',
            ]),
            'model_specification' => fake()->bothify('Model-??-####'),
            'detail_item' => $this->faker->optional()->word(),
            'location_detail' => fake()->randomElement([
                'Dekat pintu masuk', 'Lantai 1', 'Lantai 2', 'Meja petugas',
                'Ruang penyimpanan', 'Sisi kanan ruangan', 'Sisi kiri ruangan',
            ]),
            'status' => fake()->randomElement(AssetStatus::cases()),
            'condition' => fake()->randomElement(AssetCondition::cases()),
            'warranty_until' => fake()->dateTimeBetween(
                "{$purchaseYear}-01-01",
                ($purchaseYear + 5).'-12-31',
            ),
            'notes' => fake()->sentence(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Asset $asset): void {
            if ($asset->asset_code_id === null) {
                $this->attachAssetCode($asset);
            }
        });
    }

    public function forInventory(
        AssetClassification $classification,
        AssetLocation $location,
        AssetItem $item,
    ): static {
        return $this->withoutAfterCreating()
            ->afterCreating(function (Asset $asset) use ($classification, $location, $item): void {
                if ($asset->asset_code_id === null) {
                    $this->attachAssetCode($asset, $classification, $location, $item);
                }
            });
    }

    private function attachAssetCode(
        Asset $asset,
        ?AssetClassification $classification = null,
        ?AssetLocation $location = null,
        ?AssetItem $item = null,
    ): void {
        $asset->assetCode()->associate($this->createAssetCode(
            (int) $asset->purchase_year,
            $classification,
            $location,
            $item,
        ));
        $asset->save();
    }

    private function createAssetCode(
        int $year,
        ?AssetClassification $classification = null,
        ?AssetLocation $location = null,
        ?AssetItem $item = null,
    ): AssetCode {
        $classification ??= AssetClassification::query()->inRandomOrder()->first()
            ?? AssetClassification::factory()->create();
        $location ??= AssetLocation::query()->inRandomOrder()->first()
            ?? AssetLocation::factory()->create();
        $item ??= AssetItem::query()->where('asset_classification_id', $classification->id)->inRandomOrder()->first()
            ?? AssetItem::factory()->create(['asset_classification_id' => $classification->id]);

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
