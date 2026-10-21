<?php

namespace Database\Factories;

use App\Models\AssetClassification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetClassification>
 */
class AssetClassificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucwords(fake()->unique()->words(2, true)),
        ];
    }
}
