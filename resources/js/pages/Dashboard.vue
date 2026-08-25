<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowDownCircle,
    ArrowUpCircle,
    TrendingDown,
    TrendingUp,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import MoneyBs from '@/components/MoneyBs.vue';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { Document, DocumentStatus } from '@/types';

const props = defineProps<{
    metrics: {
        sales_total: number;
        purchases_total: number;
        expenses_total: number;
        profit: number;
        receivable: number;
    };
    recentDocuments: Document[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Panel', href: dashboard() }],
    },
});

const isProfit = computed(() => props.metrics.profit >= 0);

const statusLabels: Record<DocumentStatus, string> = {
    pendiente: 'Pendiente',
    parcial: 'Parcial',
    pagado: 'Pagado',
    convertido: 'Convertido',
    anulado: 'Anulado',
};

const statusStyles: Record<DocumentStatus, string> = {
    pendiente:
        'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    parcial: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    pagado: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    convertido:
        'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400',
    anulado: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
};

function money(value: number) {
    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
    }).format(value);
}
</script>

<template>
    <Head title="Panel" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border p-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">Ventas del mes</p>
                    <ArrowUpCircle class="size-4 text-emerald-600" />
                </div>
                <p class="mt-2 text-2xl font-semibold">
                    {{ money(metrics.sales_total) }}
                </p>
                <MoneyBs :amount="metrics.sales_total" />
            </div>

            <div class="rounded-xl border p-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">Compras del mes</p>
                    <ArrowDownCircle class="size-4 text-red-600" />
                </div>
                <p class="mt-2 text-2xl font-semibold">
                    {{ money(metrics.purchases_total) }}
                </p>
                <MoneyBs :amount="metrics.purchases_total" />
            </div>

            <div class="rounded-xl border p-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">
                        Ganancia / Pérdida
                    </p>
                    <TrendingUp
                        v-if="isProfit"
                        class="size-4 text-emerald-600"
                    />
                    <TrendingDown v-else class="size-4 text-red-600" />
                </div>
                <p
                    :class="[
                        'mt-2 text-2xl font-semibold',
                        isProfit ? 'text-emerald-600' : 'text-red-600',
                    ]"
                >
                    {{ money(metrics.profit) }}
                </p>
                <MoneyBs :amount="metrics.profit" />
                <p
                    v-if="metrics.expenses_total > 0"
                    class="mt-1 text-xs text-muted-foreground"
                >
                    Incluye {{ money(metrics.expenses_total) }} en gastos del
                    mes
                </p>
            </div>

            <div class="rounded-xl border p-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">
                        Cuentas por cobrar
                    </p>
                    <Wallet class="size-4 text-amber-600" />
                </div>
                <p class="mt-2 text-2xl font-semibold">
                    {{ money(metrics.receivable) }}
                </p>
                <MoneyBs :amount="metrics.receivable" />
            </div>
        </div>

        <div class="rounded-xl border">
            <div class="border-b p-4">
                <h3 class="text-sm font-medium">
                    Últimas ventas y compras del mes
                </h3>
            </div>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Número</TableHead>
                        <TableHead>Contacto</TableHead>
                        <TableHead class="hidden md:table-cell"
                            >Operación</TableHead
                        >
                        <TableHead class="hidden sm:table-cell"
                            >Fecha</TableHead
                        >
                        <TableHead>Total</TableHead>
                        <TableHead>Estado</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty
                        v-if="recentDocuments.length === 0"
                        :colspan="6"
                    >
                        Sin movimientos este mes todavía.
                    </TableEmpty>
                    <TableRow
                        v-for="document in recentDocuments"
                        :key="document.id"
                    >
                        <TableCell class="font-medium">
                            <Link
                                :href="DocumentController.show.url(document)"
                                class="hover:underline"
                            >
                                {{ document.number }}
                            </Link>
                        </TableCell>
                        <TableCell class="max-w-32 truncate sm:max-w-none">{{
                            document.contact?.name
                        }}</TableCell>
                        <TableCell class="hidden capitalize md:table-cell">{{
                            document.operation_type
                        }}</TableCell>
                        <TableCell class="hidden sm:table-cell">{{
                            formatDate(document.issue_date)
                        }}</TableCell>
                        <TableCell>
                            {{ money(Number(document.total)) }}
                            <MoneyBs :amount="document.total" />
                        </TableCell>
                        <TableCell>
                            <Badge
                                :class="statusStyles[document.status]"
                                variant="secondary"
                            >
                                {{ statusLabels[document.status] }}
                            </Badge>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
