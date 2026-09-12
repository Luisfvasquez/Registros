<script setup lang="ts">
import { computed, ref } from 'vue';
import type {
    BudgetInvoiceSource,
    BudgetLine,
    BudgetPeriod,
    BudgetPeriodOption,
    BudgetSummary,
} from '@/types';
import { formatDate, useLines } from './sheet';
import SheetLayout from './SheetLayout.vue';
import SheetTable from './SheetTable.vue';
import type { SheetColumn, SheetRow } from './SheetTable.vue';

/**
 * Facturas: cada una apunta a una compra o a una venta ya cargada y muestra sus
 * datos. Así el importe se escribe una sola vez, en la hoja donde nació.
 */
const props = defineProps<{
    period: BudgetPeriod;
    periods: BudgetPeriodOption[];
    activePeriodId: number | null;
    lines: BudgetLine[];
    sources: BudgetInvoiceSource[];
    summary: BudgetSummary;
}>();

const summary = ref<BudgetSummary | null>(props.summary);

const { rows, addRow, patchRow, removeRow } = useLines(
    props.period.id,
    'factura',
    props.lines,
    summary,
);

const byId = computed(
    () => new Map(props.sources.map((source) => [source.id, source])),
);

const sourceOf = (row: SheetRow): BudgetInvoiceSource | undefined =>
    byId.value.get((row as unknown as BudgetLine).linked_line_id ?? -1);

/** Columna traída del registro origen; vacía mientras no se elija ninguno. */
function fromSource<K extends keyof BudgetInvoiceSource>(key: K) {
    return (row: SheetRow) =>
        (sourceOf(row)?.[key] ?? null) as string | number | null;
}

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
        key: 'linked_line_id',
        label: 'Registro origen',
        type: 'select',
        width: '24rem',
        hint: 'Elegí primero el tipo: la lista trae las ventas o las compras del período.',
        options: (row) =>
            props.sources
                .filter(
                    (source) =>
                        source.tipo === ((row.tipo as string) ?? 'venta'),
                )
                .map((source) => ({ value: source.id, label: source.label })),
    },
    {
        key: 'fecha',
        label: 'Fecha',
        type: 'text',
        width: '9rem',
        readonly: true,
        value: (row) => formatDate(sourceOf(row)?.fecha) || null,
    },
    {
        key: 'party_name',
        label: 'Cliente / Proveedor',
        type: 'text',
        width: '14rem',
        readonly: true,
        value: fromSource('party_name'),
    },
    {
        key: 'producto',
        label: 'Producto',
        type: 'text',
        width: '14rem',
        readonly: true,
        value: fromSource('producto'),
    },
    {
        key: 'cantidad',
        label: 'Cantidad',
        type: 'computed',
        width: '8rem',
        total: true,
        value: fromSource('cantidad'),
    },
    {
        key: 'unit_price',
        label: 'Precio unitario',
        type: 'money',
        width: '10rem',
        readonly: true,
        value: fromSource('unit_price'),
    },
    {
        key: 'precio_total',
        label: 'Precio total',
        type: 'money',
        readonly: true,
        width: '10rem',
        total: true,
        value: fromSource('precio_total'),
    },
    {
        key: 'payment_method',
        label: 'Método de pago',
        type: 'text',
        width: '11rem',
        readonly: true,
        value: fromSource('payment_method'),
    },
    {
        key: 'payment_status',
        label: 'Estado de pago',
        type: 'text',
        width: '10rem',
        readonly: true,
        value: fromSource('payment_status'),
    },
    {
        key: 'abonado',
        label: 'Abono',
        type: 'money',
        readonly: true,
        width: '9rem',
        total: true,
        value: fromSource('abonado'),
    },
    {
        key: 'restante',
        label: 'Restante',
        type: 'money',
        readonly: true,
        width: '9rem',
        total: true,
        value: fromSource('restante'),
    },
    { key: 'notas', label: 'Nota', type: 'text', width: '18rem' },
]);

/**
 * Cambiar el tipo deja sin sentido el registro origen elegido, así que se
 * limpia en la misma edición.
 */
function update(row: SheetRow, patch: Record<string, unknown>): void {
    const line = row as unknown as BudgetLine;

    if (
        'tipo' in patch &&
        sourceOf(row) &&
        sourceOf(row)?.tipo !== patch.tipo
    ) {
        patch = { ...patch, linked_line_id: null };
    }

    patchRow(line, patch);
}

function addInvoice(): void {
    const next = rows.value.length + 1;

    addRow({
        tipo: 'venta',
        invoice_number: `FAC-${String(next).padStart(4, '0')}`,
    });
}
</script>

<template>
    <SheetLayout
        :period="period"
        :periods="periods"
        :active-period-id="activePeriodId"
        tab="invoices"
        title="Facturas"
        subtitle="Vinculan automáticamente registros de Compras o Ventas"
    >
        <SheetTable
            :columns="columns"
            :rows="rows as unknown as SheetRow[]"
            :currency="period.currency"
            tone="slate"
            add-label="Agregar factura"
            empty-text="Sin facturas emitidas en este período."
            @add="addInvoice"
            @update="update"
            @remove="(row) => removeRow(row as unknown as BudgetLine)"
        />
    </SheetLayout>
</template>
