<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Table2 } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Toaster } from '@/components/ui/sonner';
import periods from '@/routes/presupuesto/periods';
import { MONTHS } from './sheet';

/**
 * Primera pantalla del módulo mientras no existe ningún período.
 */
const today = new Date();

const form = useForm({
    year: today.getFullYear(),
    month: today.getMonth() + 1,
    currency: 'USD',
    available_money: 0,
    notes: '',
});
</script>

<template>
    <Head title="Presupuesto" />

    <div
        class="flex min-h-screen items-center justify-center bg-neutral-100 p-6 dark:bg-neutral-950"
    >
        <div
            class="w-full max-w-lg rounded-lg border border-neutral-300 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-900"
        >
            <span
                class="mb-3 flex size-10 items-center justify-center rounded bg-emerald-600 text-white"
            >
                <Table2 class="size-5" />
            </span>

            <h1 class="text-lg font-semibold">Creá el primer período</h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                Cada período es un mes de trabajo con sus hojas: directorio,
                compras, ventas, abonos, gastos, ganancias y facturas.
            </p>

            <form
                class="mt-5 grid gap-3"
                @submit.prevent="form.post(periods.store.url())"
            >
                <div class="grid grid-cols-2 gap-3">
                    <label class="grid gap-1 text-sm">
                        <span class="font-medium">Mes</span>
                        <select
                            v-model.number="form.month"
                            class="h-9 rounded border border-neutral-300 bg-white px-2 dark:border-neutral-700 dark:bg-neutral-950"
                        >
                            <option
                                v-for="(name, index) in MONTHS"
                                :key="name"
                                :value="index + 1"
                            >
                                {{ name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.month" />
                    </label>

                    <label class="grid gap-1 text-sm">
                        <span class="font-medium">Año</span>
                        <input
                            v-model.number="form.year"
                            type="number"
                            class="h-9 rounded border border-neutral-300 bg-white px-2 dark:border-neutral-700 dark:bg-neutral-950"
                        />
                        <InputError :message="form.errors.year" />
                    </label>

                    <label class="grid gap-1 text-sm">
                        <span class="font-medium">Moneda</span>
                        <input
                            v-model="form.currency"
                            type="text"
                            maxlength="3"
                            class="h-9 rounded border border-neutral-300 bg-white px-2 uppercase dark:border-neutral-700 dark:bg-neutral-950"
                        />
                        <InputError :message="form.errors.currency" />
                    </label>

                    <label class="grid gap-1 text-sm">
                        <span class="font-medium">Dinero disponible</span>
                        <input
                            v-model.number="form.available_money"
                            type="number"
                            step="0.01"
                            class="h-9 rounded border border-neutral-300 bg-white px-2 text-right dark:border-neutral-700 dark:bg-neutral-950"
                        />
                        <InputError :message="form.errors.available_money" />
                    </label>
                </div>

                <Button type="submit" :disabled="form.processing">
                    Crear período
                </Button>
            </form>
        </div>
    </div>

    <Toaster rich-colors />
</template>
