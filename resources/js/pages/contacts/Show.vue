<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowDownCircle, ArrowUpCircle } from '@lucide/vue';
import { index as contactsIndex } from '@/actions/App/Http/Controllers/ContactController';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import Heading from '@/components/Heading.vue';
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
import type { Contact, Document, DocumentStatus, DocumentType } from '@/types';

type DocumentWithBalance = Document & { balance: number };

defineProps<{
    contact: Contact;
    documents: DocumentWithBalance[];
    receivable: number;
    payable: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: dashboard() },
            { title: 'Contactos', href: contactsIndex() },
            { title: 'Detalle', href: '#' },
        ],
    },
});

const typeLabels: Record<string, string> = {
    cliente: 'Cliente',
    proveedor: 'Proveedor',
    ambos: 'Cliente y proveedor',
};

const documentTypeLabels: Record<DocumentType, string> = {
    presupuesto: 'Presupuesto',
    factura: 'Orden',
};

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

function money(value: number | string) {
    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
    }).format(Number(value));
}
</script>

<template>
    <Head :title="contact.name" />

    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="contact.name"
                :description="typeLabels[contact.type]"
            />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border p-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">
                        Nos debe (cuentas por cobrar)
                    </p>
                    <ArrowUpCircle class="size-4 text-emerald-600" />
                </div>
                <p class="mt-2 text-2xl font-semibold">
                    {{ money(receivable) }}
                </p>
                <MoneyBs :amount="receivable" />
            </div>

            <div class="rounded-xl border p-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">
                        Le debemos (cuentas por pagar)
                    </p>
                    <ArrowDownCircle class="size-4 text-red-600" />
                </div>
                <p class="mt-2 text-2xl font-semibold">{{ money(payable) }}</p>
                <MoneyBs :amount="payable" />
            </div>
        </div>

        <div class="rounded-xl border p-4">
            <h3 class="mb-2 text-sm font-medium text-muted-foreground">
                Datos de contacto
            </h3>
            <div class="grid gap-1 text-sm sm:grid-cols-2">
                <p v-if="contact.document">
                    <span class="text-muted-foreground">Documento:</span>
                    {{ contact.document }}
                </p>
                <p v-if="contact.phone">
                    <span class="text-muted-foreground">Teléfono:</span>
                    {{ contact.phone_country_code }} {{ contact.phone }}
                </p>
                <p v-if="contact.email">
                    <span class="text-muted-foreground">Email:</span>
                    {{ contact.email }}
                </p>
                <p v-if="contact.address">
                    <span class="text-muted-foreground">Dirección:</span>
                    {{ contact.address }}
                </p>
            </div>
        </div>

        <div class="rounded-xl border">
            <div class="border-b p-4">
                <h3 class="text-sm font-medium">Documentos</h3>
            </div>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Número</TableHead>
                        <TableHead class="hidden md:table-cell"
                            >Operación</TableHead
                        >
                        <TableHead class="hidden md:table-cell">Tipo</TableHead>
                        <TableHead class="hidden sm:table-cell"
                            >Fecha</TableHead
                        >
                        <TableHead>Total</TableHead>
                        <TableHead>Saldo</TableHead>
                        <TableHead>Estado</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="documents.length === 0" :colspan="7">
                        Este contacto todavía no tiene documentos.
                    </TableEmpty>
                    <TableRow v-for="document in documents" :key="document.id">
                        <TableCell class="font-medium">
                            <Link
                                :href="DocumentController.show.url(document)"
                                class="hover:underline"
                            >
                                {{ document.number }}
                            </Link>
                        </TableCell>
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
                        <TableCell v-if="document.document_type === 'factura'">
                            {{ money(document.balance) }}
                            <MoneyBs :amount="document.balance" />
                        </TableCell>
                        <TableCell v-else>—</TableCell>
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
