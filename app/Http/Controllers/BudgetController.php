<?php

namespace App\Http\Controllers;

use App\Http\Requests\BudgetLineRequest;
use App\Http\Requests\BudgetPeriodRequest;
use App\Models\BudgetLine;
use App\Models\BudgetPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    /**
     * Single-page, spreadsheet-style view of every budget period.
     */
    public function index(Request $request): Response
    {
        $periods = BudgetPeriod::query()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderBy('currency')
            ->get(['id', 'year', 'month', 'currency', 'status']);

        $current = null;

        if ($request->filled('period')) {
            $current = BudgetPeriod::find($request->integer('period'));
        }

        $current ??= BudgetPeriod::query()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();

        $lines = [];
        $summary = null;

        if ($current !== null) {
            $current->load(['lines' => fn ($query) => $query->orderBy('position')->orderBy('id')]);
            $lines = $current->lines;
            $summary = $current->summary();
        }

        return Inertia::render('presupuesto/Index', [
            'periods' => $periods,
            'period' => $current,
            'lines' => $lines,
            'summary' => $summary,
            'suggestions' => [
                'parties' => $this->distinctValues('party_name'),
                'productos' => $this->distinctValues('producto'),
                'payment_methods' => $this->distinctValues('payment_method'),
                'payment_statuses' => $this->distinctValues('payment_status'),
            ],
        ]);
    }

    public function storePeriod(BudgetPeriodRequest $request): RedirectResponse
    {
        $period = BudgetPeriod::create($request->safe()->only([
            'year', 'month', 'currency', 'status', 'available_money', 'notes',
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Período creado.')]);

        return to_route('presupuesto.index', ['period' => $period->id]);
    }

    /**
     * Inline edit of the period header (dinero disponible, estado, notas, moneda).
     */
    public function updatePeriod(BudgetPeriodRequest $request, BudgetPeriod $period): JsonResponse
    {
        $period->update($request->safe()->only([
            'year', 'month', 'currency', 'status', 'available_money', 'notes',
        ]));

        return response()->json([
            'period' => $period->fresh(),
            'summary' => $period->summary(),
        ]);
    }

    public function destroyPeriod(BudgetPeriod $period): RedirectResponse
    {
        $period->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Período eliminado.')]);

        return to_route('presupuesto.index');
    }

    /**
     * Create a blank row, or a row pre-seeded with a proveedor / cliente and a
     * fecha so several products can be logged for the same party without
     * retyping it.
     */
    public function storeLine(BudgetLineRequest $request, BudgetPeriod $period): JsonResponse
    {
        $line = $period->lines()->create([
            ...$request->safe()->except(['section', 'position']),
            'section' => $request->string('section')->toString(),
            'position' => $request->integer('position', ($period->lines()->max('position') ?? 0) + 1),
        ]);

        return response()->json([
            'line' => $line,
            'summary' => $period->summary(),
        ], 201);
    }

    /**
     * Fields mirrored from a "cliente" row onto the "venta" row it was
     * registered as, so the sale is only ever edited in one place.
     *
     * @var list<string>
     */
    private const MIRRORED_TO_SALE = ['fecha', 'producto', 'cantidad', 'unit_price'];

    public function updateLine(BudgetLineRequest $request, BudgetLine $line): JsonResponse
    {
        $data = $request->safe()->except('section');

        $line->update($data);

        if ($line->section === BudgetLine::SECTION_CLIENT) {
            $mirrored = array_intersect_key($data, array_flip(self::MIRRORED_TO_SALE));

            if ($mirrored !== []) {
                $line->saleLine()->update($mirrored);
            }
        }

        return response()->json([
            'line' => $line->fresh(),
            'summary' => $line->period->summary(),
        ]);
    }

    /**
     * Register a "relación con clientes" row as a sale: creates the matching
     * "venta" row, linked back so the amount is counted once and the two stay
     * in sync.
     */
    public function linkLineToSale(BudgetLine $line): JsonResponse
    {
        if ($line->section !== BudgetLine::SECTION_CLIENT) {
            throw ValidationException::withMessages([
                'line' => __('Solo las filas de relación con clientes se pueden registrar en ventas.'),
            ]);
        }

        if ($line->saleLine()->exists()) {
            throw ValidationException::withMessages([
                'line' => __('Esta fila ya está registrada en ventas.'),
            ]);
        }

        $period = $line->period;

        $sale = $period->lines()->create([
            'section' => BudgetLine::SECTION_SALE,
            'fecha' => $line->fecha,
            'producto' => $line->producto,
            'cantidad' => $line->cantidad,
            'unit_price' => $line->unit_price,
            'position' => ($period->lines()->max('position') ?? 0) + 1,
            'linked_line_id' => $line->id,
        ]);

        return response()->json([
            'line' => $sale,
            'summary' => $period->summary(),
        ], 201);
    }

    public function destroyLine(BudgetLine $line): JsonResponse
    {
        $period = $line->period;
        $line->delete();

        return response()->json([
            'summary' => $period->summary(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function distinctValues(string $column): array
    {
        return BudgetLine::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->map(fn (mixed $value): string => (string) $value)
            ->values()
            ->all();
    }
}
