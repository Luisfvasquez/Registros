import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { Ref } from 'vue';
import { toast } from 'vue-sonner';
import presupuesto from '@/routes/presupuesto';
import invoicesRoutes from '@/routes/presupuesto/invoices';
import lines from '@/routes/presupuesto/lines';
import payments from '@/routes/presupuesto/payments';
import type {
    BudgetInvoiceOption,
    BudgetLine,
    BudgetLinePayment,
    BudgetPeriodOption,
    BudgetSection,
    BudgetSummary,
} from '@/types';

/**
 * Llamadas JSON sueltas para editar la planilla celda por celda, sin recargar.
 * La cookie XSRF-TOKEN de Laravel se devuelve como cabecera X-XSRF-TOKEN, que el
 * framework valida igual que en una petición de Inertia.
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

export const num = (value: string | number | null | undefined): number => {
    const parsed =
        typeof value === 'number' ? value : Number.parseFloat(value ?? '0');

    return Number.isFinite(parsed) ? parsed : 0;
};

export const round2 = (value: number): number => Math.round(value * 100) / 100;

export function formatNumber(
    value: string | number | null | undefined,
): string {
    return new Intl.NumberFormat('es-VE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num(value));
}

export function formatMoney(
    value: string | number | null | undefined,
    currency = 'USD',
): string {
    try {
        return new Intl.NumberFormat('es-VE', {
            style: 'currency',
            currency,
            minimumFractionDigits: 2,
        }).format(num(value));
    } catch {
        return `${currency} ${formatNumber(value)}`;
    }
}

/** Hoy en `YYYY-MM-DD`, que es como viajan las fechas de la planilla. */
export function todayISO(): string {
    return new Date().toISOString().slice(0, 10);
}

export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const [year, month, day] = value.slice(0, 10).split('-');

    return `${day}/${month}/${year}`;
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

export function periodLabel(period: BudgetPeriodOption): string {
    return `${MONTHS[period.month - 1] ?? period.month} ${period.year} · ${period.currency}`;
}

/** Estados de pago que ofrecen los selects de compras, ventas y facturas. */
export const PAYMENT_STATUSES = ['Pendiente', 'Abonado', 'Pagado'];

/** Métodos de pago sugeridos; la celda igual acepta cualquier texto. */
export const PAYMENT_METHODS = [
    'Efectivo',
    'Transferencia',
    'Pago móvil',
    'Zelle',
    'Divisas',
];

export type SheetTab = {
    key: string;
    label: string;
    /** Color de la pestaña, como las de una hoja de cálculo. */
    tone: 'slate' | 'pink' | 'sky' | 'amber' | 'emerald' | 'violet';
    href: (periodId: number) => string;
};

/**
 * Las hojas del libro, en el orden en que aparecen abajo como pestañas.
 */
export const TABS: SheetTab[] = [
    {
        key: 'dashboard',
        label: 'Tablero',
        tone: 'violet',
        href: (id) => presupuesto.dashboard.url(id),
    },
    {
        key: 'directory',
        label: 'Directorio',
        tone: 'slate',
        href: (id) => presupuesto.directory.url(id),
    },
    {
        key: 'purchases',
        label: 'Compras',
        tone: 'pink',
        href: (id) => presupuesto.purchases.url(id),
    },
    {
        key: 'provider-account',
        label: 'Cuenta por Proveedor',
        tone: 'pink',
        href: (id) => presupuesto.providerAccount.url(id),
    },
    {
        key: 'purchase-payments',
        label: 'Abonos Compras',
        tone: 'pink',
        href: (id) => presupuesto.purchasePayments.url(id),
    },
    {
        key: 'sales',
        label: 'Ventas',
        tone: 'sky',
        href: (id) => presupuesto.sales.url(id),
    },
    {
        key: 'client-account',
        label: 'Cuenta por Cliente',
        tone: 'sky',
        href: (id) => presupuesto.clientAccount.url(id),
    },
    {
        key: 'daily-sales',
        label: 'Ventas del día',
        tone: 'sky',
        href: (id) => presupuesto.dailySales.url(id),
    },
    {
        key: 'sale-payments',
        label: 'Abonos Ventas',
        tone: 'sky',
        href: (id) => presupuesto.salePayments.url(id),
    },
    {
        key: 'results',
        label: 'Ganancias y pérdidas',
        tone: 'emerald',
        href: (id) => presupuesto.results.url(id),
    },
    {
        key: 'expenses',
        label: 'Gastos',
        tone: 'amber',
        href: (id) => presupuesto.expenses.url(id),
    },
    {
        key: 'invoices',
        label: 'Facturas',
        tone: 'slate',
        href: (id) => presupuesto.invoices.url(id),
    },
];

type LineResponse = {
    line: BudgetLine;
    /** La factura a la que quedó enganchada la compra o venta, si tiene. */
    invoice: BudgetInvoiceOption | null;
    summary: BudgetSummary | null;
};

/** Aviso de la factura con la que volvió la fila, para refrescar el select. */
type OnInvoice = (invoice: BudgetInvoiceOption | null) => void;
type PaymentResponse = {
    payment: BudgetLinePayment;
    line: BudgetLine;
    summary: BudgetSummary | null;
};

/**
 * Filas de una hoja: alta, edición celda por celda y borrado. La fila se pinta
 * al instante y el servidor devuelve la versión con los totales recalculados.
 */
export function useLines(
    periodId: number,
    section: BudgetSection,
    initialRows: BudgetLine[],
    summary?: Ref<BudgetSummary | null>,
) {
    const rows = ref<BudgetLine[]>([...initialRows]) as Ref<BudgetLine[]>;
    const busy = ref(false);

    function replace(row: BudgetLine): void {
        const index = rows.value.findIndex((current) => current.id === row.id);

        if (index !== -1) {
            rows.value[index] = row;
        }
    }

    function applySummary(next: BudgetSummary | null): void {
        if (summary && next) {
            summary.value = next;
        }
    }

    async function addRow(
        seed: Record<string, unknown> = {},
        onInvoice?: OnInvoice,
    ): Promise<void> {
        busy.value = true;

        try {
            const {
                line,
                invoice,
                summary: next,
            } = await api<LineResponse>(lines.store.url(periodId), 'POST', {
                section,
                ...seed,
            });

            rows.value.push(line);
            onInvoice?.(invoice);
            applySummary(next);
        } catch (error) {
            toast.error(firstError(error));
        } finally {
            busy.value = false;
        }
    }

    async function patchRow(
        row: BudgetLine,
        patch: Record<string, unknown>,
        onInvoice?: OnInvoice,
    ): Promise<void> {
        const previous = { ...row };

        Object.assign(row, patch);

        try {
            const {
                line,
                invoice,
                summary: next,
            } = await api<LineResponse>(
                lines.update.url(row.id),
                'PATCH',
                patch,
            );

            replace(line);
            onInvoice?.(invoice);
            applySummary(next);
        } catch (error) {
            replace(previous as BudgetLine);
            toast.error(firstError(error));
        }
    }

    async function removeRow(row: BudgetLine): Promise<void> {
        const index = rows.value.findIndex((current) => current.id === row.id);
        const [removed] = rows.value.splice(index, 1);

        try {
            const { summary: next } = await api<{
                summary: BudgetSummary | null;
            }>(lines.destroy.url(row.id), 'DELETE');

            applySummary(next);
        } catch (error) {
            rows.value.splice(index, 0, removed);
            toast.error(firstError(error));
        }
    }

    /**
     * Marca la fila como pagada registrando el abono que cubre todo el saldo,
     * para que quede asentado en la hoja de abonos y no solo en el select.
     */
    async function settleRow(row: BudgetLine): Promise<void> {
        try {
            const { line, summary: next } = await api<PaymentResponse>(
                payments.store.url(periodId),
                'POST',
                {
                    budget_line_id: row.id,
                    fecha: row.fecha?.slice(0, 10) ?? todayISO(),
                    method: row.payment_method ?? null,
                    amount: row.restante,
                },
            );

            replace(line);
            applySummary(next);
        } catch (error) {
            toast.error(firstError(error));
        }
    }

    return { rows, busy, addRow, patchRow, removeRow, replace, settleRow };
}

/** Valor de la celda "Factura" que abre una factura nueva para la fila. */
export const NEW_INVOICE = -1;

/**
 * Una hoja de compras o de ventas: las filas, sus facturas y las dos cosas que
 * pasan al editarlas — marcar Pagado ofrece asentar el abono que cubre el saldo,
 * y elegir "Nueva factura" abre una sin salir de la hoja.
 */
export function usePayableSheet(
    periodId: number,
    section: 'compra' | 'venta',
    initialRows: BudgetLine[],
    initialInvoices: BudgetInvoiceOption[],
    currency: string,
    summary?: Ref<BudgetSummary | null>,
) {
    const sheet = useLines(periodId, section, initialRows, summary);
    const invoices = ref<BudgetInvoiceOption[]>([...initialInvoices]);

    function rememberInvoice(option: BudgetInvoiceOption | null): void {
        if (!option) {
            return;
        }

        const index = invoices.value.findIndex((item) => item.id === option.id);

        if (index === -1) {
            invoices.value.push(option);
        } else {
            invoices.value[index] = option;
        }
    }

    async function openInvoiceFor(row: BudgetLine): Promise<void> {
        try {
            const { option, line } = await api<{
                option: BudgetInvoiceOption | null;
                line: BudgetLine | null;
            }>(invoicesRoutes.store.url(periodId), 'POST', { line_id: row.id });

            rememberInvoice(option);

            if (line) {
                sheet.replace(line);
            }
        } catch (error) {
            toast.error(firstError(error));
        }
    }

    /** Alta de fila: el servidor la engancha a una factura y la devuelve. */
    async function addRow(seed: Record<string, unknown> = {}): Promise<void> {
        await sheet.addRow(seed, (option) => rememberInvoice(option));
    }

    async function update(
        row: BudgetLine,
        patch: Record<string, unknown>,
    ): Promise<void> {
        if (patch.invoice_line_id === NEW_INVOICE) {
            await openInvoiceFor(row);

            return;
        }

        const marcaPagado =
            patch.payment_status === 'Pagado' && row.restante > 0.001;

        if (marcaPagado) {
            const monto = formatMoney(row.restante, currency);

            if (confirm(`¿Registrar un abono de pago completo por ${monto}?`)) {
                await sheet.settleRow(row);

                return;
            }
        }

        await sheet.patchRow(row, patch, (option) => rememberInvoice(option));
    }

    return {
        rows: sheet.rows,
        busy: sheet.busy,
        invoices,
        addRow,
        update,
        removeRow: sheet.removeRow,
        rememberInvoice,
    };
}

/**
 * Filas de una hoja de abonos. Cada cambio devuelve también la compra o venta
 * vinculada, así que los saldos de la hoja de al lado quedan al día.
 */
export function usePayments(
    periodId: number,
    initialRows: BudgetLinePayment[],
    onLineChange?: (line: BudgetLine) => void,
) {
    const rows = ref<BudgetLinePayment[]>([...initialRows]) as Ref<
        BudgetLinePayment[]
    >;
    const busy = ref(false);

    function replace(row: BudgetLinePayment): void {
        const index = rows.value.findIndex((current) => current.id === row.id);

        if (index !== -1) {
            rows.value[index] = row;
        }
    }

    async function addRow(seed: Record<string, unknown>): Promise<void> {
        busy.value = true;

        try {
            const { payment, line } = await api<PaymentResponse>(
                payments.store.url(periodId),
                'POST',
                seed,
            );

            rows.value.push(payment);
            onLineChange?.(line);
        } catch (error) {
            toast.error(firstError(error));
        } finally {
            busy.value = false;
        }
    }

    async function patchRow(
        row: BudgetLinePayment,
        patch: Record<string, unknown>,
    ): Promise<void> {
        const previous = { ...row };

        Object.assign(row, patch);

        try {
            const { payment, line } = await api<PaymentResponse>(
                payments.update.url(row.id),
                'PATCH',
                patch,
            );

            replace(payment);
            onLineChange?.(line);
        } catch (error) {
            replace(previous as BudgetLinePayment);
            toast.error(firstError(error));
        }
    }

    async function removeRow(row: BudgetLinePayment): Promise<void> {
        const index = rows.value.findIndex((current) => current.id === row.id);
        const [removed] = rows.value.splice(index, 1);

        try {
            const { line } = await api<{ line: BudgetLine }>(
                payments.destroy.url(row.id),
                'DELETE',
            );

            onLineChange?.(line);
        } catch (error) {
            rows.value.splice(index, 0, removed);
            toast.error(firstError(error));
        }
    }

    return { rows, busy, addRow, patchRow, removeRow };
}

/**
 * Recarga la hoja abierta manteniendo el scroll, para los filtros que viven en
 * la URL (el contacto del estado de cuenta, la fecha de ventas del día).
 */
export function reloadSheet(
    url: string,
    data: Record<string, string | number>,
): void {
    router.get(url, data, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
