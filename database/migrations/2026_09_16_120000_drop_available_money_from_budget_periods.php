<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El período ya no arranca con un presupuesto asignado: se registra lo que pasa
 * y listo. La columna nunca entró en ningún cálculo, así que se va en lugar de
 * quedar como un campo muerto que confunda más adelante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_periods', function (Blueprint $table) {
            $table->dropColumn('available_money');
        });
    }

    public function down(): void
    {
        Schema::table('budget_periods', function (Blueprint $table) {
            $table->decimal('available_money', 14, 2)->default(0);
        });
    }
};
