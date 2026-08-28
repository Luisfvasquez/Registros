<script setup lang="ts">
import { Download, MessageCircle } from '@lucide/vue';
import { toBlob, toPng } from 'html-to-image';
import { computed, ref } from 'vue';
import CombinedReceiptTicket from '@/components/CombinedReceiptTicket.vue';
import ReceiptTicket from '@/components/ReceiptTicket.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Contact, Document } from '@/types';

type DocumentWithTotals = Document & { balance: number; paid_total: number };

const props = defineProps<{
    // Single-document mode (used from documents/Show.vue)
    document?: Document;
    paidTotal?: number;
    balance?: number;
    // Combined mode (used from documents/Index.vue) — several documents of one contact
    documents?: DocumentWithTotals[];
    contact?: Contact;
}>();

const open = defineModel<boolean>('open', { default: false });

const isCombined = computed(
    () => Array.isArray(props.documents) && props.documents.length > 0,
);

const targetContact = computed(
    () => props.contact ?? props.document?.contact ?? null,
);

const fileName = computed(() => {
    if (isCombined.value) {
        const slug = (targetContact.value?.name ?? 'comprobante')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');

        return `${slug || 'comprobante'}-${props.documents!.length}-docs`;
    }

    return props.document?.number ?? 'comprobante';
});

const ticketEl = ref<HTMLElement | null>(null);
const generating = ref(false);
const hint = ref('');

async function withNode<T>(
    action: (node: HTMLElement) => Promise<T>,
): Promise<T | null> {
    const node = ticketEl.value?.firstElementChild as HTMLElement | undefined;

    if (!node) {
        return null;
    }

    generating.value = true;

    try {
        return await action(node);
    } finally {
        generating.value = false;
    }
}

function captureDataUrl(): Promise<string | null> {
    return withNode((node) => toPng(node, { pixelRatio: 2 }));
}

function captureBlob(): Promise<Blob | null> {
    return withNode((node) => toBlob(node, { pixelRatio: 2 }));
}

async function download() {
    const dataUrl = await captureDataUrl();

    if (!dataUrl) {
        return;
    }

    const link = window.document.createElement('a');
    link.href = dataUrl;
    link.download = `${fileName.value}.png`;
    link.click();
}

function currency(value: number | string) {
    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
    }).format(Number(value));
}

function whatsappSummary(): string {
    if (isCombined.value) {
        const documents = props.documents!;
        const operation =
            documents[0]?.operation_type === 'compra' ? 'Compra' : 'Venta';
        const total = documents.reduce(
            (sum, document) => sum + Number(document.total),
            0,
        );
        const invoices = documents.filter(
            (document) => document.document_type === 'factura',
        );
        const balance = invoices.reduce(
            (sum, document) => sum + Number(document.balance),
            0,
        );
        const paid = invoices.reduce(
            (sum, document) => sum + Number(document.paid_total),
            0,
        );

        const lines = [
            `*${operation} — ${documents.length} documento(s)*`,
            targetContact.value?.name ?? '',
            ...documents.map(
                (document) =>
                    `${document.number}   ${currency(document.total)}`,
            ),
            '----------------',
            `Total: ${currency(total)}`,
        ];

        if (balance > 0) {
            lines.push(`Abono: ${currency(paid)}`);
            lines.push(`Deuda: ${currency(balance)}`);
        }

        return lines.filter(Boolean).join('\n');
    }

    const document = props.document!;
    const lines = [
        `*${document.document_type === 'factura' ? 'Orden' : 'Presupuesto'} ${document.number}*`,
        `Total: ${currency(document.total)}`,
    ];

    if (
        document.document_type === 'factura' &&
        Number(props.balance ?? 0) > 0
    ) {
        lines.push(`Abono: ${currency(props.paidTotal ?? 0)}`);
        lines.push(`Deuda: ${currency(props.balance ?? 0)}`);
    }

    return lines.join('\n');
}

/**
 * Venezuelan numbers are stored as operator + local number (e.g. 0414 + 8361745).
 * wa.me needs the international form, so swap a leading 0 for the 58 country code.
 */
function toInternational(code?: string | null, phone?: string | null): string {
    const digits = `${code ?? ''}${phone ?? ''}`.replace(/\D/g, '');

    if (!digits) {
        return '';
    }

    if (digits.startsWith('58')) {
        return digits;
    }

    if (digits.startsWith('0')) {
        return `58${digits.slice(1)}`;
    }

    return digits;
}

async function sendWhatsapp() {
    hint.value = '';

    const blob = await captureBlob();
    const text = whatsappSummary();
    const file = blob
        ? new File([blob], `${fileName.value}.png`, { type: 'image/png' })
        : null;

    // Mobile / supported browsers: share the image straight into the WhatsApp chat.
    if (file && navigator.canShare?.({ files: [file] })) {
        try {
            await navigator.share({
                files: [file],
                text,
                title: 'Comprobante',
            });

            return;
        } catch (error) {
            if ((error as DOMException)?.name === 'AbortError') {
                return;
            }
            // fall through to the desktop path
        }
    }

    // Desktop fallback: copy the image so it can be pasted, then open the chat with the text.
    if (blob) {
        try {
            await navigator.clipboard.write([
                new ClipboardItem({ 'image/png': blob }),
            ]);
            hint.value = 'Imagen copiada — pégala en el chat con Ctrl/Cmd + V.';
        } catch {
            hint.value =
                'No se pudo copiar la imagen. Usa "Descargar imagen" y adjúntala.';
        }
    }

    const digits = toInternational(
        targetContact.value?.phone_country_code,
        targetContact.value?.phone,
    );
    const encoded = encodeURIComponent(text);
    const url = digits
        ? `https://wa.me/${digits}?text=${encoded}`
        : `https://wa.me/?text=${encoded}`;

    window.open(url, '_blank');
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="w-fit max-w-fit">
            <DialogHeader>
                <DialogTitle>Comprobante para WhatsApp</DialogTitle>
            </DialogHeader>

            <div
                ref="ticketEl"
                class="flex justify-center overflow-hidden rounded-lg border"
            >
                <CombinedReceiptTicket
                    v-if="isCombined && contact"
                    :documents="documents!"
                    :contact="contact"
                />
                <ReceiptTicket
                    v-else-if="document"
                    :document="document"
                    :paid-total="paidTotal ?? 0"
                    :balance="balance ?? 0"
                />
            </div>

            <p v-if="hint" class="text-xs text-muted-foreground">{{ hint }}</p>

            <div class="flex flex-col gap-2 sm:flex-row">
                <Button
                    variant="outline"
                    class="flex-1"
                    :disabled="generating"
                    @click="download"
                >
                    <Download class="size-4" />
                    Descargar imagen
                </Button>
                <Button
                    class="flex-1"
                    :disabled="generating"
                    @click="sendWhatsapp"
                >
                    <MessageCircle class="size-4" />
                    Enviar por WhatsApp
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
