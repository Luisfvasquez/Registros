<script setup lang="ts">
import { computed, ref } from 'vue';
import type {
    BudgetLine,
    BudgetPeriod,
    BudgetPeriodOption,
    BudgetSummary,
} from '@/types';
import { formatMoney, useLines } from './sheet';
import SheetLayout from './SheetLayout.vue';
import SheetTable from './SheetTable.vue';
import type { SheetColumn, SheetRow } from './SheetTable.vue';

/**
 * Ganancias y pérdidas del mes, fila por fila: lo ganado menos lo que se sacó
 * para gastos personales y lo que se perdió en mercancía.
 */
const props = defineProps<{
    period: BudgetPeriod;
    periods: BudgetPeriodOption[];
    activePeriodId: number | null;
    lines: BudgetLine[];
    facturas: string[];
    summary: BudgetSummary;
}>();

const summary = ref<BudgetSummary | null>(props.summary);

const { rows, addRow, patchRow, removeRow } = useLines(
    props.period.id,
    'resultado',
    props.lines,
    summary,
);

const columns = computed<SheetColumn[]>(() => [
    { key: 'fecha', label: 'Fecha', type: 'date', width: '9rem' },
    {
        key: 'invoice_number',
        label: 'Factura',
        type: 'text',
        width: '11rem',
        list: 'resultado-facturas',
        hint: 'Opcional: el nº de factura con el que se registró el movimiento.',
    },
    {
        key: 'ganancia',
        label: 'Cantidad',
        type: 'money',
        width: '11rem',
        total: true,
        hint: 'La ganancia del movimiento.',
    },
    {
        key: 'gastos_personales',
        label: 'Gastos personales',
        type: 'money',
        width: '12rem',
        total: true,
    },
    {
        key: 'perdidas_mercancia',
        label: 'Pérdida mercancía',
        type: 'money',
        width: '12rem',
        total: true,
    },
    {
        key: 'total_utilidad',
        label: 'Resultado',
        type: 'computed',
        width: '11rem',
        total: true,
        hint: 'Cantidad − gastos personales − pérdida de mercancía.',
        value: (row) => (row as unknown as BudgetLine).total_utilidad,
    },
]);

function addResult(): void {
    const last = rows.value[rows.value.length - 1];

    addRow({ fecha: last?.fecha ?? new Date().toISOString().slice(0, 10) });
}
</script>

<template>
    <SheetLayout
        :period="period"
        :periods="periods"
        :active-period-id="activePeriodId"
        tab="results"
        title="Ganancias y pérdidas"
    >
        <template #actions>
            <span class="text-sm text-neutral-500 dark:text-neutral-400">
                Utilidad neta del mes:
                <strong
                    :class="
                        (summary?.utilidad_neta ?? 0) >= 0
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : 'text-rose-600 dark:text-rose-400'
                    "
                >
                    {{
                        formatMoney(
                            summary?.utilidad_neta ?? 0,
                            period.currency,
                        )
                    }}
                </strong>
            </span>
        </template>

        <datalist id="resultado-facturas">
            <option v-for="code in facturas" :key="code" :value="code" />
        </datalist>

        <SheetTable
            :columns="columns"
            :rows="rows as unknown as SheetRow[]"
            :currency="period.currency"
            tone="emerald"
            add-label="Agregar movimiento"
            empty-text="Sin movimientos de ganancias y pérdidas."
            @add="addResult"
            @update="
                (row, patch) => patchRow(row as unknown as BudgetLine, patch)
            "
            @remove="(row) => removeRow(row as unknown as BudgetLine)"
        />
    </SheetLayout>
</template>
