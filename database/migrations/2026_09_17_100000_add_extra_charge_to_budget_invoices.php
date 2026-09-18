<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un cargo extra de la factura: el flete, el envío o lo que se le sume al
 * contacto sin ser una compra ni una venta.
 *
 * Solo lo usa la sección `factura`: se suma al total que sale de los
 * movimientos y se abona como una parte más de la factura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->decimal('monto_adicional', 14, 2)->nullable()->after('monto');
        });
    }

    public function down(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->dropColumn('monto_adicional');
        });
    }
};
