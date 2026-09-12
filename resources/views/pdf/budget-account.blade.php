@php
    /** @var \App\Models\BudgetPeriod $period */
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\BudgetLine> $lines */
    /** @var \App\Models\BudgetLine|null $party */
    $isPurchase = $section === 'compra';
    $nombre = $party?->party_name ?? 'Sin contacto';
    $currency = $period->currency;
    $money = fn ($value) => $currency.' '.number_format((float) $value, 2, ',', '.');
    $months = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    $abonos = $lines->flatMap(
        fn ($line) => $line->payments->map(fn ($payment) => [$line->producto ?? '—', $payment])
    );
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Estado de cuenta · {{ $nombre }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #1c1917; margin: 0; }
        h1 { font-size: 17px; margin: 0 0 2px; }
        h2 { font-size: 13px; margin: 22px 0 6px; }
        .muted { color: #78716c; }
        .header { border-bottom: 2px solid #1c1917; padding-bottom: 10px; margin-bottom: 16px; }
        .meta { margin-top: 6px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 8px; border-bottom: 1px solid #e7e5e4; text-align: left; }
        th { background: #f5f5f4; font-size: 10px; text-transform: uppercase; letter-spacing: .4px; }
        .right { text-align: right; }
        .totals { width: 58%; margin-left: auto; margin-top: 14px; }
        .totals td { border: none; padding: 4px 8px; }
        .totals .grand td { border-top: 2px solid #1c1917; font-size: 14px; font-weight: bold; padding-top: 8px; }
        .empty { padding: 14px 8px; color: #78716c; font-style: italic; }
        .foot { margin-top: 26px; font-size: 10px; color: #78716c; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Estado de cuenta</h1>
        <p class="muted" style="margin:0">
            {{ $isPurchase ? 'Compras y abonos entregados al proveedor' : 'Ventas y abonos recibidos del cliente' }}
        </p>
        <div class="meta">
            <strong>{{ $nombre }}</strong>
            @if ($party?->telefono)
                &nbsp;·&nbsp; {{ $party->telefono }}
            @endif
            &nbsp;·&nbsp; {{ $months[$period->month] ?? $period->month }} {{ $period->year }}
        </div>
    </div>

    <h2>{{ $isPurchase ? 'Compras' : 'Ventas' }}</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Producto</th>
                <th class="right">Cant.</th>
                <th class="right">Precio unit.</th>
                <th class="right">Total</th>
                <th class="right">Abonado</th>
                <th class="right">Restante</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lines as $line)
                <tr>
                    <td>{{ $line->fecha?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $line->producto ?? '—' }}</td>
                    <td class="right">{{ $line->cantidad !== null ? number_format((float) $line->cantidad, 2, ',', '.') : '—' }}</td>
                    <td class="right">{{ $money($line->unit_price) }}</td>
                    <td class="right">{{ $money($line->precio_total) }}</td>
                    <td class="right">{{ $money($line->abonado) }}</td>
                    <td class="right">{{ $money($line->restante) }}</td>
                    <td>{{ $line->payment_status ?? 'Pendiente' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">Sin filas registradas.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Abonos</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Producto</th>
                <th>Método</th>
                <th class="right">Monto Bs</th>
                <th class="right">Tasa</th>
                <th class="right">Monto</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($abonos as [$producto, $payment])
                <tr>
                    <td>{{ $payment->fecha->format('d/m/Y') }}</td>
                    <td>{{ $producto }}</td>
                    <td>{{ $payment->method ?? '—' }}</td>
                    <td class="right">
                        {{ $payment->amount_bs ? number_format((float) $payment->amount_bs, 2, ',', '.') : '—' }}
                    </td>
                    <td class="right">
                        {{ $payment->exchange_rate ? number_format((float) $payment->exchange_rate, 2, ',', '.') : '—' }}
                    </td>
                    <td class="right">{{ $money($payment->amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Sin abonos registrados.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Total</td>
            <td class="right">{{ $money($totals['total']) }}</td>
        </tr>
        <tr>
            <td>Abonado</td>
            <td class="right">{{ $money($totals['abonado']) }}</td>
        </tr>
        <tr class="grand">
            <td>{{ $isPurchase ? 'Saldo por pagar' : 'Saldo por cobrar' }}</td>
            <td class="right">{{ $money($totals['restante']) }}</td>
        </tr>
    </table>

    <p class="foot">Generado el {{ \Illuminate\Support\Carbon::parse($generatedAt)->format('d/m/Y H:i') }}</p>
</body>
</html>
