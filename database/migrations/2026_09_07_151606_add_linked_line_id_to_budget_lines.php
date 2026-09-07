<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a "venta" row back to the "cliente" (relación con clientes) row it was
 * registered from, so a client sale is typed once and counted once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->foreignId('linked_line_id')
                ->nullable()
                ->after('position')
                ->constrained('budget_lines')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('linked_line_id');
        });
    }
};
