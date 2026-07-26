<?php

namespace Database\Factories;

use App\Enums\CategoryAppliesTo;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => fake()->unique()->words(2, true),
            'applies_to' => CategoryAppliesTo::Both,
            'is_active' => true,
            'sort' => 0,
        ];
    }
}
