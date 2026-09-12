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
 * Gastos del mes: todo lo que sale y no es compra de mercancía.
 */
const props = defineProps<{
    period: BudgetPeriod;
    periods: BudgetPeriodOption[];
    activePeriodId: number | null;
    lines: BudgetLine[];
    categorias: string[];
    summary: BudgetSummary;
}>();

const summary = ref<BudgetSummary | null>(props.summary);

const { rows, addRow, patchRow, removeRow } = useLines(
    props.period.id,
    'gasto',
    props.lines,
    summary,
);

const columns = computed<SheetColumn[]>(() => [
    { key: 'fecha', label: 'Fecha', type: 'date', width: '9rem' },
    {
        key: 'categoria',
        label: 'Categoría',
        type: 'text',
        width: '12rem',
        list: 'gastos-categorias',
    },
    { key: 'descripcion', label: 'Descripción', type: 'text', width: '22rem' },
    {
        key: 'monto',
        label: 'Monto',
        type: 'money',
        width: '10rem',
        total: true,
    },
]);

function addExpense(): void {
    const last = rows.value[rows.value.length - 1];

    addRow({ fecha: last?.fecha ?? new Date().toISOString().slice(0, 10) });
}
</script>

<template>
    <SheetLayout
        :period="period"
        :periods="periods"
        :active-period-id="activePeriodId"
        tab="expenses"
        title="Gastos"
    >
        <template #actions>
            <span class="text-sm text-neutral-500 dark:text-neutral-400">
                Total del mes:
                <strong class="text-neutral-900 dark:text-neutral-100">
                    {{ formatMoney(summary?.gastos ?? 0, period.currency) }}
                </strong>
            </span>
        </template>

        <datalist id="gastos-categorias">
            <option v-for="name in categorias" :key="name" :value="name" />
        </datalist>

        <SheetTable
            :columns="columns"
            :rows="rows as unknown as SheetRow[]"
            :currency="period.currency"
            tone="amber"
            add-label="Agregar gasto"
            empty-text="Sin gastos cargados en este período."
            @add="addExpense"
            @update="
                (row, patch) => patchRow(row as unknown as BudgetLine, patch)
            "
            @remove="(row) => removeRow(row as unknown as BudgetLine)"
        />
    </SheetLayout>
</template>
