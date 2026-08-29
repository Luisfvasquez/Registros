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

/**
 * Mirrors BudgetPeriod::summary() on the server so the report updates instantly
 * while the background request confirms the write.
 */
export function computeSummary(
    lines: BudgetLine[],
    availableMoney: string | number,
): BudgetSummary {
    const sumActual = (sections: BudgetSection[]) =>
        lines
            .filter((line) => sections.includes(line.section))
            .reduce((total, line) => total + num(line.actual), 0);

    const sumPlanned = (sections: BudgetSection[]) =>
        lines
            .filter((line) => sections.includes(line.section))
            .reduce((total, line) => total + num(line.planned), 0);

    const ingresoTotal = sumActual(['ingreso']);
    const ingresoProyectado = sumPlanned(['ingreso']);
    const gananciasInesperadas = lines
        .filter((line) => line.section === 'ingreso' && line.is_unexpected)
        .reduce((total, line) => total + num(line.actual), 0);

    const gastosTotales = sumActual(['presupuesto', 'gasto_fijo']);
    const presupuestoTotal = sumPlanned(['presupuesto', 'gasto_fijo']);
    const pagosDeuda = sumActual(['deuda']);
    const ahorrosInversiones = sumActual(['ahorro']);

    const dineroDisponible =
        num(availableMoney) +
        ingresoTotal -
        gastosTotales -
        pagosDeuda -
        ahorrosInversiones;

    return {
        ingreso_total: round2(ingresoTotal),
        ingreso_proyectado: round2(ingresoProyectado),
        ganancias_inesperadas: round2(gananciasInesperadas),
        gastos_totales: round2(gastosTotales),
        presupuesto_total: round2(presupuestoTotal),
        presupuesto_disponible: round2(presupuestoTotal - gastosTotales),
        pagos_deuda: round2(pagosDeuda),
        ahorros_inversiones: round2(ahorrosInversiones),
        dinero_disponible: round2(dineroDisponible),
        utilidad: round2(ingresoTotal - gastosTotales - pagosDeuda),
        estado_presupuesto:
            gastosTotales > presupuestoTotal ? 'excedido' : 'dentro',
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
