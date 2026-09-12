<?php

namespace App\Http\Controllers\Budget;

use App\Models\BudgetLine;
use App\Models\BudgetLinePayment;
use App\Models\BudgetPeriod;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Inertia\Response;

/**
 * Las hojas que leen lo cargado en compras y ventas: estados de cuenta por
 * proveedor y por cliente, ventas del día y las dos hojas de abonos.
 */
class AccountController extends BaseController
{
    /**
     * Estado de cuenta de un proveedor: lo comprado, lo abonado y lo pendiente.
     */
    public function providerAccount(Request $request, BudgetPeriod $period): Response
    {
        return $this->sheet('CuentaProveedor', $period, $this->accountData(
            $request,
            $period,
            BudgetLine::SECTION_PURCHASE,
            BudgetLine::TYPE_PROVIDER,
        ));
    }

    public function clientAccount(Request $request, BudgetPeriod $period): Response
    {
        return $this->sheet('CuentaCliente', $period, $this->accountData(
            $request,
            $period,
            BudgetLine::SECTION_SALE,
            BudgetLine::TYPE_CLIENT,
        ));
    }

    /**
     * El mismo estado de cuenta en PDF, para enviárselo al proveedor o cliente.
     */
    public function accountPdf(Request $request, BudgetPeriod $period): HttpResponse
    {
        $section = $request->string('section')->toString() === BudgetLine::SECTION_SALE
            ? BudgetLine::SECTION_SALE
            : BudgetLine::SECTION_PURCHASE;

        $tipo = $section === BudgetLine::SECTION_SALE
            ? BudgetLine::TYPE_CLIENT
            : BudgetLine::TYPE_PROVIDER;

        $data = [...$this->accountData($request, $period, $section, $tipo), 'period' => $period];

        $pdf = Pdf::loadView('pdf.budget-account', $data)->setPaper('letter');

        return $pdf->download(Str::slug('estado-de-cuenta-'.$tipo.'-'.($data['party']?->party_name ?? '')).'.pdf');
    }

    /**
     * Ventas del día: todo lo vendido en una fecha, con los totales del cierre.
     */
    public function dailySales(Request $request, BudgetPeriod $period): Response
    {
        $dates = $period->lines()
            ->section(BudgetLine::SECTION_SALE)
            ->whereNotNull('fecha')
            ->distinct()
            ->orderByDesc('fecha')
            ->pluck('fecha')
            ->map(fn (mixed $date): string => Str::substr((string) $date, 0, 10))
            ->values()
            ->all();

        $fecha = $request->date('fecha')?->toDateString()
            ?? $dates[0]
            ?? now()->toDateString();

        $lines = $period->lines()
            ->section(BudgetLine::SECTION_SALE)
            ->whereDate('fecha', $fecha)
            ->with('payments')
            ->sheetOrder()
            ->get();

        $vendido = (float) $lines->sum('precio_total');
        $cobrado = (float) $lines->sum('abonado');

        return $this->sheet('VentasDelDia', $period, [
            'fecha' => $fecha,
            'fechas' => $dates,
            'lines' => $lines,
            'totals' => [
                'vendido' => round($vendido, 2),
                'cobrado' => round($cobrado, 2),
                'por_cobrar' => round($vendido - $cobrado, 2),
                'costo' => round((float) $lines->sum('costo'), 2),
                'unidades' => round((float) $lines->sum('cantidad'), 2),
                'registros' => $lines->count(),
            ],
        ]);
    }

    public function purchasePayments(BudgetPeriod $period): Response
    {
        return $this->sheet('AbonosCompras', $period, $this->paymentsData(
            $period,
            BudgetLine::SECTION_PURCHASE,
            BudgetLine::TYPE_PROVIDER,
        ));
    }

    public function salePayments(BudgetPeriod $period): Response
    {
        return $this->sheet('AbonosVentas', $period, $this->paymentsData(
            $period,
            BudgetLine::SECTION_SALE,
            BudgetLine::TYPE_CLIENT,
        ));
    }

    /**
     * Filas de una hoja de abonos: los pagos del período contra compras o ventas,
     * más los registros que el select "vinculado" puede elegir.
     *
     * @return array<string, mixed>
     */
    private function paymentsData(BudgetPeriod $period, string $section, string $tipo): array
    {
        $lines = $period->lines()
            ->section($section)
            ->with('payments')
            ->sheetOrder()
            ->get();

        $payments = BudgetLinePayment::query()
            ->whereIn('budget_line_id', $lines->modelKeys())
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        return [
            'payments' => $payments,
            'registros' => $lines->map(fn (BudgetLine $line): array => [
                'id' => $line->id,
                'label' => $line->sheetLabel(),
                'party_name' => $line->party_name,
                'contact_line_id' => $line->contact_line_id,
                'precio_total' => $line->precio_total,
                'abonado' => $line->abonado,
                'restante' => $line->restante,
            ])->all(),
            'contactos' => $this->contacts($tipo),
            'metodos' => BudgetLinePayment::query()
                ->whereNotNull('method')
                ->where('method', '!=', '')
                ->distinct()
                ->orderBy('method')
                ->pluck('method')
                ->all(),
            'totals' => [
                'abonado' => round((float) $payments->sum('amount'), 2),
                'bolivares' => round((float) $payments->sum('amount_bs'), 2),
            ],
        ];
    }

    /**
     * Cabecera y detalle del estado de cuenta de un proveedor o cliente. El
     * contacto se elige por id; si no viene ninguno, se toma el primero con
     * movimientos en el período.
     *
     * @return array<string, mixed>
     */
    private function accountData(Request $request, BudgetPeriod $period, string $section, string $tipo): array
    {
        $contacts = $this->contacts($tipo);

        /** @var Collection<int, BudgetLine> $movimientos */
        $movimientos = $period->lines()
            ->section($section)
            ->with('payments')
            ->sheetOrder()
            ->get();

        $conMovimientos = $movimientos->pluck('contact_line_id')->filter()->unique();

        $contactId = $request->integer('contacto')
            ?: (int) ($conMovimientos->first() ?? $contacts->first()?->id ?? 0);

        $party = $contacts->firstWhere('id', $contactId);

        $lines = $movimientos->where('contact_line_id', $contactId)->values();

        $total = (float) $lines->sum('precio_total');
        $abonado = (float) $lines->sum('abonado');

        return [
            'section' => $section,
            'contactos' => $contacts,
            'contactoId' => $contactId ?: null,
            'party' => $party,
            'lines' => $lines,
            'totals' => [
                'total' => round($total, 2),
                'abonado' => round($abonado, 2),
                'restante' => round($total - $abonado, 2),
                'registros' => $lines->count(),
            ],
            'generatedAt' => now()->toDateTimeString(),
        ];
    }
}
