<script setup lang="ts">
import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Legend,
    LinearScale,
    PieController,
    Tooltip,
} from 'chart.js';
import type { TooltipItem } from 'chart.js';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { BudgetLine } from '@/types';
import { formatMoney } from './lib';

Chart.register(
    ArcElement,
    BarController,
    BarElement,
    PieController,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
);

const props = defineProps<{
    lines: BudgetLine[];
    currency: string;
}>();

const PALETTE = [
    '#2563eb',
    '#16a34a',
    '#dc2626',
    '#d97706',
    '#7c3aed',
    '#0891b2',
    '#db2777',
    '#65a30d',
    '#4b5563',
    '#0d9488',
];

const num = (value: string | number | null | undefined) => {
    const parsed =
        typeof value === 'number' ? value : Number.parseFloat(value ?? '0');

    return Number.isFinite(parsed) ? parsed : 0;
};

const byCategory = computed(() => {
    const expenses = props.lines.filter(
        (line) =>
            line.section === 'presupuesto' || line.section === 'gasto_fijo',
    );

    const map = new Map<string, { planned: number; actual: number }>();

    for (const line of expenses) {
        const key = line.category?.trim() || 'Sin categoría';
        const entry = map.get(key) ?? { planned: 0, actual: 0 };

        entry.planned += num(line.planned);
        entry.actual += num(line.actual);
        map.set(key, entry);
    }

    const labels = [...map.keys()];

    return {
        labels,
        planned: labels.map((label) => map.get(label)!.planned),
        actual: labels.map((label) => map.get(label)!.actual),
    };
});

const pieCanvas = ref<HTMLCanvasElement | null>(null);
const barCanvas = ref<HTMLCanvasElement | null>(null);
let pieChart: Chart | null = null;
let barChart: Chart | null = null;

const hasData = computed(() => byCategory.value.labels.length > 0);

const moneyTooltip = {
    callbacks: {
        label: (item: TooltipItem<'pie' | 'bar'>) => {
            const prefix = item.dataset.label ? `${item.dataset.label}: ` : '';

            return prefix + formatMoney(item.raw as number, props.currency);
        },
    },
};

function renderCharts() {
    if (!pieCanvas.value || !barCanvas.value) {
        return;
    }

    const { labels, planned, actual } = byCategory.value;

    pieChart?.destroy();
    barChart?.destroy();

    pieChart = new Chart(pieCanvas.value, {
        type: 'pie',
        data: {
            labels,
            datasets: [
                {
                    data: actual,
                    backgroundColor: labels.map(
                        (_, index) => PALETTE[index % PALETTE.length],
                    ),
                    borderWidth: 0,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right', labels: { boxWidth: 12 } },
                tooltip: moneyTooltip,
            },
        },
    });

    barChart = new Chart(barCanvas.value, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Presupuesto',
                    data: planned,
                    backgroundColor: '#93c5fd',
                },
                { label: 'Gastado', data: actual, backgroundColor: '#2563eb' },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top' }, tooltip: moneyTooltip },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 } },
            },
        },
    });
}

onMounted(renderCharts);

watch(
    () => byCategory.value,
    () => renderCharts(),
    { deep: true },
);

onBeforeUnmount(() => {
    pieChart?.destroy();
    barChart?.destroy();
});
</script>

<template>
    <!--
        No single wrapping element on purpose: each card is its own root node,
        so the parent grid (3-up next to the report, or 2-up on the Gráficos
        tab) places them directly instead of nesting another grid inside.
    -->
    <div
        class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-950"
    >
        <h3 class="mb-2 text-sm font-semibold">Gastos por categoría</h3>
        <p v-if="!hasData" class="py-12 text-center text-sm text-neutral-400">
            Cargá gastos con categoría para ver el gráfico.
        </p>
        <div v-show="hasData" class="h-72">
            <canvas ref="pieCanvas"></canvas>
        </div>
    </div>
    <div
        class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-950"
    >
        <h3 class="mb-2 text-sm font-semibold">Presupuesto vs. gastado</h3>
        <p v-if="!hasData" class="py-12 text-center text-sm text-neutral-400">
            Cargá gastos con categoría para ver el gráfico.
        </p>
        <div v-show="hasData" class="h-72">
            <canvas ref="barCanvas"></canvas>
        </div>
    </div>
</template>
