<?php

use App\Http\Controllers\BudgetController;
use Illuminate\Support\Facades\Route;

/*
 * Standalone budget / personal-accounting workspace. Lives behind the same login
 * but has its own full-screen page, separate from the rest of the system.
 */
Route::middleware(['auth', 'verified'])->prefix('presupuesto')->name('presupuesto.')->group(function () {
    Route::get('/', [BudgetController::class, 'index'])->name('index');

    Route::post('periodos', [BudgetController::class, 'storePeriod'])->name('periods.store');
    Route::patch('periodos/{period}', [BudgetController::class, 'updatePeriod'])->name('periods.update');
    Route::delete('periodos/{period}', [BudgetController::class, 'destroyPeriod'])->name('periods.destroy');

    Route::post('periodos/{period}/lineas', [BudgetController::class, 'storeLine'])->name('lines.store');
    Route::patch('lineas/{line}', [BudgetController::class, 'updateLine'])->name('lines.update');
    Route::delete('lineas/{line}', [BudgetController::class, 'destroyLine'])->name('lines.destroy');
});
