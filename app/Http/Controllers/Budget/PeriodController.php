<?php

namespace App\Http\Controllers\Budget;

use App\Http\Requests\BudgetPeriodRequest;
use App\Models\BudgetPeriod;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Alta y baja de los meses de trabajo. Cada período es un libro con las mismas
 * hojas; el "activo" es el que /presupuesto abre por defecto.
 */
class PeriodController extends BaseController
{
    /**
     * Entrada al módulo: abre el tablero del período activo, o la pantalla de
     * bienvenida mientras no exista ninguno.
     */
    public function index(): Response|RedirectResponse
    {
        $period = BudgetPeriod::active();

        if ($period === null) {
            return Inertia::render('presupuesto/Empty', [
                'periods' => $this->periodOptions(),
            ]);
        }

        return to_route('presupuesto.dashboard', $period);
    }

    public function store(BudgetPeriodRequest $request): RedirectResponse
    {
        $period = BudgetPeriod::create($request->safe()->only([
            'year', 'month', 'currency', 'status', 'notes',
        ]));

        // El primer período del sistema arranca como activo: si no, no habría
        // ninguno y /presupuesto seguiría mostrando la pantalla vacía.
        if (BudgetPeriod::count() === 1) {
            Setting::put(Setting::ACTIVE_PERIOD, (string) $period->id);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Período creado.')]);

        return to_route('presupuesto.dashboard', $period);
    }

    public function update(BudgetPeriodRequest $request, BudgetPeriod $period): RedirectResponse
    {
        $period->update($request->safe()->only([
            'year', 'month', 'currency', 'status', 'notes',
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Período actualizado.')]);

        return back();
    }

    public function destroy(BudgetPeriod $period): RedirectResponse
    {
        $period->delete();

        if ((int) Setting::get(Setting::ACTIVE_PERIOD) === $period->id) {
            Setting::put(Setting::ACTIVE_PERIOD, null);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Período eliminado.')]);

        return to_route('presupuesto.index');
    }

    public function activate(BudgetPeriod $period): RedirectResponse
    {
        Setting::put(Setting::ACTIVE_PERIOD, (string) $period->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Período activo actualizado.')]);

        return back();
    }
}
