<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns the personal-budget line into a business ledger line: purchases, sales,
 * client accounts and the monthly profit / expense / loss sheet.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The old columns change meaning entirely; wipe the sample rows.
        DB::table('budget_lines')->delete();

        Schema::table('budget_lines', function (Blueprint $table) {
            $table->dropColumn([
                'detail',
                'category',
                'ideal_percent',
                'planned',
                'actual',
                'is_unexpected',
            ]);
        });

        Schema::table('budget_lines', function (Blueprint $table) {
            // compra | venta | cliente | resultado
            $table->date('fecha')->nullable()->after('section');
            $table->string('party_name')->nullable()->after('fecha'); // proveedor / cliente
            $table->string('producto')->nullable()->after('party_name');
            $table->decimal('cantidad', 14, 2)->nullable()->after('producto');
            $table->decimal('unit_price', 14, 2)->nullable()->after('cantidad'); // precio unitario
            $table->string('payment_status')->nullable()->after('unit_price'); // estado de pago
            // payment_method (metodo de pago) already exists.
            $table->decimal('ganancia', 14, 2)->nullable()->after('payment_method');
            $table->decimal('gastos_personales', 14, 2)->nullable()->after('ganancia');
            $table->decimal('perdidas_mercancia', 14, 2)->nullable()->after('gastos_personales');
            $table->decimal('inversiones', 14, 2)->nullable()->after('perdidas_mercancia');
        });
    }

    public function down(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->dropColumn([
                'fecha',
                'party_name',
                'producto',
                'cantidad',
                'unit_price',
                'payment_status',
                'ganancia',
                'gastos_personales',
                'perdidas_mercancia',
                'inversiones',
            ]);
        });

        Schema::table('budget_lines', function (Blueprint $table) {
            $table->string('detail')->default('')->after('section');
            $table->string('category')->nullable()->after('detail');
            $table->decimal('ideal_percent', 5, 2)->nullable()->after('category');
            $table->decimal('planned', 14, 2)->default(0)->after('ideal_percent');
            $table->decimal('actual', 14, 2)->default(0)->after('planned');
            $table->boolean('is_unexpected')->default(false)->after('actual');
        });
    }
};
