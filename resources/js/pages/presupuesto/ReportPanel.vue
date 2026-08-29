<script setup lang="ts">
import {
    ArrowDownCircle,
    ArrowUpCircle,
    Info,
    Landmark,
    PiggyBank,
    TrendingUp,
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

const cards = computed(() => [
    {
        label: 'Ingreso total',
        value: props.summary.ingreso_total,
        icon: ArrowUpCircle,
        bar: 'bg-emerald-500',
        text: 'text-emerald-600 dark:text-emerald-400',
        hint: 'Suma de todos los ingresos reales cargados en la pestaña Ingresos.',
    },
    {
        label: 'Gastos totales',
        value: props.summary.gastos_totales,
        icon: ArrowDownCircle,
        bar: 'bg-rose-500',
        text: 'text-rose-600 dark:text-rose-400',
        hint: 'Suma de lo gastado en Presupuesto por fecha + Gastos fijos.',
    },
    {
        label: 'Presupuesto disponible',
        value: props.summary.presupuesto_disponible,
        icon: Wallet,
        bar: 'bg-violet-500',
        text:
            props.summary.presupuesto_disponible >= 0
                ? 'text-violet-600 dark:text-violet-400'
                : 'text-rose-600 dark:text-rose-400',
        hint: 'Presupuesto planificado menos lo ya gastado. En negativo = te pasaste.',
    },
    {
        label: 'Ahorros e inversiones',
        value: props.summary.ahorros_inversiones,
        icon: PiggyBank,
        bar: 'bg-sky-500',
        text: 'text-sky-600 dark:text-sky-400',
        hint: 'Suma de lo efectivamente aportado en la pestaña Ahorros.',
    },
    {
        label: 'Pagos de deuda',
        value: props.summary.pagos_deuda,
        icon: Landmark,
        bar: 'bg-amber-500',
        text: 'text-amber-600 dark:text-amber-400',
        hint: 'Suma de lo abonado a deudas en la pestaña Estado de deudas.',
    },
    {
        label: 'Utilidad',
        value: props.summary.utilidad,
        icon: TrendingUp,
        bar: props.summary.utilidad >= 0 ? 'bg-emerald-500' : 'bg-rose-500',
        text:
            props.summary.utilidad >= 0
                ? 'text-emerald-600 dark:text-emerald-400'
                : 'text-rose-600 dark:text-rose-400',
        hint: 'Ingreso total menos gastos totales menos pagos de deuda.',
    },
]);

function onAvailableMoney(event: Event) {
    const raw = (event.target as HTMLInputElement).value;

    emit('updatePeriod', { available_money: raw === '' ? 0 : Number(raw) });
}

function onStatus(event: Event) {
    emit('updatePeriod', { status: (event.target as HTMLSelectElement).value });
}

function onNotes(event: Event) {
    const value = (event.target as HTMLTextAreaElement).value.trim();

    emit('updatePeriod', { notes: value === '' ? null : value });
}
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
    >
        <div
            class="flex flex-wrap items-center gap-x-6 gap-y-3 bg-linear-to-r from-indigo-600 via-violet-600 to-fuchsia-600 px-5 py-4 text-white"
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
                    class="rounded-md bg-white/15 px-1.5 py-0.5 text-sm font-semibold capitalize outline-none disabled:opacity-60 [&>option]:text-neutral-900"
                    @change="onStatus"
                >
                    <option value="abierto">abierto</option>
                    <option value="cerrado">cerrado</option>
                </select>
            </label>

            <label class="flex flex-col">
                <span class="text-[11px] tracking-wide uppercase opacity-75">
                    Dinero disponible (inicial)
                </span>
                <input
                    type="number"
                    step="0.01"
                    :value="period.available_money"
                    :disabled="readonly"
                    title="Plata con la que arrancás el mes (saldo inicial). Se suma al ingreso para calcular el dinero disponible."
                    class="w-32 rounded-md bg-white/15 px-1.5 py-0.5 text-sm font-semibold tabular-nums outline-none placeholder:text-white/60 disabled:opacity-60"
                    @change="onAvailableMoney"
                />
            </label>

            <span
                class="ml-auto rounded-full px-3 py-1 text-xs font-bold tracking-wide uppercase"
                :class="
                    summary.estado_presupuesto === 'excedido'
                        ? 'bg-rose-950/40 text-rose-100 ring-1 ring-rose-300/50'
                        : 'bg-emerald-950/30 text-emerald-100 ring-1 ring-emerald-300/50'
                "
            >
                {{
                    summary.estado_presupuesto === 'excedido'
                        ? 'Presupuesto excedido'
                        : 'Dentro del presupuesto'
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

        <!-- <div
            class="flex flex-wrap items-center gap-x-6 gap-y-1 border-t border-neutral-200 px-4 py-3 text-xs text-neutral-500 dark:border-neutral-800"
        >
            <span>
                Dinero disponible proyectado:
                <strong
                    class="text-neutral-800 tabular-nums dark:text-neutral-200"
                >
                    {{
                        formatMoney(summary.dinero_disponible, period.currency)
                    }}
                </strong>
            </span>
            <span>
                Ingreso proyectado:
                <strong
                    class="text-neutral-800 tabular-nums dark:text-neutral-200"
                >
                    {{
                        formatMoney(summary.ingreso_proyectado, period.currency)
                    }}
                </strong>
            </span>
            <span>
                Ganancias inesperadas:
                <strong
                    class="text-emerald-600 tabular-nums dark:text-emerald-400"
                >
                    {{
                        formatMoney(
                            summary.ganancias_inesperadas,
                            period.currency,
                        )
                    }}
                </strong>
            </span>
        </div> -->

        <!-- <label class="block px-4 pb-4">
            <span class="text-xs text-neutral-500"
                >Notas / resumen del mes</span
            >
            <textarea
                :value="period.notes ?? ''"
                :disabled="readonly"
                rows="2"
                placeholder="Comentarios del mes, decisiones, pendientes…"
                class="mt-1 block w-full resize-y rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-500/20 disabled:opacity-60 dark:border-neutral-800"
                @change="onNotes"
            ></textarea>
        </label> -->
    </div>
</template>
