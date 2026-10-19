<?php

namespace Database\Factories;

use App\Models\AssetLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetLocation>
 */
class AssetLocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numberBetween(100, 999),
            'name' => 'Ruang '.ucwords(fake()->unique()->words(2, true)),
        ];
    }
}
