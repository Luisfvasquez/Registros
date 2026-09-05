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
        return [
            'budget_period_id' => BudgetPeriod::factory(),
            'section' => fake()->randomElement(BudgetLine::SECTIONS),
            'fecha' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'party_name' => fake()->company(),
            'producto' => fake()->randomElement(['Arroz', 'Harina', 'Aceite', 'Azúcar', 'Café']),
            'cantidad' => fake()->numberBetween(1, 50),
            'unit_price' => fake()->randomFloat(2, 1, 200),
            'payment_status' => fake()->randomElement(['Pagado', 'Pendiente', 'Abonado']),
            'payment_method' => fake()->randomElement(['Efectivo', 'Transferencia', 'Pago móvil']),
            'ganancia' => null,
            'gastos_personales' => null,
            'perdidas_mercancia' => null,
            'inversiones' => null,
            'position' => fake()->numberBetween(0, 20),
        ];
    }

    public function section(string $section): static
    {
        return $this->state(fn () => ['section' => $section]);
    }

    /**
     * A monthly profit / expense / loss row.
     */
    public function result(): static
    {
        return $this->state(fn () => [
            'section' => BudgetLine::SECTION_RESULT,
            'ganancia' => fake()->randomFloat(2, 100, 3000),
            'gastos_personales' => fake()->randomFloat(2, 0, 500),
            'perdidas_mercancia' => fake()->randomFloat(2, 0, 300),
            'inversiones' => fake()->randomFloat(2, 0, 800),
        ]);
    }
}
