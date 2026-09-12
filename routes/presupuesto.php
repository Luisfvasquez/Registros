<?php

use App\Http\Controllers\Budget\AccountController;
use App\Http\Controllers\Budget\LineController;
use App\Http\Controllers\Budget\PaymentController;
use App\Http\Controllers\Budget\PeriodController;
use App\Http\Controllers\Budget\SheetController;
use Illuminate\Support\Facades\Route;

/*
 * Replica de la planilla del cliente: un libro por mes, con sus hojas como
 * pestañas. Vive detras del mismo login pero en su propia pantalla completa,
 * separada del resto del sistema.
 */
Route::middleware(['auth', 'verified'])->prefix('presupuesto')->name('presupuesto.')->group(function () {
    Route::get('/', [PeriodController::class, 'index'])->name('index');

    // Se registran antes que el grupo {period} para que "periodos" no se lea
    // como el id de un periodo.
    Route::post('periodos', [PeriodController::class, 'store'])->name('periods.store');
    Route::patch('periodos/{period}', [PeriodController::class, 'update'])->name('periods.update');
    Route::delete('periodos/{period}', [PeriodController::class, 'destroy'])->name('periods.destroy');
    Route::post('periodos/{period}/activar', [PeriodController::class, 'activate'])->name('periods.activate');

    Route::patch('lineas/{line}', [LineController::class, 'update'])->name('lines.update');
    Route::delete('lineas/{line}', [LineController::class, 'destroy'])->name('lines.destroy');

    Route::patch('abonos/{payment}', [PaymentController::class, 'update'])->name('payments.update');
    Route::delete('abonos/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

    Route::prefix('{period}')->group(function () {
        Route::get('tablero', [SheetController::class, 'dashboard'])->name('dashboard');
        Route::get('directorio', [SheetController::class, 'directory'])->name('directory');
        Route::get('compras', [SheetController::class, 'purchases'])->name('purchases');
        Route::get('ventas', [SheetController::class, 'sales'])->name('sales');
        Route::get('gastos', [SheetController::class, 'expenses'])->name('expenses');
        Route::get('ganancias-y-perdidas', [SheetController::class, 'results'])->name('results');
        Route::get('facturas', [SheetController::class, 'invoices'])->name('invoices');

        Route::get('cuenta-por-proveedor', [AccountController::class, 'providerAccount'])->name('provider-account');
        Route::get('cuenta-por-cliente', [AccountController::class, 'clientAccount'])->name('client-account');
        Route::get('ventas-del-dia', [AccountController::class, 'dailySales'])->name('daily-sales');
        Route::get('abonos-compras', [AccountController::class, 'purchasePayments'])->name('purchase-payments');
        Route::get('abonos-ventas', [AccountController::class, 'salePayments'])->name('sale-payments');
        Route::get('estado-de-cuenta/pdf', [AccountController::class, 'accountPdf'])->name('account.pdf');

        Route::post('lineas', [LineController::class, 'store'])->name('lines.store');
        Route::post('abonos', [PaymentController::class, 'store'])->name('payments.store');
    });
});
