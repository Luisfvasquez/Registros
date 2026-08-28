<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['cliente', 'proveedor', 'ambos']),
            'name' => fake()->company(),
            'document' => fake()->bothify('V-########'),
            'phone_country_code' => fake()->randomElement(['0414', '0424', '0412', '0416', '0426']),
            'phone' => fake()->numerify('#######'),
            'email' => fake()->boolean(70) ? fake()->companyEmail() : null,
            'address' => fake()->boolean(70) ? fake()->address() : null,
        ];
    }
}
