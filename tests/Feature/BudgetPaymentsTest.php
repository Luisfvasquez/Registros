<?php

namespace Tests\Feature;

use App\Models\BudgetLine;
use App\Models\BudgetPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BudgetPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private function purchase(BudgetPeriod $period, float $cantidad = 20, float $precio = 1.2): BudgetLine
    {
        return BudgetLine::factory()->for($period, 'period')->create([
            'section' => BudgetLine::SECTION_PURCHASE,
            'cantidad' => $cantidad,
            'unit_price' => $precio,
            'payment_status' => 'Pendiente',
        ]);
    }

    public function test_an_abono_in_bolivares_is_converted_with_its_own_rate(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        $line = $this->purchase($period);

        $response = $this->actingAs($user)->postJson(route('presupuesto.payments.store', $period), [
            'budget_line_id' => $line->id,
            'fecha' => '2026-09-04',
            'method' => 'Pago movil',
            'amount_bs' => 360,
            'exchange_rate' => 36,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('payment.amount', '10.00');
        $response->assertJsonPath('line.abonado', 10);
        $response->assertJsonPath('line.restante', 14);
        $response->assertJsonPath('line.payment_status', 'Abonado');
    }

    public function test_covering_the_balance_marks_the_row_as_paid(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        $line = $this->purchase($period, 50, 0.8);

        $this->actingAs($user)
            ->postJson(route('presupuesto.payments.store', $period), [
                'budget_line_id' => $line->id,
                'fecha' => '2026-09-04',
                'amount' => 40,
            ])
            ->assertCreated()
            ->assertJsonPath('line.payment_status', 'Pagado')
            ->assertJsonPath('line.restante', 0);
    }

    public function test_an_abono_cannot_exceed_what_is_left(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        $line = $this->purchase($period, 10, 1);

        $this->actingAs($user)
            ->postJson(route('presupuesto.payments.store', $period), [
                'budget_line_id' => $line->id,
                'fecha' => '2026-09-04',
                'amount' => 25,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->assertSame(0, $line->payments()->count());
    }

    public function test_an_abono_needs_an_amount_or_bolivares_with_a_rate(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        $line = $this->purchase($period);

        $this->actingAs($user)
            ->postJson(route('presupuesto.payments.store', $period), [
                'budget_line_id' => $line->id,
                'fecha' => '2026-09-04',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->actingAs($user)
            ->postJson(route('presupuesto.payments.store', $period), [
                'budget_line_id' => $line->id,
                'fecha' => '2026-09-04',
                'amount_bs' => 360,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('exchange_rate');
    }

    public function test_only_purchases_and_sales_accept_abonos(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        $gasto = BudgetLine::factory()->for($period, 'period')->expense()->create();

        $this->actingAs($user)
            ->postJson(route('presupuesto.payments.store', $period), [
                'budget_line_id' => $gasto->id,
                'fecha' => '2026-09-04',
                'amount' => 5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('budget_line_id');
    }

    public function test_an_abono_against_another_period_is_rejected(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create(['month' => 8]);
        $other = BudgetPeriod::factory()->create(['month' => 9]);
        $line = $this->purchase($other);

        $this->actingAs($user)
            ->postJson(route('presupuesto.payments.store', $period), [
                'budget_line_id' => $line->id,
                'fecha' => '2026-09-04',
                'amount' => 5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('budget_line_id');
    }

    public function test_editing_an_abono_recalculates_the_row(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        $line = $this->purchase($period, 20, 1.2);

        $payment = $line->payments()->create([
            'fecha' => '2026-09-04',
            'amount' => 10,
        ]);

        // El propio abono no cuenta contra el saldo que está reemplazando.
        $this->actingAs($user)
            ->patchJson(route('presupuesto.payments.update', $payment), ['amount' => 24])
            ->assertOk()
            ->assertJsonPath('line.abonado', 24)
            ->assertJsonPath('line.payment_status', 'Pagado');

        $this->actingAs($user)
            ->patchJson(route('presupuesto.payments.update', $payment), ['amount' => 30])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
    }

    public function test_the_linked_row_of_an_abono_cannot_be_moved(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        $line = $this->purchase($period);
        $payment = $line->payments()->create(['fecha' => '2026-09-04', 'amount' => 5]);

        $this->actingAs($user)
            ->patchJson(route('presupuesto.payments.update', $payment), [
                'budget_line_id' => $this->purchase($period)->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('budget_line_id');
    }

    public function test_deleting_the_last_abono_sends_the_row_back_to_pending(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();
        $line = $this->purchase($period, 10, 1);
        $payment = $line->payments()->create(['fecha' => '2026-09-04', 'amount' => 10]);

        $this->assertSame('Pagado', $line->refresh()->payment_status);

        $this->actingAs($user)
            ->deleteJson(route('presupuesto.payments.destroy', $payment))
            ->assertOk()
            ->assertJsonPath('line.payment_status', 'Pendiente')
            ->assertJsonPath('line.abonado', 0);
    }

    public function test_the_abonos_sheet_lists_the_payments_of_its_own_side(): void
    {
        $user = User::factory()->create();
        $period = BudgetPeriod::factory()->create();

        $compra = $this->purchase($period, 10, 1);
        $compra->payments()->create(['fecha' => '2026-09-04', 'amount' => 4]);

        $venta = BudgetLine::factory()->for($period, 'period')->sale()->create([
            'cantidad' => 5,
            'unit_price' => 2,
        ]);
        $venta->payments()->create(['fecha' => '2026-09-05', 'amount' => 6]);

        $this->actingAs($user)
            ->get(route('presupuesto.purchase-payments', $period))
            ->assertInertia(fn (Assert $page) => $page
                ->component('presupuesto/AbonosCompras')
                ->has('payments', 1)
                ->has('registros', 1)
                ->where('totals.abonado', 4)
            );

        $this->actingAs($user)
            ->get(route('presupuesto.sale-payments', $period))
            ->assertInertia(fn (Assert $page) => $page
                ->component('presupuesto/AbonosVentas')
                ->has('payments', 1)
                ->where('totals.abonado', 6)
            );
    }
}
