<script setup lang="ts">
import { computed } from 'vue';
import type { BudgetInvoice } from '@/types';
import { formatDate, formatMoney, num } from './sheet';

/**
 * La factura del presupuesto en formato ticket, que es lo que se manda por
 * WhatsApp. Los colores van fijos y claros porque se captura como imagen.
 */
const props = defineProps<{
    invoice: BudgetInvoice;
    currency: string;
    /** Mes al que pertenece la factura. */
    periodo: string;
}>();

const esCompra = computed(() => props.invoice.tipo === 'compra');

const money = (value: number | string | null | undefined) =>
    formatMoney(value, props.currency);

/**
 * Los abonos como los hizo el contacto: un pago contra la factura entera es una
 * sola línea acá, aunque por dentro se haya repartido entre los movimientos.
 */
const abonos = computed(() => props.invoice.abonos);

/** Flete u otro cargo que se le sumó al contacto aparte de los movimientos. */
const adicional = computed(() => props.invoice.totales.adicional);
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
                {{ esCompra ? 'Compra' : 'Venta' }}
            </p>
            <h1 class="text-lg font-bold tracking-wide">FACTURA</h1>
            <p class="text-sm">{{ invoice.invoice_number ?? 'Sin número' }}</p>
            <p class="text-xs text-neutral-500">
                {{ formatDate(invoice.fecha) || periodo }}
            </p>
        </div>

        <div class="border-b border-dashed border-neutral-300 py-3 text-sm">
            <p class="font-semibold">
                {{ invoice.party_name ?? 'Sin contacto' }}
            </p>
            <p v-if="invoice.telefono" class="text-xs text-neutral-500">
                Tel: {{ invoice.telefono }}
            </p>
            <p class="text-xs text-neutral-500">{{ periodo }}</p>
        </div>

        <div class="divide-y divide-dashed divide-neutral-200 py-2">
            <p
                v-if="invoice.items.length === 0"
                class="py-3 text-center text-xs text-neutral-500"
            >
                Sin movimientos.
            </p>
            <div
                v-for="item in invoice.items"
                :key="item.id"
                class="flex justify-between gap-2 py-1.5 text-xs"
            >
                <div class="min-w-0">
                    <p class="truncate">
                        {{ item.producto ?? 'Sin producto' }}
                    </p>
                    <p class="text-neutral-500">
                        {{ num(item.cantidad) }} x {{ money(item.unit_price) }}
                        <span v-if="item.fecha">
                            · {{ formatDate(item.fecha) }}
                        </span>
                    </p>
                </div>
                <p class="shrink-0 font-medium">
                    {{ money(item.precio_total) }}
                </p>
            </div>
        </div>

        <div
            class="space-y-1 border-t border-dashed border-neutral-300 pt-3 text-sm"
        >
            <template v-if="adicional > 0">
                <div class="flex justify-between text-neutral-600">
                    <span>Subtotal</span>
                    <span>{{ money(invoice.totales.subtotal) }}</span>
                </div>
                <div class="flex justify-between text-neutral-600">
                    <span>Adicional</span>
                    <span>{{ money(adicional) }}</span>
                </div>
            </template>
            <div class="flex justify-between text-base font-bold">
                <span>Total</span>
                <span>{{ money(invoice.totales.total) }}</span>
            </div>
            <div
                v-if="invoice.totales.abonado > 0"
                class="flex justify-between text-neutral-600"
            >
                <span>Abonado</span>
                <span>{{ money(invoice.totales.abonado) }}</span>
            </div>
            <div
                v-if="invoice.totales.restante > 0"
                class="flex justify-between font-semibold"
            >
                <span>{{ esCompra ? 'Por pagar' : 'Por cobrar' }}</span>
                <span>{{ money(invoice.totales.restante) }}</span>
            </div>
        </div>

        <div
            v-if="abonos.length > 0"
            class="mt-3 space-y-1 border-t border-dashed border-neutral-300 pt-3 text-xs"
        >
            <p class="font-semibold text-neutral-600">Abonos</p>
            <div
                v-for="abono in abonos"
                :key="abono.id"
                class="flex justify-between text-neutral-600"
            >
                <span>
                    {{ formatDate(abono.fecha) }}
                    <template v-if="abono.method">
                        · {{ abono.method }}</template
                    >
                </span>
                <span>{{ money(abono.amount) }}</span>
            </div>
        </div>

        <div class="mt-4 flex justify-center">
            <span
                class="rounded-full border border-neutral-300 px-3 py-1 text-xs tracking-wide uppercase"
            >
                {{ invoice.totales.estado }}
            </span>
        </div>

        <p
            v-if="invoice.notas"
            class="mt-3 text-center text-xs text-neutral-500"
        >
            {{ invoice.notas }}
        </p>
    </div>
</template>
