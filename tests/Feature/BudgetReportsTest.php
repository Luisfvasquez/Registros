<?php

namespace Tests\Feature;

use App\Models\BudgetLine;
use App\Models\BudgetPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BudgetReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_summary_is_built_from_the_rows_of_the_period(): void
    {
        $period = BudgetPeriod::factory()->create();

        BudgetLine::factory()->for($period, 'period')->create([
            'section' => BudgetLine::SECTION_PURCHASE,
            'cantidad' => 50,
            'unit_price' => 0.8,
            'payment_status' => 'Pendiente',
        ]);

        $venta = BudgetLine::factory()->for($period, 'period')->sale()->create([
            'cantidad' => 10,
            'unit_price' => 1.5,
            'costo' => 8,
            'payment_status' => 'Pendiente',
        ]);
        $venta->payments()->create(['fecha' => '2026-09-04', 'amount' => 7]);

        BudgetLine::factory()->for($period, 'period')->expense()->create(['monto' => 12.5]);

        BudgetLine::factory()->for($period, 'period')->result()->create([
            'ganancia' => 100,
            'gastos_personales' => 30,
            'perdidas_mercancia' => 8,
        ]);

        $summary = $period->fresh()->summary();

        $this->assertSame(40.0, $summary['total_compras']);
        $this->assertSame(15.0, $summary['total_ventas']);
        $this->assertSame(8.0, $summary['costo_ventas']);
        $this->assertSame(7.0, $summary['ganancia_bruta']);
        $this->assertSame(40.0, $summary['cuentas_por_pagar']);
        $this->assertSame(8.0, $summary['cuentas_por_cobrar']);
        $this->assertSame(7.0, $summary['cobrado_a_clientes']);
        $this->assertSame(12.5, $summary['gastos']);
        // 7 − 12.50 − 30 − 8
        $this->assertSame(-43.5, $summary['utilidad_neta']);
        $this->assertSame('perdida', $summary['estado']);
    }

    public function test_a_paid_row_does_not_count_towards_the_pending_balances(): void
    {
        $period = BudgetPeriod::factory()->create();

        BudgetLine::factory()->for($period, 'period')->create([
            'section' => BudgetLine::SECTION_PURCHASE,
            'cantidad' => 10,
            'unit_price' => 1,
            'payment_status' => 'Pagado',
        ]);

        $this->assertSame(0.0, $period->summary()['cuentas_por_pagar']);
    }

    public function test_the_dashboard_ships_the_three_series_and_the_breakdowns(): void
    {
        $user = User::factory()->create();
        $today = Carbon::today();
        $period = BudgetPeriod::factory()->create([
            'year' => $today->year,
            'month' => $today->month,
        ]);

        BudgetLine::factory()->for($period, 'period')->sale()->create([
            'fecha' => $today->toDateString(),
            'producto' => 'Tomate',
            'cantidad' => 10,
            'unit_price' => 1.5,
            'payment_method' => 'Efectivo',
        ]);

        $this->actingAs($user)
            ->get(route('presupuesto.dashboard', $period))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('presupuesto/Dashboard')
                ->has('series.semanal')
                ->has('series.mensual', 12)
                ->has('series.anual')
                ->has('topProductos', 1)
                ->where('topProductos.0.producto', 'Tomate')
                ->where('topProductos.0.total', 15)
                ->has('metodosPago', 1)
                ->where('metodosPago.0.metodo', 'Efectivo')
            );
    }

    public function test_the_provider_account_groups_the_purchases_of_one_contact(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();

        $proveedor = BudgetLine::factory()->contact()->create(['party_name' => 'Proveedor Ejemplo']);
        $otro = BudgetLine::factory()->contact()->create(['party_name' => 'Distribuidora Central']);

        $compra = BudgetLine::factory()->for($period, 'period')->create([
            'contact_line_id' => $proveedor->id,
            'party_name' => $proveedor->party_name,
            'cantidad' => 50,
            'unit_price' => 0.8,
        ]);
        $compra->payments()->create(['fecha' => '2026-09-04', 'amount' => 40]);

        BudgetLine::factory()->for($period, 'period')->create([
            'contact_line_id' => $otro->id,
            'cantidad' => 30,
            'unit_price' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('presupuesto.provider-account', ['period' => $period, 'contacto' => $proveedor->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('presupuesto/CuentaProveedor')
                ->where('contactoId', $proveedor->id)
                ->where('party.party_name', 'Proveedor Ejemplo')
                ->has('lines', 1)
                ->where('totals.total', 40)
                ->where('totals.abonado', 40)
                ->where('totals.restante', 0)
            );
    }

    public function test_the_client_account_falls_back_to_the_first_contact_with_movements(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();

        $cliente = BudgetLine::factory()->contact(BudgetLine::TYPE_CLIENT)->create([
            'party_name' => 'Maria Perez',
        ]);

        BudgetLine::factory()->for($period, 'period')->sale()->create([
            'contact_line_id' => $cliente->id,
            'party_name' => $cliente->party_name,
            'cantidad' => 5,
            'unit_price' => 2,
        ]);

        $this->actingAs($user)
            ->get(route('presupuesto.client-account', $period))
            ->assertInertia(fn (Assert $page) => $page
                ->where('contactoId', $cliente->id)
                ->where('totals.total', 10)
                ->where('totals.restante', 10)
            );
    }

    public function test_the_account_statement_can_be_downloaded_as_a_pdf(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        $proveedor = BudgetLine::factory()->contact()->create(['party_name' => 'Proveedor Ejemplo']);

        BudgetLine::factory()->for($period, 'period')->create([
            'contact_line_id' => $proveedor->id,
            'party_name' => $proveedor->party_name,
            'cantidad' => 2,
            'unit_price' => 3,
        ]);

        $response = $this->actingAs($user)->get(route('presupuesto.account.pdf', [
            'period' => $period,
            'section' => BudgetLine::SECTION_PURCHASE,
            'contacto' => $proveedor->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_daily_sales_sheet_closes_one_date(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();

        $venta = BudgetLine::factory()->for($period, 'period')->sale()->create([
            'fecha' => '2026-09-04',
            'cantidad' => 10,
            'unit_price' => 1.5,
        ]);
        $venta->payments()->create(['fecha' => '2026-09-04', 'amount' => 7]);

        BudgetLine::factory()->for($period, 'period')->sale()->create([
            'fecha' => '2026-09-05',
            'cantidad' => 4,
            'unit_price' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('presupuesto.daily-sales', ['period' => $period, 'fecha' => '2026-09-04']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('presupuesto/VentasDelDia')
                ->where('fecha', '2026-09-04')
                ->has('fechas', 2)
                ->has('lines', 1)
                ->where('totals.vendido', 15)
                ->where('totals.cobrado', 7)
                ->where('totals.por_cobrar', 8)
                ->where('totals.unidades', 10)
            );
    }

    public function test_the_invoices_sheet_offers_the_purchases_and_sales_as_sources(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();

        $venta = BudgetLine::factory()->for($period, 'period')->sale()->create([
            'fecha' => '2026-09-04',
            'producto' => 'Tomate',
            'cantidad' => 10,
            'unit_price' => 1.5,
        ]);
        BudgetLine::factory()->for($period, 'period')->create([
            'cantidad' => 50,
            'unit_price' => 0.8,
        ]);

        $factura = BudgetLine::factory()->for($period, 'period')->create([
            'section' => BudgetLine::SECTION_INVOICE,
            'tipo' => BudgetLine::SECTION_SALE,
            'invoice_number' => 'FAC-0001',
            'linked_line_id' => $venta->id,
            'producto' => null,
            'cantidad' => null,
            'unit_price' => null,
        ]);

        $this->actingAs($user)
            ->get(route('presupuesto.invoices', $period))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('presupuesto/Facturas')
                ->has('lines', 1)
                ->where('lines.0.id', $factura->id)
                ->where('lines.0.source_line.producto', 'Tomate')
                ->has('sources', 2)
            );
    }

    public function test_an_invoice_can_only_point_at_a_purchase_or_a_sale(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        $gasto = BudgetLine::factory()->for($period, 'period')->expense()->create();

        $this->actingAs($user)
            ->postJson(route('presupuesto.lines.store', $period), [
                'section' => BudgetLine::SECTION_INVOICE,
                'invoice_number' => 'FAC-0002',
                'linked_line_id' => $gasto->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('linked_line_id');
    }
}
