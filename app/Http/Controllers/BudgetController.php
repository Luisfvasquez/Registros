<?php

namespace App\Http\Controllers;

use App\Http\Requests\BudgetLineRequest;
use App\Http\Requests\BudgetPeriodRequest;
use App\Models\BudgetLine;
use App\Models\BudgetPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function storeLine(BudgetLineRequest $request, BudgetPeriod $period): JsonResponse
    {
        $line = $period->lines()->create([
            'section' => $request->string('section')->toString(),
            'position' => $request->integer('position', $period->lines()->max('position') + 1),
        ]);

        return response()->json([
            'line' => $line,
            'summary' => $period->summary(),
        ], 201);
    }

    public function updateLine(BudgetLineRequest $request, BudgetLine $line): JsonResponse
    {
        $line->update($request->safe()->except('section'));

        return response()->json([
            'line' => $line->fresh(),
            'summary' => $line->period->summary(),
        ]);
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
