<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;

class ExchangeRateController extends Controller
{
    /**
     * Force an immediate refresh of the USD -> Bs exchange rate, for cases where
     * the scheduled updates need to be validated or the rate looks stale.
     */
    public function refresh(): RedirectResponse
    {
        $exitCode = Artisan::call('exchange:update-usd');

        Inertia::flash('toast', $exitCode === 0
            ? ['type' => 'success', 'message' => __('La tasa del dólar ha sido actualizada.')]
            : ['type' => 'error', 'message' => __('No se pudo actualizar la tasa del dólar.')]);

        return back();
    }
}
