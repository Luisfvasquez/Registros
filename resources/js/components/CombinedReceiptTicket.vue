<script setup lang="ts">
import { computed } from 'vue';
import MoneyBs from '@/components/MoneyBs.vue';
import { formatDate } from '@/lib/utils';
import type { Contact, Document } from '@/types';

type DocumentWithTotals = Document & { balance: number; paid_total: number };

const props = defineProps<{
    documents: DocumentWithTotals[];
    contact: Contact;
}>();

const operationLabel = computed(() =>
    props.documents[0]?.operation_type === 'compra' ? 'Compra' : 'Venta',
);

const grandTotal = computed(() =>
    props.documents.reduce((sum, document) => sum + Number(document.total), 0),
);

const grandBalance = computed(() =>
    props.documents
        .filter((document) => document.document_type === 'factura')
        .reduce((sum, document) => sum + Number(document.balance), 0),
);

const hasInvoices = computed(() =>
    props.documents.some((document) => document.document_type === 'factura'),
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
            <h1 class="text-lg font-bold tracking-wide">COMPROBANTE</h1>
            <p class="text-xs text-neutral-500">
                {{ documents.length }} documento(s)
            </p>
        </div>

        <div class="border-b border-dashed border-neutral-300 py-3 text-sm">
            <p class="font-semibold">{{ contact.name }}</p>
            <p v-if="contact.document" class="text-xs text-neutral-500">
                Doc: {{ contact.document }}
            </p>
            <p v-if="contact.phone" class="text-xs text-neutral-500">
                Tel: {{ contact.phone_country_code }} {{ contact.phone }}
            </p>
        </div>

        <div class="divide-y divide-dashed divide-neutral-200 py-2">
            <div
                v-for="document in documents"
                :key="document.id"
                class="py-2 text-xs"
            >
                <div class="flex justify-between gap-2 font-medium">
                    <span>{{ document.number }}</span>
                    <span>{{ money(document.total) }}</span>
                </div>
                <p class="text-neutral-500">
                    {{ formatDate(document.issue_date) }}
                    <span v-if="document.document_type === 'factura'">
                        · Saldo {{ money(document.balance) }}</span
                    >
                </p>
                <div
                    v-for="item in document.items ?? []"
                    :key="item.id"
                    class="flex justify-between gap-2 pl-2 text-neutral-500"
                >
                    <span class="min-w-0 truncate"
                        >{{ Number(item.quantity) }} ×
                        {{ item.description }}</span
                    >
                    <span class="shrink-0">{{ money(item.subtotal) }}</span>
                </div>
            </div>
        </div>

        <div
            class="space-y-1 border-t border-dashed border-neutral-300 pt-3 text-sm"
        >
            <div class="flex justify-between text-base font-bold">
                <span>Total</span>
                <span>
                    {{ money(grandTotal) }}
                    <MoneyBs
                        :amount="grandTotal"
                        class="text-right! text-neutral-500"
                    />
                </span>
            </div>
            <div v-if="hasInvoices" class="flex justify-between font-semibold">
                <span>Saldo</span>
                <span>
                    {{ money(grandBalance) }}
                    <MoneyBs
                        :amount="grandBalance"
                        class="text-right! text-neutral-500"
                    />
                </span>
            </div>
        </div>
    </div>
</template>
