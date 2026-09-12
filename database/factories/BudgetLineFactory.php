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
            'section' => BudgetLine::SECTION_PURCHASE,
            'fecha' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'party_name' => fake()->company(),
            'producto' => fake()->randomElement(['Arroz', 'Harina', 'Aceite', 'Azúcar', 'Café']),
            'cantidad' => fake()->numberBetween(1, 50),
            'unit_price' => fake()->randomFloat(2, 1, 200),
            'payment_status' => fake()->randomElement(['Pagado', 'Pendiente', 'Abonado']),
            'payment_method' => fake()->randomElement(['Efectivo', 'Transferencia', 'Pago móvil']),
            'position' => fake()->numberBetween(0, 20),
        ];
    }

    public function section(string $section): static
    {
        return $this->state(fn () => ['section' => $section]);
    }

    /**
     * Un proveedor o cliente del Directorio: no cuelga de ningún período.
     */
    public function contact(string $tipo = BudgetLine::TYPE_PROVIDER): static
    {
        return $this->state(fn () => [
            'budget_period_id' => null,
            'section' => BudgetLine::SECTION_CONTACT,
            'tipo' => $tipo,
            'fecha' => null,
            'party_name' => fake()->company(),
            'telefono' => fake()->numerify('04##-#######'),
            'producto' => null,
            'cantidad' => null,
            'unit_price' => null,
            'payment_status' => null,
            'payment_method' => null,
        ]);
    }

    public function sale(): static
    {
        return $this->state(fn () => [
            'section' => BudgetLine::SECTION_SALE,
            'costo' => fake()->randomFloat(2, 1, 100),
        ]);
    }

    /**
     * Una fila de gastos: fecha, categoría, descripción y monto.
     */
    public function expense(): static
    {
        return $this->state(fn () => [
            'section' => BudgetLine::SECTION_EXPENSE,
            'categoria' => fake()->randomElement(['Transporte', 'Servicios', 'Sueldos', 'Alquiler']),
            'descripcion' => fake()->sentence(3),
            'monto' => fake()->randomFloat(2, 5, 400),
            'party_name' => null,
            'producto' => null,
            'cantidad' => null,
            'unit_price' => null,
        ]);
    }

    /**
     * Una fila de ganancias y pérdidas del mes.
     */
    public function result(): static
    {
        return $this->state(fn () => [
            'section' => BudgetLine::SECTION_RESULT,
            'ganancia' => fake()->randomFloat(2, 100, 3000),
            'gastos_personales' => fake()->randomFloat(2, 0, 500),
            'perdidas_mercancia' => fake()->randomFloat(2, 0, 300),
            'party_name' => null,
            'producto' => null,
            'cantidad' => null,
            'unit_price' => null,
        ]);
    }
}
