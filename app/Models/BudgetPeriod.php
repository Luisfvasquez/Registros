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
     *     total_compras: float,
     *     total_ventas: float,
     *     total_clientes: float,
     *     ingresos_totales: float,
     *     cuentas_por_pagar: float,
     *     cuentas_por_cobrar: float,
     *     ganancia_bruta: float,
     *     ganancia_registrada: float,
     *     gastos_personales: float,
     *     perdidas_mercancia: float,
     *     inversiones: float,
     *     utilidad_neta: float,
     *     estado: string
     * }
     */
    public function summary(): array
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get();

        $inSection = fn (string $section) => $lines->where('section', $section);

        $isPaid = fn (BudgetLine $line): bool => strtolower(trim((string) $line->payment_status)) === 'pagado';

        $totalCompras = (float) $inSection(BudgetLine::SECTION_PURCHASE)->sum('precio_total');
        $totalVentas = (float) $inSection(BudgetLine::SECTION_SALE)->sum('precio_total');
        $totalClientes = (float) $inSection(BudgetLine::SECTION_CLIENT)->sum('precio_total');
        $ingresosTotales = $totalVentas + $totalClientes;

        $cuentasPorPagar = (float) $inSection(BudgetLine::SECTION_PURCHASE)
            ->reject($isPaid)
            ->sum('precio_total');
        $cuentasPorCobrar = (float) $inSection(BudgetLine::SECTION_CLIENT)
            ->reject($isPaid)
            ->sum('precio_total');

        $resultado = $inSection(BudgetLine::SECTION_RESULT);
        $gananciaRegistrada = (float) $resultado->sum('ganancia');
        $gastosPersonales = (float) $resultado->sum('gastos_personales');
        $perdidasMercancia = (float) $resultado->sum('perdidas_mercancia');
        $inversiones = (float) $resultado->sum('inversiones');
        $utilidadNeta = (float) $resultado->sum('total_utilidad');

        return [
            'total_compras' => round($totalCompras, 2),
            'total_ventas' => round($totalVentas, 2),
            'total_clientes' => round($totalClientes, 2),
            'ingresos_totales' => round($ingresosTotales, 2),
            'cuentas_por_pagar' => round($cuentasPorPagar, 2),
            'cuentas_por_cobrar' => round($cuentasPorCobrar, 2),
            'ganancia_bruta' => round($ingresosTotales - $totalCompras, 2),
            'ganancia_registrada' => round($gananciaRegistrada, 2),
            'gastos_personales' => round($gastosPersonales, 2),
            'perdidas_mercancia' => round($perdidasMercancia, 2),
            'inversiones' => round($inversiones, 2),
            'utilidad_neta' => round($utilidadNeta, 2),
            'estado' => $utilidadNeta >= 0 ? 'ganancia' : 'perdida',
        ];
    }
}
