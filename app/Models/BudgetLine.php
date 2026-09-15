<?php

namespace App\Models;

use Database\Factories\BudgetLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Una fila de cualquiera de las hojas de /presupuesto. `section` dice a qué hoja
 * pertenece y, con ella, qué columnas de esta tabla ancha tienen sentido.
 *
 * @property int $id
 * @property int|null $budget_period_id
 * @property string $section
 * @property string|null $tipo
 * @property Carbon|null $fecha
 * @property int|null $contact_line_id
 * @property int|null $invoice_line_id
 * @property string|null $party_name
 * @property string|null $telefono
 * @property string|null $categoria
 * @property string|null $producto
 * @property string|null $descripcion
 * @property float|null $cantidad
 * @property float|null $unit_price
 * @property float|null $costo
 * @property float|null $monto_compra
 * @property float|null $monto_venta
 * @property float|null $monto
 * @property string|null $payment_status
 * @property string|null $payment_method
 * @property string|null $invoice_number
 * @property float|null $gastos_personales
 * @property float|null $perdidas_mercancia
 * @property string|null $notas
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read float $precio_total
 * @property-read float $utilidad
 * @property-read float $total_utilidad
 * @property-read float $abonado
 * @property-read float $restante
 * @property-read Collection<int, BudgetLinePayment> $payments
 * @property-read BudgetPeriod|null $period
 * @property-read BudgetLine|null $contact
 * @property-read BudgetLine|null $invoice
 * @property-read Collection<int, BudgetLine> $invoiceLines
 */
#[Fillable([
    'budget_period_id',
    'section',
    'tipo',
    'fecha',
    'contact_line_id',
    'invoice_line_id',
    'party_name',
    'telefono',
    'categoria',
    'producto',
    'descripcion',
    'cantidad',
    'unit_price',
    'costo',
    'monto_compra',
    'monto_venta',
    'monto',
    'payment_status',
    'payment_method',
    'invoice_number',
    'gastos_personales',
    'perdidas_mercancia',
    'notas',
    'position',
])]
class BudgetLine extends Model
{
    /** @use HasFactory<BudgetLineFactory> */
    use HasFactory;

    /** Directorio: proveedores y clientes, compartidos entre períodos. */
    public const SECTION_CONTACT = 'contacto';

    public const SECTION_PURCHASE = 'compra';

    public const SECTION_SALE = 'venta';

    public const SECTION_EXPENSE = 'gasto';

    /** Ganancias y pérdidas del mes. */
    public const SECTION_RESULT = 'resultado';

    public const SECTION_INVOICE = 'factura';

    /**
     * @var list<string>
     */
    public const SECTIONS = [
        self::SECTION_CONTACT,
        self::SECTION_PURCHASE,
        self::SECTION_SALE,
        self::SECTION_EXPENSE,
        self::SECTION_RESULT,
        self::SECTION_INVOICE,
    ];

    /**
     * Secciones cuyas filas admiten abonos.
     *
     * @var list<string>
     */
    public const PAYABLE_SECTIONS = [
        self::SECTION_PURCHASE,
        self::SECTION_SALE,
    ];

    public const TYPE_PROVIDER = 'proveedor';

    public const TYPE_CLIENT = 'cliente';

    /**
     * @var list<string>
     */
    protected $appends = ['precio_total', 'utilidad', 'total_utilidad', 'abonado', 'restante'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'cantidad' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'costo' => 'decimal:2',
            'monto_compra' => 'decimal:2',
            'monto_venta' => 'decimal:2',
            'monto' => 'decimal:2',
            'gastos_personales' => 'decimal:2',
            'perdidas_mercancia' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeSection(Builder $query, string $section): void
    {
        $query->where('section', $section);
    }

    /**
     * Orden natural de una hoja: por fecha y, a igualdad, por el orden de carga.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSheetOrder(Builder $query): void
    {
        $query->orderBy('fecha')->orderBy('position')->orderBy('id');
    }

    /**
     * cantidad × precio unitario.
     *
     * @return Attribute<float, never>
     */
    protected function precioTotal(): Attribute
    {
        return Attribute::get(fn (): float => round((float) $this->cantidad * (float) $this->unit_price, 2));
    }

    /**
     * Venta − compra − costo, para la hoja de ganancias y pérdidas.
     *
     * @return Attribute<float, never>
     */
    protected function utilidad(): Attribute
    {
        return Attribute::get(fn (): float => round(
            (float) $this->monto_venta
            - (float) $this->monto_compra
            - (float) $this->costo,
            2
        ));
    }

    /**
     * Utilidad − gastos personales − pérdidas de mercancía: el total con el que
     * cierra cada fila de ganancias y pérdidas.
     *
     * @return Attribute<float, never>
     */
    protected function totalUtilidad(): Attribute
    {
        return Attribute::get(fn (): float => round(
            $this->utilidad
            - (float) $this->gastos_personales
            - (float) $this->perdidas_mercancia,
            2
        ));
    }

    /**
     * Suma de los abonos, en la moneda del período.
     *
     * Se apoya en la relación cuando ya está cargada y, si no, evita consultar
     * las secciones que por definición no llevan abonos: el accesor se serializa
     * en cada fila, así que una consulta suelta acá es un N+1 por hoja.
     *
     * @return Attribute<float, never>
     */
    protected function abonado(): Attribute
    {
        return Attribute::get(function (): float {
            if ($this->relationLoaded('payments')) {
                return round((float) $this->payments->sum('amount'), 2);
            }

            if (! in_array($this->section, self::PAYABLE_SECTIONS, true)) {
                return 0.0;
            }

            return round((float) $this->payments()->sum('amount'), 2);
        });
    }

    /**
     * Lo que falta por pagar o cobrar: precio total − abonado.
     *
     * @return Attribute<float, never>
     */
    protected function restante(): Attribute
    {
        return Attribute::get(fn (): float => round($this->precio_total - $this->abonado, 2));
    }

    /**
     * @return BelongsTo<BudgetPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(BudgetPeriod::class, 'budget_period_id');
    }

    /**
     * Proveedor o cliente del Directorio al que apunta la fila.
     *
     * @return BelongsTo<BudgetLine, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class, 'contact_line_id');
    }

    /**
     * En una compra o venta: la factura que la agrupa.
     *
     * @return BelongsTo<BudgetLine, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class, 'invoice_line_id');
    }

    /**
     * En una factura: las compras o ventas que agrupa.
     *
     * @return HasMany<BudgetLine, $this>
     */
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(BudgetLine::class, 'invoice_line_id');
    }

    /**
     * @return HasMany<BudgetLinePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(BudgetLinePayment::class, 'budget_line_id');
    }

    /**
     * Recalcula el estado de pago a partir de los abonos y lo guarda.
     *
     * Solo lo llama el observador de abonos, así que una fila sin abonos conserva
     * lo que el usuario eligió a mano en el select: así se registra una compra
     * pagada de una vez sin detallarla. Al borrar el último abono vuelve a
     * "Pendiente".
     */
    public function syncPaymentStatus(): void
    {
        $abonado = (float) $this->payments()->sum('amount');
        $total = $this->precio_total;

        $status = match (true) {
            $abonado <= 0 => 'Pendiente',
            $total > 0 && $abonado >= $total => 'Pagado',
            default => 'Abonado',
        };

        if ($status !== $this->payment_status) {
            $this->forceFill(['payment_status' => $status])->save();
        }
    }

    /**
     * La factura tal como la muestra su hoja: los movimientos que agrupa y los
     * totales que salen de ellos.
     *
     * @return array<string, mixed>
     */
    public function toInvoiceArray(): array
    {
        $items = $this->relationLoaded('invoiceLines')
            ? $this->invoiceLines
            : $this->invoiceLines()->with('payments')->sheetOrder()->get();

        $total = round((float) $items->sum('precio_total'), 2);
        $abonado = round((float) $items->sum('abonado'), 2);
        $restante = round($total - $abonado, 2);

        return [
            ...$this->toArray(),
            'items' => $items->values()->all(),
            'abonos' => $this->groupedPayments($items),
            'totales' => [
                'movimientos' => $items->count(),
                'cantidad' => round((float) $items->sum('cantidad'), 2),
                'total' => $total,
                'abonado' => $abonado,
                'restante' => $restante,
                'estado' => match (true) {
                    $items->isEmpty() => 'Sin movimientos',
                    $abonado <= 0 => 'Pendiente',
                    $restante <= 0.001 => 'Pagada',
                    default => 'Abonada',
                },
            ],
        ];
    }

    /**
     * Los abonos de la factura como se hicieron de verdad.
     *
     * Un pago contra la factura se guarda repartido entre sus movimientos, pero
     * para el contacto fue uno solo: las filas que comparten `batch_id` se
     * suman y se muestran como un abono. Los cargados de a uno van sueltos.
     *
     * @param  Collection<int, BudgetLine>  $items
     * @return list<array<string, mixed>>
     */
    private function groupedPayments(Collection $items): array
    {
        return $items
            ->flatMap(fn (BudgetLine $item): iterable => $item->payments)
            ->groupBy(fn (BudgetLinePayment $payment): string => $payment->batch_id ?? 'x'.$payment->id)
            ->map(function (SupportCollection $grupo): array {
                $primero = $grupo->first();
                $bolivares = round((float) $grupo->sum('amount_bs'), 2);

                return [
                    'id' => $primero->batch_id ?? (string) $primero->id,
                    'fecha' => $primero->fecha?->toDateString(),
                    'method' => $primero->method,
                    'notes' => $primero->notes,
                    'amount' => round((float) $grupo->sum('amount'), 2),
                    'amount_bs' => $bolivares > 0 ? $bolivares : null,
                    'exchange_rate' => $primero->exchange_rate,
                    'movimientos' => $grupo->count(),
                ];
            })
            ->sortBy('fecha')
            ->values()
            ->all();
    }

    /**
     * Etiqueta con la que la fila aparece en los selects de abonos y facturas.
     */
    public function sheetLabel(): string
    {
        $parts = array_filter([
            $this->fecha?->format('d/m/Y'),
            $this->party_name,
            $this->producto,
            number_format($this->precio_total, 2),
        ], fn (?string $part): bool => $part !== null && $part !== '');

        return implode(' · ', $parts);
    }
}
