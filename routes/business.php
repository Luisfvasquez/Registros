<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('exchange-rate/refresh', [ExchangeRateController::class, 'refresh'])->name('exchange-rate.refresh');

    Route::get('contacts/search', [ContactController::class, 'search'])->name('contacts.search');
    Route::resource('contacts', ContactController::class)->only(['index', 'show', 'store', 'update', 'destroy']);

    Route::get('products/search', [ProductController::class, 'search'])->name('products.search');
    Route::resource('products', ProductController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');

    Route::get('payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
    Route::post('payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');

    Route::post('documents/{document}/convert', [DocumentController::class, 'convertToInvoice'])->name('documents.convert');
    Route::resource('documents', DocumentController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);

    Route::post('documents/{document}/payments', [PaymentController::class, 'store'])->name('documents.payments.store');
    Route::delete('documents/{document}/payments/{payment}', [PaymentController::class, 'destroy'])->name('documents.payments.destroy');
});
