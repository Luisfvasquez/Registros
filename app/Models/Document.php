<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $number
 * @property string $operation_type
 * @property string $document_type
 * @property string $status
 * @property int $contact_id
 * @property int|null $converted_from_id
 * @property Carbon $issue_date
 * @property float $subtotal
 * @property float $tax_total
 * @property float $total
 * @property float|null $exchange_rate
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['number', 'operation_type', 'document_type', 'status', 'contact_id', 'converted_from_id', 'issue_date', 'subtotal', 'tax_total', 'total', 'exchange_rate', 'notes'])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'exchange_rate' => 'decimal:2',
        ];
    }

    public function totalInBs(): ?float
    {
        return $this->exchange_rate ? round((float) $this->total * (float) $this->exchange_rate, 2) : null;
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return HasMany<DocumentItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DocumentItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * The budget (presupuesto) this invoice was converted from.
     *
     * @return BelongsTo<Document, $this>
     */
    public function convertedFrom(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'converted_from_id');
    }

    /**
     * The invoice this budget was converted into, if any.
     *
     * @return HasOne<Document, $this>
     */
    public function convertedTo(): HasOne
    {
        return $this->hasOne(Document::class, 'converted_from_id');
    }

    public function expensesTotal(): float
    {
        return (float) $this->expenses()->sum('amount');
    }

    public function paidTotal(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function balance(): float
    {
        return round((float) $this->total - $this->paidTotal(), 2);
    }

    /**
     * Recalculate and persist the payment status based on registered payments.
     * Only applies to invoices — budgets keep their own lifecycle status.
     */
    public function syncPaymentStatus(): void
    {
        if ($this->document_type !== 'factura') {
            return;
        }

        $paid = $this->paidTotal();

        $status = match (true) {
            $paid <= 0 => 'pendiente',
            $paid < (float) $this->total => 'parcial',
            default => 'pagado',
        };

        if ($status !== $this->status) {
            $this->update(['status' => $status]);
        }
    }
}
