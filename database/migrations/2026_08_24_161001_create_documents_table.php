<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->enum('operation_type', ['venta', 'compra']);
            $table->enum('document_type', ['presupuesto', 'factura']);
            $table->enum('status', ['pendiente', 'parcial', 'pagado', 'convertido', 'anulado'])->default('pendiente');
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->foreignId('converted_from_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->date('issue_date');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('exchange_rate', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['operation_type', 'document_type']);
            $table->index('issue_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
