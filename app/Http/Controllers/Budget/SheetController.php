<?php

namespace App\Http\Controllers\Budget;

use App\Models\BudgetLine;
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
            'facturas' => $period->lines()
                ->section(BudgetLine::SECTION_INVOICE)
                ->whereNotNull('invoice_number')
                ->orderBy('invoice_number')
                ->pluck('invoice_number')
                ->all(),
            'summary' => $period->summary(),
        ]);
    }

    /**
     * Facturas: cada una apunta a una compra o venta y muestra sus datos.
     */
    public function invoices(BudgetPeriod $period): Response
    {
        $lines = $period->lines()
            ->section(BudgetLine::SECTION_INVOICE)
            ->with(['sourceLine.payments'])
            ->sheetOrder()
            ->get();

        return $this->sheet('Facturas', $period, [
            'lines' => $lines,
            'sources' => $this->invoiceSources($period),
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
     * Compras y ventas del período con todo lo que la hoja de facturas muestra,
     * para que el select de "registro origen" traiga los datos ya resueltos.
     *
     * @return list<array<string, mixed>>
     */
    private function invoiceSources(BudgetPeriod $period): array
    {
        return $period->lines()
            ->whereIn('section', BudgetLine::PAYABLE_SECTIONS)
            ->with('payments')
            ->sheetOrder()
            ->get()
            ->map(fn (BudgetLine $line): array => [
                'id' => $line->id,
                'tipo' => $line->section,
                'label' => $line->sheetLabel(),
                'fecha' => $line->fecha?->toDateString(),
                'party_name' => $line->party_name,
                'producto' => $line->producto,
                'cantidad' => $line->cantidad,
                'unit_price' => $line->unit_price,
                'precio_total' => $line->precio_total,
                'payment_method' => $line->payment_method,
                'payment_status' => $line->payment_status,
                'abonado' => $line->abonado,
                'restante' => $line->restante,
            ])
            ->all();
    }

    /**
     * Semanas del mes del período.
     *
     * @return list<array{label: string, ventas: float, compras: float, costo: float, gastos: float, utilidad: float}>
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
     * @return list<array{label: string, ventas: float, compras: float, costo: float, gastos: float, utilidad: float}>
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
     * @return list<array{label: string, ventas: float, compras: float, costo: float, gastos: float, utilidad: float}>
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
     * @param  array{ventas: array<array-key, float>, compras: array<array-key, float>, costo: array<array-key, float>, gastos: array<array-key, float>}  $totals
     * @return array{label: string, ventas: float, compras: float, costo: float, gastos: float, utilidad: float}
     */
    private function point(string $label, array $totals, string $key): array
    {
        $ventas = round((float) ($totals['ventas'][$key] ?? 0), 2);
        $compras = round((float) ($totals['compras'][$key] ?? 0), 2);
        $costo = round((float) ($totals['costo'][$key] ?? 0), 2);
        $gastos = round((float) ($totals['gastos'][$key] ?? 0), 2);

        return [
            'label' => $label,
            'ventas' => $ventas,
            'compras' => $compras,
            'costo' => $costo,
            'gastos' => $gastos,
            'utilidad' => round($ventas - $costo - $gastos, 2),
        ];
    }

    /**
     * Totales por día plegados al bucket que pida el llamador (semana, mes o año).
     *
     * @param  (callable(Carbon): string)|null  $bucket
     * @return array{ventas: array<array-key, float>, compras: array<array-key, float>, costo: array<array-key, float>, gastos: array<array-key, float>}
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
            'costo' => $fold($byDay(BudgetLine::SECTION_SALE, 'costo')),
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
