<?php

namespace Tests\Feature\Business;

use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExchangeRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_update_command_stores_the_usd_rate_and_deactivates_the_previous_one()
    {
        $stale = ExchangeRate::create([
            'currency_from' => 'USD',
            'currency_to' => 'BS',
            'rate' => 100,
            'date' => now()->subDay()->toDateString(),
            'is_active' => true,
        ]);

        Http::fake([
            've.dolarapi.com/*' => Http::response([
                ['moneda' => 'USD', 'promedio' => 190.5, 'fechaActualizacion' => now()->toIso8601String()],
                ['moneda' => 'EUR', 'promedio' => 200.1, 'fechaActualizacion' => now()->toIso8601String()],
            ]),
        ]);

        $this->artisan('exchange:update-usd')->assertSuccessful();

        $this->assertFalse($stale->fresh()->is_active);
        $this->assertDatabaseHas('exchange_rates', [
            'currency_from' => 'USD',
            'currency_to' => 'BS',
            'is_active' => true,
        ]);
        $this->assertSame(1, ExchangeRate::where('is_active', true)->count());
    }

    public function test_the_update_command_fails_gracefully_when_usd_is_missing()
    {
        Http::fake([
            've.dolarapi.com/*' => Http::response([
                ['moneda' => 'EUR', 'promedio' => 200.1, 'fechaActualizacion' => now()->toIso8601String()],
            ]),
        ]);

        $this->artisan('exchange:update-usd')->assertFailed();
    }

    public function test_the_exchange_rate_is_shared_with_every_authenticated_page()
    {
        $user = User::factory()->create();
        ExchangeRate::create([
            'currency_from' => 'USD',
            'currency_to' => 'BS',
            'rate' => 172.5,
            'date' => now()->toDateString(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page->where('exchangeRate', '172.5000'));
    }

    public function test_forcing_a_refresh_calls_the_update_command()
    {
        $user = User::factory()->create();

        Http::fake([
            've.dolarapi.com/*' => Http::response([
                ['moneda' => 'USD', 'promedio' => 195, 'fechaActualizacion' => now()->toIso8601String()],
            ]),
        ]);

        $response = $this->actingAs($user)->post(route('exchange-rate.refresh'));

        $response->assertRedirect();
        $this->assertDatabaseHas('exchange_rates', ['rate' => 195]);
    }
}
