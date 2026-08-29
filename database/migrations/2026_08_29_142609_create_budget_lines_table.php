<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_period_id')->constrained()->cascadeOnDelete();
            // ingreso | presupuesto | gasto_fijo | ahorro | deuda
            $table->string('section');
            $table->string('detail')->default('');
            $table->string('category')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('ideal_percent', 5, 2)->nullable(); // % ideal (presupuesto por fecha)
            $table->decimal('planned', 14, 2)->default(0); // proyeccion (ingreso) / presupuesto (resto)
            $table->decimal('actual', 14, 2)->default(0);  // ingreso real (ingreso) / gastado (resto)
            $table->boolean('is_unexpected')->default(false); // ganancias de trabajos no esperados
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['budget_period_id', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_lines');
    }
};
