<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { formatMoney, formatNumber, num } from './sheet';

export type SheetRow = { id: number } & Record<string, unknown>;

export type SheetOption = { value: string | number | null; label: string };

export type SheetColumnType =
    'text' | 'date' | 'number' | 'money' | 'select' | 'computed';

export type SheetColumn = {
    key: string;
    label: string;
    /** Por defecto `text`. `computed` es de solo lectura y usa `value`. */
    type?: SheetColumnType;
    width?: string;
    /** Opciones fijas, o calculadas por fila cuando dependen de otra celda. */
    options?: SheetOption[] | ((row: SheetRow) => SheetOption[]);
    /** Id de un `<datalist>` para autocompletar una columna de texto. */
    list?: string;
    readonly?: boolean;
    /** Suma la columna en la fila de totales. */
    total?: boolean;
    hint?: string;
    /** Valor mostrado: obligatorio en `computed`, opcional en el resto. */
    value?: (row: SheetRow) => string | number | null;
};

export type SheetTone =
    'slate' | 'pink' | 'sky' | 'amber' | 'emerald' | 'violet';

const props = withDefaults(
    defineProps<{
        columns: SheetColumn[];
        rows: SheetRow[];
        currency?: string;
        tone?: SheetTone;
        /** Oculta el botón de agregar y bloquea todas las celdas. */
        readonly?: boolean;
        addLabel?: string;
        /** Oculta el botón de agregar cuando el alta necesita un formulario. */
        canAdd?: boolean;
        emptyText?: string;
        /** Muestra la fila de totales al pie. */
        totals?: boolean;
        /** Alto máximo del área desplazable; el encabezado queda fijo. */
        maxHeight?: string;
    }>(),
    {
        currency: 'USD',
        tone: 'slate',
        addLabel: 'Agregar fila',
        canAdd: true,
        emptyText: 'Sin registros todavía.',
        totals: true,
        maxHeight: 'calc(100vh - 19rem)',
    },
);

const emit = defineEmits<{
    add: [];
    update: [row: SheetRow, patch: Record<string, unknown>];
    remove: [row: SheetRow];
}>();

/**
 * Clases completas por color, para que el escáner de Tailwind las conserve.
 */
const TONES: Record<SheetTone, { head: string; band: string; totals: string }> =
    {
        slate: {
            head: 'bg-slate-200 text-slate-900 dark:bg-slate-800 dark:text-slate-100',
            band: 'border-slate-300 dark:border-slate-700',
            totals: 'bg-slate-100 text-slate-900 dark:bg-slate-800/70 dark:text-slate-100',
        },
        pink: {
            head: 'bg-pink-200 text-pink-950 dark:bg-pink-950 dark:text-pink-100',
            band: 'border-pink-300 dark:border-pink-900',
            totals: 'bg-pink-100 text-pink-950 dark:bg-pink-950/60 dark:text-pink-100',
        },
        sky: {
            head: 'bg-sky-200 text-sky-950 dark:bg-sky-950 dark:text-sky-100',
            band: 'border-sky-300 dark:border-sky-900',
            totals: 'bg-sky-100 text-sky-950 dark:bg-sky-950/60 dark:text-sky-100',
        },
        amber: {
            head: 'bg-amber-200 text-amber-950 dark:bg-amber-950 dark:text-amber-100',
            band: 'border-amber-300 dark:border-amber-900',
            totals: 'bg-amber-100 text-amber-950 dark:bg-amber-950/60 dark:text-amber-100',
        },
        emerald: {
            head: 'bg-emerald-200 text-emerald-950 dark:bg-emerald-950 dark:text-emerald-100',
            band: 'border-emerald-300 dark:border-emerald-900',
            totals: 'bg-emerald-100 text-emerald-950 dark:bg-emerald-950/60 dark:text-emerald-100',
        },
        violet: {
            head: 'bg-violet-200 text-violet-950 dark:bg-violet-950 dark:text-violet-100',
            band: 'border-violet-300 dark:border-violet-900',
            totals: 'bg-violet-100 text-violet-950 dark:bg-violet-950/60 dark:text-violet-100',
        },
    };

const tone = computed(() => TONES[props.tone]);

const optionsFor = (row: SheetRow, column: SheetColumn): SheetOption[] =>
    typeof column.options === 'function'
        ? column.options(row)
        : (column.options ?? []);

const isNumeric = (column: SheetColumn): boolean =>
    column.type === 'money' ||
    column.type === 'number' ||
    column.type === 'computed';

const cellValue = (row: SheetRow, column: SheetColumn): string => {
    const raw = column.value ? column.value(row) : (row[column.key] as string);

    return raw === null || raw === undefined ? '' : String(raw);
};

const displayValue = (row: SheetRow, column: SheetColumn): string => {
    const raw = column.value ? column.value(row) : row[column.key];

    if (raw === null || raw === undefined || raw === '') {
        return '—';
    }

    if (column.type === 'money') {
        return formatMoney(raw as number, props.currency);
    }

    if (column.type === 'computed' || column.type === 'number') {
        return formatNumber(raw as number);
    }

    return String(raw);
};

const totalsRow = computed<Record<string, number>>(() => {
    const result: Record<string, number> = {};

    for (const column of props.columns) {
        if (!column.total) {
            continue;
        }

        result[column.key] = props.rows.reduce(
            (sum, row) =>
                sum +
                num(
                    column.value
                        ? column.value(row)
                        : (row[column.key] as string),
                ),
            0,
        );
    }

    return result;
});

const isLocked = (column: SheetColumn): boolean =>
    props.readonly || column.readonly === true || column.type === 'computed';

function onText(row: SheetRow, column: SheetColumn, event: Event): void {
    const value = (event.target as HTMLInputElement).value.trim();

    emit('update', row, { [column.key]: value === '' ? null : value });
}

function onNumberInput(row: SheetRow, column: SheetColumn, event: Event): void {
    const raw = (event.target as HTMLInputElement).value;

    emit('update', row, { [column.key]: raw === '' ? null : Number(raw) });
}

function onSelect(row: SheetRow, column: SheetColumn, event: Event): void {
    const raw = (event.target as HTMLSelectElement).value;
    const option = optionsFor(row, column).find(
        (candidate) => String(candidate.value ?? '') === raw,
    );

    emit('update', row, {
        [column.key]: raw === '' ? null : (option?.value ?? raw),
    });
}
</script>

<template>
    <div
        class="overflow-hidden rounded-lg border border-neutral-300 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-950"
    >
        <div class="overflow-auto" :style="{ maxHeight }">
            <table class="w-max border-collapse text-[13px] whitespace-nowrap">
                <thead class="sticky top-0 z-20">
                    <tr :class="tone.head">
                        <th
                            class="sticky left-0 z-30 w-10 border border-r-2 px-1 py-1.5 text-center text-[11px] font-semibold"
                            :class="[tone.head, tone.band]"
                        >
                            #
                        </th>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            class="border px-2 py-1.5 text-[11px] font-bold tracking-wide uppercase"
                            :class="[
                                tone.band,
                                isNumeric(column) ? 'text-right' : 'text-left',
                                column.hint ? 'cursor-help' : '',
                            ]"
                            :style="{ minWidth: column.width ?? '8rem' }"
                            :title="column.hint"
                        >
                            {{ column.label }}
                        </th>
                        <th
                            class="w-16 border px-2 py-1.5 text-[11px] font-bold"
                            :class="tone.band"
                        >
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-if="rows.length === 0">
                        <td
                            :colspan="columns.length + 2"
                            class="border px-3 py-8 text-center text-neutral-400 dark:border-neutral-700"
                        >
                            {{ emptyText }}
                        </td>
                    </tr>

                    <tr
                        v-for="(row, index) in rows"
                        :key="row.id"
                        class="group hover:bg-neutral-50 dark:hover:bg-neutral-900/60"
                    >
                        <th
                            class="sticky left-0 z-10 border border-r-2 bg-neutral-100 px-1 py-0 text-center text-[11px] font-normal text-neutral-500 dark:bg-neutral-900 dark:text-neutral-400"
                            :class="tone.band"
                            scope="row"
                        >
                            {{ index + 1 }}
                        </th>

                        <td
                            v-for="column in columns"
                            :key="column.key"
                            class="border p-0 dark:border-neutral-700"
                            :class="[
                                tone.band,
                                isLocked(column)
                                    ? 'bg-neutral-50/60 dark:bg-neutral-900/40'
                                    : '',
                            ]"
                            :style="{ minWidth: column.width ?? '8rem' }"
                        >
                            <select
                                v-if="
                                    column.type === 'select' &&
                                    !isLocked(column)
                                "
                                :value="cellValue(row, column)"
                                class="h-8 w-full bg-transparent px-1.5 outline-none focus:bg-sky-50 focus:ring-2 focus:ring-sky-400/60 focus:ring-inset dark:focus:bg-sky-950/40 [&>option]:text-neutral-900"
                                @change="onSelect(row, column, $event)"
                            >
                                <option value="">—</option>
                                <option
                                    v-for="option in optionsFor(row, column)"
                                    :key="String(option.value)"
                                    :value="String(option.value ?? '')"
                                >
                                    {{ option.label }}
                                </option>
                            </select>

                            <input
                                v-else-if="
                                    column.type === 'date' && !isLocked(column)
                                "
                                type="date"
                                :value="cellValue(row, column).slice(0, 10)"
                                class="h-8 w-full bg-transparent px-1.5 outline-none focus:bg-sky-50 focus:ring-2 focus:ring-sky-400/60 focus:ring-inset dark:focus:bg-sky-950/40"
                                @change="onText(row, column, $event)"
                            />

                            <input
                                v-else-if="
                                    (column.type === 'money' ||
                                        column.type === 'number') &&
                                    !isLocked(column)
                                "
                                type="number"
                                :step="column.type === 'money' ? '0.01' : 'any'"
                                :value="cellValue(row, column)"
                                class="h-8 w-full bg-transparent px-1.5 text-right tabular-nums outline-none focus:bg-sky-50 focus:ring-2 focus:ring-sky-400/60 focus:ring-inset dark:focus:bg-sky-950/40"
                                @change="onNumberInput(row, column, $event)"
                            />

                            <input
                                v-else-if="!isLocked(column)"
                                type="text"
                                :list="column.list"
                                :value="cellValue(row, column)"
                                class="h-8 w-full bg-transparent px-1.5 outline-none focus:bg-sky-50 focus:ring-2 focus:ring-sky-400/60 focus:ring-inset dark:focus:bg-sky-950/40"
                                @change="onText(row, column, $event)"
                            />

                            <span
                                v-else
                                class="flex h-8 items-center px-1.5 text-neutral-700 dark:text-neutral-300"
                                :class="
                                    isNumeric(column)
                                        ? 'justify-end font-medium tabular-nums'
                                        : ''
                                "
                            >
                                {{ displayValue(row, column) }}
                            </span>
                        </td>

                        <td
                            class="border px-1 text-center dark:border-neutral-700"
                            :class="tone.band"
                        >
                            <div
                                class="flex items-center justify-center gap-0.5"
                            >
                                <slot name="row-actions" :row="row" />

                                <button
                                    v-if="!readonly"
                                    type="button"
                                    class="rounded p-1 text-neutral-300 transition group-hover:text-neutral-400 hover:bg-rose-100 hover:text-rose-600 dark:text-neutral-600 dark:hover:bg-rose-950/40 dark:hover:text-rose-400"
                                    title="Eliminar fila"
                                    @click="emit('remove', row)"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>

                <tfoot
                    v-if="totals && rows.length > 0"
                    class="sticky bottom-0 z-20"
                >
                    <tr :class="tone.totals">
                        <th
                            class="sticky left-0 z-30 border border-r-2 px-1 py-1.5"
                            :class="[tone.totals, tone.band]"
                        ></th>
                        <td
                            v-for="(column, index) in columns"
                            :key="column.key"
                            class="border px-2 py-1.5 font-semibold tabular-nums"
                            :class="[
                                tone.band,
                                isNumeric(column) ? 'text-right' : 'text-left',
                            ]"
                        >
                            <span
                                v-if="index === 0"
                                class="text-[11px] font-bold tracking-wide uppercase opacity-70"
                            >
                                Totales
                            </span>
                            <template v-else-if="column.total">
                                {{
                                    column.type === 'money'
                                        ? formatMoney(
                                              totalsRow[column.key],
                                              currency,
                                          )
                                        : formatNumber(totalsRow[column.key])
                                }}
                            </template>
                        </td>
                        <td class="border" :class="tone.band"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div
            v-if="!readonly"
            class="flex items-center gap-2 border-t border-neutral-200 bg-neutral-50 px-2 py-1.5 dark:border-neutral-800 dark:bg-neutral-900"
        >
            <button
                v-if="canAdd"
                type="button"
                class="inline-flex items-center gap-1 rounded border border-neutral-300 bg-white px-2 py-1 text-[12px] font-medium text-neutral-700 transition hover:bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-200 dark:hover:bg-neutral-800"
                @click="emit('add')"
            >
                <Plus class="size-3.5" />
                {{ addLabel }}
            </button>

            <slot name="toolbar" />

            <span class="ml-auto text-[11px] text-neutral-400">
                {{ rows.length }} {{ rows.length === 1 ? 'fila' : 'filas' }}
            </span>
        </div>
    </div>
</template>
