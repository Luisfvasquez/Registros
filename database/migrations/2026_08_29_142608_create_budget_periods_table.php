<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month'); // 1-12
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('abierto'); // abierto | cerrado
            $table->decimal('available_money', 14, 2)->default(0); // dinero disponible inicial
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_periods');
    }
};
