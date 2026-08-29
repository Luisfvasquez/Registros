<script setup lang="ts">
import { Info, Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import type { BudgetLine, BudgetSection } from '@/types';
import { formatMoney } from './lib';

type ColumnType = 'text' | 'autocomplete' | 'money' | 'percent' | 'check';

export type GridColumn = {
    field: keyof BudgetLine;
    label: string;
    type: ColumnType;
    /** Shown as a native tooltip on hover, on the header and on each cell. */
    hint?: string;
    list?: string;
    total?: boolean;
    width?: string;
};

export type Accent = 'emerald' | 'violet' | 'rose' | 'sky' | 'amber' | 'slate';

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        section: BudgetSection;
        columns: GridColumn[];
        rows: BudgetLine[];
        currency: string;
        accent?: Accent;
        readonly?: boolean;
    }>(),
    { accent: 'slate' },
);

const emit = defineEmits<{
    add: [section: BudgetSection];
    update: [line: BudgetLine, patch: Record<string, unknown>];
    remove: [line: BudgetLine];
}>();

/**
 * Full class strings per accent so Tailwind's scanner keeps them in the build.
 */
const ACCENTS: Record<Accent, Record<string, string>> = {
    emerald: {
        card: 'border-l-emerald-400 dark:border-l-emerald-500',
        head: 'bg-emerald-100/70 text-emerald-950 dark:bg-emerald-950/40 dark:text-emerald-50',
        badge: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
        btn: 'border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-950/70',
        rowHover: 'hover:bg-emerald-50 dark:hover:bg-emerald-950/20',
        focus: 'focus:border-emerald-400 focus:ring-2 focus:ring-emerald-500/25 dark:focus:border-emerald-500',
        totals: 'bg-emerald-100/80 text-emerald-950 dark:bg-emerald-950/50 dark:text-emerald-50',
        check: 'accent-emerald-600',
    },
    violet: {
        card: 'border-l-violet-400 dark:border-l-violet-500',
        head: 'bg-violet-100/70 text-violet-950 dark:bg-violet-950/40 dark:text-violet-50',
        badge: 'bg-violet-500/15 text-violet-700 dark:text-violet-300',
        btn: 'border-violet-300 bg-violet-50 text-violet-700 hover:bg-violet-100 dark:border-violet-800 dark:bg-violet-950/40 dark:text-violet-300 dark:hover:bg-violet-950/70',
        rowHover: 'hover:bg-violet-50 dark:hover:bg-violet-950/20',
        focus: 'focus:border-violet-400 focus:ring-2 focus:ring-violet-500/25 dark:focus:border-violet-500',
        totals: 'bg-violet-100/80 text-violet-950 dark:bg-violet-950/50 dark:text-violet-50',
        check: 'accent-violet-600',
    },
    rose: {
        card: 'border-l-rose-400 dark:border-l-rose-500',
        head: 'bg-rose-100/70 text-rose-950 dark:bg-rose-950/40 dark:text-rose-50',
        badge: 'bg-rose-500/15 text-rose-700 dark:text-rose-300',
        btn: 'border-rose-300 bg-rose-50 text-rose-700 hover:bg-rose-100 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-950/70',
        rowHover: 'hover:bg-rose-50 dark:hover:bg-rose-950/20',
        focus: 'focus:border-rose-400 focus:ring-2 focus:ring-rose-500/25 dark:focus:border-rose-500',
        totals: 'bg-rose-100/80 text-rose-950 dark:bg-rose-950/50 dark:text-rose-50',
        check: 'accent-rose-600',
    },
    sky: {
        card: 'border-l-sky-400 dark:border-l-sky-500',
        head: 'bg-sky-100/70 text-sky-950 dark:bg-sky-950/40 dark:text-sky-50',
        badge: 'bg-sky-500/15 text-sky-700 dark:text-sky-300',
        btn: 'border-sky-300 bg-sky-50 text-sky-700 hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-300 dark:hover:bg-sky-950/70',
        rowHover: 'hover:bg-sky-50 dark:hover:bg-sky-950/20',
        focus: 'focus:border-sky-400 focus:ring-2 focus:ring-sky-500/25 dark:focus:border-sky-500',
        totals: 'bg-sky-100/80 text-sky-950 dark:bg-sky-950/50 dark:text-sky-50',
        check: 'accent-sky-600',
    },
    amber: {
        card: 'border-l-amber-400 dark:border-l-amber-500',
        head: 'bg-amber-100/70 text-amber-950 dark:bg-amber-950/40 dark:text-amber-50',
        badge: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
        btn: 'border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300 dark:hover:bg-amber-950/70',
        rowHover: 'hover:bg-amber-50 dark:hover:bg-amber-950/20',
        focus: 'focus:border-amber-400 focus:ring-2 focus:ring-amber-500/25 dark:focus:border-amber-500',
        totals: 'bg-amber-100/80 text-amber-950 dark:bg-amber-950/50 dark:text-amber-50',
        check: 'accent-amber-600',
    },
    slate: {
        card: 'border-l-slate-400 dark:border-l-slate-500',
        head: 'bg-slate-100 text-slate-900 dark:bg-slate-800/60 dark:text-slate-50',
        badge: 'bg-slate-500/15 text-slate-700 dark:text-slate-300',
        btn: 'border-slate-300 bg-slate-50 text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-200 dark:hover:bg-slate-800',
        rowHover: 'hover:bg-slate-50 dark:hover:bg-slate-800/30',
        focus: 'focus:border-slate-400 focus:ring-2 focus:ring-slate-500/25 dark:focus:border-slate-500',
        totals: 'bg-slate-100 text-slate-900 dark:bg-slate-800/70 dark:text-slate-50',
        check: 'accent-slate-600',
    },
};

const a = computed(() => ACCENTS[props.accent]);

const num = (value: string | number | null | undefined) => {
    const parsed =
        typeof value === 'number' ? value : Number.parseFloat(value ?? '0');

    return Number.isFinite(parsed) ? parsed : 0;
};

const gridTemplate = computed(() => {
    const cols = props.columns
        .map((column) => column.width ?? 'minmax(6rem, 1fr)')
        .join(' ');

    return `${cols} 2rem`;
});

const totals = computed(() => {
    const result: Record<string, number> = {};

    for (const column of props.columns) {
        if (column.total) {
            result[column.field] = props.rows.reduce(
                (sum, row) => sum + num(row[column.field] as string),
                0,
            );
        }
    }

    return result;
});

const hasPlanActual = computed(
    () =>
        props.columns.some((c) => c.field === 'planned' && c.total) &&
        props.columns.some((c) => c.field === 'actual' && c.total),
);

/** For income, spending more than planned is good; for everything else it's bad. */
const delta = computed(() => {
    const planned = totals.value.planned ?? 0;
    const actual = totals.value.actual ?? 0;

    return props.section === 'ingreso' ? actual - planned : planned - actual;
});

function align(column: GridColumn): string {
    return column.type === 'money' || column.type === 'percent'
        ? 'justify-end text-right'
        : '';
}

function onText(line: BudgetLine, field: keyof BudgetLine, event: Event) {
    const value = (event.target as HTMLInputElement).value.trim();

    emit('update', line, { [field]: value === '' ? null : value });
}

function onNumber(line: BudgetLine, field: keyof BudgetLine, event: Event) {
    const raw = (event.target as HTMLInputElement).value;

    emit('update', line, { [field]: raw === '' ? null : Number(raw) });
}

function onCheck(line: BudgetLine, field: keyof BudgetLine, event: Event) {
    emit('update', line, {
        [field]: (event.target as HTMLInputElement).checked,
    });
}
</script>

<template>
    <section
        class="overflow-hidden rounded-xl border border-l-4 border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
        :class="a.card"
    >
        <header
            class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2"
            :class="a.head"
        >
            <h2 class="text-sm font-semibold">{{ title }}</h2>
            <span
                class="rounded-full px-1.5 py-0.5 text-[11px] font-semibold tabular-nums"
                :class="a.badge"
            >
                {{ rows.length }}
            </span>
            <p v-if="description" class="text-[11px] opacity-70">
                {{ description }}
            </p>

            <span
                v-if="hasPlanActual && rows.length > 0"
                class="rounded-full px-2 py-0.5 text-[11px] font-semibold tabular-nums"
                :class="
                    delta >= 0
                        ? 'bg-emerald-500/20 text-emerald-800 dark:text-emerald-200'
                        : 'bg-rose-500/20 text-rose-800 dark:text-rose-200'
                "
            >
                {{ delta >= 0 ? '▲ A favor' : '▼ Excedido' }}
                {{ formatMoney(Math.abs(delta), currency) }}
            </span>

            <button
                v-if="!readonly"
                type="button"
                class="ml-auto inline-flex items-center gap-1 rounded-md border px-2 py-1 text-[12px] font-medium transition"
                :class="a.btn"
                @click="emit('add', section)"
            >
                <Plus class="size-3.5" />
                Agregar fila
            </button>
        </header>

        <div class="overflow-x-auto">
            <div class="min-w-md text-[13px]">
                <!-- Column headers -->
                <div
                    class="grid items-center border-b border-neutral-200 bg-neutral-50/80 px-2 py-1.5 text-[11px] font-semibold tracking-wide text-neutral-500 uppercase dark:border-neutral-800 dark:bg-neutral-900/60 dark:text-neutral-400"
                    :style="{ gridTemplateColumns: gridTemplate }"
                >
                    <div
                        v-for="column in columns"
                        :key="String(column.field)"
                        class="flex items-center gap-0.5 truncate px-1"
                        :class="[
                            align(column),
                            column.hint ? 'cursor-help' : '',
                        ]"
                        :title="column.hint"
                    >
                        <span
                            :class="
                                column.hint
                                    ? 'underline decoration-dotted decoration-1 underline-offset-4'
                                    : ''
                            "
                        >
                            {{ column.label }}
                        </span>
                        <Info
                            v-if="column.hint"
                            class="size-3 shrink-0 opacity-50"
                        />
                    </div>
                    <div></div>
                </div>

                <!-- Rows -->
                <p
                    v-if="rows.length === 0"
                    class="px-3 py-6 text-center text-neutral-400"
                >
                    Sin registros todavía.
                </p>

                <div
                    v-for="line in rows"
                    :key="line.id"
                    class="group grid items-center border-b border-neutral-100 px-2 transition last:border-0 odd:bg-neutral-50/40 dark:border-neutral-900 dark:odd:bg-neutral-900/20"
                    :class="a.rowHover"
                    :style="{ gridTemplateColumns: gridTemplate }"
                >
                    <div
                        v-for="column in columns"
                        :key="String(column.field)"
                        class="flex min-w-0 items-center px-0.5 py-0.5"
                        :class="align(column)"
                    >
                        <input
                            v-if="
                                column.type === 'text' ||
                                column.type === 'autocomplete'
                            "
                            type="text"
                            :list="
                                column.type === 'autocomplete'
                                    ? column.list
                                    : undefined
                            "
                            :value="(line[column.field] as string) ?? ''"
                            :disabled="readonly"
                            :title="column.hint"
                            class="h-7 w-full rounded-md border border-transparent bg-transparent px-2 transition outline-none focus:bg-white disabled:opacity-60 dark:focus:bg-neutral-950"
                            :class="a.focus"
                            @change="onText(line, column.field, $event)"
                        />
                        <input
                            v-else-if="
                                column.type === 'money' ||
                                column.type === 'percent'
                            "
                            type="number"
                            step="0.01"
                            :min="column.type === 'percent' ? 0 : undefined"
                            :max="column.type === 'percent' ? 100 : undefined"
                            :value="(line[column.field] as string) ?? ''"
                            :disabled="readonly"
                            :title="column.hint"
                            class="h-7 w-full rounded-md border border-transparent bg-transparent px-2 text-right tabular-nums transition outline-none focus:bg-white disabled:opacity-60 dark:focus:bg-neutral-950"
                            :class="a.focus"
                            @change="onNumber(line, column.field, $event)"
                        />
                        <input
                            v-else-if="column.type === 'check'"
                            type="checkbox"
                            :checked="Boolean(line[column.field])"
                            :disabled="readonly"
                            :title="column.hint"
                            class="size-4"
                            :class="a.check"
                            @change="onCheck(line, column.field, $event)"
                        />
                    </div>
                    <div class="flex justify-center">
                        <button
                            v-if="!readonly"
                            type="button"
                            class="rounded p-1 text-neutral-300 opacity-0 transition group-hover:opacity-100 hover:bg-rose-100 hover:text-rose-600 dark:text-neutral-600 dark:hover:bg-rose-950/40 dark:hover:text-rose-400"
                            title="Eliminar fila"
                            @click="emit('remove', line)"
                        >
                            <Trash2 class="size-3.5" />
                        </button>
                    </div>
                </div>

                <!-- Totals -->
                <div
                    v-if="rows.length > 0"
                    class="grid items-center px-2 py-1.5 font-semibold"
                    :class="a.totals"
                    :style="{ gridTemplateColumns: gridTemplate }"
                >
                    <div
                        v-for="(column, index) in columns"
                        :key="String(column.field)"
                        class="flex items-center px-1 tabular-nums"
                        :class="align(column)"
                    >
                        <span
                            v-if="index === 0"
                            class="text-[11px] font-semibold tracking-wide uppercase opacity-70"
                        >
                            Totales
                        </span>
                        <template v-if="column.total">
                            {{
                                column.type === 'percent'
                                    ? `${totals[column.field].toFixed(1)}%`
                                    : formatMoney(
                                          totals[column.field],
                                          currency,
                                      )
                            }}
                        </template>
                    </div>
                    <div></div>
                </div>
            </div>
        </div>
    </section>
</template>
