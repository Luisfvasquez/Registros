<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuilds the /presupuesto sheet as the spreadsheet the client works with:
 * directorio, compras, ventas, abonos, cuentas por proveedor/cliente, ventas del
 * día, ganancias y pérdidas, gastos y facturas.
 *
 * Las dos tablas se recrean desde cero (el módulo anterior guardaba las columnas
 * con otro significado), así que esta migración borra las filas existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('budget_line_payments');
        Schema::dropIfExists('budget_lines');

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            // Null solo en el Directorio: proveedores y clientes se comparten entre períodos.
            $table->foreignId('budget_period_id')->nullable()->constrained()->cascadeOnDelete();
            // contacto | compra | venta | gasto | resultado | factura
            $table->string('section');
            // contacto: proveedor | cliente · factura: venta | compra
            $table->string('tipo')->nullable();
            $table->date('fecha')->nullable();
            // Proveedor o cliente elegido en el Directorio.
            $table->foreignId('contact_line_id')->nullable()->constrained('budget_lines')->nullOnDelete();
            // Copia del nombre del contacto: la fila sigue siendo legible si se borra del Directorio.
            $table->string('party_name')->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('categoria')->nullable();
            $table->string('producto')->nullable();
            $table->string('descripcion')->nullable();
            $table->decimal('cantidad', 14, 2)->nullable();
            $table->decimal('unit_price', 14, 2)->nullable();
            // Costo de la mercancía vendida, solo en ventas.
            $table->decimal('costo', 14, 2)->nullable();
            // Monto del gasto, solo en la sección gasto.
            $table->decimal('monto', 14, 2)->nullable();
            $table->string('payment_status')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('invoice_number')->nullable();
            $table->decimal('ganancia', 14, 2)->nullable();
            $table->decimal('gastos_personales', 14, 2)->nullable();
            $table->decimal('perdidas_mercancia', 14, 2)->nullable();
            // En una factura: la compra o venta de la que salió.
            $table->foreignId('linked_line_id')->nullable()->constrained('budget_lines')->nullOnDelete();
            $table->text('notas')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['budget_period_id', 'section']);
            $table->index(['section', 'fecha']);
        });

        Schema::create('budget_line_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_line_id')->constrained()->cascadeOnDelete();
            $table->date('fecha');
            $table->string('method')->nullable();
            // Lo que se entregó en bolívares y la tasa de ese día.
            $table->decimal('amount_bs', 14, 2)->nullable();
            $table->decimal('exchange_rate', 12, 4)->nullable();
            // Siempre la cifra en la moneda del período: los saldos salen de acá.
            $table->decimal('amount', 14, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_line_payments');
        Schema::dropIfExists('budget_lines');
    }
};
