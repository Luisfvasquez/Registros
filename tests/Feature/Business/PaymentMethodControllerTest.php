<?php

namespace Tests\Feature\Business;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_methods_can_be_searched_for_the_picker()
    {
        $user = User::factory()->create();
        PaymentMethod::factory()->create(['name' => 'Efectivo']);
        PaymentMethod::factory()->create(['name' => 'Transferencia']);

        $response = $this->actingAs($user)->getJson(route('payment-methods.index', ['q' => 'Efec']));

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_a_payment_method_can_be_created_inline()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('payment-methods.store'), [
            'name' => 'Pago móvil',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('payment_methods', ['name' => 'Pago móvil']);
    }

    public function test_a_payment_method_name_must_be_unique()
    {
        $user = User::factory()->create();
        PaymentMethod::factory()->create(['name' => 'Efectivo']);

        $response = $this->actingAs($user)->postJson(route('payment-methods.store'), [
            'name' => 'Efectivo',
        ]);

        $response->assertUnprocessable();
    }
}
