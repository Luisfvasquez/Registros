<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarPlus, Loader2, LogOut, Trash2, Wallet } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import BudgetController, {
    index,
} from '@/actions/App/Http/Controllers/BudgetController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Toaster } from '@/components/ui/sonner';
import { logout } from '@/routes';
import type {
    BudgetLine,
    BudgetPeriod,
    BudgetPeriodOption,
    BudgetSection,
    BudgetSummary,
} from '@/types';
import BudgetCharts from './BudgetCharts.vue';
import {
    api,
    computeSummary,
    firstError,
    lineTotal,
    lineUtilidad,
    MONTHS,
    periodLabel,
} from './lib';
import ReportPanel from './ReportPanel.vue';
import SectionGrid from './SectionGrid.vue';
import type { Accent, GridColumn } from './SectionGrid.vue';

const props = defineProps<{
    periods: BudgetPeriodOption[];
    period: BudgetPeriod | null;
    lines: BudgetLine[];
    summary: BudgetSummary | null;
    suggestions: {
        parties: string[];
        productos: string[];
        payment_methods: string[];
        payment_statuses: string[];
    };
}>();

const lines = ref<BudgetLine[]>(props.lines.map((line) => ({ ...line })));
const periodState = ref<BudgetPeriod | null>(
    props.period ? { ...props.period } : null,
);

watch(
    () => [props.period?.id, props.lines],
    () => {
        lines.value = props.lines.map((line) => ({ ...line }));
        periodState.value = props.period ? { ...props.period } : null;
    },
);

const readonly = computed(() => periodState.value?.status === 'cerrado');

const liveSummary = computed<BudgetSummary>(() => computeSummary(lines.value));

/** Ids of relación-con-clientes rows that already have a linked venta row. */
const registeredClientIds = computed(() =>
    lines.value
        .filter(
            (line) => line.section === 'venta' && line.linked_line_id != null,
        )
        .map((line) => line.linked_line_id as number),
);

const tabs = [
    { key: 'general', label: 'Vista general' },
    { key: 'resumen', label: 'Resumen' },
    { key: 'compras', label: 'Compras' },
    { key: 'ventas', label: 'Ventas' },
    { key: 'clientes', label: 'Relación clientes' },
    { key: 'resultados', label: 'Ganancias y pérdidas' },
    { key: 'graficos', label: 'Gráficos' },
] as const;

const activeTab = ref<(typeof tabs)[number]['key']>('general');

const PAYMENT_STATUSES = ['Pagado', 'Pendiente', 'Abonado'];
const PAYMENT_METHODS = [
    'Efectivo',
    'Transferencia',
    'Pago móvil',
    'Tarjeta',
    'Zelle',
    'Divisas',
];

function linesFor(section: BudgetSection): BudgetLine[] {
    return lines.value.filter((line) => line.section === section);
}

const dateColumn: GridColumn = {
    field: 'fecha',
    label: 'Fecha',
    type: 'date',
    width: '135px',
};

const purchaseColumns: GridColumn[] = [
    dateColumn,
    {
        field: 'party_name',
        label: 'Proveedor',
        type: 'autocomplete',
        list: 'budget-parties',
        width: '16%',
        hint: 'Nombre del proveedor al que se le compró la mercancía.',
    },
    {
        field: 'producto',
        label: 'Producto',
        type: 'autocomplete',
        list: 'budget-productos',
        width: '16%',
        hint: 'Producto o mercancía comprada.',
    },
    {
        field: 'cantidad',
        label: 'Cantidad',
        type: 'number',
        width: '95px',
        hint: 'Unidades compradas.',
    },
    {
        field: 'unit_price',
        label: 'Precio unitario',
        type: 'money',
        hint: 'Precio pagado por unidad.',
    },
    {
        field: 'precio_total',
        label: 'Precio total',
        type: 'computed',
        compute: lineTotal,
        total: true,
        hint: 'Cantidad × precio unitario. Se calcula solo.',
    },
    {
        field: 'payment_status',
        label: 'Estado de pago',
        type: 'select',
        options: PAYMENT_STATUSES,
        width: '130px',
        hint: 'Si la compra ya se pagó al proveedor o queda pendiente.',
    },
];

const saleColumns: GridColumn[] = [
    dateColumn,
    {
        field: 'cantidad',
        label: 'Cantidad',
        type: 'number',
        width: '95px',
        hint: 'Unidades vendidas.',
    },
    {
        field: 'producto',
        label: 'Producto',
        type: 'autocomplete',
        list: 'budget-productos',
        width: '18%',
        hint: 'Producto vendido.',
    },
    {
        field: 'unit_price',
        label: 'Precio unitario',
        type: 'money',
        hint: 'Precio de venta por unidad.',
    },
    {
        field: 'precio_total',
        label: 'Precio total',
        type: 'computed',
        compute: lineTotal,
        total: true,
        hint: 'Cantidad × precio unitario. Se calcula solo.',
    },
    {
        field: 'payment_method',
        label: 'Método de pago',
        type: 'select',
        options: PAYMENT_METHODS,
        width: '140px',
        hint: 'Forma en que el cliente pagó la venta.',
    },
];

const clientColumns: GridColumn[] = [
    dateColumn,
    {
        field: 'party_name',
        label: 'Cliente',
        type: 'autocomplete',
        list: 'budget-parties',
        width: '16%',
        hint: 'Nombre del cliente.',
    },
    {
        field: 'producto',
        label: 'Producto',
        type: 'autocomplete',
        list: 'budget-productos',
        width: '16%',
        hint: 'Producto entregado al cliente.',
    },
    {
        field: 'cantidad',
        label: 'Cantidad',
        type: 'number',
        width: '95px',
        hint: 'Unidades entregadas.',
    },
    {
        field: 'unit_price',
        label: 'Precio unitario',
        type: 'money',
        hint: 'Precio acordado por unidad.',
    },
    {
        field: 'precio_total',
        label: 'Precio total',
        type: 'computed',
        compute: lineTotal,
        total: true,
        hint: 'Cantidad × precio unitario. Se calcula solo.',
    },
    {
        field: 'payment_status',
        label: 'Estado del pago',
        type: 'select',
        options: PAYMENT_STATUSES,
        width: '130px',
        hint: 'Si el cliente ya pagó, abonó o está pendiente.',
    },
];

const resultColumns: GridColumn[] = [
    dateColumn,
    {
        field: 'ganancia',
        label: 'Ganancia',
        type: 'money',
        total: true,
        hint: 'Ganancia bruta obtenida en la fecha.',
    },
    {
        field: 'gastos_personales',
        label: 'Gastos personales',
        type: 'money',
        total: true,
        hint: 'Dinero del negocio usado en gastos personales.',
    },
    {
        field: 'perdidas_mercancia',
        label: 'Pérdidas mercancía',
        type: 'money',
        total: true,
        hint: 'Pérdidas por mercancía mala: valor de la mercancía dañada, vencida o no vendible.',
    },
    {
        field: 'inversiones',
        label: 'Inversiones',
        type: 'money',
        total: true,
        hint: 'Dinero reinvertido en el negocio.',
    },
    {
        field: 'total_utilidad',
        label: 'Total utilidad',
        type: 'computed',
        compute: lineUtilidad,
        total: true,
        hint: 'Ganancia − gastos personales − pérdidas − inversiones. Se calcula solo.',
    },
];

/**
 * Every module in one place. Reused for the individual section tabs and for the
 * "Vista general" that stacks them all on a single page.
 */
const sectionDefs = [
    {
        tab: 'compras',
        title: 'Compras',
        description: 'Mercancía comprada a proveedores.',
        section: 'compra',
        columns: purchaseColumns,
        accent: 'peach',
        groupBy: 'party_name',
        linkable: false,
    },
    {
        tab: 'ventas',
        title: 'Ventas',
        description: 'Ventas del período y su forma de cobro.',
        section: 'venta',
        columns: saleColumns,
        accent: 'sky',
        groupBy: undefined,
        linkable: false,
    },
    {
        tab: 'clientes',
        title: 'Relación con clientes',
        description: 'Ventas a clientes y estado de pago de cada una.',
        section: 'cliente',
        columns: clientColumns,
        accent: 'pink',
        groupBy: 'party_name',
        linkable: true,
    },
    {
        tab: 'resultados',
        title: 'Ganancias, gastos y pérdidas',
        description: 'Cierre mensual: ganancia, gastos, pérdidas y utilidad.',
        section: 'resultado',
        columns: resultColumns,
        accent: 'lavender',
        groupBy: undefined,
        linkable: false,
    },
] as const satisfies ReadonlyArray<{
    tab: (typeof tabs)[number]['key'];
    title: string;
    description: string;
    section: BudgetSection;
    columns: GridColumn[];
    accent: Accent;
    groupBy?: keyof BudgetLine;
    linkable?: boolean;
}>;

async function addLine(section: BudgetSection, seed?: Record<string, unknown>) {
    if (!periodState.value) {
        return;
    }

    try {
        const { line } = await api<{ line: BudgetLine }>(
            BudgetController.storeLine.url(periodState.value.id),
            'POST',
            { section, ...seed },
        );

        const seedParty =
            typeof seed?.party_name === 'string' ? seed.party_name : '';

        if (seedParty !== '') {
            // Drop the new row right after the last one of that proveedor /
            // cliente so it stays grouped before the next reload.
            let insertAfter = -1;

            lines.value.forEach((item, index) => {
                if (
                    item.section === section &&
                    (item.party_name ?? '') === seedParty
                ) {
                    insertAfter = index;
                }
            });

            if (insertAfter !== -1) {
                lines.value.splice(insertAfter + 1, 0, line);

                return;
            }
        }

        lines.value.push(line);
    } catch (error) {
        toast.error(firstError(error));
    }
}

/**
 * Register a "relación con clientes" row as a sale. Creates the linked venta row
 * so the amount is typed once and counted once.
 */
async function registerInSales(line: BudgetLine) {
    try {
        const { line: sale } = await api<{ line: BudgetLine }>(
            BudgetController.linkLineToSale.url(line.id),
            'POST',
        );

        lines.value.push(sale);
        toast.success('Venta registrada desde relación con clientes.');
    } catch (error) {
        toast.error(firstError(error));
    }
}

async function updateLine(line: BudgetLine, patch: Record<string, unknown>) {
    const target = lines.value.find((item) => item.id === line.id);

    if (!target) {
        return;
    }

    const previous: Record<string, unknown> = {};

    for (const key of Object.keys(patch)) {
        previous[key] = (target as Record<string, unknown>)[key];
        (target as Record<string, unknown>)[key] = patch[key];
    }

    try {
        const { line: fresh } = await api<{ line: BudgetLine }>(
            BudgetController.updateLine.url(line.id),
            'PATCH',
            patch,
        );

        Object.assign(target, fresh);
    } catch (error) {
        Object.assign(target, previous);
        toast.error(firstError(error));
    }
}

async function removeLine(line: BudgetLine) {
    const idx = lines.value.findIndex((item) => item.id === line.id);

    if (idx === -1) {
        return;
    }

    const [removed] = lines.value.splice(idx, 1);

    // Server drops the FK (nullOnDelete); unlink any local sale row too so it
    // stops showing as "registrada desde cliente" and its fields unlock.
    const unlinked = lines.value.filter(
        (item) => item.linked_line_id === line.id,
    );

    for (const sale of unlinked) {
        sale.linked_line_id = null;
    }

    try {
        await api(BudgetController.destroyLine.url(line.id), 'DELETE');
    } catch (error) {
        lines.value.splice(idx, 0, removed);

        for (const sale of unlinked) {
            sale.linked_line_id = line.id;
        }

        toast.error(firstError(error));
    }
}

async function updatePeriod(patch: Record<string, unknown>) {
    if (!periodState.value) {
        return;
    }

    const previous: Record<string, unknown> = {};

    for (const key of Object.keys(patch)) {
        previous[key] = (periodState.value as Record<string, unknown>)[key];
        (periodState.value as Record<string, unknown>)[key] = patch[key];
    }

    try {
        const { period } = await api<{ period: BudgetPeriod }>(
            BudgetController.updatePeriod.url(periodState.value.id),
            'PATCH',
            patch,
        );

        periodState.value = { ...period };
    } catch (error) {
        Object.assign(periodState.value, previous);
        toast.error(firstError(error));
    }
}

function switchPeriod(event: Event) {
    const id = Number((event.target as HTMLSelectElement).value);

    router.get(index.url(), { period: id }, { preserveScroll: true });
}

function deletePeriod() {
    if (!periodState.value) {
        return;
    }

    if (
        !confirm(
            `¿Eliminar el período ${periodLabel(periodState.value)} y todos sus registros?`,
        )
    ) {
        return;
    }

    router.delete(BudgetController.destroyPeriod.url(periodState.value.id));
}

const now = new Date();
const createOpen = ref(false);
const createForm = useForm({
    year: now.getFullYear(),
    month: now.getMonth() + 1,
    currency: props.periods[0]?.currency ?? 'USD',
    available_money: 0,
});

function submitCreate() {
    createForm
        .transform((data) => ({
            ...data,
            currency: data.currency.toUpperCase(),
        }))
        .post(BudgetController.storePeriod.url(), {
            onSuccess: () => {
                createOpen.value = false;
                createForm.reset();
            },
        });
}
</script>

<template>
    <Head title="Presupuesto" />

    <datalist id="budget-parties">
        <option
            v-for="value in suggestions.parties"
            :key="value"
            :value="value"
        />
    </datalist>
    <datalist id="budget-productos">
        <option
            v-for="value in suggestions.productos"
            :key="value"
            :value="value"
        />
    </datalist>

    <div
        class="min-h-screen bg-rose-50/50 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100"
    >
        <header
            class="sticky top-0 z-20 flex flex-wrap items-center gap-3 border-b border-pink-100 bg-white/80 px-4 py-3 backdrop-blur dark:border-neutral-800 dark:bg-neutral-900/80"
        >
            <div class="mr-auto flex items-center gap-3">
                <span
                    class="flex size-8 items-center justify-center rounded-lg bg-linear-to-br from-pink-300 to-sky-300 text-white shadow-sm"
                >
                    <Wallet class="size-4" />
                </span>
                <h1 class="text-lg font-semibold">Control del negocio</h1>
                <select
                    v-if="periods.length > 0"
                    :value="periodState?.id"
                    class="rounded-md border border-pink-200 bg-transparent px-2 py-1.5 text-sm font-medium outline-none focus:border-pink-400 focus:ring-2 focus:ring-pink-300/30 dark:border-neutral-700"
                    @change="switchPeriod"
                >
                    <option
                        v-for="option in periods"
                        :key="option.id"
                        :value="option.id"
                    >
                        {{ MONTHS[option.month - 1] }} {{ option.year }} ·
                        {{ option.currency }}
                        <template v-if="option.status === 'cerrado'"
                            >(cerrado)</template
                        >
                    </option>
                </select>
            </div>

            <Dialog v-model:open="createOpen">
                <DialogTrigger as-child>
                    <Button size="sm" variant="outline">
                        <CalendarPlus class="size-4" />
                        Nuevo período
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Nuevo período</DialogTitle>
                    </DialogHeader>
                    <form class="grid gap-4" @submit.prevent="submitCreate">
                        <div class="grid grid-cols-2 gap-3">
                            <label class="grid gap-1 text-sm">
                                Año
                                <input
                                    v-model.number="createForm.year"
                                    type="number"
                                    min="2000"
                                    max="2100"
                                    class="rounded-md border border-neutral-300 bg-transparent px-2 py-1.5 outline-none dark:border-neutral-700"
                                />
                                <span
                                    v-if="createForm.errors.year"
                                    class="text-xs text-red-600"
                                >
                                    {{ createForm.errors.year }}
                                </span>
                            </label>
                            <label class="grid gap-1 text-sm">
                                Mes
                                <select
                                    v-model.number="createForm.month"
                                    class="rounded-md border border-neutral-300 bg-transparent px-2 py-1.5 outline-none dark:border-neutral-700"
                                >
                                    <option
                                        v-for="(name, idx) in MONTHS"
                                        :key="name"
                                        :value="idx + 1"
                                    >
                                        {{ name }}
                                    </option>
                                </select>
                                <span
                                    v-if="createForm.errors.month"
                                    class="text-xs text-red-600"
                                >
                                    {{ createForm.errors.month }}
                                </span>
                            </label>
                            <label class="grid gap-1 text-sm">
                                Moneda
                                <input
                                    v-model="createForm.currency"
                                    maxlength="3"
                                    class="rounded-md border border-neutral-300 bg-transparent px-2 py-1.5 uppercase outline-none dark:border-neutral-700"
                                />
                                <span
                                    v-if="createForm.errors.currency"
                                    class="text-xs text-red-600"
                                >
                                    {{ createForm.errors.currency }}
                                </span>
                            </label>
                            <label class="grid gap-1 text-sm">
                                Capital inicial
                                <input
                                    v-model.number="createForm.available_money"
                                    type="number"
                                    step="0.01"
                                    class="rounded-md border border-neutral-300 bg-transparent px-2 py-1.5 outline-none dark:border-neutral-700"
                                />
                            </label>
                        </div>
                        <DialogFooter>
                            <Button
                                type="submit"
                                :disabled="createForm.processing"
                            >
                                <Loader2
                                    v-if="createForm.processing"
                                    class="size-4 animate-spin"
                                />
                                Crear período
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Button
                v-if="periodState"
                size="sm"
                variant="ghost"
                class="text-rose-600 hover:text-rose-700"
                @click="deletePeriod"
            >
                <Trash2 class="size-4" />
                Eliminar
            </Button>

            <Button
                size="sm"
                variant="ghost"
                @click="router.post(logout().url)"
            >
                <LogOut class="size-4" />
                Salir
            </Button>
        </header>

        <main class="mx-auto w-full max-w-[1700px] px-4 py-6">
            <div
                v-if="!periodState"
                class="mx-auto mt-16 max-w-md rounded-xl border border-dashed border-pink-200 p-8 text-center dark:border-neutral-700"
            >
                <h2 class="text-base font-semibold">Todavía no hay períodos</h2>
                <p class="mt-1 text-sm text-neutral-500">
                    Creá tu primer período mensual para empezar a registrar
                    compras, ventas y resultados.
                </p>
                <Button class="mt-4" @click="createOpen = true">
                    <CalendarPlus class="size-4" />
                    Crear período
                </Button>
            </div>

            <template v-else>
                <nav class="mb-5 flex flex-wrap gap-1.5">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                        :class="
                            activeTab === tab.key
                                ? 'bg-linear-to-r from-pink-300 to-sky-300 text-rose-950 shadow-sm'
                                : 'bg-white text-neutral-600 ring-1 ring-pink-100 hover:bg-pink-50 hover:text-neutral-900 dark:bg-neutral-900 dark:text-neutral-300 dark:ring-neutral-800 dark:hover:bg-neutral-800'
                        "
                        @click="activeTab = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </nav>

                <div
                    v-if="activeTab === 'general'"
                    class="grid grid-cols-1 gap-6 xl:grid-cols-2 xl:items-start"
                >
                    <div
                        class="grid grid-cols-1 gap-6 xl:col-span-2 xl:grid-cols-3 xl:items-start"
                    >
                        <ReportPanel
                            :period="periodState"
                            :summary="liveSummary"
                            :readonly="readonly"
                            @update-period="updatePeriod"
                        />
                        <BudgetCharts
                            :lines="lines"
                            :currency="periodState.currency"
                        />
                    </div>
                    <SectionGrid
                        v-for="def in sectionDefs"
                        :key="def.tab"
                        :title="def.title"
                        :description="def.description"
                        :section="def.section"
                        :columns="def.columns"
                        :rows="linesFor(def.section)"
                        :currency="periodState.currency"
                        :accent="def.accent"
                        :readonly="readonly"
                        :group-by="def.groupBy"
                        :linkable="def.linkable"
                        :registered-ids="registeredClientIds"
                        @add="addLine"
                        @update="updateLine"
                        @remove="removeLine"
                        @register="registerInSales"
                    />
                </div>

                <div v-show="activeTab === 'resumen'">
                    <ReportPanel
                        :period="periodState"
                        :summary="liveSummary"
                        :readonly="readonly"
                        @update-period="updatePeriod"
                    />
                </div>

                <div
                    v-for="def in sectionDefs"
                    v-show="activeTab === def.tab"
                    :key="def.tab"
                >
                    <SectionGrid
                        :title="def.title"
                        :description="def.description"
                        :section="def.section"
                        :columns="def.columns"
                        :rows="linesFor(def.section)"
                        :currency="periodState.currency"
                        :accent="def.accent"
                        :readonly="readonly"
                        :group-by="def.groupBy"
                        :linkable="def.linkable"
                        :registered-ids="registeredClientIds"
                        @add="addLine"
                        @update="updateLine"
                        @remove="removeLine"
                        @register="registerInSales"
                    />
                </div>

                <div
                    v-if="activeTab === 'graficos'"
                    class="grid gap-4 lg:grid-cols-2"
                >
                    <BudgetCharts
                        :lines="lines"
                        :currency="periodState.currency"
                    />
                </div>
            </template>
        </main>
    </div>

    <Toaster rich-colors />
</template>
