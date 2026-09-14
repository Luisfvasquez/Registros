<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Da vuelta la relación entre facturas y movimientos: antes cada factura
 * apuntaba a una sola compra o venta; ahora son las compras y ventas las que
 * apuntan a su factura, así una factura agrupa varias.
 *
 * De paso reacomoda la hoja de ganancias y pérdidas, que pasa a llevar el monto
 * de compra y el de venta de cada operación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            // En una compra o venta: la factura que la agrupa.
            $table->foreignId('invoice_line_id')
                ->nullable()
                ->after('contact_line_id')
                ->constrained('budget_lines')
                ->nullOnDelete();

            // Ganancias y pérdidas: compra y venta de la operación. El costo va
            // en la columna `costo`, que dejó de usarse en la hoja de ventas.
            $table->decimal('monto_compra', 14, 2)->nullable()->after('costo');
            $table->decimal('monto_venta', 14, 2)->nullable()->after('monto_compra');
        });

        Schema::table('budget_lines', function (Blueprint $table) {
            // `ganancia` la reemplaza la utilidad calculada (venta − compra − costo).
            $table->dropColumn('ganancia');
        });

        Schema::table('budget_lines', function (Blueprint $table) {
            // El vínculo factura → origen ya no existe: ahora va al revés.
            $table->dropConstrainedForeignId('linked_line_id');
        });
    }

    public function down(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->foreignId('linked_line_id')
                ->nullable()
                ->constrained('budget_lines')
                ->nullOnDelete();
            $table->decimal('ganancia', 14, 2)->nullable();
        });

        Schema::table('budget_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_line_id');
            $table->dropColumn(['monto_compra', 'monto_venta']);
        });
    }
};
