<?php

namespace Tests\Feature;

use App\Models\BudgetLine;
use App\Models\BudgetPeriod;
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

    public function test_the_workspace_renders_the_selected_period_with_its_lines(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create(['year' => 2026, 'month' => 8, 'currency' => 'USD']);
        BudgetLine::factory()->count(3)->for($period, 'period')->create(['section' => BudgetLine::SECTION_SALE]);

        $response = $this->actingAs($user)->get(route('presupuesto.index', ['period' => $period->id]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('presupuesto/Index')
            ->where('period.id', $period->id)
            ->has('lines', 3)
            ->has('summary')
            ->has('periods', 1)
        );
    }

    public function test_a_period_can_be_created(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('presupuesto.periods.store'), [
            'year' => 2026,
            'month' => 9,
            'currency' => 'usd',
            'available_money' => 1500,
        ]);

        $period = BudgetPeriod::firstOrFail();

        $response->assertRedirect(route('presupuesto.index', ['period' => $period->id]));
        $this->assertDatabaseHas('budget_periods', [
            'year' => 2026,
            'month' => 9,
            'currency' => 'USD',
            'available_money' => 1500,
        ]);
    }

    public function test_a_period_must_be_unique_by_year_month_and_currency(): void
    {
        $user = User::factory()->create();
        BudgetPeriod::factory()->create(['year' => 2026, 'month' => 9, 'currency' => 'USD']);

        $response = $this->actingAs($user)->post(route('presupuesto.periods.store'), [
            'year' => 2026,
            'month' => 9,
            'currency' => 'USD',
        ]);

        $response->assertSessionHasErrors('month');
        $this->assertSame(1, BudgetPeriod::count());
    }

    public function test_period_header_fields_are_updated_inline_as_json(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create(['available_money' => 0, 'status' => 'abierto']);

        $response = $this->actingAs($user)->patchJson(route('presupuesto.periods.update', $period), [
            'available_money' => 800.50,
            'status' => 'cerrado',
        ]);

        $response->assertOk()
            ->assertJsonPath('period.status', 'cerrado')
            ->assertJsonStructure(['period', 'summary' => ['utilidad_neta', 'ganancia_bruta', 'estado']]);

        $this->assertDatabaseHas('budget_periods', [
            'id' => $period->id,
            'available_money' => 800.50,
            'status' => 'cerrado',
        ]);
    }

    public function test_a_line_can_be_added_updated_and_removed_without_a_page_reload(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();

        $created = $this->actingAs($user)->postJson(route('presupuesto.lines.store', $period), [
            'section' => BudgetLine::SECTION_PURCHASE,
        ]);

        $created->assertCreated()->assertJsonPath('line.section', BudgetLine::SECTION_PURCHASE);
        $lineId = $created->json('line.id');

        $updated = $this->actingAs($user)->patchJson(route('presupuesto.lines.update', $lineId), [
            'fecha' => '2026-09-03',
            'party_name' => 'Distribuidora Sur',
            'producto' => 'Arroz',
            'cantidad' => 10,
            'unit_price' => 5,
            'payment_status' => 'Pendiente',
        ]);

        $updated->assertOk()
            ->assertJsonPath('line.producto', 'Arroz')
            ->assertJsonPath('line.precio_total', 50)
            ->assertJsonPath('summary.total_compras', 50)
            ->assertJsonPath('summary.cuentas_por_pagar', 50);

        $this->assertDatabaseHas('budget_lines', [
            'id' => $lineId,
            'producto' => 'Arroz',
            'cantidad' => 10,
            'unit_price' => 5,
        ]);

        $this->actingAs($user)->deleteJson(route('presupuesto.lines.destroy', $lineId))
            ->assertOk()
            ->assertJsonPath('summary.total_compras', 0);

        $this->assertDatabaseMissing('budget_lines', ['id' => $lineId]);
    }

    public function test_the_section_of_an_existing_line_cannot_be_changed(): void
    {
        $user = User::factory()->create();
        $line = BudgetLine::factory()->section(BudgetLine::SECTION_SALE)->create();

        $this->actingAs($user)->patchJson(route('presupuesto.lines.update', $line), [
            'section' => BudgetLine::SECTION_PURCHASE,
        ])->assertStatus(422);

        $this->assertDatabaseHas('budget_lines', ['id' => $line->id, 'section' => BudgetLine::SECTION_SALE]);
    }

    public function test_deleting_a_period_cascades_to_its_lines(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        BudgetLine::factory()->count(2)->for($period, 'period')->create();

        $this->actingAs($user)->delete(route('presupuesto.periods.destroy', $period))
            ->assertRedirect(route('presupuesto.index'));

        $this->assertDatabaseMissing('budget_periods', ['id' => $period->id]);
        $this->assertSame(0, BudgetLine::count());
    }

    public function test_the_summary_totals_are_derived_from_the_lines(): void
    {
        $period = BudgetPeriod::factory()->create();

        BudgetLine::factory()->for($period, 'period')->create([
            'section' => BudgetLine::SECTION_PURCHASE, 'cantidad' => 10, 'unit_price' => 5, 'payment_status' => 'Pagado',
        ]);
        BudgetLine::factory()->for($period, 'period')->create([
            'section' => BudgetLine::SECTION_PURCHASE, 'cantidad' => 4, 'unit_price' => 10, 'payment_status' => 'Pendiente',
        ]);
        BudgetLine::factory()->for($period, 'period')->create([
            'section' => BudgetLine::SECTION_SALE, 'cantidad' => 10, 'unit_price' => 12,
        ]);
        BudgetLine::factory()->for($period, 'period')->create([
            'section' => BudgetLine::SECTION_CLIENT, 'cantidad' => 2, 'unit_price' => 15, 'payment_status' => 'Pendiente',
        ]);
        BudgetLine::factory()->for($period, 'period')->create([
            'section' => BudgetLine::SECTION_RESULT,
            'ganancia' => 200, 'gastos_personales' => 50, 'perdidas_mercancia' => 20, 'inversiones' => 30,
        ]);

        $summary = $period->summary();

        $this->assertSame(90.0, $summary['total_compras']);
        $this->assertSame(120.0, $summary['total_ventas']);
        $this->assertSame(30.0, $summary['total_clientes']);
        $this->assertSame(150.0, $summary['ingresos_totales']);
        $this->assertSame(40.0, $summary['cuentas_por_pagar']);
        $this->assertSame(30.0, $summary['cuentas_por_cobrar']);
        $this->assertSame(60.0, $summary['ganancia_bruta']);
        $this->assertSame(200.0, $summary['ganancia_registrada']);
        $this->assertSame(50.0, $summary['gastos_personales']);
        $this->assertSame(20.0, $summary['perdidas_mercancia']);
        $this->assertSame(30.0, $summary['inversiones']);
        $this->assertSame(100.0, $summary['utilidad_neta']);
        $this->assertSame('ganancia', $summary['estado']);
    }
}
