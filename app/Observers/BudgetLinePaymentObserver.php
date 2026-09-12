<?php

namespace App\Observers;

use App\Models\BudgetLinePayment;
use Illuminate\Support\Facades\DB;

/**
 * Keeps an abono and the row it belongs to in sync: converts the bolivar amount
 * using the rate captured with the payment, then recalculates the row's estado
 * de pago from everything abonado so far.
 */
class BudgetLinePaymentObserver
{
    /**
     * An abono entered in bolivares stores what was handed over plus the rate of
     * that day; `amount` always ends up holding the figure in the period's
     * currency, which is what balances and the report are built on.
     */
    public function saving(BudgetLinePayment $payment): void
    {
        if ($payment->amount_bs !== null && (float) $payment->exchange_rate > 0) {
            $payment->amount = round((float) $payment->amount_bs / (float) $payment->exchange_rate, 2);
        }
    }

    public function saved(BudgetLinePayment $payment): void
    {
        $this->syncLine($payment);
    }

    public function deleted(BudgetLinePayment $payment): void
    {
        $this->syncLine($payment);
    }

    private function syncLine(BudgetLinePayment $payment): void
    {
        DB::transaction(fn () => $payment->line->syncPaymentStatus());
    }
}
