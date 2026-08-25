<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $operationType = fake()->randomElement(['venta', 'compra']);
        $documentType = fake()->randomElement(['presupuesto', 'factura']);
        $prefix = $documentType === 'factura' ? 'FAC' : 'PRE';

        return [
            'number' => $prefix.'-'.fake()->unique()->numerify('#####'),
            'operation_type' => $operationType,
            'document_type' => $documentType,
            'status' => 'pendiente',
            'contact_id' => Contact::factory(),
            'issue_date' => fake()->dateTimeBetween('-2 months', 'now'),
            'subtotal' => 0,
            'tax_total' => 0,
            'total' => 0,
            'notes' => fake()->boolean(20) ? fake()->sentence() : null,
        ];
    }
}
