<?php

namespace Database\Factories;

use App\Models\AssetItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetItem>
 */
class AssetItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numberBetween(100, 999),
            'name' => ucwords(fake()->unique()->words(2, true)),
        ];
    }
}
