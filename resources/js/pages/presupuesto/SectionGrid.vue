<script setup lang="ts">
import { Info, Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import type { BudgetLine, BudgetSection } from '@/types';
import { formatMoney } from './lib';

type ColumnType =
    | 'text'
    | 'autocomplete'
    | 'date'
    | 'select'
    | 'money'
    | 'number'
    | 'computed';

export type GridColumn = {
    field: keyof BudgetLine;
    label: string;
    type: ColumnType;
    /** Shown as a native tooltip on hover, on the header and on each cell. */
    hint?: string;
    /** Datalist id for `autocomplete` columns. */
    list?: string;
    /** Fixed choices for `select` columns. */
    options?: string[];
    /** Read-only value for `computed` columns (e.g. precio total). */
    compute?: (row: BudgetLine) => number;
    total?: boolean;
    width?: string;
};

export type Accent = 'pink' | 'peach' | 'sky' | 'lavender' | 'slate';

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
 * Soft pastel palette: rosados, color piel y azules claros.
 */
const ACCENTS: Record<Accent, Record<string, string>> = {
    pink: {
        card: 'border-l-pink-300 dark:border-l-pink-400',
        head: 'bg-pink-100 text-pink-900 dark:bg-pink-950/40 dark:text-pink-50',
        badge: 'bg-pink-400/20 text-pink-700 dark:text-pink-200',
        btn: 'border-pink-300 bg-pink-50 text-pink-700 hover:bg-pink-100 dark:border-pink-800 dark:bg-pink-950/40 dark:text-pink-200 dark:hover:bg-pink-950/70',
        rowHover: 'hover:bg-pink-50 dark:hover:bg-pink-950/20',
        focus: 'focus:border-pink-400 focus:ring-2 focus:ring-pink-400/30 dark:focus:border-pink-500',
        totals: 'bg-pink-100 text-pink-900 dark:bg-pink-950/50 dark:text-pink-50',
    },
    peach: {
        card: 'border-l-orange-300 dark:border-l-orange-400',
        head: 'bg-orange-100 text-orange-900 dark:bg-orange-950/40 dark:text-orange-50',
        badge: 'bg-orange-400/20 text-orange-700 dark:text-orange-200',
        btn: 'border-orange-300 bg-orange-50 text-orange-700 hover:bg-orange-100 dark:border-orange-800 dark:bg-orange-950/40 dark:text-orange-200 dark:hover:bg-orange-950/70',
        rowHover: 'hover:bg-orange-50 dark:hover:bg-orange-950/20',
        focus: 'focus:border-orange-300 focus:ring-2 focus:ring-orange-300/30 dark:focus:border-orange-500',
        totals: 'bg-orange-100 text-orange-900 dark:bg-orange-950/50 dark:text-orange-50',
    },
    sky: {
        card: 'border-l-sky-300 dark:border-l-sky-400',
        head: 'bg-sky-100 text-sky-900 dark:bg-sky-950/40 dark:text-sky-50',
        badge: 'bg-sky-400/20 text-sky-700 dark:text-sky-200',
        btn: 'border-sky-300 bg-sky-50 text-sky-700 hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-200 dark:hover:bg-sky-950/70',
        rowHover: 'hover:bg-sky-50 dark:hover:bg-sky-950/20',
        focus: 'focus:border-sky-400 focus:ring-2 focus:ring-sky-400/30 dark:focus:border-sky-500',
        totals: 'bg-sky-100 text-sky-900 dark:bg-sky-950/50 dark:text-sky-50',
    },
    lavender: {
        card: 'border-l-indigo-200 dark:border-l-indigo-400',
        head: 'bg-indigo-100 text-indigo-900 dark:bg-indigo-950/40 dark:text-indigo-50',
        badge: 'bg-indigo-400/20 text-indigo-700 dark:text-indigo-200',
        btn: 'border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-200 dark:hover:bg-indigo-950/70',
        rowHover: 'hover:bg-indigo-50 dark:hover:bg-indigo-950/20',
        focus: 'focus:border-indigo-300 focus:ring-2 focus:ring-indigo-300/30 dark:focus:border-indigo-500',
        totals: 'bg-indigo-100 text-indigo-900 dark:bg-indigo-950/50 dark:text-indigo-50',
    },
    slate: {
        card: 'border-l-slate-300 dark:border-l-slate-500',
        head: 'bg-slate-100 text-slate-900 dark:bg-slate-800/60 dark:text-slate-50',
        badge: 'bg-slate-400/20 text-slate-700 dark:text-slate-200',
        btn: 'border-slate-300 bg-slate-50 text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-200 dark:hover:bg-slate-800',
        rowHover: 'hover:bg-slate-50 dark:hover:bg-slate-800/30',
        focus: 'focus:border-slate-400 focus:ring-2 focus:ring-slate-400/25 dark:focus:border-slate-500',
        totals: 'bg-slate-100 text-slate-900 dark:bg-slate-800/70 dark:text-slate-50',
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

const cellValue = (line: BudgetLine, column: GridColumn): number =>
    column.type === 'computed' && column.compute
        ? column.compute(line)
        : num(line[column.field] as string);

const totals = computed(() => {
    const result: Record<string, number> = {};

    for (const column of props.columns) {
        if (column.total) {
            result[column.field] = props.rows.reduce(
                (sum, row) => sum + cellValue(row, column),
                0,
            );
        }
    }

    return result;
});

function align(column: GridColumn): string {
    return column.type === 'money' ||
        column.type === 'number' ||
        column.type === 'computed'
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
                            v-else-if="column.type === 'date'"
                            type="date"
                            :value="
                                ((line[column.field] as string) ?? '').slice(
                                    0,
                                    10,
                                )
                            "
                            :disabled="readonly"
                            :title="column.hint"
                            class="h-7 w-full rounded-md border border-transparent bg-transparent px-2 transition outline-none focus:bg-white disabled:opacity-60 dark:focus:bg-neutral-950"
                            :class="a.focus"
                            @change="onText(line, column.field, $event)"
                        />
                        <select
                            v-else-if="column.type === 'select'"
                            :value="(line[column.field] as string) ?? ''"
                            :disabled="readonly"
                            :title="column.hint"
                            class="h-7 w-full rounded-md border border-transparent bg-transparent px-1 transition outline-none focus:bg-white disabled:opacity-60 dark:focus:bg-neutral-950 [&>option]:text-neutral-900"
                            :class="a.focus"
                            @change="onText(line, column.field, $event)"
                        >
                            <option value="">—</option>
                            <option
                                v-for="opt in column.options"
                                :key="opt"
                                :value="opt"
                            >
                                {{ opt }}
                            </option>
                        </select>
                        <input
                            v-else-if="
                                column.type === 'money' ||
                                column.type === 'number'
                            "
                            type="number"
                            :step="column.type === 'money' ? '0.01' : 'any'"
                            min="0"
                            :value="(line[column.field] as string) ?? ''"
                            :disabled="readonly"
                            :title="column.hint"
                            class="h-7 w-full rounded-md border border-transparent bg-transparent px-2 text-right tabular-nums transition outline-none focus:bg-white disabled:opacity-60 dark:focus:bg-neutral-950"
                            :class="a.focus"
                            @change="onNumber(line, column.field, $event)"
                        />
                        <span
                            v-else-if="column.type === 'computed'"
                            :title="column.hint"
                            class="w-full px-2 text-right font-semibold text-neutral-700 tabular-nums dark:text-neutral-300"
                        >
                            {{
                                formatMoney(
                                    column.compute ? column.compute(line) : 0,
                                    currency,
                                )
                            }}
                        </span>
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
                            {{ formatMoney(totals[column.field], currency) }}
                        </template>
                    </div>
                    <div></div>
                </div>
            </div>
        </div>
    </section>
</template>
