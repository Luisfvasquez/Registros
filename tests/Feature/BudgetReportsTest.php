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
            'payment_status' => 'Pendiente',
        ]);
        $venta->payments()->create(['fecha' => '2026-09-04', 'amount' => 7]);

        BudgetLine::factory()->for($period, 'period')->expense()->create(['monto' => 12.5]);

        BudgetLine::factory()->for($period, 'period')->result()->create([
            'monto_compra' => 100,
            'monto_venta' => 300,
            'costo' => 20,
            'gastos_personales' => 30,
            'perdidas_mercancia' => 8,
        ]);

        $summary = $period->fresh()->summary();

        $this->assertSame(40.0, $summary['total_compras']);
        $this->assertSame(15.0, $summary['total_ventas']);
        // 15 − 40
        $this->assertSame(-25.0, $summary['ganancia_bruta']);
        $this->assertSame(40.0, $summary['cuentas_por_pagar']);
        $this->assertSame(8.0, $summary['cuentas_por_cobrar']);
        $this->assertSame(7.0, $summary['cobrado_a_clientes']);
        $this->assertSame(12.5, $summary['gastos']);
        // (300 − 100 − 20) − 30 − 8
        $this->assertSame(142.0, $summary['resultado_utilidad']);
        // −25 − 12.50 − 30 − 8
        $this->assertSame(-75.5, $summary['utilidad_neta']);
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

    public function test_the_invoices_sheet_groups_the_movements_of_each_invoice(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();

        $cliente = BudgetLine::factory()->contact(BudgetLine::TYPE_CLIENT)->create();
        $factura = BudgetLine::factory()->for($period, 'period')->invoice()->create([
            'invoice_number' => 'FAC-0001',
            'contact_line_id' => $cliente->id,
            'party_name' => $cliente->party_name,
        ]);

        $primera = BudgetLine::factory()->for($period, 'period')->sale()->create([
            'invoice_line_id' => $factura->id,
            'cantidad' => 10,
            'unit_price' => 1.5,
        ]);
        $primera->payments()->create(['fecha' => '2026-09-04', 'amount' => 5]);

        BudgetLine::factory()->for($period, 'period')->sale()->create([
            'invoice_line_id' => $factura->id,
            'cantidad' => 2,
            'unit_price' => 10,
        ]);

        // Una venta suelta, sin factura: no tiene que contar.
        BudgetLine::factory()->for($period, 'period')->sale()->create([
            'cantidad' => 1,
            'unit_price' => 99,
        ]);

        $this->actingAs($user)
            ->get(route('presupuesto.invoices', $period))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('presupuesto/Facturas')
                ->has('lines', 1)
                ->where('lines.0.invoice_number', 'FAC-0001')
                ->has('lines.0.items', 2)
                // El ticket que se comparte lista los abonos de cada movimiento.
                ->has('lines.0.items.0.payments')
                ->where('lines.0.totales.movimientos', 2)
                ->where('lines.0.totales.total', 35)
                ->where('lines.0.totales.abonado', 5)
                ->where('lines.0.totales.restante', 30)
                ->where('lines.0.totales.estado', 'Abonada')
            );
    }

    public function test_creating_a_purchase_does_not_open_an_invoice_on_its_own(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        $proveedor = BudgetLine::factory()->contact()->create();

        $this->actingAs($user)
            ->postJson(route('presupuesto.lines.store', $period), [
                'section' => BudgetLine::SECTION_PURCHASE,
                'contact_line_id' => $proveedor->id,
                'cantidad' => 2,
                'unit_price' => 5,
            ])
            ->assertCreated()
            ->assertJsonPath('line.invoice_line_id', null)
            ->assertJsonPath('invoice', null);

        $this->assertSame(0, BudgetLine::where('section', BudgetLine::SECTION_INVOICE)->count());
    }

    public function test_the_invoice_is_opened_on_demand_and_the_next_purchase_joins_it(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        $proveedor = BudgetLine::factory()->contact()->create();

        $compra = $this->actingAs($user)
            ->postJson(route('presupuesto.lines.store', $period), [
                'section' => BudgetLine::SECTION_PURCHASE,
                'contact_line_id' => $proveedor->id,
                'cantidad' => 2,
                'unit_price' => 5,
            ])
            ->json('line.id');

        // "＋ Nueva factura" sobre esa fila: recién acá nace la factura.
        $facturaId = $this->actingAs($user)
            ->postJson(route('presupuesto.invoices.store', $period), ['line_id' => $compra])
            ->assertCreated()
            ->assertJsonPath('line.invoice_line_id', fn (mixed $id) => $id !== null)
            ->json('invoice.id');

        $this->assertSame('FAC-0001', BudgetLine::find($facturaId)->invoice_number);

        // La segunda compra al mismo proveedor cae sola en esa factura.
        $this->actingAs($user)
            ->postJson(route('presupuesto.lines.store', $period), [
                'section' => BudgetLine::SECTION_PURCHASE,
                'contact_line_id' => $proveedor->id,
                'cantidad' => 1,
                'unit_price' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('line.invoice_line_id', $facturaId);

        // Y otro proveedor, que no tiene factura, queda sin ella.
        $otro = BudgetLine::factory()->contact()->create();

        $this->actingAs($user)
            ->postJson(route('presupuesto.lines.store', $period), [
                'section' => BudgetLine::SECTION_PURCHASE,
                'contact_line_id' => $otro->id,
                'cantidad' => 1,
                'unit_price' => 7,
            ])
            ->assertCreated()
            ->assertJsonPath('line.invoice_line_id', null);

        $this->assertSame(1, BudgetLine::where('section', BudgetLine::SECTION_INVOICE)->count());
    }

    public function test_changing_the_contact_of_a_row_does_not_leave_an_empty_invoice_behind(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        $tomas = BudgetLine::factory()->contact()->create(['party_name' => 'Tomas']);
        $jazmin = BudgetLine::factory()->contact()->create(['party_name' => 'Jazmin']);

        $factura = BudgetLine::factory()->for($period, 'period')->invoice(BudgetLine::SECTION_PURCHASE)->create([
            'contact_line_id' => $tomas->id,
            'party_name' => 'Tomas',
        ]);

        $line = BudgetLine::factory()->for($period, 'period')->create([
            'contact_line_id' => $tomas->id,
            'party_name' => 'Tomas',
            'invoice_line_id' => $factura->id,
        ]);

        // Se pasa la fila a Jazmin, que no tiene factura: se suelta y no se crea nada.
        $this->actingAs($user)
            ->patchJson(route('presupuesto.lines.update', $line), [
                'contact_line_id' => $jazmin->id,
            ])
            ->assertOk()
            ->assertJsonPath('line.invoice_line_id', null)
            ->assertJsonPath('invoice', null);

        $this->assertSame(1, BudgetLine::where('section', BudgetLine::SECTION_INVOICE)->count());
    }

    public function test_a_row_without_a_contact_is_not_attached_to_any_invoice(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();

        $this->actingAs($user)
            ->postJson(route('presupuesto.lines.store', $period), [
                'section' => BudgetLine::SECTION_SALE,
                'producto' => 'Venta de mostrador',
                'cantidad' => 1,
                'unit_price' => 5,
            ])
            ->assertCreated()
            ->assertJsonPath('line.invoice_line_id', null)
            ->assertJsonPath('invoice', null);

        $this->assertSame(0, BudgetLine::where('section', BudgetLine::SECTION_INVOICE)->count());
    }

    public function test_an_invoice_payment_is_spread_across_its_movements(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        $cliente = BudgetLine::factory()->contact(BudgetLine::TYPE_CLIENT)->create();

        $factura = BudgetLine::factory()->for($period, 'period')->invoice()->create([
            'contact_line_id' => $cliente->id,
        ]);

        $primera = BudgetLine::factory()->for($period, 'period')->sale()->create([
            'invoice_line_id' => $factura->id,
            'fecha' => '2026-09-04',
            'cantidad' => 1,
            'unit_price' => 3000,
            'payment_status' => 'Pendiente',
        ]);
        $segunda = BudgetLine::factory()->for($period, 'period')->sale()->create([
            'invoice_line_id' => $factura->id,
            'fecha' => '2026-09-05',
            'cantidad' => 1,
            'unit_price' => 3000,
            'payment_status' => 'Pendiente',
        ]);

        $this->actingAs($user)
            ->postJson(route('presupuesto.invoices.payments.store', [$period, $factura]), [
                'fecha' => '2026-09-06',
                'method' => 'Transferencia',
                'amount' => 4000,
            ])
            ->assertCreated()
            ->assertJsonPath('invoice.totales.abonado', 4000)
            ->assertJsonPath('invoice.totales.restante', 2000);

        // La primera queda pagada entera y la segunda con 1.000 abonados.
        $this->assertSame(3000.0, $primera->fresh()->abonado);
        $this->assertSame('Pagado', $primera->fresh()->payment_status);
        $this->assertSame(1000.0, $segunda->fresh()->abonado);
        $this->assertSame('Abonado', $segunda->fresh()->payment_status);
    }

    public function test_an_invoice_payment_shows_as_one_abono_even_though_it_is_spread(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        $factura = BudgetLine::factory()->for($period, 'period')->invoice()->create();

        foreach ([3000, 3000, 3000] as $index => $precio) {
            BudgetLine::factory()->for($period, 'period')->sale()->create([
                'invoice_line_id' => $factura->id,
                'fecha' => '2026-09-0'.($index + 1),
                'cantidad' => 1,
                'unit_price' => $precio,
                'payment_status' => 'Pendiente',
            ]);
        }

        $abonos = $this->actingAs($user)
            ->postJson(route('presupuesto.invoices.payments.store', [$period, $factura]), [
                'fecha' => '2026-09-06',
                'method' => 'Pago móvil',
                'amount' => 6000,
            ])
            ->assertCreated()
            ->json('invoice.abonos');

        // Por dentro son dos filas de 3.000, pero para el cliente fue un pago.
        $this->assertCount(1, $abonos);
        $this->assertSame(6000.0, (float) $abonos[0]['amount']);
        $this->assertSame('Pago móvil', $abonos[0]['method']);
        $this->assertSame(2, $abonos[0]['movimientos']);
        $this->assertSame(2, $factura->invoiceLines()->has('payments')->count());
    }

    public function test_abonos_loaded_one_by_one_stay_separate_on_the_receipt(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        $factura = BudgetLine::factory()->for($period, 'period')->invoice()->create();

        $venta = BudgetLine::factory()->for($period, 'period')->sale()->create([
            'invoice_line_id' => $factura->id,
            'cantidad' => 1,
            'unit_price' => 100,
            'payment_status' => 'Pendiente',
        ]);

        foreach ([30, 20] as $monto) {
            $this->actingAs($user)
                ->postJson(route('presupuesto.payments.store', $period), [
                    'budget_line_id' => $venta->id,
                    'fecha' => '2026-09-06',
                    'method' => 'Efectivo',
                    'amount' => $monto,
                ])
                ->assertCreated();
        }

        $this->actingAs($user)
            ->get(route('presupuesto.invoices', $period))
            ->assertInertia(fn (Assert $page) => $page
                ->has('lines.0.abonos', 2)
                ->where('lines.0.abonos.0.movimientos', 1)
            );
    }

    public function test_an_invoice_payment_in_bolivares_keeps_the_delivered_total(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        $factura = BudgetLine::factory()->for($period, 'period')->invoice()->create();

        foreach ([100, 100] as $index => $precio) {
            BudgetLine::factory()->for($period, 'period')->sale()->create([
                'invoice_line_id' => $factura->id,
                'fecha' => '2026-09-0'.($index + 4),
                'cantidad' => 1,
                'unit_price' => $precio,
                'payment_status' => 'Pendiente',
            ]);
        }

        $this->actingAs($user)
            ->postJson(route('presupuesto.invoices.payments.store', [$period, $factura]), [
                'fecha' => '2026-09-06',
                'amount_bs' => 5400,
                'exchange_rate' => 36,
            ])
            ->assertCreated();

        $entregado = $factura->invoiceLines()
            ->with('payments')
            ->get()
            ->flatMap->payments
            ->sum(fn ($payment) => (float) $payment->amount_bs);

        $this->assertSame(5400.0, $entregado);
    }

    public function test_an_invoice_payment_cannot_exceed_what_the_invoice_owes(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        $factura = BudgetLine::factory()->for($period, 'period')->invoice()->create();

        BudgetLine::factory()->for($period, 'period')->sale()->create([
            'invoice_line_id' => $factura->id,
            'cantidad' => 1,
            'unit_price' => 100,
        ]);

        $this->actingAs($user)
            ->postJson(route('presupuesto.invoices.payments.store', [$period, $factura]), [
                'fecha' => '2026-09-06',
                'amount' => 250,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
    }

    public function test_a_row_can_only_be_attached_to_an_invoice_row(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        $gasto = BudgetLine::factory()->for($period, 'period')->expense()->create();

        $this->actingAs($user)
            ->postJson(route('presupuesto.lines.store', $period), [
                'section' => BudgetLine::SECTION_PURCHASE,
                'invoice_line_id' => $gasto->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('invoice_line_id');
    }

    public function test_the_extra_charge_is_added_to_what_the_invoice_owes(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();

        $factura = BudgetLine::factory()->for($period, 'period')->invoice()->create([
            'monto_adicional' => 25,
        ]);

        BudgetLine::factory()->for($period, 'period')->sale()->create([
            'invoice_line_id' => $factura->id,
            'cantidad' => 2,
            'unit_price' => 50,
        ]);

        $this->actingAs($user)
            ->get(route('presupuesto.invoices', $period))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lines.0.totales.subtotal', 100)
                ->where('lines.0.totales.adicional', 25)
                ->where('lines.0.totales.total', 125)
                ->where('lines.0.totales.restante', 125)
                ->where('lines.0.totales.estado', 'Pendiente')
            );
    }

    public function test_editing_the_extra_charge_returns_the_invoice_with_its_totals_rehechos(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        $factura = BudgetLine::factory()->for($period, 'period')->invoice()->create();

        BudgetLine::factory()->for($period, 'period')->sale()->create([
            'invoice_line_id' => $factura->id,
            'cantidad' => 1,
            'unit_price' => 80,
        ]);

        $this->actingAs($user)
            ->patchJson(route('presupuesto.lines.update', $factura), ['monto_adicional' => 20])
            ->assertOk()
            ->assertJsonPath('line.totales.adicional', 20)
            ->assertJsonPath('line.totales.total', 100)
            ->assertJsonPath('line.totales.restante', 100);
    }

    public function test_an_invoice_payment_covers_the_extra_charge_after_the_movements(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();

        $factura = BudgetLine::factory()->for($period, 'period')->invoice()->create([
            'monto_adicional' => 20,
        ]);

        $venta = BudgetLine::factory()->for($period, 'period')->sale()->create([
            'invoice_line_id' => $factura->id,
            'cantidad' => 1,
            'unit_price' => 100,
            'payment_status' => 'Pendiente',
        ]);

        // Cubre la venta entera y 10 del flete.
        $this->actingAs($user)
            ->postJson(route('presupuesto.invoices.payments.store', [$period, $factura]), [
                'fecha' => '2026-09-06',
                'method' => 'Efectivo',
                'amount' => 110,
            ])
            ->assertCreated()
            ->assertJsonPath('invoice.totales.abonado', 110)
            ->assertJsonPath('invoice.totales.restante', 10)
            ->assertJsonPath('invoice.totales.adicional_restante', 10)
            ->assertJsonPath('invoice.totales.estado', 'Abonada')
            // Para el cliente fue un solo pago, aunque por dentro sean dos filas.
            ->assertJsonCount(1, 'invoice.abonos')
            ->assertJsonPath('invoice.abonos.0.amount', 110);

        $this->assertSame(100.0, $venta->fresh()->abonado);
        $this->assertSame('Pagado', $venta->fresh()->payment_status);
        $this->assertSame(10.0, round((float) $factura->payments()->sum('amount'), 2));

        // Lo que queda del flete cierra la factura.
        $this->actingAs($user)
            ->postJson(route('presupuesto.invoices.payments.store', [$period, $factura]), [
                'fecha' => '2026-09-07',
                'amount' => 10,
            ])
            ->assertCreated()
            ->assertJsonPath('invoice.totales.restante', 0)
            ->assertJsonPath('invoice.totales.estado', 'Pagada');
    }

    public function test_an_invoice_payment_cannot_exceed_the_movements_plus_the_extra_charge(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();

        $factura = BudgetLine::factory()->for($period, 'period')->invoice()->create([
            'monto_adicional' => 20,
        ]);

        BudgetLine::factory()->for($period, 'period')->sale()->create([
            'invoice_line_id' => $factura->id,
            'cantidad' => 1,
            'unit_price' => 100,
        ]);

        $this->actingAs($user)
            ->postJson(route('presupuesto.invoices.payments.store', [$period, $factura]), [
                'fecha' => '2026-09-06',
                'amount' => 121,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
    }

    public function test_a_profit_and_loss_row_keeps_its_note(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();

        $fila = $this->actingAs($user)
            ->postJson(route('presupuesto.lines.store', $period), [
                'section' => BudgetLine::SECTION_RESULT,
                'fecha' => '2026-09-04',
                'monto_venta' => 300,
                'notas' => 'Se vendió con descuento por volumen.',
            ])
            ->assertCreated()
            ->assertJsonPath('line.notas', 'Se vendió con descuento por volumen.')
            ->json('line.id');

        $this->actingAs($user)
            ->get(route('presupuesto.results', $period))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('presupuesto/Ganancias')
                ->where('lines.0.id', $fila)
                ->where('lines.0.notas', 'Se vendió con descuento por volumen.')
            );
    }

    public function test_the_flete_lowers_the_profit_of_a_result_row(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();

        $this->actingAs($user)
            ->postJson(route('presupuesto.lines.store', $period), [
                'section' => BudgetLine::SECTION_RESULT,
                'fecha' => '2026-09-04',
                'monto_compra' => 100,
                'monto_venta' => 300,
                'costo' => 20,
                'flete' => 30,
                'gastos_personales' => 50,
            ])
            ->assertCreated()
            // 300 − 100 − 20 − 30.
            ->assertJsonPath('line.utilidad', 150)
            // Y el total baja además por los gastos personales.
            ->assertJsonPath('line.total_utilidad', 100)
            ->assertJsonPath('summary.resultado_utilidad', 100);

        $this->actingAs($user)
            ->get(route('presupuesto.results', $period))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('presupuesto/Ganancias')
                ->where('lines.0.flete', '30.00')
                ->where('lines.0.utilidad', 150)
            );
    }
}
