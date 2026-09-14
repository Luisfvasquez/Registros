<?php

namespace App\Concerns;

use App\Models\BudgetLine;

/**
 * Cómo una compra o venta encuentra su factura.
 *
 * La idea es no hacer viajar al admin a la pestaña Facturas: la primera compra a
 * un proveedor abre su factura y las siguientes se cuelgan de esa misma, hasta
 * que él elija otra a mano en la celda "Factura".
 */
trait ResolvesBudgetInvoices
{
    /**
     * La factura donde va esta compra o venta: la última abierta para ese
     * contacto en el período, o una nueva. Devuelve null si la fila todavía no
     * tiene contacto, porque una factura sin proveedor ni cliente no sirve.
     */
    protected function invoiceFor(BudgetLine $line): ?BudgetLine
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
            ->first()
            ?? $this->createInvoiceFor($line);
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
