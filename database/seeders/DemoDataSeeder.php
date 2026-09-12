<?php

namespace Database\Seeders;

use App\Models\BudgetLine;
use App\Models\BudgetPeriod;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Los mismos datos de ejemplo que el cliente ya conoce de la planilla: dos
 * proveedores y dos clientes en el Directorio, compras y ventas con sus abonos,
 * gastos, el resultado del mes y un par de facturas.
 */
class DemoDataSeeder extends Seeder
{
    private const RATE = 36;

    public function run(): void
    {
        $today = Carbon::today();

        $period = BudgetPeriod::firstOrCreate(
            ['year' => $today->year, 'month' => $today->month, 'currency' => 'USD'],
            ['status' => 'abierto', 'available_money' => 100, 'notes' => 'Datos de ejemplo.'],
        );

        Setting::put(Setting::ACTIVE_PERIOD, (string) $period->id);

        $proveedorEjemplo = $this->contact(BudgetLine::TYPE_PROVIDER, 'Proveedor Ejemplo', '0412-1234567', 'Vende verduras, entrega los martes');
        $distribuidora = $this->contact(BudgetLine::TYPE_PROVIDER, 'Distribuidora Central', '0414-7654321', 'Precios por mayor');
        $clienteEjemplo = $this->contact(BudgetLine::TYPE_CLIENT, 'Cliente Ejemplo', '0424-1112233', 'Compra semanal');
        $mariaPerez = $this->contact(BudgetLine::TYPE_CLIENT, 'Maria Perez', '0416-9998877', 'Cliente frecuente');

        $position = 0;

        // Compras: pagada, pendiente y abonada con 10.
        $compra = $this->purchase($period, $position, $today, $proveedorEjemplo, 'Tomate', 50, 0.8, 40);
        $this->purchase($period, $position, $today, $distribuidora, 'Cebolla', 30, 1, 0);
        $this->purchase($period, $position, $today, $proveedorEjemplo, 'Cebollin', 20, 1.2, 10);

        // Ventas: abonada con 7, cobrada entera y pendiente.
        $venta = $this->sale($period, $position, $today, $clienteEjemplo, 'Tomate', 10, 1.5, 8, 'Efectivo', 7);
        $this->sale($period, $position, $today, $mariaPerez, 'Cebolla', 5, 2, 5, 'Transferencia', 10);
        $this->sale($period, $position, $today, $mariaPerez, 'Zanahoria', 8, 1, 6, 'Efectivo', 0);
        $this->sale($period, $position, $today, null, 'Pimenton', 6, 2.5, 9, 'Efectivo', 15);

        foreach ([
            ['Transporte', 'Flete del mercado', 12.50],
            ['Servicios', 'Electricidad del local', 18],
            ['Personal', 'Ayudante de carga', 25],
        ] as [$categoria, $descripcion, $monto]) {
            $period->lines()->firstOrCreate(
                ['section' => BudgetLine::SECTION_EXPENSE, 'descripcion' => $descripcion],
                [
                    'fecha' => $today->toDateString(),
                    'categoria' => $categoria,
                    'monto' => $monto,
                    'position' => ++$position,
                ],
            );
        }

        $period->lines()->firstOrCreate(
            ['section' => BudgetLine::SECTION_RESULT, 'fecha' => $today->toDateString()],
            [
                'ganancia' => 120,
                'gastos_personales' => 30,
                'perdidas_mercancia' => 8,
                'position' => ++$position,
            ],
        );

        $this->invoice($period, $position, 'FAC-0001', BudgetLine::SECTION_SALE, $venta);
        $this->invoice($period, $position, 'FAC-0002', BudgetLine::SECTION_PURCHASE, $compra);
    }

    /**
     * Un proveedor o cliente del Directorio: vive fuera de los períodos.
     */
    private function contact(string $tipo, string $nombre, string $telefono, string $notas): BudgetLine
    {
        return BudgetLine::firstOrCreate(
            ['section' => BudgetLine::SECTION_CONTACT, 'tipo' => $tipo, 'party_name' => $nombre],
            ['telefono' => $telefono, 'notas' => $notas],
        );
    }

    private function purchase(
        BudgetPeriod $period,
        int &$position,
        Carbon $fecha,
        BudgetLine $proveedor,
        string $producto,
        float $cantidad,
        float $unitPrice,
        float $abonado,
    ): BudgetLine {
        return $this->movement($period, $position, BudgetLine::SECTION_PURCHASE, [
            'fecha' => $fecha->toDateString(),
            'contact_line_id' => $proveedor->id,
            'party_name' => $proveedor->party_name,
            'telefono' => $proveedor->telefono,
            'producto' => $producto,
            'cantidad' => $cantidad,
            'unit_price' => $unitPrice,
        ], $fecha, $abonado);
    }

    private function sale(
        BudgetPeriod $period,
        int &$position,
        Carbon $fecha,
        ?BudgetLine $cliente,
        string $producto,
        float $cantidad,
        float $unitPrice,
        float $costo,
        string $metodo,
        float $abonado,
    ): BudgetLine {
        return $this->movement($period, $position, BudgetLine::SECTION_SALE, [
            'fecha' => $fecha->toDateString(),
            'contact_line_id' => $cliente?->id,
            'party_name' => $cliente?->party_name,
            'telefono' => $cliente?->telefono,
            'producto' => $producto,
            'cantidad' => $cantidad,
            'unit_price' => $unitPrice,
            'costo' => $costo,
            'payment_method' => $metodo,
        ], $fecha, $abonado);
    }

    /**
     * Crea la fila y, si lleva abono, lo registra en bolívares para ejercitar la
     * conversión y el recálculo del estado de pago.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function movement(
        BudgetPeriod $period,
        int &$position,
        string $section,
        array $attributes,
        Carbon $fecha,
        float $abonado,
    ): BudgetLine {
        $line = $period->lines()->firstOrCreate(
            [
                'section' => $section,
                'producto' => $attributes['producto'],
                'contact_line_id' => $attributes['contact_line_id'],
            ],
            [...$attributes, 'payment_status' => 'Pendiente', 'position' => ++$position],
        );

        if ($abonado > 0 && $line->payments()->count() === 0) {
            $line->payments()->create([
                'fecha' => $fecha->toDateString(),
                'method' => 'Efectivo',
                'amount_bs' => round($abonado * self::RATE, 2),
                'exchange_rate' => self::RATE,
                'amount' => $abonado,
            ]);
        }

        return $line;
    }

    private function invoice(
        BudgetPeriod $period,
        int &$position,
        string $numero,
        string $tipo,
        BudgetLine $origen,
    ): void {
        $period->lines()->firstOrCreate(
            ['section' => BudgetLine::SECTION_INVOICE, 'invoice_number' => $numero],
            [
                'tipo' => $tipo,
                'linked_line_id' => $origen->id,
                'position' => ++$position,
            ],
        );
    }
}
