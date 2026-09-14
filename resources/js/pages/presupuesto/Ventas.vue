<script setup lang="ts">
import { computed, ref } from 'vue';
import type {
    BudgetInvoiceOption,
    BudgetLine,
    BudgetPeriod,
    BudgetPeriodOption,
    BudgetSummary,
} from '@/types';
import ContactQuickAdd from './ContactQuickAdd.vue';
import {
    NEW_INVOICE,
    PAYMENT_METHODS,
    PAYMENT_STATUSES,
    todayISO,
    usePayableSheet,
} from './sheet';
import SheetLayout from './SheetLayout.vue';
import SheetTable from './SheetTable.vue';
import type { SheetColumn, SheetRow } from './SheetTable.vue';

/**
 * Hoja de ventas: lo vendido en el mes, cómo se cobró y a quién. El cliente es
 * opcional: una venta de mostrador no lleva ninguno.
 */
const props = defineProps<{
    period: BudgetPeriod;
    periods: BudgetPeriodOption[];
    activePeriodId: number | null;
    lines: BudgetLine[];
    clientes: BudgetLine[];
    productos: string[];
    metodos: string[];
    facturas: BudgetInvoiceOption[];
    summary: BudgetSummary;
}>();

const summary = ref<BudgetSummary | null>(props.summary);
const clientes = ref<BudgetLine[]>([...props.clientes]);

const { rows, invoices, addRow, update, removeRow } = usePayableSheet(
    props.period.id,
    'venta',
    props.lines,
    props.facturas,
    props.period.currency,
    summary,
);

const metodos = computed(() => [
    ...new Set([...PAYMENT_METHODS, ...props.metodos]),
]);

const columns = computed<SheetColumn[]>(() => [
    { key: 'fecha', label: 'Fecha', type: 'date', width: '9rem' },
    {
        key: 'cantidad',
        label: 'Cantidad',
        type: 'number',
        width: '8rem',
        total: true,
    },
    {
        key: 'producto',
        label: 'Producto',
        type: 'text',
        width: '14rem',
        list: 'ventas-productos',
    },
    {
        key: 'unit_price',
        label: 'Precio unitario',
        type: 'money',
        width: '10rem',
    },
    {
        key: 'precio_total',
        label: 'Venta total',
        type: 'computed',
        width: '10rem',
        total: true,
        hint: 'Cantidad × precio unitario.',
        value: (row) => (row as unknown as BudgetLine).precio_total,
    },
    {
        key: 'payment_method',
        label: 'Método de pago',
        type: 'text',
        width: '11rem',
        list: 'ventas-metodos',
    },
    {
        key: 'contact_line_id',
        label: 'Cliente',
        type: 'select',
        width: '14rem',
        hint: 'Opcional: una venta de mostrador puede ir sin cliente.',
        options: clientes.value.map((contact) => ({
            value: contact.id,
            label: contact.party_name ?? '—',
        })),
    },
    {
        key: 'invoice_line_id',
        label: 'Factura',
        type: 'select',
        width: '16rem',
        hint: 'Si el cliente ya tiene factura, la fila se engancha sola. Si no, abrila con "＋ Nueva factura".',
        options: [
            ...invoices.value.map((invoice) => ({
                value: invoice.id,
                label: invoice.label,
            })),
            { value: NEW_INVOICE, label: '＋ Nueva factura' },
        ],
    },
    {
        key: 'payment_status',
        label: 'Estado del pago',
        type: 'select',
        width: '10rem',
        hint: 'Al marcar Pagado se ofrece asentar el abono que cubre todo el saldo.',
        options: PAYMENT_STATUSES.map((status) => ({
            value: status,
            label: status,
        })),
    },
    {
        key: 'abonado',
        label: 'Abono',
        type: 'computed',
        width: '9rem',
        total: true,
        hint: 'Suma de lo cargado en Abonos Ventas.',
        value: (row) => (row as unknown as BudgetLine).abonado,
    },
    {
        key: 'restante',
        label: 'Restante',
        type: 'computed',
        width: '9rem',
        total: true,
        value: (row) => (row as unknown as BudgetLine).restante,
    },
]);

function addSale(): void {
    const last = rows.value[rows.value.length - 1];

    addRow({
        fecha: last?.fecha ?? todayISO(),
        contact_line_id: last?.contact_line_id ?? null,
        payment_status: 'Pendiente',
    });
}
</script>

<template>
    <SheetLayout
        :period="period"
        :periods="periods"
        :active-period-id="activePeriodId"
        tab="sales"
        title="Ventas"
    >
        <datalist id="ventas-productos">
            <option v-for="name in productos" :key="name" :value="name" />
        </datalist>
        <datalist id="ventas-metodos">
            <option v-for="name in metodos" :key="name" :value="name" />
        </datalist>

        <SheetTable
            :columns="columns"
            :rows="rows as unknown as SheetRow[]"
            :currency="period.currency"
            tone="sky"
            add-label="Agregar venta"
            empty-text="Todavía no cargaste ventas en este período."
            @add="addSale"
            @update="
                (row, patch) => update(row as unknown as BudgetLine, patch)
            "
            @remove="(row) => removeRow(row as unknown as BudgetLine)"
        >
            <template #toolbar>
                <ContactQuickAdd
                    :period-id="period.id"
                    tipo="cliente"
                    label="cliente"
                    @created="(contact) => clientes.push(contact)"
                />
            </template>
        </SheetTable>
    </SheetLayout>
</template>
