<?php

namespace App\Http\Controllers\Budget;

use App\Models\BudgetLine;
use App\Models\BudgetLinePayment;
use App\Models\BudgetPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Response;

/**
 * Las hojas que se editan como una planilla: tablero, directorio, compras,
 * ventas, gastos, ganancias y pérdidas, y facturas.
 */
class SheetController extends BaseController
{
    /**
     * Tablero del período: métricas, resumen y series semanal / mensual / anual.
     */
    public function dashboard(BudgetPeriod $period): Response
    {
        $period->load(['lines' => fn ($query) => $query->with('payments')]);

        return $this->sheet('Dashboard', $period, [
            'summary' => $period->summary(),
            'series' => [
                'semanal' => $this->weeklySeries($period),
                'mensual' => $this->monthlySeries($period->year),
                'anual' => $this->yearlySeries(),
            ],
            'topProductos' => $this->topProducts($period),
            'metodosPago' => $this->paymentMethodBreakdown($period),
        ]);
    }

    /**
     * Directorio: proveedores y clientes, compartidos por todos los períodos.
     */
    public function directory(BudgetPeriod $period): Response
    {
        $contacts = $this->contacts();

        return $this->sheet('Directorio', $period, [
            'proveedores' => $contacts->where('tipo', BudgetLine::TYPE_PROVIDER)->values(),
            'clientes' => $contacts->where('tipo', BudgetLine::TYPE_CLIENT)->values(),
        ]);
    }

    public function purchases(BudgetPeriod $period): Response
    {
        return $this->sheet('Compras', $period, [
            'lines' => $this->linesOf($period, BudgetLine::SECTION_PURCHASE),
            'proveedores' => $this->contacts(BudgetLine::TYPE_PROVIDER),
            'productos' => $this->suggestions('producto'),
            'facturas' => $this->invoiceOptions($period, BudgetLine::SECTION_PURCHASE),
            'summary' => $period->summary(),
        ]);
    }

    public function sales(BudgetPeriod $period): Response
    {
        return $this->sheet('Ventas', $period, [
            'lines' => $this->linesOf($period, BudgetLine::SECTION_SALE),
            'clientes' => $this->contacts(BudgetLine::TYPE_CLIENT),
            'productos' => $this->suggestions('producto'),
            'metodos' => $this->suggestions('payment_method'),
            'facturas' => $this->invoiceOptions($period, BudgetLine::SECTION_SALE),
            'summary' => $period->summary(),
        ]);
    }

    public function expenses(BudgetPeriod $period): Response
    {
        return $this->sheet('Gastos', $period, [
            'lines' => $this->linesOf($period, BudgetLine::SECTION_EXPENSE),
            'categorias' => $this->suggestions('categoria', BudgetLine::SECTION_EXPENSE),
            'summary' => $period->summary(),
        ]);
    }

    /**
     * Ganancias y pérdidas del mes, fila por fila.
     */
    public function results(BudgetPeriod $period): Response
    {
        return $this->sheet('Ganancias', $period, [
            'lines' => $this->linesOf($period, BudgetLine::SECTION_RESULT),
            'summary' => $period->summary(),
        ]);
    }

    /**
     * Facturas: cada una agrupa las compras o ventas de un proveedor o cliente,
     * con los totales que salen de esos movimientos.
     */
    public function invoices(BudgetPeriod $period): Response
    {
        $invoices = $period->lines()
            ->section(BudgetLine::SECTION_INVOICE)
            ->with(['payments', 'invoiceLines' => fn ($query) => $query->with('payments')->sheetOrder()])
            ->sheetOrder()
            ->get()
            ->map(fn (BudgetLine $invoice): array => $invoice->toInvoiceArray())
            ->all();

        return $this->sheet('Facturas', $period, [
            'lines' => $invoices,
            'proveedores' => $this->contacts(BudgetLine::TYPE_PROVIDER),
            'clientes' => $this->contacts(BudgetLine::TYPE_CLIENT),
            'metodos' => $this->paymentMethods(),
            'summary' => $period->summary(),
        ]);
    }

    /**
     * @return Collection<int, BudgetLine>
     */
    private function linesOf(BudgetPeriod $period, string $section): Collection
    {
        return $period->lines()
            ->section($section)
            ->with('payments')
            ->sheetOrder()
            ->get();
    }

    /**
     * Facturas del período de un tipo, para la celda "Factura" de la hoja de
     * compras o de ventas.
     *
     * @return list<array{id: int, label: string, contact_line_id: int|null}>
     */
    private function invoiceOptions(BudgetPeriod $period, string $tipo): array
    {
        return $period->lines()
            ->section(BudgetLine::SECTION_INVOICE)
            ->where('tipo', $tipo)
            ->orderBy('invoice_number')
            ->get(['id', 'section', 'invoice_number', 'party_name', 'contact_line_id'])
            ->map(fn (BudgetLine $invoice): array => [
                'id' => $invoice->id,
                'label' => ($invoice->invoice_number ?? 'Factura').' · '.($invoice->party_name ?? 'Sin contacto'),
                'contact_line_id' => $invoice->contact_line_id,
            ])
            ->all();
    }

    /**
     * Métodos de pago ya usados en algún abono.
     *
     * @return list<string>
     */
    private function paymentMethods(): array
    {
        return BudgetLinePayment::query()
            ->whereNotNull('method')
            ->where('method', '!=', '')
            ->distinct()
            ->orderBy('method')
            ->pluck('method')
            ->map(fn (mixed $method): string => (string) $method)
            ->values()
            ->all();
    }

    /**
     * Semanas del mes del período.
     *
     * @return list<array{label: string, ventas: float, compras: float, gastos: float, utilidad: float}>
     */
    private function weeklySeries(BudgetPeriod $period): array
    {
        $start = Carbon::create($period->year, $period->month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $totals = $this->dailyTotals($start->toDateString(), $end->toDateString());

        $series = [];
        // Illuminate\Support\Carbon es mutable: cada salto trabaja sobre una copia
        // para no correr también $start ni $end. Un mes toca 6 semanas como mucho.
        $cursor = $start->copy()->startOfWeek();

        for ($week = 1; $week <= 6 && $cursor->lte($end); $week++) {
            $closes = $cursor->copy()->endOfWeek();

            $series[] = $this->point(
                sprintf('Sem %d · %s–%s', $week, $cursor->format('d/m'), $closes->format('d/m')),
                $totals,
                $cursor->isoFormat('GGGG-WW'),
            );

            $cursor = $cursor->copy()->addWeek();
        }

        return $series;
    }

    /**
     * @return list<array{label: string, ventas: float, compras: float, gastos: float, utilidad: float}>
     */
    private function monthlySeries(int $year): array
    {
        $totals = $this->dailyTotals($year.'-01-01', $year.'-12-31', fn (Carbon $date): string => $date->format('Y-m'));

        return array_map(
            fn (int $month): array => $this->point(
                Carbon::create($year, $month, 1)->isoFormat('MMM'),
                $totals,
                sprintf('%04d-%02d', $year, $month),
            ),
            range(1, 12),
        );
    }

    /**
     * @return list<array{label: string, ventas: float, compras: float, gastos: float, utilidad: float}>
     */
    private function yearlySeries(): array
    {
        $years = $this->availableYears();

        if ($years === []) {
            return [];
        }

        $totals = $this->dailyTotals(
            min($years).'-01-01',
            max($years).'-12-31',
            fn (Carbon $date): string => $date->format('Y'),
        );

        return array_map(
            fn (int $year): array => $this->point((string) $year, $totals, (string) $year),
            $years,
        );
    }

    /**
     * @param  array{ventas: array<array-key, float>, compras: array<array-key, float>, gastos: array<array-key, float>}  $totals
     * @return array{label: string, ventas: float, compras: float, gastos: float, utilidad: float}
     */
    private function point(string $label, array $totals, string $key): array
    {
        $ventas = round((float) ($totals['ventas'][$key] ?? 0), 2);
        $compras = round((float) ($totals['compras'][$key] ?? 0), 2);
        $gastos = round((float) ($totals['gastos'][$key] ?? 0), 2);

        return [
            'label' => $label,
            'ventas' => $ventas,
            'compras' => $compras,
            'gastos' => $gastos,
            'utilidad' => round($ventas - $compras - $gastos, 2),
        ];
    }

    /**
     * Totales por día plegados al bucket que pida el llamador (semana, mes o año).
     *
     * @param  (callable(Carbon): string)|null  $bucket
     * @return array{ventas: array<array-key, float>, compras: array<array-key, float>, gastos: array<array-key, float>}
     */
    private function dailyTotals(string $from, string $to, ?callable $bucket = null): array
    {
        $bucket ??= fn (Carbon $date): string => $date->isoFormat('GGGG-WW');

        $fold = function (Collection $daily) use ($bucket): array {
            $totals = [];

            foreach ($daily as $date => $total) {
                $key = $bucket(Carbon::parse((string) $date));
                $totals[$key] = ($totals[$key] ?? 0) + (float) $total;
            }

            return $totals;
        };

        $byDay = fn (string $section, string $expression) => BudgetLine::query()
            ->section($section)
            ->whereNotNull('fecha')
            ->whereBetween('fecha', [$from, $to])
            ->selectRaw('fecha, SUM('.$expression.') as day_total')
            ->groupBy('fecha')
            ->pluck('day_total', 'fecha');

        return [
            'ventas' => $fold($byDay(BudgetLine::SECTION_SALE, 'cantidad * unit_price')),
            'compras' => $fold($byDay(BudgetLine::SECTION_PURCHASE, 'cantidad * unit_price')),
            'gastos' => $fold($byDay(BudgetLine::SECTION_EXPENSE, 'monto')),
        ];
    }

    /**
     * Lo más vendido del período, por importe.
     *
     * @return list<array{producto: string, cantidad: float, total: float}>
     */
    private function topProducts(BudgetPeriod $period): array
    {
        return $period->lines()
            ->section(BudgetLine::SECTION_SALE)
            ->whereNotNull('producto')
            ->where('producto', '!=', '')
            ->selectRaw('producto, SUM(cantidad) as total_cantidad, SUM(cantidad * unit_price) as total_importe')
            ->groupBy('producto')
            ->orderByDesc('total_importe')
            ->limit(8)
            ->get()
            ->map(fn (BudgetLine $row): array => [
                'producto' => (string) $row->producto,
                'cantidad' => round((float) $row->getAttribute('total_cantidad'), 2),
                'total' => round((float) $row->getAttribute('total_importe'), 2),
            ])
            ->all();
    }

    /**
     * Cómo se cobró: importe de ventas por método de pago.
     *
     * @return list<array{metodo: string, total: float}>
     */
    private function paymentMethodBreakdown(BudgetPeriod $period): array
    {
        return $period->lines()
            ->section(BudgetLine::SECTION_SALE)
            ->selectRaw('payment_method, SUM(cantidad * unit_price) as total_importe')
            ->groupBy('payment_method')
            ->orderByDesc('total_importe')
            ->get()
            ->map(fn (BudgetLine $row): array => [
                'metodo' => (string) ($row->payment_method ?: 'Sin método'),
                'total' => round((float) $row->getAttribute('total_importe'), 2),
            ])
            ->all();
    }

    /**
     * Cada año con al menos una fila fechada, del más viejo al más nuevo.
     *
     * @return list<int>
     */
    private function availableYears(): array
    {
        $dated = BudgetLine::query()->whereNotNull('fecha');

        $first = $dated->clone()->min('fecha');
        $last = $dated->clone()->max('fecha');

        if ($first === null || $last === null) {
            return [];
        }

        return range(
            (int) Carbon::parse((string) $first)->year,
            (int) Carbon::parse((string) $last)->year,
        );
    }
}
