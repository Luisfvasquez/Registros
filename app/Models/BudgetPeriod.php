<?php

namespace App\Models;

use Database\Factories\BudgetPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $year
 * @property int $month
 * @property string $currency
 * @property string $status
 * @property float $available_money
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, BudgetLine> $lines
 */
#[Fillable(['year', 'month', 'currency', 'status', 'available_money', 'notes'])]
class BudgetPeriod extends Model
{
    /** @use HasFactory<BudgetPeriodFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'available_money' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<BudgetLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }

    /**
     * Rolled-up figures for the monthly report. Everything derives from the
     * period's lines so the numbers stay consistent no matter how they're edited.
     *
     * @return array{
     *     ingreso_total: float,
     *     ingreso_proyectado: float,
     *     ganancias_inesperadas: float,
     *     gastos_totales: float,
     *     presupuesto_total: float,
     *     presupuesto_disponible: float,
     *     pagos_deuda: float,
     *     ahorros_inversiones: float,
     *     dinero_disponible: float,
     *     utilidad: float,
     *     estado_presupuesto: string
     * }
     */
    public function summary(): array
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get();

        $sumActual = fn (array $sections) => (float) $lines
            ->whereIn('section', $sections)
            ->sum('actual');

        $ingresoTotal = $sumActual([BudgetLine::SECTION_INCOME]);
        $ingresoProyectado = (float) $lines->where('section', BudgetLine::SECTION_INCOME)->sum('planned');
        $gananciasInesperadas = (float) $lines
            ->where('section', BudgetLine::SECTION_INCOME)
            ->where('is_unexpected', true)
            ->sum('actual');

        $gastosTotales = $sumActual([BudgetLine::SECTION_BUDGET, BudgetLine::SECTION_FIXED_EXPENSE]);
        $presupuestoTotal = (float) $lines
            ->whereIn('section', [BudgetLine::SECTION_BUDGET, BudgetLine::SECTION_FIXED_EXPENSE])
            ->sum('planned');

        $pagosDeuda = $sumActual([BudgetLine::SECTION_DEBT]);
        $ahorrosInversiones = $sumActual([BudgetLine::SECTION_SAVING]);

        $dineroDisponible = (float) $this->available_money
            + $ingresoTotal
            - $gastosTotales
            - $pagosDeuda
            - $ahorrosInversiones;

        return [
            'ingreso_total' => round($ingresoTotal, 2),
            'ingreso_proyectado' => round($ingresoProyectado, 2),
            'ganancias_inesperadas' => round($gananciasInesperadas, 2),
            'gastos_totales' => round($gastosTotales, 2),
            'presupuesto_total' => round($presupuestoTotal, 2),
            'presupuesto_disponible' => round($presupuestoTotal - $gastosTotales, 2),
            'pagos_deuda' => round($pagosDeuda, 2),
            'ahorros_inversiones' => round($ahorrosInversiones, 2),
            'dinero_disponible' => round($dineroDisponible, 2),
            'utilidad' => round($ingresoTotal - $gastosTotales - $pagosDeuda, 2),
            'estado_presupuesto' => $gastosTotales > $presupuestoTotal ? 'excedido' : 'dentro',
        ];
    }
}
