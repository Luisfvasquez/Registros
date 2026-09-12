<?php

namespace App\Models;

use App\Observers\BudgetLinePaymentObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An abono against a purchase, sale or client row of the budget sheet.
 *
 * @property int $id
 * @property int $budget_line_id
 * @property Carbon $fecha
 * @property string|null $method
 * @property float|null $amount_bs
 * @property float|null $exchange_rate
 * @property float $amount
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BudgetLine $line
 */
#[ObservedBy(BudgetLinePaymentObserver::class)]
#[Fillable(['budget_line_id', 'fecha', 'method', 'amount_bs', 'exchange_rate', 'amount', 'notes'])]
class BudgetLinePayment extends Model
{
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'amount_bs' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<BudgetLine, $this>
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class, 'budget_line_id');
    }
}
