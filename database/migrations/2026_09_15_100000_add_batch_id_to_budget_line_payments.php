<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un abono contra una factura se guarda repartido entre sus movimientos, para
 * que cada compra o venta lleve su propio saldo. `batch_id` marca las filas que
 * salieron de ese único pago, así el comprobante lo muestra como uno solo y no
 * como tres pagos sueltos.
 *
 * Null en los abonos cargados de a uno desde las hojas de Abonos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_line_payments', function (Blueprint $table) {
            $table->uuid('batch_id')->nullable()->after('budget_line_id');

            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('budget_line_payments', function (Blueprint $table) {
            $table->dropIndex(['batch_id']);
            $table->dropColumn('batch_id');
        });
    }
};
