<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Http\Requests\BudgetLinePaymentRequest;
use App\Models\BudgetLine;
use App\Models\BudgetLinePayment;
use App\Models\BudgetPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hojas de abonos: cada fila es un pago contra una compra o una venta. El estado
 * de pago de la fila vinculada lo recalcula el observador.
 */
class PaymentController extends Controller
{
    public function store(BudgetLinePaymentRequest $request, BudgetPeriod $period): JsonResponse
    {
        $data = $request->validated();

        $line = BudgetLine::findOrFail($data['budget_line_id']);

        $this->assertBelongsToPeriod($line, $period);

        $data['amount'] = $this->amountInCurrency($data);

        $this->assertFitsBalance($line, (float) $data['amount']);

        $payment = DB::transaction(fn () => $line->payments()->create($data));

        return response()->json([
            'payment' => $payment,
            'line' => $line->fresh()->load('payments'),
            'summary' => $period->summary(),
        ], 201);
    }

    public function update(BudgetLinePaymentRequest $request, BudgetLinePayment $payment): JsonResponse
    {
        $line = $payment->line;
        $data = $request->validated();

        // El monto se recalcula sobre la mezcla de lo guardado y lo que llega,
        // porque una edición puede tocar solo la tasa o solo los bolívares.
        $merged = [
            'amount' => $payment->amount,
            'amount_bs' => $payment->amount_bs,
            'exchange_rate' => $payment->exchange_rate,
            ...$data,
        ];

        $amount = $this->amountInCurrency($merged);

        $this->assertFitsBalance($line, $amount, $payment);

        DB::transaction(fn () => $payment->update([...$data, 'amount' => $amount]));

        return response()->json([
            'payment' => $payment->fresh(),
            'line' => $line->fresh()->load('payments'),
            'summary' => $line->period?->summary(),
        ]);
    }

    public function destroy(BudgetLinePayment $payment): JsonResponse
    {
        $line = $payment->line;

        DB::transaction(fn () => $payment->delete());

        return response()->json([
            'line' => $line->fresh()->load('payments'),
            'summary' => $line->period?->summary(),
        ]);
    }

    private function assertBelongsToPeriod(BudgetLine $line, BudgetPeriod $period): void
    {
        if ($line->budget_period_id !== $period->id) {
            throw ValidationException::withMessages([
                'budget_line_id' => __('El registro vinculado no pertenece a este período.'),
            ]);
        }
    }

    /**
     * Un abono no puede pasarse de lo que falta. Al editar, el propio abono no
     * cuenta contra el saldo que está reemplazando.
     */
    private function assertFitsBalance(BudgetLine $line, float $amount, ?BudgetLinePayment $editing = null): void
    {
        $yaAbonado = (float) $line->payments()
            ->when($editing, fn ($query) => $query->whereKeyNot($editing->getKey()))
            ->sum('amount');

        $restante = round($line->precio_total - $yaAbonado, 2);

        if ($amount > $restante + 0.001) {
            throw ValidationException::withMessages([
                'amount' => __('El abono no puede superar lo que falta (:restante).', [
                    'restante' => number_format($restante, 2),
                ]),
            ]);
        }
    }

    /**
     * Un abono cargado en bolívares se convierte con la tasa que se guardó junto
     * a él, igual que hace el observador al persistirlo.
     *
     * @param  array<string, mixed>  $data
     */
    private function amountInCurrency(array $data): float
    {
        if (isset($data['amount_bs']) && (float) ($data['exchange_rate'] ?? 0) > 0) {
            return round((float) $data['amount_bs'] / (float) $data['exchange_rate'], 2);
        }

        return round((float) ($data['amount'] ?? 0), 2);
    }
}
