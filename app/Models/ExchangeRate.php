<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $currency_from
 * @property string $currency_to
 * @property float $rate
 * @property Carbon $date
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['currency_from', 'currency_to', 'rate', 'date', 'is_active'])]
class ExchangeRate extends Model
{
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'date' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
