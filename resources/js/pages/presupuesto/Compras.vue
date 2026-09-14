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
    PAYMENT_STATUSES,
    todayISO,
    usePayableSheet,
} from './sheet';
import SheetLayout from './SheetLayout.vue';
import SheetTable from './SheetTable.vue';
import type { SheetColumn, SheetRow } from './SheetTable.vue';

/**
 * Hoja de compras: lo que se le compra a cada proveedor, con su estado de pago
 * y el saldo que sale de los abonos.
 */
const props = defineProps<{
    period: BudgetPeriod;
    periods: BudgetPeriodOption[];
    activePeriodId: number | null;
    lines: BudgetLine[];
    proveedores: BudgetLine[];
    productos: string[];
    facturas: BudgetInvoiceOption[];
    summary: BudgetSummary;
}>();

const summary = ref<BudgetSummary | null>(props.summary);
const proveedores = ref<BudgetLine[]>([...props.proveedores]);

const { rows, invoices, addRow, update, removeRow } = usePayableSheet(
    props.period.id,
    'compra',
    props.lines,
    props.facturas,
    props.period.currency,
    summary,
);

const columns = computed<SheetColumn[]>(() => [
    { key: 'fecha', label: 'Fecha', type: 'date', width: '9rem' },
    {
        key: 'contact_line_id',
        label: 'Proveedor',
        type: 'select',
        width: '14rem',
        options: proveedores.value.map((contact) => ({
            value: contact.id,
            label: contact.party_name ?? '—',
        })),
    },
    {
        key: 'invoice_line_id',
        label: 'Factura',
        type: 'select',
        width: '16rem',
        hint: 'Si el proveedor ya tiene factura, la fila se engancha sola. Si no, abrila con "＋ Nueva factura".',
        options: [
            ...invoices.value.map((invoice) => ({
                value: invoice.id,
                label: invoice.label,
            })),
            { value: NEW_INVOICE, label: '＋ Nueva factura' },
        ],
    },
    {
        key: 'producto',
        label: 'Producto',
        type: 'text',
        width: '14rem',
        list: 'compras-productos',
    },
    {
        key: 'cantidad',
        label: 'Cantidad',
        type: 'number',
        width: '8rem',
        total: true,
    },
    {
        key: 'unit_price',
        label: 'Precio unitario',
        type: 'money',
        width: '10rem',
    },
    {
        key: 'precio_total',
        label: 'Precio total',
        type: 'computed',
        width: '10rem',
        total: true,
        hint: 'Cantidad × precio unitario.',
        value: (row) => (row as unknown as BudgetLine).precio_total,
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
        hint: 'Suma de lo cargado en Abonos Compras.',
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

/** La fila nueva hereda la fecha de la anterior, como al arrastrar en Excel. */
function addPurchase(): void {
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
        tab="purchases"
        title="Compras"
    >
        <datalist id="compras-productos">
            <option v-for="name in productos" :key="name" :value="name" />
        </datalist>

        <SheetTable
            :columns="columns"
            :rows="rows as unknown as SheetRow[]"
            :currency="period.currency"
            tone="pink"
            add-label="Agregar compra"
            empty-text="Todavía no cargaste compras en este período."
            @add="addPurchase"
            @update="
                (row, patch) => update(row as unknown as BudgetLine, patch)
            "
            @remove="(row) => removeRow(row as unknown as BudgetLine)"
        >
            <template #toolbar>
                <ContactQuickAdd
                    :period-id="period.id"
                    tipo="proveedor"
                    label="proveedor"
                    @created="(contact) => proveedores.push(contact)"
                />
            </template>
        </SheetTable>
    </SheetLayout>
</template>
