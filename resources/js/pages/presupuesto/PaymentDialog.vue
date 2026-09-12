<script setup lang="ts">
import { HandCoins } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { BudgetPayable } from '@/types';
import { formatMoney } from './sheet';

/**
 * Alta de un abono. El monto se carga en la moneda del período o en bolívares
 * con la tasa del día; en ese caso la conversión se muestra antes de guardar.
 */
const props = defineProps<{
    registros: BudgetPayable[];
    metodos: string[];
    currency: string;
    linkLabel: string;
    saving: boolean;
}>();

const emit = defineEmits<{ submit: [payload: Record<string, unknown>] }>();

const open = ref(false);

const blank = () => ({
    budget_line_id: props.registros[0]?.id ?? null,
    fecha: new Date().toISOString().slice(0, 10),
    method: '',
    amount_bs: null as number | null,
    exchange_rate: null as number | null,
    amount: null as number | null,
    notes: '',
});

const form = ref(blank());

const selected = computed(() =>
    props.registros.find((item) => item.id === form.value.budget_line_id),
);

/** Lo que se va a guardar: si hay bolívares y tasa, manda la conversión. */
const resolvedAmount = computed(() => {
    if (form.value.amount_bs && form.value.exchange_rate) {
        return (
            Math.round(
                (form.value.amount_bs / form.value.exchange_rate) * 100,
            ) / 100
        );
    }

    return form.value.amount ?? 0;
});

function submit(): void {
    emit('submit', {
        budget_line_id: form.value.budget_line_id,
        fecha: form.value.fecha,
        method: form.value.method || null,
        amount_bs: form.value.amount_bs,
        exchange_rate: form.value.exchange_rate,
        amount: form.value.amount_bs ? null : form.value.amount,
        notes: form.value.notes || null,
    });

    form.value = blank();
    open.value = false;
}
</script>

<template>
    <button
        type="button"
        class="inline-flex items-center gap-1 rounded border border-neutral-300 bg-white px-2 py-1 text-[12px] font-medium text-neutral-700 transition hover:bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-200 dark:hover:bg-neutral-800"
        @click="
            form = blank();
            open = true;
        "
    >
        <HandCoins class="size-3.5" />
        Agregar abono
    </button>

    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Nuevo abono</DialogTitle>
                <DialogDescription>
                    Podés cargarlo en {{ currency }} o en bolívares con la tasa
                    del día.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-3" @submit.prevent="submit">
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">{{ linkLabel }}</span>
                    <select
                        v-model.number="form.budget_line_id"
                        class="h-9 rounded border border-neutral-300 bg-white px-2 dark:border-neutral-700 dark:bg-neutral-950"
                    >
                        <option
                            v-for="item in registros"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.label }}
                        </option>
                    </select>
                    <span
                        v-if="selected"
                        class="text-xs text-neutral-500 dark:text-neutral-400"
                    >
                        Falta
                        {{ formatMoney(selected.restante, currency) }}
                        de {{ formatMoney(selected.precio_total, currency) }}
                    </span>
                </label>

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
                            list="abonos-metodos"
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
                        <span class="font-medium"
                            >Tasa (Bs por {{ currency }})</span
                        >
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

                <p class="text-sm text-neutral-600 dark:text-neutral-300">
                    Se va a registrar
                    <strong>{{ formatMoney(resolvedAmount, currency) }}</strong>
                </p>

                <datalist id="abonos-metodos">
                    <option v-for="name in metodos" :key="name" :value="name" />
                </datalist>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="open = false">
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        :disabled="saving || !form.budget_line_id"
                    >
                        Guardar abono
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
