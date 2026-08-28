<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRightLeft,
    MessageCircle,
    Plus,
    Search,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch, watchEffect } from 'vue';
import DocumentController, {
    index,
    create,
} from '@/actions/App/Http/Controllers/DocumentController';
import CombinedReceiptTicket from '@/components/CombinedReceiptTicket.vue';
import Heading from '@/components/Heading.vue';
import MoneyBs from '@/components/MoneyBs.vue';
import ReceiptTicket from '@/components/ReceiptTicket.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import WhatsAppReceiptDialog from '@/components/WhatsAppReceiptDialog.vue';
import { formatDate } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as purchasesIndex } from '@/routes/documents/purchases';
import { index as salesIndex } from '@/routes/documents/sales';
import type {
    Contact,
    ContactType,
    Document,
    DocumentStatus,
    DocumentType,
} from '@/types';

type DocumentWithTotals = Document & { balance: number; paid_total: number };

type ContactSummary = {
    id: number;
    name: string;
    type: ContactType;
    document: string | null;
    phone: string | null;
    phone_country_code: string | null;
    documents_count: number;
    invoices_count: number;
    total: number;
    balance: number;
};

const props = defineProps<{
    contacts: ContactSummary[];
    selectedContact: Contact | null;
    selectedDocuments: DocumentWithTotals[] | null;
    filters: { search?: string };
    lockedOperationType?: 'venta' | 'compra' | null;
}>();

const pageUrl =
    props.lockedOperationType === 'venta'
        ? salesIndex().url
        : props.lockedOperationType === 'compra'
          ? purchasesIndex().url
          : index().url;

const pageTitle =
    props.lockedOperationType === 'venta'
        ? 'Ventas'
        : props.lockedOperationType === 'compra'
          ? 'Compras'
          : 'Ventas/Compras';

const pageDescription =
    props.lockedOperationType === 'venta'
        ? 'Clientes y sus órdenes de venta'
        : props.lockedOperationType === 'compra'
          ? 'Proveedores y sus órdenes de compra'
          : 'Contactos y sus documentos de venta y compra';

const operationLabel =
    props.lockedOperationType === 'compra' ? 'Compra' : 'Venta';

// Carries the contact through so the document form opens with them pre-selected.
const newDocumentUrl = computed(() => {
    const query: Record<string, string | number> = {};

    if (props.lockedOperationType) {
        query.operation_type = props.lockedOperationType;
    }

    if (props.selectedContact) {
        query.contact = props.selectedContact.id;
    }

    return create.url(Object.keys(query).length > 0 ? { query } : undefined);
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: dashboard() },
            { title: 'Ventas/Compras', href: index() },
        ],
    },
});

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Panel', href: dashboard() },
            { title: pageTitle, href: pageUrl },
        ],
    });
});

const search = ref(props.filters.search ?? '');

const runFilter = useDebounceFn(() => {
    router.get(
        pageUrl,
        { search: search.value || undefined },
        { preserveState: true, replace: true },
    );
}, 300);

watch(search, runFilter);

function openContact(contact: ContactSummary) {
    router.get(
        pageUrl,
        { contact: contact.id },
        { preserveState: true, preserveScroll: true },
    );
}

function backToContacts() {
    router.get(pageUrl, {}, { preserveState: true });
}

const contactTypeLabels: Record<ContactType, string> = {
    cliente: 'Cliente',
    proveedor: 'Proveedor',
    ambos: 'Cliente y proveedor',
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

function money(value: number | string) {
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

/* --- Multi-select + combined WhatsApp share --- */
const selectedIds = ref<number[]>([]);
const previewId = ref<number | null>(null);

watch(
    () => props.selectedContact?.id,
    () => {
        selectedIds.value = [];
        previewId.value = null;
    },
);

function toggle(id: number, checked: boolean) {
    if (checked) {
        selectedIds.value = [...selectedIds.value, id];
    } else {
        selectedIds.value = selectedIds.value.filter((value) => value !== id);
    }
}

const allSelected = computed(
    () =>
        (props.selectedDocuments?.length ?? 0) > 0 &&
        selectedIds.value.length === props.selectedDocuments?.length,
);

function toggleAll(checked: boolean) {
    selectedIds.value = checked
        ? (props.selectedDocuments ?? []).map((document) => document.id)
        : [];
}

const selectedDocumentsForShare = computed(() =>
    (props.selectedDocuments ?? []).filter((document) =>
        selectedIds.value.includes(document.id),
    ),
);

/**
 * What the preview panel shows: the checked documents (so it matches exactly what
 * "Enviar por WhatsApp" will send), otherwise the row the user last clicked, and
 * as a fallback the first document — so the preview is never empty on entry.
 */
const previewDocuments = computed<DocumentWithTotals[]>(() => {
    if (selectedDocumentsForShare.value.length > 0) {
        return selectedDocumentsForShare.value;
    }

    const documents = props.selectedDocuments ?? [];

    if (previewId.value !== null) {
        const found = documents.find(
            (document) => document.id === previewId.value,
        );

        if (found) {
            return [found];
        }
    }

    return documents.length > 0 ? [documents[0]] : [];
});

const previewIsCombined = computed(() => previewDocuments.value.length > 1);

const whatsappOpen = ref(false);
const shareDocuments = ref<DocumentWithTotals[]>([]);

function shareSelection() {
    if (selectedDocumentsForShare.value.length === 0) {
        return;
    }

    shareDocuments.value = selectedDocumentsForShare.value;
    whatsappOpen.value = true;
}

function shareOne(document: DocumentWithTotals) {
    shareDocuments.value = [document];
    whatsappOpen.value = true;
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <Heading :title="pageTitle" :description="pageDescription" />
            <Button as-child>
                <Link :href="newDocumentUrl">
                    <Plus class="size-4" />
                    Nuevo documento
                </Link>
            </Button>
        </div>

        <!-- Contact detail: this contact's documents -->
        <template v-if="selectedContact">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Button variant="ghost" size="sm" @click="backToContacts">
                    <ArrowLeft class="size-4" />
                    Todos los contactos
                </Button>
            </div>

            <div class="rounded-xl border p-4">
                <p class="font-medium">{{ selectedContact.name }}</p>
                <p class="text-sm text-muted-foreground">
                    {{ contactTypeLabels[selectedContact.type] }}
                </p>
                <div
                    class="mt-1 flex flex-wrap gap-x-4 gap-y-0.5 text-sm text-muted-foreground"
                >
                    <span v-if="selectedContact.document"
                        >Doc: {{ selectedContact.document }}</span
                    >
                    <span v-if="selectedContact.phone"
                        >Tel: {{ selectedContact.phone_country_code }}
                        {{ selectedContact.phone }}</span
                    >
                    <span v-if="selectedContact.email">{{
                        selectedContact.email
                    }}</span>
                </div>
            </div>

            <div
                class="lg:grid lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start lg:gap-6"
            >
                <div class="flex flex-col gap-4">
                    <div class="rounded-xl border">
                        <div
                            class="flex items-center gap-3 border-b p-3 text-sm font-medium"
                        >
                            <Checkbox
                                :model-value="allSelected"
                                aria-label="Seleccionar todo"
                                @update:model-value="toggleAll(Boolean($event))"
                            />
                            <span>Documentos</span>
                        </div>

                        <p
                            v-if="
                                !selectedDocuments ||
                                selectedDocuments.length === 0
                            "
                            class="p-4 text-sm text-muted-foreground"
                        >
                            Este contacto todavía no tiene documentos de
                            {{ operationLabel.toLowerCase() }}.
                        </p>

                        <ul v-else class="divide-y">
                            <li
                                v-for="document in selectedDocuments"
                                :key="document.id"
                                class="flex cursor-pointer flex-wrap items-center gap-3 p-3 transition-colors"
                                :class="
                                    previewDocuments.some(
                                        (item) => item.id === document.id,
                                    )
                                        ? 'bg-accent/60'
                                        : 'hover:bg-accent/30'
                                "
                                @click="previewId = document.id"
                            >
                                <Checkbox
                                    :model-value="
                                        selectedIds.includes(document.id)
                                    "
                                    :aria-label="`Seleccionar ${document.number}`"
                                    @update:model-value="
                                        toggle(document.id, Boolean($event))
                                    "
                                />
                                <div class="min-w-0 flex-1">
                                    <Link
                                        :href="
                                            DocumentController.show.url(
                                                document,
                                            )
                                        "
                                        class="font-medium hover:underline"
                                    >
                                        {{ document.number }}
                                    </Link>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            documentTypeLabels[
                                                document.document_type
                                            ]
                                        }}
                                        ·
                                        {{ formatDate(document.issue_date) }}
                                    </p>
                                </div>
                                <div class="text-right text-sm">
                                    <p>{{ money(document.total) }}</p>
                                    <MoneyBs :amount="document.total" />
                                    <p
                                        v-if="
                                            document.document_type ===
                                                'factura' &&
                                            document.balance > 0
                                        "
                                        class="text-xs text-amber-600 dark:text-amber-400"
                                    >
                                        Deuda {{ money(document.balance) }}
                                    </p>
                                </div>
                                <Badge
                                    :class="statusStyles[document.status]"
                                    variant="secondary"
                                >
                                    {{ statusLabels[document.status] }}
                                </Badge>
                                <Button
                                    v-if="
                                        document.document_type ===
                                            'presupuesto' &&
                                        document.status === 'pendiente'
                                    "
                                    variant="ghost"
                                    size="sm"
                                    @click="convert(document)"
                                >
                                    <ArrowRightLeft class="size-4" />
                                    Convertir
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="shareOne(document)"
                                >
                                    <MessageCircle class="size-4" />
                                </Button>
                            </li>
                        </ul>
                    </div>

                    <div
                        v-if="selectedIds.length > 0"
                        class="sticky bottom-4 flex items-center justify-between gap-3 rounded-xl border bg-background/95 p-3 shadow-lg backdrop-blur"
                    >
                        <span class="text-sm text-muted-foreground">
                            {{ selectedIds.length }} seleccionada(s)
                        </span>
                        <Button @click="shareSelection">
                            <MessageCircle class="size-4" />
                            Enviar {{ selectedIds.length }} por WhatsApp
                        </Button>
                    </div>
                </div>

                <div
                    v-if="selectedContact && previewDocuments.length > 0"
                    class="mt-4 lg:sticky lg:top-4 lg:mt-0"
                >
                    <p
                        class="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Vista previa{{
                            previewIsCombined
                                ? ` · ${previewDocuments.length} documentos`
                                : ''
                        }}
                    </p>
                    <div class="overflow-auto rounded-xl border">
                        <CombinedReceiptTicket
                            v-if="previewIsCombined"
                            :documents="previewDocuments"
                            :contact="selectedContact"
                        />
                        <ReceiptTicket
                            v-else
                            :document="previewDocuments[0]"
                            :paid-total="previewDocuments[0].paid_total"
                            :balance="previewDocuments[0].balance"
                        />
                    </div>
                </div>
            </div>
        </template>

        <!-- Contact list -->
        <template v-else>
            <div class="relative w-full sm:max-w-xs">
                <Search
                    class="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Buscar por nombre, documento o teléfono…"
                    class="pl-9"
                />
            </div>

            <p
                v-if="contacts.length === 0"
                class="rounded-xl border p-6 text-center text-sm text-muted-foreground"
            >
                No hay contactos con documentos todavía.
            </p>

            <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="contact in contacts"
                    :key="contact.id"
                    type="button"
                    class="flex flex-col gap-2 rounded-xl border p-4 text-left transition-colors hover:bg-accent hover:text-accent-foreground"
                    @click="openContact(contact)"
                >
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-medium">{{ contact.name }}</p>
                        <Badge variant="secondary">{{
                            contactTypeLabels[contact.type]
                        }}</Badge>
                    </div>
                    <p
                        v-if="contact.document || contact.phone"
                        class="text-xs text-muted-foreground"
                    >
                        <span v-if="contact.document">{{
                            contact.document
                        }}</span>
                        <span v-if="contact.document && contact.phone">
                            ·
                        </span>
                        <span v-if="contact.phone"
                            >{{ contact.phone_country_code }}
                            {{ contact.phone }}</span
                        >
                    </p>
                    <div
                        class="mt-1 flex items-end justify-between gap-2 border-t pt-2 text-sm"
                    >
                        <span class="text-muted-foreground">
                            {{ contact.documents_count }} documento(s)
                        </span>
                        <div class="text-right">
                            <p class="font-semibold">
                                {{ money(contact.total) }}
                            </p>
                            <p
                                v-if="contact.balance > 0"
                                class="text-xs text-amber-600 dark:text-amber-400"
                            >
                                Deuda {{ money(contact.balance) }}
                            </p>
                        </div>
                    </div>
                </button>
            </div>
        </template>
    </div>

    <WhatsAppReceiptDialog
        v-if="selectedContact"
        v-model:open="whatsappOpen"
        :documents="shareDocuments"
        :contact="selectedContact"
    />
</template>
