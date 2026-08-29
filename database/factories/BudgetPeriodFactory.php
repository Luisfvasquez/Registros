<?php

namespace Database\Factories;

use App\Models\BudgetPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetPeriod>
 */
class BudgetPeriodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'year' => fake()->numberBetween(2024, 2027),
            'month' => fake()->numberBetween(1, 12),
            'currency' => fake()->randomElement(['USD', 'VES', 'EUR']),
            'status' => 'abierto',
            'available_money' => fake()->randomFloat(2, 0, 5000),
            'notes' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => 'cerrado']);
    }
}
