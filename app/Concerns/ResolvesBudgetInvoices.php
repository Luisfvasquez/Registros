<?php

namespace App\Concerns;

use App\Models\BudgetLine;

/**
 * Cómo una compra o venta encuentra su factura.
 *
 * Una factura nunca nace sola: la abre el admin desde "＋ Nueva factura". Lo que
 * sí es automático es engancharse a una que ya exista para ese contacto, para no
 * hacerlo viajar a la pestaña Facturas por cada fila.
 *
 * Esa diferencia importa: si crear la fila abriera una factura, cambiar de
 * contacto o mover la fila a otra factura iría dejando facturas vacías atrás.
 */
trait ResolvesBudgetInvoices
{
    /**
     * La última factura abierta de ese contacto en el período, o null si no hay
     * ninguna todavía (o si la fila aún no tiene contacto). No crea nada.
     */
    protected function openInvoiceFor(BudgetLine $line): ?BudgetLine
    {
        if ($line->contact_line_id === null || $line->budget_period_id === null) {
            return null;
        }

        return BudgetLine::query()
            ->where('budget_period_id', $line->budget_period_id)
            ->section(BudgetLine::SECTION_INVOICE)
            ->where('tipo', $line->section)
            ->where('contact_line_id', $line->contact_line_id)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Abre una factura nueva para el contacto y el tipo de la fila.
     */
    protected function createInvoiceFor(BudgetLine $line): BudgetLine
    {
        return BudgetLine::create([
            'budget_period_id' => $line->budget_period_id,
            'section' => BudgetLine::SECTION_INVOICE,
            'tipo' => $line->section,
            'fecha' => $line->fecha,
            'contact_line_id' => $line->contact_line_id,
            'party_name' => $line->party_name,
            'telefono' => $line->telefono,
            'invoice_number' => $this->nextInvoiceNumber((int) $line->budget_period_id),
            'position' => (int) BudgetLine::query()
                ->section(BudgetLine::SECTION_INVOICE)
                ->where('budget_period_id', $line->budget_period_id)
                ->max('position') + 1,
        ]);
    }

    /**
     * La factura como la espera la celda "Factura" de compras y ventas.
     *
     * @return array{id: int, label: string, contact_line_id: int|null}|null
     */
    protected function invoiceOption(?BudgetLine $invoice): ?array
    {
        if ($invoice === null) {
            return null;
        }

        return [
            'id' => $invoice->id,
            'label' => ($invoice->invoice_number ?? 'Factura').' · '.($invoice->party_name ?? 'Sin contacto'),
            'contact_line_id' => $invoice->contact_line_id,
        ];
    }

    /**
     * El siguiente número libre del período: se mira el mayor ya usado en lugar
     * de contar filas, para que borrar una factura no repita su número.
     */
    protected function nextInvoiceNumber(int $periodId): string
    {
        $used = BudgetLine::query()
            ->where('budget_period_id', $periodId)
            ->section(BudgetLine::SECTION_INVOICE)
            ->whereNotNull('invoice_number')
            ->pluck('invoice_number')
            ->map(fn (mixed $number): int => (int) preg_replace('/\D/', '', (string) $number))
            ->all();

        return sprintf('FAC-%04d', ($used === [] ? 0 : max($used)) + 1);
    }
}
