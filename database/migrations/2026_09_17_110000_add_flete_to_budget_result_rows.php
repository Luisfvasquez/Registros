<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El flete de una operación de ganancias y pérdidas: lo que costó traer o
 * mandar la mercancía. Va junto al costo porque baja igual la utilidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->decimal('flete', 14, 2)->nullable()->after('costo');
        });
    }

    public function down(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->dropColumn('flete');
        });
    }
};
