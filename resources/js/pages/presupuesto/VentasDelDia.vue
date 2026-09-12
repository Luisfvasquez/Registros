<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { PencilLine } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import presupuesto from '@/routes/presupuesto';
import type { BudgetLine, BudgetPeriod, BudgetPeriodOption } from '@/types';
import { formatDate, formatMoney, formatNumber, reloadSheet } from './sheet';
import SheetLayout from './SheetLayout.vue';
import SheetTable from './SheetTable.vue';
import type { SheetColumn, SheetRow } from './SheetTable.vue';

/**
 * Cierre del día: todo lo vendido en una fecha, con lo cobrado y lo que queda
 * por cobrar. Las filas se cargan y editan en Ventas.
 */
const props = defineProps<{
    period: BudgetPeriod;
    periods: BudgetPeriodOption[];
    activePeriodId: number | null;
    fecha: string;
    fechas: string[];
    lines: BudgetLine[];
    totals: {
        vendido: number;
        cobrado: number;
        por_cobrar: number;
        costo: number;
        unidades: number;
        registros: number;
    };
}>();

const columns: SheetColumn[] = [
    {
        key: 'cantidad',
        label: 'Cantidad',
        type: 'number',
        width: '8rem',
        readonly: true,
        total: true,
    },
    {
        key: 'producto',
        label: 'Producto',
        type: 'text',
        width: '14rem',
        readonly: true,
    },
    {
        key: 'unit_price',
        label: 'Precio unitario',
        type: 'money',
        width: '10rem',
        readonly: true,
    },
    {
        key: 'precio_total',
        label: 'Precio total',
        type: 'money',
        width: '10rem',
        readonly: true,
        total: true,
        value: (row) => (row as unknown as BudgetLine).precio_total,
    },
    {
        key: 'payment_method',
        label: 'Método de pago',
        type: 'text',
        width: '11rem',
        readonly: true,
    },
    {
        key: 'party_name',
        label: 'Cliente',
        type: 'text',
        width: '14rem',
        readonly: true,
    },
    {
        key: 'payment_status',
        label: 'Estado de pago',
        type: 'text',
        width: '10rem',
        readonly: true,
    },
    {
        key: 'abonado',
        label: 'Abono',
        type: 'money',
        width: '9rem',
        readonly: true,
        total: true,
        value: (row) => (row as unknown as BudgetLine).abonado,
    },
    {
        key: 'restante',
        label: 'Restante',
        type: 'money',
        width: '9rem',
        readonly: true,
        total: true,
        value: (row) => (row as unknown as BudgetLine).restante,
    },
];

function selectDate(event: Event): void {
    reloadSheet(presupuesto.dailySales.url(props.period.id), {
        fecha: (event.target as HTMLInputElement | HTMLSelectElement).value,
    });
}
</script>

<template>
    <SheetLayout
        :period="period"
        :periods="periods"
        :active-period-id="activePeriodId"
        tab="daily-sales"
        title="Ventas del día"
    >
        <template #actions>
            <Button as-child variant="outline" size="sm">
                <Link :href="presupuesto.sales.url(period.id)">
                    <PencilLine class="size-4" />
                    Editar en Ventas
                </Link>
            </Button>
        </template>

        <div
            class="mb-3 rounded-lg border border-neutral-300 bg-white p-3 dark:border-neutral-700 dark:bg-neutral-950"
        >
            <div class="flex flex-wrap items-end gap-4">
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Fecha</span>
                    <select
                        class="h-9 min-w-48 rounded border border-neutral-300 bg-white px-2 dark:border-neutral-700 dark:bg-neutral-900"
                        :value="fecha"
                        @change="selectDate"
                    >
                        <option v-if="!fechas.includes(fecha)" :value="fecha">
                            {{ formatDate(fecha) }}
                        </option>
                        <option v-for="day in fechas" :key="day" :value="day">
                            {{ formatDate(day) }}
                        </option>
                    </select>
                </label>

                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Otra fecha</span>
                    <input
                        type="date"
                        class="h-9 rounded border border-neutral-300 bg-white px-2 dark:border-neutral-700 dark:bg-neutral-900"
                        :value="fecha"
                        @change="selectDate"
                    />
                </label>

                <dl class="ml-auto flex flex-wrap gap-6 text-sm">
                    <div>
                        <dt class="text-neutral-500 dark:text-neutral-400">
                            Cantidad
                        </dt>
                        <dd class="text-base font-semibold tabular-nums">
                            {{ formatNumber(totals.unidades) }}
                            <span class="text-xs font-normal text-neutral-500">
                                en {{ totals.registros }}
                                {{
                                    totals.registros === 1 ? 'venta' : 'ventas'
                                }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-neutral-500 dark:text-neutral-400">
                            Vendido
                        </dt>
                        <dd class="text-base font-semibold tabular-nums">
                            {{ formatMoney(totals.vendido, period.currency) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-neutral-500 dark:text-neutral-400">
                            Cobrado
                        </dt>
                        <dd
                            class="text-base font-semibold text-emerald-600 tabular-nums dark:text-emerald-400"
                        >
                            {{ formatMoney(totals.cobrado, period.currency) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-neutral-500 dark:text-neutral-400">
                            Por cobrar
                        </dt>
                        <dd
                            class="text-base font-semibold tabular-nums"
                            :class="
                                totals.por_cobrar > 0
                                    ? 'text-rose-600 dark:text-rose-400'
                                    : 'text-neutral-500'
                            "
                        >
                            {{
                                formatMoney(totals.por_cobrar, period.currency)
                            }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <SheetTable
            :columns="columns"
            :rows="lines as unknown as SheetRow[]"
            :currency="period.currency"
            tone="sky"
            readonly
            empty-text="No hay ventas registradas en esa fecha."
            max-height="calc(100vh - 27rem)"
        />
    </SheetLayout>
</template>
