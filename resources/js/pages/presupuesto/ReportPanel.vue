<script setup lang="ts">
import {
    ArrowDownCircle,
    ArrowUpCircle,
    Info,
    Landmark,
    PackageX,
    PiggyBank,
    TrendingDown,
    TrendingUp,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import type { BudgetPeriod, BudgetSummary } from '@/types';
import { formatMoney, MONTHS } from './lib';

const props = defineProps<{
    period: BudgetPeriod;
    summary: BudgetSummary;
    readonly?: boolean;
}>();

const emit = defineEmits<{
    updatePeriod: [patch: Record<string, unknown>];
}>();

const monthName = computed(
    () => MONTHS[props.period.month - 1] ?? String(props.period.month),
);

const positive = 'text-sky-600 dark:text-sky-400';
const negative = 'text-rose-600 dark:text-rose-400';

const cards = computed(() => [
    {
        label: 'Total ventas',
        value: props.summary.total_ventas,
        icon: ArrowUpCircle,
        bar: 'bg-sky-300',
        text: positive,
        hint: 'Suma del precio total de todas las ventas registradas.',
    },
    {
        label: 'Ventas a clientes',
        value: props.summary.total_clientes,
        icon: Users,
        bar: 'bg-pink-300',
        text: 'text-pink-600 dark:text-pink-400',
        hint: 'Suma del precio total de las ventas cargadas en Relación con clientes.',
    },
    {
        label: 'Total compras',
        value: props.summary.total_compras,
        icon: ArrowDownCircle,
        bar: 'bg-orange-300',
        text: 'text-orange-600 dark:text-orange-400',
        hint: 'Suma del precio total de la mercancía comprada a proveedores.',
    },
    {
        label: 'Ganancia bruta',
        value: props.summary.ganancia_bruta,
        icon: props.summary.ganancia_bruta >= 0 ? TrendingUp : TrendingDown,
        bar:
            props.summary.ganancia_bruta >= 0 ? 'bg-indigo-300' : 'bg-rose-300',
        text: props.summary.ganancia_bruta >= 0 ? positive : negative,
        hint: 'Ingresos por ventas y clientes menos el total de compras.',
    },
    {
        label: 'Cuentas por cobrar',
        value: props.summary.cuentas_por_cobrar,
        icon: Wallet,
        bar: 'bg-pink-300',
        text: 'text-pink-600 dark:text-pink-400',
        hint: 'Ventas a clientes que todavía no están pagadas.',
    },
    {
        label: 'Cuentas por pagar',
        value: props.summary.cuentas_por_pagar,
        icon: Landmark,
        bar: 'bg-orange-300',
        text: 'text-orange-600 dark:text-orange-400',
        hint: 'Compras que todavía no se pagaron al proveedor.',
    },
    {
        label: 'Gastos personales',
        value: props.summary.gastos_personales,
        icon: ArrowDownCircle,
        bar: 'bg-rose-300',
        text: negative,
        hint: 'Dinero del negocio usado en gastos personales (pestaña Ganancias y pérdidas).',
    },
    {
        label: 'Pérdidas por mercancía',
        value: props.summary.perdidas_mercancia,
        icon: PackageX,
        bar: 'bg-rose-300',
        text: negative,
        hint: 'Valor de la mercancía dañada, vencida o no vendible.',
    },
    {
        label: 'Inversiones',
        value: props.summary.inversiones,
        icon: PiggyBank,
        bar: 'bg-indigo-300',
        text: 'text-indigo-600 dark:text-indigo-400',
        hint: 'Dinero reinvertido en el negocio.',
    },
    {
        label: 'Utilidad neta',
        value: props.summary.utilidad_neta,
        icon: props.summary.utilidad_neta >= 0 ? TrendingUp : TrendingDown,
        bar: props.summary.utilidad_neta >= 0 ? 'bg-sky-300' : 'bg-rose-300',
        text: props.summary.utilidad_neta >= 0 ? positive : negative,
        hint: 'Ganancia − gastos personales − pérdidas − inversiones (pestaña Ganancias y pérdidas).',
    },
]);

function onAvailableMoney(event: Event) {
    const raw = (event.target as HTMLInputElement).value;

    emit('updatePeriod', { available_money: raw === '' ? 0 : Number(raw) });
}

function onStatus(event: Event) {
    emit('updatePeriod', { status: (event.target as HTMLSelectElement).value });
}
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border border-pink-100 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
    >
        <div
            class="flex flex-wrap items-center gap-x-6 gap-y-3 bg-linear-to-r from-pink-300 via-rose-200 to-sky-300 px-5 py-4 text-rose-950"
        >
            <div class="flex items-center gap-2">
                <Wallet class="size-5 opacity-90" />
                <div>
                    <div
                        class="text-[11px] font-medium tracking-wide uppercase opacity-75"
                    >
                        Reporte del período
                    </div>
                    <div class="text-lg leading-tight font-bold">
                        {{ monthName }} {{ period.year }}
                    </div>
                </div>
            </div>

            <div
                class="flex cursor-help flex-col"
                title="Moneda en la que se registra todo este período."
            >
                <span class="text-[11px] tracking-wide uppercase opacity-75"
                    >Moneda</span
                >
                <span class="text-sm font-semibold">{{ period.currency }}</span>
            </div>

            <label class="flex flex-col">
                <span class="text-[11px] tracking-wide uppercase opacity-75"
                    >Estado</span
                >
                <select
                    :value="period.status"
                    :disabled="readonly"
                    title="Abierto: seguís cargando datos. Cerrado: el período queda de solo lectura."
                    class="rounded-md bg-white/40 px-1.5 py-0.5 text-sm font-semibold capitalize outline-none disabled:opacity-60 [&>option]:text-neutral-900"
                    @change="onStatus"
                >
                    <option value="abierto">abierto</option>
                    <option value="cerrado">cerrado</option>
                </select>
            </label>

            <label class="flex flex-col">
                <span class="text-[11px] tracking-wide uppercase opacity-75">
                    Capital inicial
                </span>
                <input
                    type="number"
                    step="0.01"
                    :value="period.available_money"
                    :disabled="readonly"
                    title="Dinero con el que arranca el período."
                    class="w-32 rounded-md bg-white/40 px-1.5 py-0.5 text-sm font-semibold tabular-nums outline-none placeholder:text-rose-900/50 disabled:opacity-60"
                    @change="onAvailableMoney"
                />
            </label>

            <span
                class="ml-auto rounded-full px-3 py-1 text-xs font-bold tracking-wide uppercase"
                :class="
                    summary.estado === 'perdida'
                        ? 'bg-rose-100 text-rose-700 ring-1 ring-rose-300'
                        : 'bg-sky-100 text-sky-700 ring-1 ring-sky-300'
                "
            >
                {{
                    summary.estado === 'perdida'
                        ? 'Período en pérdida'
                        : 'Período con ganancia'
                }}
            </span>
        </div>

        <div class="grid grid-cols-2 gap-3 p-4">
            <div
                v-for="card in cards"
                :key="card.label"
                :title="card.hint"
                class="relative cursor-help overflow-hidden rounded-xl border border-neutral-200 bg-neutral-50/60 p-3 pl-4 dark:border-neutral-800 dark:bg-neutral-900/40"
            >
                <span
                    class="absolute inset-y-0 left-0 w-1.5"
                    :class="card.bar"
                ></span>
                <div class="flex items-center justify-between">
                    <span
                        class="flex items-center gap-1 text-xs font-medium text-neutral-500"
                    >
                        {{ card.label }}
                        <Info class="size-3 opacity-50" />
                    </span>
                    <component
                        :is="card.icon"
                        class="size-4"
                        :class="card.text"
                    />
                </div>
                <div
                    class="mt-1 text-xl font-bold tabular-nums"
                    :class="card.text"
                >
                    {{ formatMoney(card.value, period.currency) }}
                </div>
            </div>
        </div>
    </div>
</template>
