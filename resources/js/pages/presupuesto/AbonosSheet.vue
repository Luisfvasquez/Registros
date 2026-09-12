<script setup lang="ts">
import { computed, ref } from 'vue';
import type {
    BudgetLine,
    BudgetLinePayment,
    BudgetPayable,
    BudgetPeriod,
    BudgetPeriodOption,
} from '@/types';
import PaymentDialog from './PaymentDialog.vue';
import { formatDate, formatMoney, usePayments } from './sheet';
import SheetLayout from './SheetLayout.vue';
import SheetTable from './SheetTable.vue';
import type { SheetColumn, SheetRow } from './SheetTable.vue';

/**
 * Hoja de abonos: cada fila es un pago contra una compra o una venta. El
 * registro vinculado se elige al crear el abono y después no cambia, para que el
 * saldo de la otra hoja no quede colgado.
 */
const props = defineProps<{
    period: BudgetPeriod;
    periods: BudgetPeriodOption[];
    activePeriodId: number | null;
    tab: string;
    title: string;
    tone: 'pink' | 'sky';
    /** "Compra vinculada" o "Venta vinculada". */
    linkLabel: string;
    partyLabel: string;
    payments: BudgetLinePayment[];
    registros: BudgetPayable[];
    metodos: string[];
    totals: { abonado: number; bolivares: number };
}>();

/** Saldos de los registros vinculados, al día tras cada abono. */
const registros = ref<BudgetPayable[]>([...props.registros]);

const byId = computed(
    () => new Map(registros.value.map((item) => [item.id, item])),
);

function syncRegistro(line: BudgetLine): void {
    const registro = byId.value.get(line.id);

    if (registro) {
        registro.abonado = line.abonado;
        registro.restante = line.restante;
    }
}

const { rows, busy, addRow, patchRow, removeRow } = usePayments(
    props.period.id,
    props.payments,
    syncRegistro,
);

const linked = (row: SheetRow): BudgetPayable | undefined =>
    byId.value.get((row as unknown as BudgetLinePayment).budget_line_id);

const columns = computed<SheetColumn[]>(() => [
    {
        key: 'fecha',
        label: 'Fecha del pago',
        type: 'date',
        width: '10rem',
    },
    {
        key: 'budget_line_id',
        label: props.linkLabel,
        type: 'text',
        width: '24rem',
        readonly: true,
        hint: 'Se elige al crear el abono y no se cambia después.',
        value: (row) => linked(row)?.label ?? null,
    },
    {
        key: 'party_name',
        label: props.partyLabel,
        type: 'text',
        width: '14rem',
        readonly: true,
        hint: 'Sale del registro vinculado.',
        value: (row) => linked(row)?.party_name ?? null,
    },
    {
        key: 'method',
        label: 'Método',
        type: 'text',
        width: '11rem',
        list: 'abonos-sheet-metodos',
    },
    {
        key: 'amount_bs',
        label: 'Monto Bs',
        type: 'money',
        width: '11rem',
        total: true,
    },
    {
        key: 'exchange_rate',
        label: `Tasa (Bs por ${props.period.currency})`,
        type: 'number',
        width: '11rem',
    },
    {
        key: 'amount',
        label: `Monto ${props.period.currency}`,
        type: 'money',
        width: '11rem',
        total: true,
        hint: 'Si cargás monto en Bs y tasa, se recalcula solo.',
    },
    { key: 'notes', label: 'Notas', type: 'text', width: '20rem' },
]);
</script>

<template>
    <SheetLayout
        :period="period"
        :periods="periods"
        :active-period-id="activePeriodId"
        :tab="tab"
        :title="title"
    >
        <template #actions>
            <span class="text-sm text-neutral-500 dark:text-neutral-400">
                Total abonado:
                <strong class="text-neutral-900 dark:text-neutral-100">
                    {{ formatMoney(totals.abonado, period.currency) }}
                </strong>
            </span>
        </template>

        <datalist id="abonos-sheet-metodos">
            <option v-for="name in metodos" :key="name" :value="name" />
        </datalist>

        <SheetTable
            :columns="columns"
            :rows="rows as unknown as SheetRow[]"
            :currency="period.currency"
            :tone="tone"
            :can-add="false"
            empty-text="Sin abonos registrados en este período."
            @update="
                (row, patch) =>
                    patchRow(row as unknown as BudgetLinePayment, patch)
            "
            @remove="(row) => removeRow(row as unknown as BudgetLinePayment)"
        >
            <template #toolbar>
                <PaymentDialog
                    :registros="registros"
                    :metodos="metodos"
                    :currency="period.currency"
                    :link-label="linkLabel"
                    :saving="busy"
                    @submit="addRow"
                />
                <span
                    v-if="registros.length === 0"
                    class="text-[11px] text-neutral-400"
                >
                    Cargá primero un registro en la hoja de origen.
                </span>
                <span v-else class="text-[11px] text-neutral-400">
                    Último abono:
                    {{ formatDate(rows[rows.length - 1]?.fecha) || '—' }}
                </span>
            </template>
        </SheetTable>
    </SheetLayout>
</template>
