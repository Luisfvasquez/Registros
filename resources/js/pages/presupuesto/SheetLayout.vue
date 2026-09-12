<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarPlus, Check, Star, Table2, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Toaster } from '@/components/ui/sonner';
import periodsRoutes from '@/routes/presupuesto/periods';
import type { BudgetPeriod, BudgetPeriodOption } from '@/types';
import { MONTHS, TABS, periodLabel } from './sheet';

/**
 * El "libro" de un mes: barra superior con el período y sus acciones, la hoja
 * abierta en el medio y la tira de pestañas abajo, como en una planilla.
 */
const props = defineProps<{
    period: BudgetPeriod;
    periods: BudgetPeriodOption[];
    activePeriodId: number | null;
    /** Clave de la pestaña abierta, de `TABS`. */
    tab: string;
    title: string;
    subtitle?: string;
}>();

const creating = ref(false);

const current = computed(() =>
    TABS.find((candidate) => candidate.key === props.tab),
);

const isActive = computed(() => props.activePeriodId === props.period.id);

const today = new Date();

const form = useForm({
    year: today.getFullYear(),
    month: today.getMonth() + 1,
    currency: 'USD',
    available_money: 0,
    notes: '',
});

/** Al cambiar de período se abre la misma hoja del período nuevo. */
function goToPeriod(event: Event): void {
    const id = Number((event.target as HTMLSelectElement).value);
    const tab = current.value ?? TABS[0];

    router.visit(tab.href(id));
}

function createPeriod(): void {
    form.post(periodsRoutes.store.url(), {
        onSuccess: () => {
            creating.value = false;
            form.reset();
        },
    });
}

function activatePeriod(): void {
    router.post(
        periodsRoutes.activate.url(props.period.id),
        {},
        { preserveScroll: true },
    );
}

function destroyPeriod(): void {
    if (!confirm(`¿Eliminar ${periodLabel(props.period)} y todas sus hojas?`)) {
        return;
    }

    router.delete(periodsRoutes.destroy.url(props.period.id));
}

/** Colores de cada pestaña, en clases completas para el build de Tailwind. */
const TAB_TONES: Record<string, string> = {
    slate: 'border-t-slate-400 text-slate-700 dark:text-slate-300',
    pink: 'border-t-pink-400 text-pink-700 dark:text-pink-300',
    sky: 'border-t-sky-400 text-sky-700 dark:text-sky-300',
    amber: 'border-t-amber-400 text-amber-700 dark:text-amber-300',
    emerald: 'border-t-emerald-400 text-emerald-700 dark:text-emerald-300',
    violet: 'border-t-violet-400 text-violet-700 dark:text-violet-300',
};
</script>

<template>
    <Head :title="`${title} · ${periodLabel(period)}`" />

    <div
        class="flex h-screen flex-col bg-neutral-100 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100"
    >
        <header
            class="flex shrink-0 flex-wrap items-center gap-2 border-b border-neutral-300 bg-white px-3 py-2 dark:border-neutral-800 dark:bg-neutral-900"
        >
            <span
                class="flex size-8 items-center justify-center rounded bg-emerald-600 text-white"
            >
                <Table2 class="size-4" />
            </span>

            <div class="mr-2">
                <h1 class="text-sm leading-tight font-semibold">{{ title }}</h1>
                <p class="text-[11px] text-neutral-500 dark:text-neutral-400">
                    {{ subtitle ?? periodLabel(period) }}
                </p>
            </div>

            <select
                class="h-8 rounded border border-neutral-300 bg-white px-2 text-sm dark:border-neutral-700 dark:bg-neutral-950"
                :value="period.id"
                title="Período abierto"
                @change="goToPeriod"
            >
                <option
                    v-for="option in periods"
                    :key="option.id"
                    :value="option.id"
                >
                    {{ periodLabel(option) }}
                    {{ option.status === 'cerrado' ? '(cerrado)' : '' }}
                </option>
            </select>

            <Button variant="outline" size="sm" @click="creating = true">
                <CalendarPlus class="size-4" />
                Nuevo período
            </Button>

            <Button
                v-if="!isActive"
                variant="ghost"
                size="sm"
                title="Abrir este período por defecto"
                @click="activatePeriod"
            >
                <Star class="size-4" />
                Marcar activo
            </Button>
            <span
                v-else
                class="inline-flex items-center gap-1 rounded bg-emerald-100 px-2 py-1 text-[11px] font-medium text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300"
            >
                <Check class="size-3" />
                Período activo
            </span>

            <div class="ml-auto flex items-center gap-2">
                <slot name="actions" />

                <Button
                    variant="ghost"
                    size="sm"
                    class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-950/40"
                    title="Eliminar período"
                    @click="destroyPeriod"
                >
                    <Trash2 class="size-4" />
                </Button>
            </div>
        </header>

        <main class="min-h-0 flex-1 overflow-auto p-3">
            <slot />
        </main>

        <nav
            class="flex shrink-0 items-end gap-0.5 overflow-x-auto border-t border-neutral-300 bg-neutral-200 px-2 pt-1 dark:border-neutral-800 dark:bg-neutral-900"
        >
            <Link
                v-for="item in TABS"
                :key="item.key"
                :href="item.href(period.id)"
                class="shrink-0 rounded-t border-t-2 border-r border-l px-3 py-1.5 text-[12px] font-medium transition"
                :class="[
                    TAB_TONES[item.tone],
                    item.key === tab
                        ? 'border-neutral-300 bg-white dark:border-neutral-700 dark:bg-neutral-950'
                        : 'border-transparent border-t-transparent text-neutral-500 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800',
                ]"
            >
                {{ item.label }}
            </Link>
        </nav>
    </div>

    <Dialog v-model:open="creating">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Nuevo período</DialogTitle>
                <DialogDescription>
                    Cada período es un mes con sus propias hojas. El Directorio
                    de proveedores y clientes se comparte entre todos.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-3" @submit.prevent="createPeriod">
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

                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        @click="creating = false"
                    >
                        Cancelar
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        Crear período
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Toaster rich-colors />
</template>
