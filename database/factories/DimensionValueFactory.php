<?php

namespace Database\Factories;

use App\Models\Dimension;
use App\Models\DimensionValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DimensionValue>
 */
class DimensionValueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dimension_id' => Dimension::factory(),
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
            'sort' => 0,
        ];
    }
}
