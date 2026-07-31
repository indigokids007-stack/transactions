<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'department_id' => null,
            'type' => TransactionType::Expense,
            'amount_minor' => fake()->numberBetween(1000, 1000000),
            'currency' => 'UZS',
            'occurred_on' => fake()->date(),
            'category_id' => Category::factory(),
            'note' => null,
            'created_by' => User::factory(),
            'idempotency_key' => null,
        ];
    }
}
