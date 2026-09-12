<script setup lang="ts">
import { Table, TrendingDown, TrendingUp } from '@lucide/vue';
import { computed, ref } from 'vue';
import type {
    BudgetPeriod,
    BudgetPeriodOption,
    BudgetSeriesPoint,
    BudgetSummary,
} from '@/types';
import BudgetChart from './BudgetChart.vue';
import { formatMoney, formatNumber } from './sheet';
import SheetLayout from './SheetLayout.vue';

/**
 * Tablero del período: las cifras del mes arriba y la serie de ventas, compras,
 * gastos y utilidad abajo, en semanas, meses o años.
 */
const props = defineProps<{
    period: BudgetPeriod;
    periods: BudgetPeriodOption[];
    activePeriodId: number | null;
    summary: BudgetSummary;
    series: {
        semanal: BudgetSeriesPoint[];
        mensual: BudgetSeriesPoint[];
        anual: BudgetSeriesPoint[];
    };
    topProductos: { producto: string; cantidad: number; total: number }[];
    metodosPago: { metodo: string; total: number }[];
}>();

type Range = 'semanal' | 'mensual' | 'anual';

const RANGES: { key: Range; label: string; hint: string }[] = [
    { key: 'semanal', label: 'Semanal', hint: 'Semanas del mes del período' },
    { key: 'mensual', label: 'Mensual', hint: 'Meses del año del período' },
    { key: 'anual', label: 'Anual', hint: 'Todos los años con movimientos' },
];

const range = ref<Range>('mensual');
const showTable = ref(false);

const points = computed(() => props.series[range.value]);

const cards = computed(() => [
    { label: 'Total ventas', value: props.summary.total_ventas, tone: 'sky' },
    {
        label: 'Total compras',
        value: props.summary.total_compras,
        tone: 'pink',
    },
    {
        label: 'Costo de ventas',
        value: props.summary.costo_ventas,
        tone: 'slate',
    },
    {
        label: 'Ganancia bruta',
        value: props.summary.ganancia_bruta,
        tone: 'emerald',
    },
    { label: 'Gastos', value: props.summary.gastos, tone: 'amber' },
    {
        label: 'Por cobrar',
        value: props.summary.cuentas_por_cobrar,
        tone: 'rose',
    },
    {
        label: 'Por pagar',
        value: props.summary.cuentas_por_pagar,
        tone: 'rose',
    },
    {
        label: 'Cobrado a clientes',
        value: props.summary.cobrado_a_clientes,
        tone: 'emerald',
    },
]);

const TONES: Record<string, string> = {
    sky: 'text-sky-700 dark:text-sky-300',
    pink: 'text-pink-700 dark:text-pink-300',
    emerald: 'text-emerald-700 dark:text-emerald-300',
    amber: 'text-amber-700 dark:text-amber-300',
    rose: 'text-rose-700 dark:text-rose-300',
    slate: 'text-slate-700 dark:text-slate-300',
};

const maxMetodo = computed(() =>
    Math.max(1, ...props.metodosPago.map((item) => item.total)),
);
</script>

<template>
    <SheetLayout
        :period="period"
        :periods="periods"
        :active-period-id="activePeriodId"
        tab="dashboard"
        title="Tablero"
    >
        <div class="grid gap-3">
            <section
                class="rounded-lg border border-neutral-300 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-950"
            >
                <div class="flex flex-wrap items-center gap-6">
                    <div>
                        <p
                            class="text-xs text-neutral-500 dark:text-neutral-400"
                        >
                            Utilidad neta del período
                        </p>
                        <p
                            class="flex items-center gap-2 text-3xl font-semibold tabular-nums"
                            :class="
                                summary.estado === 'ganancia'
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-rose-600 dark:text-rose-400'
                            "
                        >
                            <TrendingUp
                                v-if="summary.estado === 'ganancia'"
                                class="size-6"
                            />
                            <TrendingDown v-else class="size-6" />
                            {{
                                formatMoney(
                                    summary.utilidad_neta,
                                    period.currency,
                                )
                            }}
                        </p>
                        <p
                            class="text-xs text-neutral-500 dark:text-neutral-400"
                        >
                            Ganancia bruta − gastos − gastos personales −
                            pérdidas
                        </p>
                    </div>

                    <dl
                        class="ml-auto grid grid-cols-2 gap-x-8 gap-y-2 lg:grid-cols-4"
                    >
                        <div v-for="card in cards" :key="card.label">
                            <dt
                                class="text-xs text-neutral-500 dark:text-neutral-400"
                            >
                                {{ card.label }}
                            </dt>
                            <dd
                                class="text-lg font-semibold tabular-nums"
                                :class="TONES[card.tone]"
                            >
                                {{ formatMoney(card.value, period.currency) }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section
                class="rounded-lg border border-neutral-300 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-950"
            >
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <h2 class="text-sm font-semibold">
                        Resumen del movimiento
                    </h2>

                    <div
                        class="ml-auto flex items-center gap-0.5 rounded-md border border-neutral-300 p-0.5 dark:border-neutral-700"
                    >
                        <button
                            v-for="option in RANGES"
                            :key="option.key"
                            type="button"
                            class="rounded px-2.5 py-1 text-[12px] font-medium transition"
                            :class="
                                range === option.key
                                    ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900'
                                    : 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800'
                            "
                            :title="option.hint"
                            @click="range = option.key"
                        >
                            {{ option.label }}
                        </button>
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded border border-neutral-300 px-2 py-1 text-[12px] font-medium text-neutral-600 transition hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                        @click="showTable = !showTable"
                    >
                        <Table class="size-3.5" />
                        {{ showTable ? 'Ocultar tabla' : 'Ver tabla' }}
                    </button>
                </div>

                <BudgetChart :points="points" :currency="period.currency" />

                <div v-if="showTable" class="mt-3 overflow-x-auto">
                    <table class="w-full border-collapse text-[13px]">
                        <thead>
                            <tr class="bg-neutral-100 dark:bg-neutral-900">
                                <th
                                    class="border px-2 py-1 text-left dark:border-neutral-700"
                                >
                                    Período
                                </th>
                                <th
                                    class="border px-2 py-1 text-right dark:border-neutral-700"
                                >
                                    Ventas
                                </th>
                                <th
                                    class="border px-2 py-1 text-right dark:border-neutral-700"
                                >
                                    Compras
                                </th>
                                <th
                                    class="border px-2 py-1 text-right dark:border-neutral-700"
                                >
                                    Gastos
                                </th>
                                <th
                                    class="border px-2 py-1 text-right dark:border-neutral-700"
                                >
                                    Utilidad
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="point in points" :key="point.label">
                                <td
                                    class="border px-2 py-1 dark:border-neutral-700"
                                >
                                    {{ point.label }}
                                </td>
                                <td
                                    class="border px-2 py-1 text-right tabular-nums dark:border-neutral-700"
                                >
                                    {{
                                        formatMoney(
                                            point.ventas,
                                            period.currency,
                                        )
                                    }}
                                </td>
                                <td
                                    class="border px-2 py-1 text-right tabular-nums dark:border-neutral-700"
                                >
                                    {{
                                        formatMoney(
                                            point.compras,
                                            period.currency,
                                        )
                                    }}
                                </td>
                                <td
                                    class="border px-2 py-1 text-right tabular-nums dark:border-neutral-700"
                                >
                                    {{
                                        formatMoney(
                                            point.gastos,
                                            period.currency,
                                        )
                                    }}
                                </td>
                                <td
                                    class="border px-2 py-1 text-right tabular-nums dark:border-neutral-700"
                                >
                                    {{
                                        formatMoney(
                                            point.utilidad,
                                            period.currency,
                                        )
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="grid gap-3 lg:grid-cols-2">
                <section
                    class="rounded-lg border border-neutral-300 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-950"
                >
                    <h2 class="mb-2 text-sm font-semibold">Lo más vendido</h2>

                    <p
                        v-if="topProductos.length === 0"
                        class="py-6 text-center text-sm text-neutral-400"
                    >
                        Todavía no hay ventas con producto.
                    </p>

                    <table v-else class="w-full text-[13px]">
                        <thead>
                            <tr
                                class="text-left text-[11px] text-neutral-500 uppercase dark:text-neutral-400"
                            >
                                <th class="py-1">Producto</th>
                                <th class="py-1 text-right">Cantidad</th>
                                <th class="py-1 text-right">Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in topProductos"
                                :key="item.producto"
                                class="border-t border-neutral-100 dark:border-neutral-800"
                            >
                                <td class="py-1">{{ item.producto }}</td>
                                <td class="py-1 text-right tabular-nums">
                                    {{ formatNumber(item.cantidad) }}
                                </td>
                                <td
                                    class="py-1 text-right font-medium tabular-nums"
                                >
                                    {{
                                        formatMoney(item.total, period.currency)
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section
                    class="rounded-lg border border-neutral-300 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-950"
                >
                    <h2 class="mb-2 text-sm font-semibold">Cómo se cobró</h2>

                    <p
                        v-if="metodosPago.length === 0"
                        class="py-6 text-center text-sm text-neutral-400"
                    >
                        Todavía no hay ventas cargadas.
                    </p>

                    <ul v-else class="grid gap-2">
                        <li v-for="item in metodosPago" :key="item.metodo">
                            <div
                                class="flex items-baseline justify-between text-[13px]"
                            >
                                <span>{{ item.metodo }}</span>
                                <span class="font-medium tabular-nums">
                                    {{
                                        formatMoney(item.total, period.currency)
                                    }}
                                </span>
                            </div>
                            <div
                                class="mt-0.5 h-2 rounded-full bg-neutral-100 dark:bg-neutral-800"
                            >
                                <div
                                    class="h-2 rounded-full bg-sky-500"
                                    :style="{
                                        width: `${Math.max(2, (item.total / maxMetodo) * 100)}%`,
                                    }"
                                ></div>
                            </div>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </SheetLayout>
</template>
