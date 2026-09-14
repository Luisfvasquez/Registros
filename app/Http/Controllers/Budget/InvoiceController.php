<?php

namespace App\Http\Controllers\Budget;

use App\Concerns\ResolvesBudgetInvoices;
use App\Http\Controllers\Controller;
use App\Http\Requests\BudgetInvoicePaymentRequest;
use App\Models\BudgetLine;
use App\Models\BudgetPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Facturas: agrupan varias compras o ventas del mismo proveedor o cliente, y
 * admiten un abono que se reparte entre ellas.
 */
class InvoiceController extends Controller
{
    use ResolvesBudgetInvoices;

    /**
     * Abre una factura nueva. Si viene `line_id`, la factura nace para el
     * contacto de esa fila y se la lleva con ella: así se puede sacar una
     * factura aparte desde la hoja de compras o ventas.
     */
    public function store(Request $request, BudgetPeriod $period): JsonResponse
    {
        $lineId = $request->integer('line_id');

        $line = $lineId
            ? BudgetLine::where('budget_period_id', $period->id)
                ->whereIn('section', BudgetLine::PAYABLE_SECTIONS)
                ->findOrFail($lineId)
            : null;

        if ($line !== null && $line->contact_line_id === null) {
            throw ValidationException::withMessages([
                'line_id' => __('Elegí primero el proveedor o el cliente de la fila.'),
            ]);
        }

        $invoice = DB::transaction(function () use ($period, $line): BudgetLine {
            $invoice = $line !== null
                ? $this->createInvoiceFor($line)
                : $this->blankInvoice($period);

            $line?->forceFill(['invoice_line_id' => $invoice->id])->save();

            return $invoice;
        });

        $invoice = $invoice->fresh();

        return response()->json([
            'invoice' => $this->present($invoice),
            'option' => $this->invoiceOption($invoice),
            'line' => $line?->fresh()->load('payments'),
        ], 201);
    }

    /**
     * Abono contra la factura entera: se va cubriendo movimiento por movimiento,
     * del más viejo al más nuevo, hasta agotar el monto. Dos ventas de 3.000 con
     * un abono de 4.000 dejan la primera pagada y 1.000 abonados en la segunda.
     */
    public function storePayment(
        BudgetInvoicePaymentRequest $request,
        BudgetPeriod $period,
        BudgetLine $invoice,
    ): JsonResponse {
        $this->assertInvoiceOfPeriod($invoice, $period);

        $data = $request->validated();
        $rate = (float) ($data['exchange_rate'] ?? 0) > 0 ? (float) $data['exchange_rate'] : null;

        $pendientes = $invoice->invoiceLines()
            ->with('payments')
            ->sheetOrder()
            ->get()
            ->filter(fn (BudgetLine $line): bool => $line->restante > 0.001)
            ->values();

        $porCubrir = round((float) $pendientes->sum('restante'), 2);
        $monto = $this->amountInCurrency($data, $rate);

        if ($pendientes->isEmpty()) {
            throw ValidationException::withMessages([
                'amount' => __('Esta factura no tiene nada pendiente.'),
            ]);
        }

        if ($monto > $porCubrir + 0.001) {
            throw ValidationException::withMessages([
                'amount' => __('El abono no puede superar lo que falta en la factura (:restante).', [
                    'restante' => number_format($porCubrir, 2),
                ]),
            ]);
        }

        DB::transaction(function () use ($pendientes, $data, $monto, $rate): void {
            $this->spread($pendientes, $data, $monto, $rate);
        });

        return response()->json([
            'invoice' => $this->present($invoice->fresh()),
            'summary' => $period->summary(),
        ], 201);
    }

    /**
     * Reparte el abono entre los movimientos pendientes.
     *
     * Cuando el pago vino en bolívares, lo que se reparte son los bolívares y el
     * último movimiento se queda con el resto: así la suma de los abonos da
     * exactamente lo que se entregó, sin perder centavos en el redondeo.
     *
     * @param  Collection<int, BudgetLine>  $pendientes
     * @param  array<string, mixed>  $data
     */
    private function spread(Collection $pendientes, array $data, float $monto, ?float $rate): void
    {
        $restanteBs = $rate !== null ? round((float) $data['amount_bs'], 2) : null;
        $porRepartir = $monto;
        $ultimo = $pendientes->count() - 1;

        foreach ($pendientes as $index => $line) {
            if ($porRepartir <= 0.001) {
                break;
            }

            $parte = round(min($porRepartir, $line->restante), 2);
            $parteBs = null;

            if ($rate !== null) {
                $parteBs = $index === $ultimo || $parte >= $porRepartir
                    ? $restanteBs
                    : round($parte * $rate, 2);
                $restanteBs = round((float) $restanteBs - (float) $parteBs, 2);
            }

            $line->payments()->create([
                'fecha' => $data['fecha'],
                'method' => $data['method'] ?? null,
                'amount_bs' => $parteBs,
                'exchange_rate' => $rate,
                'amount' => $parte,
                'notes' => $data['notes'] ?? null,
            ]);

            $porRepartir = round($porRepartir - $parte, 2);
        }
    }

    /**
     * Una factura sin contacto todavía: el admin elige el proveedor o cliente en
     * la propia hoja.
     */
    private function blankInvoice(BudgetPeriod $period): BudgetLine
    {
        return BudgetLine::create([
            'budget_period_id' => $period->id,
            'section' => BudgetLine::SECTION_INVOICE,
            'tipo' => BudgetLine::SECTION_SALE,
            'fecha' => now()->toDateString(),
            'invoice_number' => $this->nextInvoiceNumber($period->id),
            'position' => (int) $period->lines()
                ->section(BudgetLine::SECTION_INVOICE)
                ->max('position') + 1,
        ]);
    }

    private function assertInvoiceOfPeriod(BudgetLine $invoice, BudgetPeriod $period): void
    {
        if ($invoice->section !== BudgetLine::SECTION_INVOICE || $invoice->budget_period_id !== $period->id) {
            throw ValidationException::withMessages([
                'invoice' => __('La factura no pertenece a este período.'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function amountInCurrency(array $data, ?float $rate): float
    {
        if ($rate !== null && isset($data['amount_bs'])) {
            return round((float) $data['amount_bs'] / $rate, 2);
        }

        return round((float) ($data['amount'] ?? 0), 2);
    }

    /**
     * La factura tal como la muestra la hoja: con sus movimientos y sus totales.
     *
     * @return array<string, mixed>
     */
    private function present(BudgetLine $invoice): array
    {
        return $invoice->load(['invoiceLines' => fn ($query) => $query->with('payments')->sheetOrder()])
            ->toInvoiceArray();
    }
}
