<?php

namespace App\Http\Controllers\Budget;

use App\Concerns\ResolvesBudgetInvoices;
use App\Http\Controllers\Controller;
use App\Http\Requests\BudgetLineRequest;
use App\Models\BudgetLine;
use App\Models\BudgetPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Alta, edición y borrado de filas de cualquier hoja. Responde JSON porque las
 * planillas se editan celda por celda, sin recargar la página.
 */
class LineController extends Controller
{
    use ResolvesBudgetInvoices;

    /**
     * Campos que una compra o venta copia del contacto elegido, para que la fila
     * siga siendo legible aunque después se borre del Directorio.
     *
     * @var list<string>
     */
    private const FROM_CONTACT = ['party_name', 'telefono'];

    public function store(BudgetLineRequest $request, BudgetPeriod $period): JsonResponse
    {
        $section = $request->string('section')->toString();
        $data = $this->resolveContact($request->safe()->except(['section', 'position']));

        // El Directorio es global: sus filas no cuelgan de ningún período.
        $periodId = $section === BudgetLine::SECTION_CONTACT ? null : $period->id;

        $line = DB::transaction(function () use ($data, $periodId, $section, $request): BudgetLine {
            $line = BudgetLine::create([
                ...$data,
                'budget_period_id' => $periodId,
                'section' => $section,
                'position' => $request->integer('position') ?: $this->nextPosition($section, $periodId),
            ]);

            $this->syncInvoice($line, $data);

            return $line;
        });

        $line = $line->fresh()->load('payments', 'invoice');

        return response()->json([
            'line' => $line,
            'invoice' => $this->invoiceOption($line->invoice),
            'summary' => $periodId === null ? null : $period->summary(),
        ], 201);
    }

    public function update(BudgetLineRequest $request, BudgetLine $line): JsonResponse
    {
        $data = $this->resolveContact($request->safe()->except('section'));

        DB::transaction(function () use ($line, $data): void {
            $line->update($data);

            if ($line->section === BudgetLine::SECTION_CONTACT) {
                $this->propagateContact($line, $data);
            }

            $this->syncInvoice($line, $data);
        });

        $fresh = $line->fresh()->load('payments', 'invoice');

        return response()->json([
            'line' => $this->linePayload($fresh),
            'invoice' => $this->invoiceOption($fresh->invoice),
            'summary' => $line->period?->summary(),
        ]);
    }

    public function destroy(BudgetLine $line): JsonResponse
    {
        $period = $line->period;

        $line->delete();

        return response()->json(['summary' => $period?->summary()]);
    }

    /**
     * La fila como la espera su hoja. La de una factura se devuelve con sus
     * movimientos y totales: tocar el cargo extra cambia lo que la factura suma,
     * y esos números los arma el servidor.
     *
     * @return BudgetLine|array<string, mixed>
     */
    private function linePayload(BudgetLine $line): BudgetLine|array
    {
        return $line->section === BudgetLine::SECTION_INVOICE
            ? $line->toInvoiceArray()
            : $line;
    }

    /**
     * Engancha la compra o venta a la factura que ya exista para su contacto.
     *
     * Nunca abre una: eso solo pasa cuando el admin toca "＋ Nueva factura". Si
     * el proveedor todavía no tiene factura, la fila queda sin ella y él decide.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncInvoice(BudgetLine $line, array $data): void
    {
        if (! in_array($line->section, BudgetLine::PAYABLE_SECTIONS, true)) {
            return;
        }

        if (array_key_exists('invoice_line_id', $data)) {
            return;
        }

        // Al cambiar de contacto, la factura anterior deja de corresponder. Si
        // se volvió a elegir el mismo, la fila se queda donde está.
        if (array_key_exists('contact_line_id', $data) && $line->invoice_line_id !== null) {
            $invoice = $line->invoice()->first();

            if ($invoice?->contact_line_id !== $line->contact_line_id) {
                $line->forceFill(['invoice_line_id' => null])->save();
            }
        }

        if ($line->invoice_line_id !== null) {
            return;
        }

        $invoice = $this->openInvoiceFor($line);

        if ($invoice !== null) {
            $line->forceFill(['invoice_line_id' => $invoice->id])->save();
        }
    }

    /**
     * La fila nueva va al final de su hoja.
     */
    private function nextPosition(string $section, ?int $periodId): int
    {
        $last = BudgetLine::query()
            ->section($section)
            ->where('budget_period_id', $periodId)
            ->max('position');

        return (int) $last + 1;
    }

    /**
     * El nombre y el teléfono de una compra o venta salen siempre del contacto
     * elegido, nunca de lo que se escriba en la celda.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveContact(array $data): array
    {
        if (! array_key_exists('contact_line_id', $data)) {
            return $data;
        }

        $contact = $data['contact_line_id'] ? BudgetLine::find($data['contact_line_id']) : null;

        return [
            ...$data,
            'party_name' => $contact?->party_name,
            'telefono' => $contact?->telefono,
        ];
    }

    /**
     * Al renombrar un proveedor o cliente, las compras y ventas que lo tenían
     * copiado se actualizan con él.
     *
     * @param  array<string, mixed>  $data
     */
    private function propagateContact(BudgetLine $contact, array $data): void
    {
        $changed = array_intersect_key($data, array_flip(self::FROM_CONTACT));

        if ($changed === []) {
            return;
        }

        BudgetLine::where('contact_line_id', $contact->id)->update($changed);
    }
}
