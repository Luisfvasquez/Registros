<script setup lang="ts">
import { HandCoins, Share2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import invoicesRoutes from '@/routes/presupuesto/invoices';
import invoicePayments from '@/routes/presupuesto/invoices/payments';
import lineRoutes from '@/routes/presupuesto/lines';
import type {
    BudgetInvoice,
    BudgetLine,
    BudgetPeriod,
    BudgetPeriodOption,
    BudgetSummary,
} from '@/types';
import BudgetShareDialog from './BudgetShareDialog.vue';
import InvoicePaymentDialog from './InvoicePaymentDialog.vue';
import { api, firstError, formatDate, formatMoney, periodLabel } from './sheet';
import SheetLayout from './SheetLayout.vue';
import SheetTable from './SheetTable.vue';
import type { SheetColumn, SheetRow } from './SheetTable.vue';

/**
 * Facturas: cada una agrupa las compras o ventas de un proveedor o cliente. Los
 * importes no se escriben acá, salen de esos movimientos; lo que sí se hace es
 * abonar la factura entera y mandársela al contacto.
 */
const props = defineProps<{
    period: BudgetPeriod;
    periods: BudgetPeriodOption[];
    activePeriodId: number | null;
    lines: BudgetInvoice[];
    proveedores: BudgetLine[];
    clientes: BudgetLine[];
    metodos: string[];
    summary: BudgetSummary;
}>();

const rows = ref<BudgetInvoice[]>([...props.lines]);
const saving = ref(false);
const abonando = ref<BudgetInvoice | null>(null);
const compartiendo = ref<BudgetInvoice | null>(null);

const periodo = computed(() => periodLabel(props.period));

const totales = computed(() => ({
    total: rows.value.reduce((sum, row) => sum + row.totales.total, 0),
    abonado: rows.value.reduce((sum, row) => sum + row.totales.abonado, 0),
    restante: rows.value.reduce((sum, row) => sum + row.totales.restante, 0),
}));

const contactosDe = (tipo: string) =>
    (tipo === 'compra' ? props.proveedores : props.clientes).map((contact) => ({
        value: contact.id,
        label: contact.party_name ?? '—',
    }));

const columns = computed<SheetColumn[]>(() => [
    {
        key: 'invoice_number',
        label: 'Nº Factura',
        type: 'text',
        width: '10rem',
    },
    {
        key: 'tipo',
        label: 'Tipo',
        type: 'select',
        width: '8rem',
        options: [
            { value: 'venta', label: 'Venta' },
            { value: 'compra', label: 'Compra' },
        ],
    },
    {
        key: 'contact_line_id',
        label: 'Cliente / Proveedor',
        type: 'select',
        width: '16rem',
        hint: 'Las compras o ventas de este contacto se enganchan solas a esta factura.',
        options: (row) => contactosDe((row.tipo as string) ?? 'venta'),
    },
    { key: 'fecha', label: 'Fecha', type: 'date', width: '9rem' },
    {
        key: 'movimientos',
        label: 'Movimientos',
        type: 'computed',
        width: '9rem',
        hint: 'Compras o ventas agrupadas en la factura. Desplegá la fila para verlas.',
        value: (row) => (row as unknown as BudgetInvoice).totales.movimientos,
    },
    {
        key: 'cantidad_total',
        label: 'Cantidad',
        type: 'computed',
        width: '9rem',
        total: true,
        value: (row) => (row as unknown as BudgetInvoice).totales.cantidad,
    },
    {
        key: 'total',
        label: 'Precio total',
        type: 'money',
        width: '11rem',
        readonly: true,
        total: true,
        value: (row) => (row as unknown as BudgetInvoice).totales.total,
    },
    {
        key: 'abonado_total',
        label: 'Abono',
        type: 'money',
        width: '11rem',
        readonly: true,
        total: true,
        value: (row) => (row as unknown as BudgetInvoice).totales.abonado,
    },
    {
        key: 'restante_total',
        label: 'Restante',
        type: 'money',
        width: '11rem',
        readonly: true,
        total: true,
        value: (row) => (row as unknown as BudgetInvoice).totales.restante,
    },
    {
        key: 'estado',
        label: 'Estado de pago',
        type: 'text',
        width: '11rem',
        readonly: true,
        value: (row) => (row as unknown as BudgetInvoice).totales.estado,
    },
    { key: 'notas', label: 'Nota', type: 'text', width: '18rem' },
]);

function replace(invoice: BudgetInvoice): void {
    const index = rows.value.findIndex((row) => row.id === invoice.id);

    if (index !== -1) {
        rows.value[index] = invoice;
    }
}

async function addInvoice(): Promise<void> {
    saving.value = true;

    try {
        const { invoice } = await api<{ invoice: BudgetInvoice }>(
            invoicesRoutes.store.url(props.period.id),
            'POST',
        );

        rows.value.push(invoice);
    } catch (error) {
        toast.error(firstError(error));
    } finally {
        saving.value = false;
    }
}

/**
 * Los totales de la factura los calcula el servidor a partir de sus
 * movimientos, así que la respuesta se funde con la fila sin pisarlos.
 */
async function update(
    row: SheetRow,
    patch: Record<string, unknown>,
): Promise<void> {
    const invoice = row as unknown as BudgetInvoice;
    const previous = { ...invoice };

    if ('tipo' in patch && patch.tipo !== invoice.tipo) {
        patch = { ...patch, contact_line_id: null };
    }

    Object.assign(invoice, patch);

    try {
        const { line } = await api<{ line: BudgetLine }>(
            lineRoutes.update.url(invoice.id),
            'PATCH',
            patch,
        );

        Object.assign(invoice, line);
    } catch (error) {
        Object.assign(invoice, previous);
        toast.error(firstError(error));
    }
}

async function removeInvoice(row: SheetRow): Promise<void> {
    const invoice = row as unknown as BudgetInvoice;

    if (
        invoice.totales.movimientos > 0 &&
        !confirm(
            `${invoice.invoice_number ?? 'La factura'} agrupa ${invoice.totales.movimientos} movimiento(s). Se van a desenganchar, no se borran. ¿Seguir?`,
        )
    ) {
        return;
    }

    const index = rows.value.findIndex((current) => current.id === invoice.id);
    const [removed] = rows.value.splice(index, 1);

    try {
        await api(lineRoutes.destroy.url(invoice.id), 'DELETE');
    } catch (error) {
        rows.value.splice(index, 0, removed);
        toast.error(firstError(error));
    }
}

async function abonar(payload: Record<string, unknown>): Promise<void> {
    if (!abonando.value) {
        return;
    }

    saving.value = true;

    try {
        const { invoice } = await api<{ invoice: BudgetInvoice }>(
            invoicePayments.store.url({
                period: props.period.id,
                invoice: abonando.value.id,
            }),
            'POST',
            payload,
        );

        replace(invoice);
        abonando.value = null;
        toast.success('Abono repartido entre los movimientos de la factura.');
    } catch (error) {
        toast.error(firstError(error));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <SheetLayout
        :period="period"
        :periods="periods"
        :active-period-id="activePeriodId"
        tab="invoices"
        title="Facturas"
        subtitle="Agrupan las compras o ventas de un mismo proveedor o cliente"
    >
        <template #actions>
            <span class="text-sm text-neutral-500 dark:text-neutral-400">
                Facturado
                <strong class="text-neutral-900 dark:text-neutral-100">
                    {{ formatMoney(totales.total, period.currency) }}
                </strong>
                · falta
                <strong
                    :class="
                        totales.restante > 0
                            ? 'text-rose-600 dark:text-rose-400'
                            : 'text-emerald-600 dark:text-emerald-400'
                    "
                >
                    {{ formatMoney(totales.restante, period.currency) }}
                </strong>
            </span>
        </template>

        <SheetTable
            :columns="columns"
            :rows="rows as unknown as SheetRow[]"
            :currency="period.currency"
            tone="slate"
            expandable
            add-label="Agregar factura"
            empty-text="Sin facturas en este período. Podés abrirlas acá o desde la celda Factura de Compras y Ventas."
            @add="addInvoice"
            @update="update"
            @remove="removeInvoice"
        >
            <template #row-actions="{ row }">
                <button
                    type="button"
                    class="rounded p-1 text-neutral-400 transition hover:bg-sky-100 hover:text-sky-600 dark:hover:bg-sky-950/40 dark:hover:text-sky-400"
                    title="Compartir la factura"
                    @click="compartiendo = row as unknown as BudgetInvoice"
                >
                    <Share2 class="size-3.5" />
                </button>
                <button
                    type="button"
                    class="rounded p-1 text-neutral-400 transition hover:bg-emerald-100 hover:text-emerald-600 dark:hover:bg-emerald-950/40 dark:hover:text-emerald-400"
                    title="Abonar la factura"
                    @click="abonando = row as unknown as BudgetInvoice"
                >
                    <HandCoins class="size-3.5" />
                </button>
            </template>

            <template #row-detail="{ row }">
                <div class="px-3 py-2">
                    <p
                        class="mb-1 text-[11px] font-semibold tracking-wide text-neutral-500 uppercase dark:text-neutral-400"
                    >
                        Movimientos · se cargan y se editan en
                        {{
                            (row as unknown as BudgetInvoice).tipo === 'compra'
                                ? 'Compras'
                                : 'Ventas'
                        }}
                    </p>

                    <table class="w-full text-[12px]">
                        <thead
                            class="text-[10px] text-neutral-500 uppercase dark:text-neutral-400"
                        >
                            <tr>
                                <th class="py-1 pr-3 text-left">Fecha</th>
                                <th class="py-1 pr-3 text-left">Producto</th>
                                <th class="py-1 pr-3 text-right">Cantidad</th>
                                <th class="py-1 pr-3 text-right">
                                    P. unitario
                                </th>
                                <th class="py-1 pr-3 text-right">Total</th>
                                <th class="py-1 pr-3 text-right">Abono</th>
                                <th class="py-1 pr-3 text-right">Restante</th>
                                <th class="py-1 text-left">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-if="
                                    (row as unknown as BudgetInvoice).items
                                        .length === 0
                                "
                            >
                                <td
                                    colspan="8"
                                    class="py-3 text-center text-neutral-400"
                                >
                                    Esta factura todavía no agrupa movimientos.
                                </td>
                            </tr>
                            <tr
                                v-for="item in (row as unknown as BudgetInvoice)
                                    .items"
                                :key="item.id"
                                class="border-t border-neutral-200 dark:border-neutral-800"
                            >
                                <td class="py-1 pr-3">
                                    {{ formatDate(item.fecha) || '—' }}
                                </td>
                                <td class="py-1 pr-3">
                                    {{ item.producto ?? '—' }}
                                </td>
                                <td class="py-1 pr-3 text-right tabular-nums">
                                    {{ item.cantidad ?? '—' }}
                                </td>
                                <td class="py-1 pr-3 text-right tabular-nums">
                                    {{
                                        formatMoney(
                                            item.unit_price,
                                            period.currency,
                                        )
                                    }}
                                </td>
                                <td
                                    class="py-1 pr-3 text-right font-medium tabular-nums"
                                >
                                    {{
                                        formatMoney(
                                            item.precio_total,
                                            period.currency,
                                        )
                                    }}
                                </td>
                                <td class="py-1 pr-3 text-right tabular-nums">
                                    {{
                                        formatMoney(
                                            item.abonado,
                                            period.currency,
                                        )
                                    }}
                                </td>
                                <td class="py-1 pr-3 text-right tabular-nums">
                                    {{
                                        formatMoney(
                                            item.restante,
                                            period.currency,
                                        )
                                    }}
                                </td>
                                <td class="py-1">
                                    {{ item.payment_status ?? 'Pendiente' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </SheetTable>

        <InvoicePaymentDialog
            :invoice="abonando"
            :metodos="metodos"
            :currency="period.currency"
            :saving="saving"
            @submit="abonar"
            @close="abonando = null"
        />

        <BudgetShareDialog
            :invoice="compartiendo"
            :currency="period.currency"
            :periodo="periodo"
            @close="compartiendo = null"
        />
    </SheetLayout>
</template>
