import type { BudgetLine, BudgetSection, BudgetSummary } from '@/types';

/**
 * Standalone JSON calls for inline (no page reload) editing of the budget grid.
 * Laravel's XSRF-TOKEN cookie is sent back as the X-XSRF-TOKEN header; the
 * framework decrypts and verifies it just like an Inertia request.
 */
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export class ApiError extends Error {
    constructor(
        public status: number,
        public payload: unknown,
    ) {
        super(`Request failed with status ${status}`);
    }
}

export async function api<T>(
    url: string,
    method: 'POST' | 'PATCH' | 'DELETE',
    body?: Record<string, unknown>,
): Promise<T> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    if (!response.ok) {
        const payload = await response.json().catch(() => null);

        throw new ApiError(response.status, payload);
    }

    if (response.status === 204) {
        return undefined as T;
    }

    return (await response.json()) as T;
}

export function firstError(error: unknown): string {
    if (
        error instanceof ApiError &&
        error.payload &&
        typeof error.payload === 'object'
    ) {
        const bag = (error.payload as { errors?: Record<string, string[]> })
            .errors;

        if (bag) {
            const first = Object.values(bag)[0];

            if (Array.isArray(first) && first.length > 0) {
                return first[0];
            }
        }

        const message = (error.payload as { message?: string }).message;

        if (message) {
            return message;
        }
    }

    return 'No se pudo guardar el cambio. Reintentá.';
}

const num = (value: string | number | null | undefined): number => {
    const parsed =
        typeof value === 'number' ? value : Number.parseFloat(value ?? '0');

    return Number.isFinite(parsed) ? parsed : 0;
};

const round2 = (value: number): number => Math.round(value * 100) / 100;

export const lineTotal = (line: BudgetLine): number =>
    round2(num(line.cantidad) * num(line.unit_price));

export const lineUtilidad = (line: BudgetLine): number =>
    round2(
        num(line.ganancia) -
            num(line.gastos_personales) -
            num(line.perdidas_mercancia) -
            num(line.inversiones),
    );

/**
 * Mirrors BudgetPeriod::summary() on the server so the report updates instantly
 * while the background request confirms the write.
 */
export function computeSummary(lines: BudgetLine[]): BudgetSummary {
    const sumTotal = (section: BudgetSection) =>
        lines
            .filter((line) => line.section === section)
            .reduce((total, line) => total + lineTotal(line), 0);

    const isPaid = (line: BudgetLine) =>
        (line.payment_status ?? '').trim().toLowerCase() === 'pagado';

    const sumUnpaid = (section: BudgetSection) =>
        lines
            .filter((line) => line.section === section && !isPaid(line))
            .reduce((total, line) => total + lineTotal(line), 0);

    const resultado = lines.filter((line) => line.section === 'resultado');
    const sumField = (field: keyof BudgetLine) =>
        resultado.reduce(
            (total, line) => total + num(line[field] as string),
            0,
        );

    const totalCompras = sumTotal('compra');
    const totalVentas = sumTotal('venta');
    const totalClientes = sumTotal('cliente');
    const ingresosTotales = totalVentas + totalClientes;
    const utilidadNeta = resultado.reduce(
        (total, line) => total + lineUtilidad(line),
        0,
    );

    return {
        total_compras: round2(totalCompras),
        total_ventas: round2(totalVentas),
        total_clientes: round2(totalClientes),
        ingresos_totales: round2(ingresosTotales),
        cuentas_por_pagar: round2(sumUnpaid('compra')),
        cuentas_por_cobrar: round2(sumUnpaid('cliente')),
        ganancia_bruta: round2(ingresosTotales - totalCompras),
        ganancia_registrada: round2(sumField('ganancia')),
        gastos_personales: round2(sumField('gastos_personales')),
        perdidas_mercancia: round2(sumField('perdidas_mercancia')),
        inversiones: round2(sumField('inversiones')),
        utilidad_neta: round2(utilidadNeta),
        estado: utilidadNeta >= 0 ? 'ganancia' : 'perdida',
    };
}

export function formatMoney(
    value: string | number | null | undefined,
    currency: string,
): string {
    const amount = num(value);

    try {
        return new Intl.NumberFormat('es-VE', {
            style: 'currency',
            currency,
            minimumFractionDigits: 2,
        }).format(amount);
    } catch {
        return `${currency} ${amount.toFixed(2)}`;
    }
}

export const MONTHS = [
    'Enero',
    'Febrero',
    'Marzo',
    'Abril',
    'Mayo',
    'Junio',
    'Julio',
    'Agosto',
    'Septiembre',
    'Octubre',
    'Noviembre',
    'Diciembre',
];

export function periodLabel(period: {
    year: number;
    month: number;
    currency: string;
}): string {
    return `${MONTHS[period.month - 1] ?? period.month} ${period.year} · ${period.currency}`;
}
