<script setup lang="ts">
import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    ArcElement,
    Legend,
    LinearScale,
    PieController,
    Tooltip,
} from 'chart.js';
import type { TooltipItem } from 'chart.js';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { BudgetLine } from '@/types';
import { formatMoney, lineTotal } from './lib';

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

/** Soft pastel palette: rosados, color piel y azules claros. */
const PALETTE = [
    '#f9a8d4',
    '#fdba74',
    '#93c5fd',
    '#c4b5fd',
    '#fbcfe8',
    '#fed7aa',
    '#bae6fd',
    '#ddd6fe',
    '#fca5a5',
    '#a5b4fc',
];

const num = (value: string | number | null | undefined) => {
    const parsed =
        typeof value === 'number' ? value : Number.parseFloat(value ?? '0');

    return Number.isFinite(parsed) ? parsed : 0;
};

const salesByProduct = computed(() => {
    const map = new Map<string, number>();

    for (const line of props.lines) {
        if (line.section !== 'venta' && line.section !== 'cliente') {
            continue;
        }

        const key = line.producto?.trim() || 'Sin producto';
        map.set(key, (map.get(key) ?? 0) + lineTotal(line));
    }

    const labels = [...map.keys()];

    return { labels, values: labels.map((label) => map.get(label) ?? 0) };
});

const movements = computed(() => {
    const sumTotal = (section: BudgetLine['section']) =>
        props.lines
            .filter((line) => line.section === section)
            .reduce((total, line) => total + lineTotal(line), 0);

    const sumField = (field: keyof BudgetLine) =>
        props.lines
            .filter((line) => line.section === 'resultado')
            .reduce((total, line) => total + num(line[field] as string), 0);

    return {
        labels: [
            'Compras',
            'Ventas',
            'Clientes',
            'Gastos pers.',
            'Pérdidas',
            'Inversiones',
        ],
        values: [
            sumTotal('compra'),
            sumTotal('venta'),
            sumTotal('cliente'),
            sumField('gastos_personales'),
            sumField('perdidas_mercancia'),
            sumField('inversiones'),
        ],
    };
});

const pieCanvas = ref<HTMLCanvasElement | null>(null);
const barCanvas = ref<HTMLCanvasElement | null>(null);
let pieChart: Chart | null = null;
let barChart: Chart | null = null;

const hasSales = computed(() => salesByProduct.value.labels.length > 0);
const hasMovements = computed(() =>
    movements.value.values.some((value) => value !== 0),
);

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

    pieChart?.destroy();
    barChart?.destroy();

    const sales = salesByProduct.value;

    pieChart = new Chart(pieCanvas.value, {
        type: 'pie',
        data: {
            labels: sales.labels,
            datasets: [
                {
                    data: sales.values,
                    backgroundColor: sales.labels.map(
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
            labels: movements.value.labels,
            datasets: [
                {
                    label: 'Monto',
                    data: movements.value.values,
                    backgroundColor: movements.value.labels.map(
                        (_, index) => PALETTE[index % PALETTE.length],
                    ),
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: moneyTooltip },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 } },
            },
        },
    });
}

onMounted(renderCharts);

watch(
    () => [salesByProduct.value, movements.value],
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
        class="rounded-xl border border-pink-100 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-950"
    >
        <h3 class="mb-2 text-sm font-semibold">Ventas por producto</h3>
        <p v-if="!hasSales" class="py-12 text-center text-sm text-neutral-400">
            Cargá ventas con producto para ver el gráfico.
        </p>
        <div v-show="hasSales" class="h-72">
            <canvas ref="pieCanvas"></canvas>
        </div>
    </div>
    <div
        class="rounded-xl border border-pink-100 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-950"
    >
        <h3 class="mb-2 text-sm font-semibold">Movimientos del período</h3>
        <p
            v-if="!hasMovements"
            class="py-12 text-center text-sm text-neutral-400"
        >
            Cargá compras, ventas o resultados para ver el gráfico.
        </p>
        <div v-show="hasMovements" class="h-72">
            <canvas ref="barCanvas"></canvas>
        </div>
    </div>
</template>
