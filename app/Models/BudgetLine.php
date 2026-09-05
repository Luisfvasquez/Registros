<?php

namespace App\Models;

use Database\Factories\BudgetLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $budget_period_id
 * @property string $section
 * @property Carbon|null $fecha
 * @property string|null $party_name
 * @property string|null $producto
 * @property float|null $cantidad
 * @property float|null $unit_price
 * @property string|null $payment_status
 * @property string|null $payment_method
 * @property float|null $ganancia
 * @property float|null $gastos_personales
 * @property float|null $perdidas_mercancia
 * @property float|null $inversiones
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read float $precio_total
 * @property-read float $total_utilidad
 * @property-read BudgetPeriod $period
 */
#[Fillable([
    'budget_period_id',
    'section',
    'fecha',
    'party_name',
    'producto',
    'cantidad',
    'unit_price',
    'payment_status',
    'payment_method',
    'ganancia',
    'gastos_personales',
    'perdidas_mercancia',
    'inversiones',
    'position',
])]
class BudgetLine extends Model
{
    /** @use HasFactory<BudgetLineFactory> */
    use HasFactory;

    public const SECTION_PURCHASE = 'compra';

    public const SECTION_SALE = 'venta';

    public const SECTION_CLIENT = 'cliente';

    public const SECTION_RESULT = 'resultado';

    /**
     * @var list<string>
     */
    public const SECTIONS = [
        self::SECTION_PURCHASE,
        self::SECTION_SALE,
        self::SECTION_CLIENT,
        self::SECTION_RESULT,
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['precio_total', 'total_utilidad'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'cantidad' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'ganancia' => 'decimal:2',
            'gastos_personales' => 'decimal:2',
            'perdidas_mercancia' => 'decimal:2',
            'inversiones' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    /**
     * cantidad × precio unitario, for compra / venta / cliente rows.
     *
     * @return Attribute<float, never>
     */
    protected function precioTotal(): Attribute
    {
        return Attribute::get(fn (): float => round((float) $this->cantidad * (float) $this->unit_price, 2));
    }

    /**
     * ganancia − gastos personales − pérdidas por mercancía − inversiones,
     * for the monthly resultado sheet.
     *
     * @return Attribute<float, never>
     */
    protected function totalUtilidad(): Attribute
    {
        return Attribute::get(fn (): float => round(
            (float) $this->ganancia
            - (float) $this->gastos_personales
            - (float) $this->perdidas_mercancia
            - (float) $this->inversiones,
            2
        ));
    }

    /**
     * @return BelongsTo<BudgetPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(BudgetPeriod::class, 'budget_period_id');
    }
}
