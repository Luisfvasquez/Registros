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
 * Un mes de trabajo. Cada período tiene sus propias hojas (compras, ventas,
 * abonos, gastos…); solo el Directorio se comparte entre todos.
 *
 * @property int $id
 * @property int $year
 * @property int $month
 * @property string $currency
 * @property string $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, BudgetLine> $lines
 */
#[Fillable(['year', 'month', 'currency', 'status', 'notes'])]
class BudgetPeriod extends Model
{
    /** @use HasFactory<BudgetPeriodFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
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
     * Cifras del mes para el tablero. Todo sale de las filas del período, así que
     * los números no dependen de por dónde se hayan cargado.
     *
     * @return array{
     *     total_compras: float,
     *     total_ventas: float,
     *     ganancia_bruta: float,
     *     pagado_a_proveedores: float,
     *     cobrado_a_clientes: float,
     *     cuentas_por_pagar: float,
     *     cuentas_por_cobrar: float,
     *     gastos: float,
     *     gastos_personales: float,
     *     perdidas_mercancia: float,
     *     resultado_utilidad: float,
     *     utilidad_neta: float,
     *     estado: string,
     *     compras: int,
     *     ventas: int
     * }
     */
    public function summary(): array
    {
        $lines = $this->relationLoaded('lines')
            ? $this->lines
            : $this->lines()->with('payments')->get();

        $inSection = fn (string $section) => $lines->where('section', $section);

        $isPaid = fn (BudgetLine $line): bool => strtolower(trim((string) $line->payment_status)) === 'pagado';

        $compras = $inSection(BudgetLine::SECTION_PURCHASE);
        $ventas = $inSection(BudgetLine::SECTION_SALE);

        $totalCompras = (float) $compras->sum('precio_total');
        $totalVentas = (float) $ventas->sum('precio_total');
        $gananciaBruta = $totalVentas - $totalCompras;

        // Lo que falta por pagar o cobrar descuenta los abonos ya registrados.
        $cuentasPorPagar = (float) $compras->reject($isPaid)->sum('restante');
        $cuentasPorCobrar = (float) $ventas->reject($isPaid)->sum('restante');

        $gastos = (float) $inSection(BudgetLine::SECTION_EXPENSE)->sum('monto');

        // La hoja de ganancias y pérdidas se carga a mano: de ella solo bajan la
        // utilidad neta los gastos personales y las pérdidas de mercancía, para
        // no contar dos veces las compras y ventas que ya están en sus hojas.
        $resultado = $inSection(BudgetLine::SECTION_RESULT);
        $gastosPersonales = (float) $resultado->sum('gastos_personales');
        $perdidasMercancia = (float) $resultado->sum('perdidas_mercancia');

        $utilidadNeta = $gananciaBruta - $gastos - $gastosPersonales - $perdidasMercancia;

        return [
            'total_compras' => round($totalCompras, 2),
            'total_ventas' => round($totalVentas, 2),
            'ganancia_bruta' => round($gananciaBruta, 2),
            'pagado_a_proveedores' => round((float) $compras->sum('abonado'), 2),
            'cobrado_a_clientes' => round((float) $ventas->sum('abonado'), 2),
            'cuentas_por_pagar' => round($cuentasPorPagar, 2),
            'cuentas_por_cobrar' => round($cuentasPorCobrar, 2),
            'gastos' => round($gastos, 2),
            'gastos_personales' => round($gastosPersonales, 2),
            'perdidas_mercancia' => round($perdidasMercancia, 2),
            'resultado_utilidad' => round((float) $resultado->sum('total_utilidad'), 2),
            'utilidad_neta' => round($utilidadNeta, 2),
            'estado' => $utilidadNeta >= 0 ? 'ganancia' : 'perdida',
            'compras' => $compras->count(),
            'ventas' => $ventas->count(),
        ];
    }

    /**
     * El período que abre /presupuesto por defecto. Si no hay ninguno marcado,
     * el más reciente.
     */
    public static function active(): ?self
    {
        $id = Setting::get(Setting::ACTIVE_PERIOD);

        return ($id ? static::find((int) $id) : null)
            ?? static::orderByDesc('year')->orderByDesc('month')->first();
    }

    /**
     * Primer y último día del período, como `Y-m-d` listos para whereBetween.
     *
     * @return array{0: string, 1: string}
     */
    public function dateRange(): array
    {
        $start = Carbon::create($this->year, $this->month, 1)->startOfMonth();

        // Illuminate\Support\Carbon es mutable: endOfMonth() correría $start.
        return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];
    }

    public function isActive(): bool
    {
        return (int) Setting::get(Setting::ACTIVE_PERIOD) === $this->id;
    }
}
