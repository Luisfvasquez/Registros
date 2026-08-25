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
            'document' => fake()->numerify('########-#'),
            'phone_country_code' => '+54',
            'phone' => fake()->numerify('9##########'),
            'email' => fake()->boolean(70) ? fake()->companyEmail() : null,
            'address' => fake()->boolean(70) ? fake()->address() : null,
        ];
    }
}
