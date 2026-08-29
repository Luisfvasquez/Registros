<?php

namespace Database\Factories;

use App\Models\BudgetLine;
use App\Models\BudgetPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetLine>
 */
class BudgetLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $planned = fake()->randomFloat(2, 50, 2000);

        return [
            'budget_period_id' => BudgetPeriod::factory(),
            'section' => fake()->randomElement(BudgetLine::SECTIONS),
            'detail' => fake()->words(2, true),
            'category' => fake()->randomElement(['Hogar', 'Comida', 'Transporte', 'Salud', 'Ocio']),
            'payment_method' => fake()->randomElement(['Efectivo', 'Débito', 'Crédito', 'Transferencia']),
            'ideal_percent' => fake()->randomFloat(2, 0, 30),
            'planned' => $planned,
            'actual' => fake()->randomFloat(2, 0, $planned * 1.4),
            'is_unexpected' => false,
            'position' => fake()->numberBetween(0, 20),
        ];
    }

    public function section(string $section): static
    {
        return $this->state(fn () => ['section' => $section]);
    }
}
