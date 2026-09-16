<?php

use App\Models\BudgetLine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hay mercancía que se vende en bolívares y no en dólares. La fila guarda los
 * dos precios y la tasa con la que se convirtieron, así el número que se tipeó
 * queda tal cual y el otro se puede reconstruir.
 *
 * Los saldos, abonos y reportes siguen calculándose sobre `unit_price`, que es
 * la moneda del período: los bolívares son un espejo, no una segunda verdad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->decimal('unit_price_bs', 14, 2)->nullable()->after('unit_price');
            // Bs por unidad de la moneda del período, como en exchange_rates.rate.
            $table->decimal('exchange_rate', 12, 4)->nullable()->after('unit_price_bs');
        });

        $this->backfill();
    }

    /**
     * Completa las compras y ventas que ya existían con la tasa activa, para que
     * no queden con la columna de bolívares vacía.
     */
    private function backfill(): void
    {
        $rate = DB::table('exchange_rates')->where('is_active', true)->value('rate');

        if (! $rate) {
            return;
        }

        DB::table('budget_lines')
            ->whereIn('section', [BudgetLine::SECTION_PURCHASE, BudgetLine::SECTION_SALE])
            ->whereNotNull('unit_price')
            ->update([
                'exchange_rate' => $rate,
                'unit_price_bs' => DB::raw('ROUND(unit_price * '.(float) $rate.', 2)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->dropColumn(['unit_price_bs', 'exchange_rate']);
        });
    }
};
