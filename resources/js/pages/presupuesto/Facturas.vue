<script setup lang="ts">
import { HandCoins, List } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import InvoicePaymentDialog from './InvoicePaymentDialog.vue';
import { api, firstError, formatDate, formatMoney } from './sheet';
import SheetLayout from './SheetLayout.vue';
import SheetTable from './SheetTable.vue';
import type { SheetColumn, SheetRow } from './SheetTable.vue';

/**
 * Facturas: cada una agrupa las compras o ventas de un proveedor o cliente. Los
 * importes no se escriben acá, salen de esos movimientos; lo que sí se hace es
 * abonar la factura entera y que el monto se reparta entre ellos.
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
const viendo = ref<BudgetInvoice | null>(null);

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
        hint: 'Compras o ventas agrupadas en la factura.',
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
            add-label="Agregar factura"
            empty-text="Sin facturas en este período. Se abren solas al cargar una compra o una venta."
            @add="addInvoice"
            @update="update"
            @remove="removeInvoice"
        >
            <template #row-actions="{ row }">
                <button
                    type="button"
                    class="rounded p-1 text-neutral-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    title="Ver movimientos"
                    @click="viendo = row as unknown as BudgetInvoice"
                >
                    <List class="size-3.5" />
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
        </SheetTable>

        <InvoicePaymentDialog
            :invoice="abonando"
            :metodos="metodos"
            :currency="period.currency"
            :saving="saving"
            @submit="abonar"
            @close="abonando = null"
        />

        <Dialog
            :open="viendo !== null"
            @update:open="(value) => !value && (viendo = null)"
        >
            <DialogContent class="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {{ viendo?.invoice_number ?? 'Factura' }} ·
                        {{ viendo?.party_name ?? 'Sin contacto' }}
                    </DialogTitle>
                    <DialogDescription>
                        Los movimientos se cargan y se editan en la hoja de
                        {{ viendo?.tipo === 'compra' ? 'Compras' : 'Ventas' }}.
                    </DialogDescription>
                </DialogHeader>

                <div class="max-h-96 overflow-auto">
                    <table class="w-full text-[13px]">
                        <thead
                            class="sticky top-0 bg-neutral-100 text-[11px] uppercase dark:bg-neutral-900"
                        >
                            <tr>
                                <th class="px-2 py-1 text-left">Fecha</th>
                                <th class="px-2 py-1 text-left">Producto</th>
                                <th class="px-2 py-1 text-right">Cantidad</th>
                                <th class="px-2 py-1 text-right">
                                    P. unitario
                                </th>
                                <th class="px-2 py-1 text-right">Total</th>
                                <th class="px-2 py-1 text-right">Abono</th>
                                <th class="px-2 py-1 text-right">Restante</th>
                                <th class="px-2 py-1 text-left">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="(viendo?.items.length ?? 0) === 0">
                                <td
                                    colspan="8"
                                    class="px-2 py-6 text-center text-neutral-400"
                                >
                                    Esta factura todavía no agrupa movimientos.
                                </td>
                            </tr>
                            <tr
                                v-for="item in viendo?.items ?? []"
                                :key="item.id"
                                class="border-t border-neutral-100 dark:border-neutral-800"
                            >
                                <td class="px-2 py-1">
                                    {{ formatDate(item.fecha) || '—' }}
                                </td>
                                <td class="px-2 py-1">
                                    {{ item.producto ?? '—' }}
                                </td>
                                <td class="px-2 py-1 text-right tabular-nums">
                                    {{ item.cantidad ?? '—' }}
                                </td>
                                <td class="px-2 py-1 text-right tabular-nums">
                                    {{
                                        formatMoney(
                                            item.unit_price,
                                            period.currency,
                                        )
                                    }}
                                </td>
                                <td
                                    class="px-2 py-1 text-right font-medium tabular-nums"
                                >
                                    {{
                                        formatMoney(
                                            item.precio_total,
                                            period.currency,
                                        )
                                    }}
                                </td>
                                <td class="px-2 py-1 text-right tabular-nums">
                                    {{
                                        formatMoney(
                                            item.abonado,
                                            period.currency,
                                        )
                                    }}
                                </td>
                                <td class="px-2 py-1 text-right tabular-nums">
                                    {{
                                        formatMoney(
                                            item.restante,
                                            period.currency,
                                        )
                                    }}
                                </td>
                                <td class="px-2 py-1">
                                    {{ item.payment_status ?? 'Pendiente' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </DialogContent>
        </Dialog>
    </SheetLayout>
</template>
