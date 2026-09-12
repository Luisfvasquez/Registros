<?php

namespace Tests\Feature;

use App\Models\BudgetLine;
use App\Models\BudgetPeriod;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BudgetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('presupuesto.index'))->assertRedirect(route('login'));
    }

    public function test_the_entry_point_offers_to_create_the_first_period(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('presupuesto.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('presupuesto/Empty')
            ->has('periods', 0)
        );
    }

    public function test_the_entry_point_opens_the_active_period_dashboard(): void
    {
        $user = User::factory()->create();
        BudgetPeriod::factory()->create(['year' => 2026, 'month' => 7]);
        $active = BudgetPeriod::factory()->create(['year' => 2026, 'month' => 8]);

        Setting::put(Setting::ACTIVE_PERIOD, (string) $active->id);

        $this->actingAs($user)
            ->get(route('presupuesto.index'))
            ->assertRedirect(route('presupuesto.dashboard', $active));
    }

    public function test_each_sheet_renders_its_own_page_for_the_period(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();

        $sheets = [
            'presupuesto.dashboard' => 'presupuesto/Dashboard',
            'presupuesto.directory' => 'presupuesto/Directorio',
            'presupuesto.purchases' => 'presupuesto/Compras',
            'presupuesto.sales' => 'presupuesto/Ventas',
            'presupuesto.expenses' => 'presupuesto/Gastos',
            'presupuesto.results' => 'presupuesto/Ganancias',
            'presupuesto.invoices' => 'presupuesto/Facturas',
            'presupuesto.provider-account' => 'presupuesto/CuentaProveedor',
            'presupuesto.client-account' => 'presupuesto/CuentaCliente',
            'presupuesto.daily-sales' => 'presupuesto/VentasDelDia',
            'presupuesto.purchase-payments' => 'presupuesto/AbonosCompras',
            'presupuesto.sale-payments' => 'presupuesto/AbonosVentas',
        ];

        foreach ($sheets as $route => $component) {
            $this->actingAs($user)
                ->get(route($route, $period))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($component)
                    ->where('period.id', $period->id)
                    ->has('periods', 1)
                );
        }
    }

    public function test_the_purchases_sheet_only_lists_purchase_rows(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();

        BudgetLine::factory()->count(3)->for($period, 'period')->create();
        BudgetLine::factory()->for($period, 'period')->sale()->create();

        $this->actingAs($user)
            ->get(route('presupuesto.purchases', $period))
            ->assertInertia(fn (Assert $page) => $page
                ->component('presupuesto/Compras')
                ->has('lines', 3)
                ->has('summary')
            );
    }

    public function test_a_period_can_be_created_and_becomes_the_active_one(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('presupuesto.periods.store'), [
            'year' => 2026,
            'month' => 9,
            'currency' => 'usd',
            'available_money' => 1500,
        ]);

        $period = BudgetPeriod::firstOrFail();

        $response->assertRedirect(route('presupuesto.dashboard', $period));
        $this->assertDatabaseHas('budget_periods', [
            'year' => 2026,
            'month' => 9,
            'currency' => 'USD',
            'available_money' => 1500,
        ]);
        $this->assertSame((string) $period->id, Setting::get(Setting::ACTIVE_PERIOD));
    }

    public function test_a_duplicate_period_is_rejected(): void
    {
        $user = User::factory()->create();
        BudgetPeriod::factory()->create(['year' => 2026, 'month' => 9, 'currency' => 'USD']);

        $this->actingAs($user)
            ->post(route('presupuesto.periods.store'), [
                'year' => 2026,
                'month' => 9,
                'currency' => 'USD',
            ])
            ->assertSessionHasErrors('month');

        $this->assertSame(1, BudgetPeriod::count());
    }

    public function test_a_period_can_be_marked_active_and_deleted(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();

        $this->actingAs($user)
            ->post(route('presupuesto.periods.activate', $period))
            ->assertRedirect();

        $this->assertSame((string) $period->id, Setting::get(Setting::ACTIVE_PERIOD));

        $this->actingAs($user)
            ->delete(route('presupuesto.periods.destroy', $period))
            ->assertRedirect(route('presupuesto.index'));

        $this->assertDatabaseMissing('budget_periods', ['id' => $period->id]);
        $this->assertNull(Setting::get(Setting::ACTIVE_PERIOD));
    }

    public function test_a_row_is_created_in_the_period_and_appended_to_its_sheet(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();

        $response = $this->actingAs($user)->postJson(route('presupuesto.lines.store', $period), [
            'section' => BudgetLine::SECTION_PURCHASE,
            'fecha' => '2026-09-03',
            'producto' => 'Tomate',
            'cantidad' => 50,
            'unit_price' => 0.8,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('line.section', BudgetLine::SECTION_PURCHASE);
        $response->assertJsonPath('line.precio_total', 40);
        $response->assertJsonPath('line.position', 1);
        $response->assertJsonPath('summary.total_compras', 40);

        $this->assertSame($period->id, BudgetLine::firstOrFail()->budget_period_id);
    }

    public function test_a_directory_row_is_shared_across_periods(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        $other = BudgetPeriod::factory()->create(['month' => $period->month === 12 ? 1 : $period->month + 1]);

        $this->actingAs($user)
            ->postJson(route('presupuesto.lines.store', $period), [
                'section' => BudgetLine::SECTION_CONTACT,
                'tipo' => BudgetLine::TYPE_PROVIDER,
                'party_name' => 'Distribuidora Central',
                'telefono' => '0414-7654321',
            ])
            ->assertCreated()
            ->assertJsonPath('line.budget_period_id', null)
            ->assertJsonPath('summary', null);

        $this->actingAs($user)
            ->get(route('presupuesto.directory', $other))
            ->assertInertia(fn (Assert $page) => $page
                ->component('presupuesto/Directorio')
                ->has('proveedores', 1)
                ->has('clientes', 0)
            );
    }

    public function test_choosing_a_contact_copies_its_name_and_phone_onto_the_row(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        $contact = BudgetLine::factory()->contact()->create([
            'party_name' => 'Proveedor Ejemplo',
            'telefono' => '0412-1234567',
        ]);
        $line = BudgetLine::factory()->for($period, 'period')->create([
            'party_name' => null,
            'telefono' => null,
        ]);

        $this->actingAs($user)
            ->patchJson(route('presupuesto.lines.update', $line), [
                'contact_line_id' => $contact->id,
            ])
            ->assertOk()
            ->assertJsonPath('line.party_name', 'Proveedor Ejemplo')
            ->assertJsonPath('line.telefono', '0412-1234567');
    }

    public function test_renaming_a_contact_updates_the_rows_that_point_at_it(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        $contact = BudgetLine::factory()->contact()->create(['party_name' => 'Proveedor Ejemplo']);
        $line = BudgetLine::factory()->for($period, 'period')->create([
            'contact_line_id' => $contact->id,
            'party_name' => 'Proveedor Ejemplo',
        ]);

        $this->actingAs($user)
            ->patchJson(route('presupuesto.lines.update', $contact), [
                'party_name' => 'Proveedor Nuevo',
                'telefono' => '0424-0000000',
            ])
            ->assertOk();

        $this->assertSame('Proveedor Nuevo', $line->refresh()->party_name);
        $this->assertSame('0424-0000000', $line->telefono);
    }

    public function test_the_section_of_an_existing_row_cannot_be_changed(): void
    {
        $user = User::factory()->create();
        $line = BudgetLine::factory()->create();

        $this->actingAs($user)
            ->patchJson(route('presupuesto.lines.update', $line), [
                'section' => BudgetLine::SECTION_SALE,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('section');
    }

    public function test_a_row_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        $line = BudgetLine::factory()->for($period, 'period')->create();

        $this->actingAs($user)
            ->deleteJson(route('presupuesto.lines.destroy', $line))
            ->assertOk()
            ->assertJsonPath('summary.total_compras', 0);

        $this->assertDatabaseMissing('budget_lines', ['id' => $line->id]);
    }
}
