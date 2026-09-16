<?php

namespace App\Observers;

use App\Models\BudgetLine;
use App\Models\ExchangeRate;

/**
 * Mantiene en pie los dos precios de una compra o venta.
 *
 * El admin escribe uno solo — hay mercancía que se vende en bolívares y otra en
 * dólares — y acá se completa el otro. La tasa usada queda guardada en la fila:
 * así la conversión se puede rehacer mañana aunque el dólar haya cambiado, y
 * editar un precio viejo no lo reconvierte a la tasa de hoy.
 */
class BudgetLineObserver
{
    public function saving(BudgetLine $line): void
    {
        if (! in_array($line->section, BudgetLine::PAYABLE_SECTIONS, true)) {
            return;
        }

        // La fila conserva su propia tasa; recién si no tiene, toma la del día.
        $rate = (float) ($line->exchange_rate ?: ExchangeRate::activeRate());

        if ($rate <= 0) {
            return;
        }

        // Cambiar la tasa recalcula los bolívares: lo que se pactó en la moneda
        // del período es lo que manda.
        if ($line->isDirty('exchange_rate') && $line->unit_price !== null) {
            $line->exchange_rate = $rate;
            $line->unit_price_bs = round((float) $line->unit_price * $rate, 2);

            return;
        }

        if ($line->isDirty('unit_price_bs') && $line->unit_price_bs !== null) {
            $line->exchange_rate = $rate;
            $line->unit_price = round((float) $line->unit_price_bs / $rate, 2);

            return;
        }

        if ($line->isDirty('unit_price') && $line->unit_price !== null) {
            $line->exchange_rate = $rate;
            $line->unit_price_bs = round((float) $line->unit_price * $rate, 2);
        }
    }
}
