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
        BudgetLine::factory()->count(3)->for($period, 'period')->create(['section' => BudgetLine::SECTION_INCOME]);

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
            ->assertJsonStructure(['period', 'summary' => ['dinero_disponible', 'utilidad']]);

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
            'section' => BudgetLine::SECTION_FIXED_EXPENSE,
        ]);

        $created->assertCreated()->assertJsonPath('line.section', BudgetLine::SECTION_FIXED_EXPENSE);
        $lineId = $created->json('line.id');

        $updated = $this->actingAs($user)->patchJson(route('presupuesto.lines.update', $lineId), [
            'detail' => 'Alquiler',
            'category' => 'Hogar',
            'planned' => 500,
            'actual' => 520,
            'payment_method' => 'Transferencia',
        ]);

        $updated->assertOk()
            ->assertJsonPath('line.detail', 'Alquiler')
            ->assertJsonPath('summary.gastos_totales', 520)
            ->assertJsonPath('summary.estado_presupuesto', 'excedido');

        $this->assertDatabaseHas('budget_lines', ['id' => $lineId, 'detail' => 'Alquiler', 'actual' => 520]);

        $this->actingAs($user)->deleteJson(route('presupuesto.lines.destroy', $lineId))
            ->assertOk()
            ->assertJsonPath('summary.gastos_totales', 0);

        $this->assertDatabaseMissing('budget_lines', ['id' => $lineId]);
    }

    public function test_the_section_of_an_existing_line_cannot_be_changed(): void
    {
        $user = User::factory()->create();
        $line = BudgetLine::factory()->section(BudgetLine::SECTION_SAVING)->create();

        $this->actingAs($user)->patchJson(route('presupuesto.lines.update', $line), [
            'section' => BudgetLine::SECTION_DEBT,
        ])->assertStatus(422);

        $this->assertDatabaseHas('budget_lines', ['id' => $line->id, 'section' => BudgetLine::SECTION_SAVING]);
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
        $period = BudgetPeriod::factory()->create(['available_money' => 100]);

        BudgetLine::factory()->for($period, 'period')->create([
            'section' => BudgetLine::SECTION_INCOME, 'planned' => 1000, 'actual' => 1200, 'is_unexpected' => true,
        ]);
        BudgetLine::factory()->for($period, 'period')->create([
            'section' => BudgetLine::SECTION_BUDGET, 'planned' => 400, 'actual' => 350,
        ]);
        BudgetLine::factory()->for($period, 'period')->create([
            'section' => BudgetLine::SECTION_FIXED_EXPENSE, 'planned' => 200, 'actual' => 200,
        ]);
        BudgetLine::factory()->for($period, 'period')->create([
            'section' => BudgetLine::SECTION_SAVING, 'planned' => 150, 'actual' => 150,
        ]);
        BudgetLine::factory()->for($period, 'period')->create([
            'section' => BudgetLine::SECTION_DEBT, 'planned' => 100, 'actual' => 90,
        ]);

        $summary = $period->summary();

        $this->assertSame(1200.0, $summary['ingreso_total']);
        $this->assertSame(1200.0, $summary['ganancias_inesperadas']);
        $this->assertSame(550.0, $summary['gastos_totales']);
        $this->assertSame(600.0, $summary['presupuesto_total']);
        $this->assertSame(50.0, $summary['presupuesto_disponible']);
        $this->assertSame(150.0, $summary['ahorros_inversiones']);
        $this->assertSame(90.0, $summary['pagos_deuda']);
        $this->assertSame(560.0, $summary['utilidad']);
        // 100 disponible + 1200 ingreso - 550 gastos - 90 deuda - 150 ahorro
        $this->assertSame(510.0, $summary['dinero_disponible']);
        $this->assertSame('dentro', $summary['estado_presupuesto']);
    }
}
