<script setup lang="ts">
import { FileDown } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import account from '@/routes/presupuesto/account';
import type { BudgetLine, BudgetPeriod, BudgetPeriodOption } from '@/types';
import { formatDate, formatMoney, reloadSheet } from './sheet';
import SheetLayout from './SheetLayout.vue';
import SheetTable from './SheetTable.vue';
import type { SheetColumn, SheetRow } from './SheetTable.vue';

/**
 * Estado de cuenta individual: se elige un proveedor o un cliente y se ve todo
 * lo que se le compró o vendió, lo abonado y el saldo. Las filas se editan en
 * Compras o Ventas; acá solo se leen.
 */
const props = defineProps<{
    period: BudgetPeriod;
    periods: BudgetPeriodOption[];
    activePeriodId: number | null;
    tab: string;
    title: string;
    /** `compra` o `venta`: de qué hoja salen los movimientos. */
    section: 'compra' | 'venta';
    tone: 'pink' | 'sky';
    /** Cómo se llama el contacto en esta hoja. */
    contactLabel: string;
    totalLabel: string;
    saldoLabel: string;
    /** URL de la propia hoja, para recargarla al cambiar de contacto. */
    baseUrl: string;
    contactos: BudgetLine[];
    contactoId: number | null;
    party: BudgetLine | null;
    lines: BudgetLine[];
    totals: {
        total: number;
        abonado: number;
        restante: number;
        registros: number;
    };
}>();

const columns: SheetColumn[] = [
    {
        key: 'fecha',
        label: 'Fecha',
        type: 'text',
        width: '9rem',
        readonly: true,
        value: (row) => formatDate((row as unknown as BudgetLine).fecha),
    },
    {
        key: 'producto',
        label: 'Producto',
        type: 'text',
        width: '14rem',
        readonly: true,
    },
    {
        key: 'cantidad',
        label: 'Cantidad',
        type: 'number',
        width: '8rem',
        readonly: true,
        total: true,
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
        type: 'computed',
        width: '10rem',
        total: true,
        value: (row) => (row as unknown as BudgetLine).precio_total,
    },
    {
        key: 'payment_status',
        label: 'Estado del pago',
        type: 'text',
        width: '10rem',
        readonly: true,
    },
    {
        key: 'abonado',
        label: 'Abono',
        type: 'computed',
        width: '9rem',
        total: true,
        value: (row) => (row as unknown as BudgetLine).abonado,
    },
    {
        key: 'restante',
        label: 'Restante',
        type: 'computed',
        width: '9rem',
        total: true,
        value: (row) => (row as unknown as BudgetLine).restante,
    },
];

const pdfUrl = computed(() =>
    account.pdf.url(props.period.id, {
        query: { section: props.section, contacto: props.contactoId ?? '' },
    }),
);

function selectContact(event: Event): void {
    reloadSheet(props.baseUrl, {
        contacto: (event.target as HTMLSelectElement).value,
    });
}
</script>

<template>
    <SheetLayout
        :period="period"
        :periods="periods"
        :active-period-id="activePeriodId"
        :tab="tab"
        :title="title"
        subtitle="Estado de cuenta individual"
    >
        <template #actions>
            <Button
                v-if="contactoId"
                as="a"
                :href="pdfUrl"
                variant="outline"
                size="sm"
            >
                <FileDown class="size-4" />
                PDF
            </Button>
        </template>

        <div
            class="mb-3 rounded-lg border border-neutral-300 bg-white p-3 dark:border-neutral-700 dark:bg-neutral-950"
        >
            <div class="flex flex-wrap items-end gap-4">
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">{{ contactLabel }}</span>
                    <select
                        class="h-9 min-w-64 rounded border border-neutral-300 bg-white px-2 dark:border-neutral-700 dark:bg-neutral-900"
                        :value="contactoId ?? ''"
                        @change="selectContact"
                    >
                        <option value="">— elegí uno —</option>
                        <option
                            v-for="contact in contactos"
                            :key="contact.id"
                            :value="contact.id"
                        >
                            {{ contact.party_name }}
                        </option>
                    </select>
                </label>

                <div class="text-sm">
                    <div class="text-neutral-500 dark:text-neutral-400">
                        Teléfono
                    </div>
                    <div class="font-medium">{{ party?.telefono ?? '—' }}</div>
                </div>

                <dl class="ml-auto flex flex-wrap gap-6 text-sm">
                    <div>
                        <dt class="text-neutral-500 dark:text-neutral-400">
                            {{ totalLabel }}
                        </dt>
                        <dd class="text-base font-semibold tabular-nums">
                            {{ formatMoney(totals.total, period.currency) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-neutral-500 dark:text-neutral-400">
                            Total abonado
                        </dt>
                        <dd
                            class="text-base font-semibold text-emerald-600 tabular-nums dark:text-emerald-400"
                        >
                            {{ formatMoney(totals.abonado, period.currency) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-neutral-500 dark:text-neutral-400">
                            {{ saldoLabel }}
                        </dt>
                        <dd
                            class="text-base font-semibold tabular-nums"
                            :class="
                                totals.restante > 0
                                    ? 'text-rose-600 dark:text-rose-400'
                                    : 'text-neutral-500'
                            "
                        >
                            {{ formatMoney(totals.restante, period.currency) }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <SheetTable
            :columns="columns"
            :rows="lines as unknown as SheetRow[]"
            :currency="period.currency"
            :tone="tone"
            readonly
            empty-text="Este contacto no tiene movimientos en el período."
            max-height="calc(100vh - 27rem)"
        />
    </SheetLayout>
</template>
