<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rounds the budget sheet out with the parts the spreadsheet had and the module
 * was missing: partial payments in Bs/USD against a purchase, sale or client row,
 * a "gastos" section with its own amount and category, and the invoice number a
 * purchase or sale was billed under.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_line_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_line_id')->constrained()->cascadeOnDelete();
            $table->date('fecha');
            $table->string('method')->nullable();
            // What was handed over in bolivares and the rate used that day.
            $table->decimal('amount_bs', 14, 2)->nullable();
            $table->decimal('exchange_rate', 12, 4)->nullable();
            // Always the figure in the period's currency; balances derive from it.
            $table->decimal('amount', 14, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('fecha');
        });

        Schema::table('budget_lines', function (Blueprint $table) {
            $table->string('categoria')->nullable()->after('party_name');
            $table->decimal('monto', 14, 2)->nullable()->after('unit_price');
            $table->string('invoice_number')->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_line_payments');

        Schema::table('budget_lines', function (Blueprint $table) {
            $table->dropColumn(['categoria', 'monto', 'invoice_number']);
        });
    }
};
