<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Http\Requests\BudgetLineRequest;
use App\Models\BudgetLine;
use App\Models\BudgetPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Alta, edición y borrado de filas de cualquier hoja. Responde JSON porque las
 * planillas se editan celda por celda, sin recargar la página.
 */
class LineController extends Controller
{
    /**
     * Campos que una compra o venta copia del contacto elegido, para que la fila
     * siga siendo legible aunque después se borre del Directorio.
     *
     * @var list<string>
     */
    private const FROM_CONTACT = ['party_name', 'telefono'];

    public function store(BudgetLineRequest $request, BudgetPeriod $period): JsonResponse
    {
        $section = $request->string('section')->toString();
        $data = $this->resolveContact($request->safe()->except(['section', 'position']));

        // El Directorio es global: sus filas no cuelgan de ningún período.
        $periodId = $section === BudgetLine::SECTION_CONTACT ? null : $period->id;

        $line = BudgetLine::create([
            ...$data,
            'budget_period_id' => $periodId,
            'section' => $section,
            'position' => $request->integer('position') ?: $this->nextPosition($section, $periodId),
        ]);

        return response()->json([
            'line' => $line->load('payments'),
            'summary' => $periodId === null ? null : $period->summary(),
        ], 201);
    }

    public function update(BudgetLineRequest $request, BudgetLine $line): JsonResponse
    {
        $data = $this->resolveContact($request->safe()->except('section'));

        DB::transaction(function () use ($line, $data): void {
            $line->update($data);

            if ($line->section === BudgetLine::SECTION_CONTACT) {
                $this->propagateContact($line, $data);
            }
        });

        return response()->json([
            'line' => $line->fresh()->load('payments'),
            'summary' => $line->period?->summary(),
        ]);
    }

    public function destroy(BudgetLine $line): JsonResponse
    {
        $period = $line->period;

        $line->delete();

        return response()->json(['summary' => $period?->summary()]);
    }

    /**
     * La fila nueva va al final de su hoja.
     */
    private function nextPosition(string $section, ?int $periodId): int
    {
        $last = BudgetLine::query()
            ->section($section)
            ->where('budget_period_id', $periodId)
            ->max('position');

        return (int) $last + 1;
    }

    /**
     * El nombre y el teléfono de una compra o venta salen siempre del contacto
     * elegido, nunca de lo que se escriba en la celda.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveContact(array $data): array
    {
        if (! array_key_exists('contact_line_id', $data)) {
            return $data;
        }

        $contact = $data['contact_line_id'] ? BudgetLine::find($data['contact_line_id']) : null;

        return [
            ...$data,
            'party_name' => $contact?->party_name,
            'telefono' => $contact?->telefono,
        ];
    }

    /**
     * Al renombrar un proveedor o cliente, las compras y ventas que lo tenían
     * copiado se actualizan con él.
     *
     * @param  array<string, mixed>  $data
     */
    private function propagateContact(BudgetLine $contact, array $data): void
    {
        $changed = array_intersect_key($data, array_flip(self::FROM_CONTACT));

        if ($changed === []) {
            return;
        }

        BudgetLine::where('contact_line_id', $contact->id)->update($changed);
    }
}
