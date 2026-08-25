<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'amount' => fake()->randomFloat(2, 10, 200),
            'reference' => fake()->boolean(50) ? fake()->bothify('REF-####') : null,
            'paid_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
