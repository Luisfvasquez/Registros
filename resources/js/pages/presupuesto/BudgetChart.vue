<script setup lang="ts">
import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';
import type { TooltipItem } from 'chart.js';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { BudgetSeriesPoint } from '@/types';
import { formatMoney, formatNumber } from './sheet';

Chart.register(
    BarController,
    BarElement,
    LineController,
    LineElement,
    PointElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
);

/**
 * Ventas, compras y gastos del período como barras, con la utilidad encima como
 * línea. Las cuatro series son importes en la misma moneda, así que comparten
 * un único eje.
 */
const props = defineProps<{
    points: BudgetSeriesPoint[];
    currency: string;
}>();

/**
 * Pasos validados para cada fondo (contraste y separación para daltonismo).
 */
const PALETTE = {
    light: {
        ventas: '#0ea5e9',
        compras: '#f472b6',
        gastos: '#f59e0b',
        utilidad: '#059669',
        grid: 'rgba(120, 113, 108, 0.15)',
        ink: '#57534e',
    },
    dark: {
        ventas: '#0284c7',
        compras: '#ec4899',
        gastos: '#d97706',
        utilidad: '#10b981',
        grid: 'rgba(212, 212, 216, 0.14)',
        ink: '#a1a1aa',
    },
};

const canvas = ref<HTMLCanvasElement | null>(null);
const isDark = ref(false);
let chart: Chart | null = null;
let observer: MutationObserver | null = null;

const colors = computed(() => (isDark.value ? PALETTE.dark : PALETTE.light));

const hasData = computed(() =>
    props.points.some(
        (point) =>
            point.ventas !== 0 || point.compras !== 0 || point.gastos !== 0,
    ),
);

function render(): void {
    if (!canvas.value) {
        return;
    }

    chart?.destroy();

    const palette = colors.value;
    const money = (value: number) => formatMoney(value, props.currency);

    chart = new Chart(canvas.value, {
        data: {
            labels: props.points.map((point) => point.label),
            datasets: [
                {
                    type: 'bar',
                    label: 'Ventas',
                    data: props.points.map((point) => point.ventas),
                    backgroundColor: palette.ventas,
                    borderRadius: 4,
                    // 2px de fondo entre barras vecinas.
                    barPercentage: 0.86,
                    categoryPercentage: 0.72,
                },
                {
                    type: 'bar',
                    label: 'Compras',
                    data: props.points.map((point) => point.compras),
                    backgroundColor: palette.compras,
                    borderRadius: 4,
                    barPercentage: 0.86,
                    categoryPercentage: 0.72,
                },
                {
                    type: 'bar',
                    label: 'Gastos',
                    data: props.points.map((point) => point.gastos),
                    backgroundColor: palette.gastos,
                    borderRadius: 4,
                    barPercentage: 0.86,
                    categoryPercentage: 0.72,
                },
                {
                    type: 'line',
                    label: 'Utilidad',
                    data: props.points.map((point) => point.utilidad),
                    borderColor: palette.utilidad,
                    backgroundColor: palette.utilidad,
                    borderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.25,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: palette.ink,
                        boxWidth: 10,
                        boxHeight: 10,
                        usePointStyle: true,
                    },
                },
                tooltip: {
                    callbacks: {
                        label: (item: TooltipItem<'bar' | 'line'>) =>
                            `${item.dataset.label}: ${money(item.parsed.y ?? 0)}`,
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: palette.ink },
                },
                y: {
                    beginAtZero: true,
                    border: { display: false },
                    grid: { color: palette.grid },
                    ticks: {
                        color: palette.ink,
                        callback: (value) => formatNumber(Number(value)),
                    },
                },
            },
        },
    });
}

/** El tema se marca con la clase `dark` en el `<html>`. */
function syncTheme(): void {
    isDark.value = document.documentElement.classList.contains('dark');
}

onMounted(() => {
    syncTheme();
    render();

    observer = new MutationObserver(syncTheme);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
});

watch(() => [props.points, colors.value], render, { deep: true });

onBeforeUnmount(() => {
    chart?.destroy();
    observer?.disconnect();
});
</script>

<template>
    <div>
        <p v-if="!hasData" class="py-16 text-center text-sm text-neutral-400">
            Sin movimientos en este rango.
        </p>
        <div v-show="hasData" class="h-80">
            <canvas ref="canvas"></canvas>
        </div>
    </div>
</template>
