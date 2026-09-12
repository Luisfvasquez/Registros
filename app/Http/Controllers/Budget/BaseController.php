<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Models\BudgetLine;
use App\Models\BudgetPeriod;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Chrome compartido por las pestañas de /presupuesto: el período que se está
 * editando y el selector de períodos que va en la barra de hojas.
 */
abstract class BaseController extends Controller
{
    /**
     * @param  array<string, mixed>  $props
     */
    protected function sheet(string $component, BudgetPeriod $period, array $props = []): Response
    {
        return Inertia::render('presupuesto/'.$component, [
            'period' => $period,
            'periods' => $this->periodOptions(),
            'activePeriodId' => ($id = Setting::get(Setting::ACTIVE_PERIOD)) ? (int) $id : null,
            ...$props,
        ]);
    }

    /**
     * @return Collection<int, BudgetPeriod>
     */
    protected function periodOptions(): Collection
    {
        return BudgetPeriod::query()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderBy('currency')
            ->get(['id', 'year', 'month', 'currency', 'status']);
    }

    /**
     * Proveedores o clientes del Directorio, para los selects de las hojas.
     *
     * @return Collection<int, BudgetLine>
     */
    protected function contacts(?string $tipo = null): Collection
    {
        return BudgetLine::query()
            ->section(BudgetLine::SECTION_CONTACT)
            ->when($tipo, fn ($query) => $query->where('tipo', $tipo))
            ->orderBy('party_name')
            // `section` viaja para que el accesor `abonado` sepa que estas filas
            // no llevan abonos y no consulte una por una.
            ->get(['id', 'section', 'tipo', 'party_name', 'telefono', 'notas']);
    }

    /**
     * Valores ya usados en una columna, para autocompletar mientras se escribe.
     *
     * @return list<string>
     */
    protected function suggestions(string $column, ?string $section = null): array
    {
        return BudgetLine::query()
            ->when($section, fn ($query) => $query->section($section))
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->map(fn (mixed $value): string => (string) $value)
            ->values()
            ->all();
    }
}
