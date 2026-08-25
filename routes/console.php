<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('exchange:update-usd')
    ->dailyAt('7:05') // 7:05 AM
    ->timezone('America/Caracas')
    ->withoutOverlapping()
    ->onSuccess(function () {
        Log::info('Tasa de cambio actualizada correctamente');
    })
    ->onFailure(function () {
        Log::info('No se pudo actualizar la tasa de cambio');
    });

Schedule::command('exchange:update-usd')
    ->dailyAt('14:05') // 2:05 PM
    ->timezone('America/Caracas')
    ->withoutOverlapping()
    ->onSuccess(function () {
        Log::info('Tasa de cambio actualizada correctamente');
    })
    ->onFailure(function () {
        Log::info('No se pudo actualizar la tasa de cambio');
    });
