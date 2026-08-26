<script setup lang="ts">
import { computed } from 'vue';
import MoneyBs from '@/components/MoneyBs.vue';
import { formatDate } from '@/lib/utils';
import type { Document } from '@/types';

const props = defineProps<{
    document: Document;
    paidTotal: number;
    balance: number;
}>();

const statusLabels: Record<string, string> = {
    pendiente: 'Pendiente',
    parcial: 'Abonado parcialmente',
    pagado: 'Pagado',
    convertido: 'Convertido a orden',
    anulado: 'Anulado',
};

const kindLabel = computed(() =>
    props.document.document_type === 'factura' ? 'ORDEN' : 'PRESUPUESTO',
);
const operationLabel = computed(() =>
    props.document.operation_type === 'venta' ? 'Venta' : 'Compra',
);

function money(value: number | string) {
    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
    }).format(Number(value));
}
</script>

<template>
    <div
        class="w-[360px] bg-white p-6 font-mono text-neutral-900"
        data-slot="receipt"
    >
        <div
            class="flex flex-col items-center gap-1 border-b border-dashed border-neutral-300 pb-4 text-center"
        >
            <p class="text-xs tracking-widest text-neutral-500">
                {{ operationLabel }}
            </p>
            <h1 class="text-lg font-bold tracking-wide">{{ kindLabel }}</h1>
            <p class="text-sm">{{ document.number }}</p>
            <p class="text-xs text-neutral-500">
                {{ formatDate(document.issue_date) }}
            </p>
        </div>

        <div class="border-b border-dashed border-neutral-300 py-3 text-sm">
            <p class="font-semibold">{{ document.contact?.name }}</p>
            <p
                v-if="document.contact?.document"
                class="text-xs text-neutral-500"
            >
                Doc: {{ document.contact.document }}
            </p>
            <p v-if="document.contact?.phone" class="text-xs text-neutral-500">
                Tel: {{ document.contact.phone_country_code }}
                {{ document.contact.phone }}
            </p>
        </div>

        <div class="divide-y divide-dashed divide-neutral-200 py-2">
            <div
                v-for="item in document.items"
                :key="item.id"
                class="flex justify-between gap-2 py-1.5 text-xs"
            >
                <div class="min-w-0">
                    <p class="truncate">{{ item.description }}</p>
                    <p class="text-neutral-500">
                        {{ Number(item.quantity) }} x
                        {{ money(item.unit_price) }}
                    </p>
                </div>
                <p class="shrink-0 font-medium">{{ money(item.subtotal) }}</p>
            </div>
        </div>

        <div
            class="space-y-1 border-t border-dashed border-neutral-300 pt-3 text-sm"
        >
            <div class="flex justify-between text-neutral-600">
                <span>Subtotal</span>
                <span>{{ money(document.subtotal) }}</span>
            </div>
            <div
                v-if="Number(document.tax_total) > 0"
                class="flex justify-between text-neutral-600"
            >
                <span>Impuestos</span>
                <span>{{ money(document.tax_total) }}</span>
            </div>
            <div class="flex justify-between text-base font-bold">
                <span>Total</span>
                <span>
                    {{ money(document.total) }}
                    <MoneyBs
                        :amount="document.total"
                        class="text-right! text-neutral-500"
                    />
                </span>
            </div>

            <template v-if="document.document_type === 'factura'">
                <div class="flex justify-between text-neutral-600">
                    <span>Abonado</span>
                    <span>{{ money(paidTotal) }}</span>
                </div>
                <div class="flex justify-between font-semibold">
                    <span>Saldo</span>
                    <span>
                        {{ money(balance) }}
                        <MoneyBs
                            :amount="balance"
                            class="text-right! text-neutral-500"
                        />
                    </span>
                </div>
            </template>
        </div>

        <div
            v-if="document.payments?.length"
            class="mt-3 space-y-1 border-t border-dashed border-neutral-300 pt-3 text-xs"
        >
            <p class="font-semibold text-neutral-600">Abonos</p>
            <div
                v-for="payment in document.payments"
                :key="payment.id"
                class="flex justify-between text-neutral-600"
            >
                <span>{{ formatDate(payment.paid_at) }}</span>
                <span>{{ money(payment.amount) }}</span>
            </div>
        </div>

        <div class="mt-4 flex justify-center">
            <span
                class="rounded-full border border-neutral-300 px-3 py-1 text-xs tracking-wide uppercase"
            >
                {{ statusLabels[document.status] }}
            </span>
        </div>

        <p
            v-if="document.notes"
            class="mt-3 text-center text-xs text-neutral-500"
        >
            {{ document.notes }}
        </p>
    </div>
</template>
