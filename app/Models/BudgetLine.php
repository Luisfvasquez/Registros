<?php

namespace App\Models;

use Database\Factories\BudgetLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $budget_period_id
 * @property string $section
 * @property string $detail
 * @property string|null $category
 * @property string|null $payment_method
 * @property float|null $ideal_percent
 * @property float $planned
 * @property float $actual
 * @property bool $is_unexpected
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BudgetPeriod $period
 */
#[Fillable([
    'budget_period_id',
    'section',
    'detail',
    'category',
    'payment_method',
    'ideal_percent',
    'planned',
    'actual',
    'is_unexpected',
    'position',
])]
class BudgetLine extends Model
{
    /** @use HasFactory<BudgetLineFactory> */
    use HasFactory;

    public const SECTION_INCOME = 'ingreso';

    public const SECTION_BUDGET = 'presupuesto';

    public const SECTION_FIXED_EXPENSE = 'gasto_fijo';

    public const SECTION_SAVING = 'ahorro';

    public const SECTION_DEBT = 'deuda';

    /**
     * @var list<string>
     */
    public const SECTIONS = [
        self::SECTION_INCOME,
        self::SECTION_BUDGET,
        self::SECTION_FIXED_EXPENSE,
        self::SECTION_SAVING,
        self::SECTION_DEBT,
    ];

    protected function casts(): array
    {
        return [
            'ideal_percent' => 'decimal:2',
            'planned' => 'decimal:2',
            'actual' => 'decimal:2',
            'is_unexpected' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BudgetPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(BudgetPeriod::class, 'budget_period_id');
    }
}
