<script setup lang="ts">
import { computed, ref } from 'vue';
import type {
    BudgetLine,
    BudgetPeriod,
    BudgetPeriodOption,
    BudgetSummary,
} from '@/types';
import { formatMoney, todayISO, useLines } from './sheet';
import SheetLayout from './SheetLayout.vue';
import SheetTable from './SheetTable.vue';
import type { SheetColumn, SheetRow } from './SheetTable.vue';

/**
 * Ganancias y pérdidas del mes, operación por operación: lo que costó, lo que
 * se vendió y qué quedó después de los gastos personales y las pérdidas.
 */
const props = defineProps<{
    period: BudgetPeriod;
    periods: BudgetPeriodOption[];
    activePeriodId: number | null;
    lines: BudgetLine[];
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
        key: 'monto_compra',
        label: 'Compra',
        type: 'money',
        width: '11rem',
        total: true,
    },
    {
        key: 'monto_venta',
        label: 'Venta',
        type: 'money',
        width: '11rem',
        total: true,
    },
    {
        key: 'costo',
        label: 'Costo',
        type: 'money',
        width: '11rem',
        total: true,
    },
    {
        key: 'utilidad',
        label: 'Utilidad',
        type: 'computed',
        width: '11rem',
        total: true,
        hint: 'Venta − compra − costo.',
        value: (row) => (row as unknown as BudgetLine).utilidad,
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
        label: 'Total',
        type: 'computed',
        width: '11rem',
        total: true,
        hint: 'Utilidad − gastos personales − pérdida de mercancía.',
        value: (row) => (row as unknown as BudgetLine).total_utilidad,
    },
]);

function addResult(): void {
    const last = rows.value[rows.value.length - 1];

    addRow({ fecha: last?.fecha ?? todayISO() });
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
                Total de la hoja:
                <strong
                    :class="
                        (summary?.resultado_utilidad ?? 0) >= 0
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : 'text-rose-600 dark:text-rose-400'
                    "
                >
                    {{
                        formatMoney(
                            summary?.resultado_utilidad ?? 0,
                            period.currency,
                        )
                    }}
                </strong>
            </span>
        </template>

        <SheetTable
            :columns="columns"
            :rows="rows as unknown as SheetRow[]"
            :currency="period.currency"
            tone="emerald"
            add-label="Agregar operación"
            empty-text="Sin operaciones cargadas en ganancias y pérdidas."
            @add="addResult"
            @update="
                (row, patch) => patchRow(row as unknown as BudgetLine, patch)
            "
            @remove="(row) => removeRow(row as unknown as BudgetLine)"
        />
    </SheetLayout>
</template>
