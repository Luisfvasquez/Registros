<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { BudgetInvoice, BudgetLine } from '@/types';
import { formatMoney, num, round2, todayISO } from './sheet';

/**
 * Abono contra una factura entera. El monto se reparte entre los movimientos
 * pendientes, del más viejo al más nuevo, y el reparto se muestra antes de
 * guardar para que no haya sorpresas.
 */
const props = defineProps<{
    invoice: BudgetInvoice | null;
    metodos: string[];
    currency: string;
    saving: boolean;
}>();

const emit = defineEmits<{
    submit: [payload: Record<string, unknown>];
    close: [];
}>();

const open = computed({
    get: () => props.invoice !== null,
    set: (value: boolean) => {
        if (!value) {
            emit('close');
        }
    },
});

const blank = () => ({
    fecha: todayISO(),
    method: '',
    amount_bs: null as number | null,
    exchange_rate: null as number | null,
    amount: null as number | null,
    notes: '',
});

const form = ref(blank());

watch(
    () => props.invoice?.id,
    () => (form.value = blank()),
);

/** Lo que se va a guardar: si hay bolívares y tasa, manda la conversión. */
const monto = computed(() => {
    if (form.value.amount_bs && form.value.exchange_rate) {
        return round2(form.value.amount_bs / form.value.exchange_rate);
    }

    return form.value.amount ?? 0;
});

const pendientes = computed<BudgetLine[]>(
    () => props.invoice?.items.filter((item) => item.restante > 0.001) ?? [],
);

/**
 * Lo que la factura tiene pendiente, en el orden en que lo cubre el servidor:
 * los movimientos del más viejo al más nuevo y, al final, el cargo adicional.
 */
const objetivos = computed<{ id: string; label: string; restante: number }[]>(
    () => {
        const filas = pendientes.value.map((item) => ({
            id: `movimiento-${item.id}`,
            label: item.producto ?? 'Sin producto',
            restante: item.restante,
        }));

        const adicional = props.invoice?.totales.adicional_restante ?? 0;

        if (adicional > 0.001) {
            filas.push({
                id: 'adicional',
                label: 'Adicional (flete u otro cargo)',
                restante: adicional,
            });
        }

        return filas;
    },
);

const porCubrir = computed(() =>
    round2(objetivos.value.reduce((total, fila) => total + fila.restante, 0)),
);

/** Cómo caería el abono: mismo reparto en cascada que hace el servidor. */
const reparto = computed(() => {
    let restante = monto.value;

    return objetivos.value.map((objetivo) => {
        const parte = round2(
            Math.min(Math.max(restante, 0), objetivo.restante),
        );
        restante = round2(restante - parte);

        return { objetivo, parte, queda: round2(objetivo.restante - parte) };
    });
});

const excede = computed(() => monto.value > porCubrir.value + 0.001);

function submit(): void {
    emit('submit', {
        fecha: form.value.fecha,
        method: form.value.method || null,
        amount_bs: form.value.amount_bs,
        exchange_rate: form.value.exchange_rate,
        amount: form.value.amount_bs ? null : form.value.amount,
        notes: form.value.notes || null,
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>
                    Abonar {{ invoice?.invoice_number ?? 'factura' }}
                </DialogTitle>
                <DialogDescription>
                    El monto se reparte entre los movimientos pendientes de
                    {{ invoice?.party_name ?? 'la factura' }}, del más viejo al
                    más nuevo, y al final cubre el cargo adicional. Falta
                    {{ formatMoney(porCubrir, currency) }}.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-3" @submit.prevent="submit">
                <div class="grid grid-cols-2 gap-3">
                    <label class="grid gap-1 text-sm">
                        <span class="font-medium">Fecha del pago</span>
                        <input
                            v-model="form.fecha"
                            type="date"
                            class="h-9 rounded border border-neutral-300 bg-white px-2 dark:border-neutral-700 dark:bg-neutral-950"
                        />
                    </label>

                    <label class="grid gap-1 text-sm">
                        <span class="font-medium">Método</span>
                        <input
                            v-model="form.method"
                            type="text"
                            list="factura-metodos"
                            class="h-9 rounded border border-neutral-300 bg-white px-2 dark:border-neutral-700 dark:bg-neutral-950"
                        />
                    </label>

                    <label class="grid gap-1 text-sm">
                        <span class="font-medium">Monto Bs</span>
                        <input
                            v-model.number="form.amount_bs"
                            type="number"
                            step="0.01"
                            class="h-9 rounded border border-neutral-300 bg-white px-2 text-right dark:border-neutral-700 dark:bg-neutral-950"
                        />
                    </label>

                    <label class="grid gap-1 text-sm">
                        <span class="font-medium">
                            Tasa (Bs por {{ currency }})
                        </span>
                        <input
                            v-model.number="form.exchange_rate"
                            type="number"
                            step="0.0001"
                            class="h-9 rounded border border-neutral-300 bg-white px-2 text-right dark:border-neutral-700 dark:bg-neutral-950"
                        />
                    </label>

                    <label class="grid gap-1 text-sm">
                        <span class="font-medium">Monto {{ currency }}</span>
                        <input
                            v-model.number="form.amount"
                            type="number"
                            step="0.01"
                            :disabled="!!form.amount_bs"
                            class="h-9 rounded border border-neutral-300 bg-white px-2 text-right disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-950"
                        />
                    </label>

                    <label class="grid gap-1 text-sm">
                        <span class="font-medium">Notas</span>
                        <input
                            v-model="form.notes"
                            type="text"
                            class="h-9 rounded border border-neutral-300 bg-white px-2 dark:border-neutral-700 dark:bg-neutral-950"
                        />
                    </label>
                </div>

                <div
                    v-if="objetivos.length > 0"
                    class="rounded border border-neutral-200 dark:border-neutral-800"
                >
                    <table class="w-full text-[13px]">
                        <thead
                            class="bg-neutral-100 text-[11px] uppercase dark:bg-neutral-900"
                        >
                            <tr>
                                <th class="px-2 py-1 text-left">Movimiento</th>
                                <th class="px-2 py-1 text-right">Falta</th>
                                <th class="px-2 py-1 text-right">Se abona</th>
                                <th class="px-2 py-1 text-right">Queda</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="fila in reparto"
                                :key="fila.objetivo.id"
                                class="border-t border-neutral-100 dark:border-neutral-800"
                                :class="
                                    fila.parte > 0
                                        ? ''
                                        : 'text-neutral-400 dark:text-neutral-600'
                                "
                            >
                                <td class="px-2 py-1">
                                    {{ fila.objetivo.label }}
                                </td>
                                <td class="px-2 py-1 text-right tabular-nums">
                                    {{
                                        formatMoney(
                                            fila.objetivo.restante,
                                            currency,
                                        )
                                    }}
                                </td>
                                <td
                                    class="px-2 py-1 text-right font-medium tabular-nums"
                                    :class="
                                        fila.parte > 0
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : ''
                                    "
                                >
                                    {{ formatMoney(fila.parte, currency) }}
                                </td>
                                <td class="px-2 py-1 text-right tabular-nums">
                                    {{ formatMoney(fila.queda, currency) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p
                    v-if="excede"
                    class="text-sm text-rose-600 dark:text-rose-400"
                >
                    El abono supera lo que falta en la factura ({{
                        formatMoney(porCubrir, currency)
                    }}).
                </p>

                <datalist id="factura-metodos">
                    <option v-for="name in metodos" :key="name" :value="name" />
                </datalist>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        @click="emit('close')"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        :disabled="saving || excede || num(monto) <= 0"
                    >
                        Abonar {{ formatMoney(monto, currency) }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
