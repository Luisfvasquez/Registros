<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRightLeft, Plus, Search } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { ref, watch } from 'vue';
import DocumentController, {
    index,
    create,
} from '@/actions/App/Http/Controllers/DocumentController';
import Heading from '@/components/Heading.vue';
import MoneyBs from '@/components/MoneyBs.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import type {
    Document,
    DocumentStatus,
    DocumentType,
    PaginatedData,
} from '@/types';

const props = defineProps<{
    documents: PaginatedData<Document>;
    filters: {
        search?: string;
        operation_type?: string;
        document_type?: string;
        status?: string;
        from?: string;
        to?: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: dashboard() },
            { title: 'Ventas/Compras', href: index() },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const operationType = ref(props.filters.operation_type ?? 'all');
const documentType = ref(props.filters.document_type ?? 'all');
const status = ref(props.filters.status ?? 'all');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

const runFilter = useDebounceFn(() => {
    router.get(
        index().url,
        {
            search: search.value || undefined,
            operation_type:
                operationType.value === 'all' ? undefined : operationType.value,
            document_type:
                documentType.value === 'all' ? undefined : documentType.value,
            status: status.value === 'all' ? undefined : status.value,
            from: from.value || undefined,
            to: to.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}, 300);

watch([search, operationType, documentType, status, from, to], runFilter);

const statusStyles: Record<DocumentStatus, string> = {
    pendiente:
        'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    parcial: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    pagado: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    convertido:
        'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400',
    anulado: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
};

const statusLabels: Record<DocumentStatus, string> = {
    pendiente: 'Pendiente',
    parcial: 'Parcial',
    pagado: 'Pagado',
    convertido: 'Convertido',
    anulado: 'Anulado',
};

const documentTypeLabels: Record<DocumentType, string> = {
    presupuesto: 'Presupuesto',
    factura: 'Orden',
};

function money(value: string) {
    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
    }).format(Number(value));
}

function convert(document: Document) {
    if (confirm(`¿Convertir el presupuesto ${document.number} en orden?`)) {
        router.post(DocumentController.convertToInvoice.url(document));
    }
}
</script>

<template>
    <Head title="Ventas/Compras" />

    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <Heading
                title="Ventas/Compras"
                description="Presupuestos y órdenes de venta y compra"
            />
            <Button as-child>
                <Link :href="create()">
                    <Plus class="size-4" />
                    Nuevo documento
                </Link>
            </Button>
        </div>

        <div
            class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center"
        >
            <div class="relative w-full sm:max-w-xs">
                <Search
                    class="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Buscar por número o contacto…"
                    class="pl-9"
                />
            </div>

            <Select v-model="operationType">
                <SelectTrigger class="w-full sm:w-40"
                    ><SelectValue placeholder="Operación"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Venta y compra</SelectItem>
                    <SelectItem value="venta">Venta</SelectItem>
                    <SelectItem value="compra">Compra</SelectItem>
                </SelectContent>
            </Select>

            <Select v-model="documentType">
                <SelectTrigger class="w-full sm:w-44"
                    ><SelectValue placeholder="Tipo"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Presupuestos y órdenes</SelectItem>
                    <SelectItem value="presupuesto">Presupuestos</SelectItem>
                    <SelectItem value="factura">Órdenes</SelectItem>
                </SelectContent>
            </Select>

            <Select v-model="status">
                <SelectTrigger class="w-full sm:w-40"
                    ><SelectValue placeholder="Estado"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Todos los estados</SelectItem>
                    <SelectItem value="pendiente">Pendiente</SelectItem>
                    <SelectItem value="parcial">Parcial</SelectItem>
                    <SelectItem value="pagado">Pagado</SelectItem>
                    <SelectItem value="convertido">Convertido</SelectItem>
                </SelectContent>
            </Select>

            <Input v-model="from" type="date" class="w-full sm:w-40" />
            <Input v-model="to" type="date" class="w-full sm:w-40" />
        </div>

        <div class="rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Número</TableHead>
                        <TableHead>Contacto</TableHead>
                        <TableHead class="hidden md:table-cell"
                            >Operación</TableHead
                        >
                        <TableHead class="hidden md:table-cell">Tipo</TableHead>
                        <TableHead class="hidden sm:table-cell"
                            >Fecha</TableHead
                        >
                        <TableHead>Total</TableHead>
                        <TableHead>Estado</TableHead>
                        <TableHead class="text-right">Acciones</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="documents.data.length === 0" :colspan="8">
                        No hay documentos todavía.
                    </TableEmpty>
                    <TableRow
                        v-for="document in documents.data"
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
                        <TableCell class="hidden md:table-cell">{{
                            documentTypeLabels[document.document_type]
                        }}</TableCell>
                        <TableCell class="hidden sm:table-cell">{{
                            formatDate(document.issue_date)
                        }}</TableCell>
                        <TableCell>
                            {{ money(document.total) }}
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
                        <TableCell class="text-right">
                            <Button
                                v-if="
                                    document.document_type === 'presupuesto' &&
                                    document.status === 'pendiente'
                                "
                                variant="ghost"
                                size="sm"
                                @click="convert(document)"
                            >
                                <ArrowRightLeft class="size-4" />
                                Convertir
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination :paginator="documents" />
    </div>
</template>
