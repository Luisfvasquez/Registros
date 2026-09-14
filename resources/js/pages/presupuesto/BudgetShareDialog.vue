<script setup lang="ts">
import { Download, MessageCircle } from '@lucide/vue';
import { toBlob, toPng } from 'html-to-image';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { BudgetInvoice } from '@/types';
import BudgetReceiptTicket from './BudgetReceiptTicket.vue';
import { formatDate, formatMoney } from './sheet';

/**
 * Compartir una factura del presupuesto: la misma mecánica que el comprobante
 * de /ventas — se captura el ticket como imagen y se manda por WhatsApp, con
 * descarga como alternativa.
 */
const props = defineProps<{
    invoice: BudgetInvoice | null;
    currency: string;
    periodo: string;
}>();

const emit = defineEmits<{ close: [] }>();

const open = computed({
    get: () => props.invoice !== null,
    set: (value: boolean) => {
        if (!value) {
            emit('close');
        }
    },
});

const ticketEl = ref<HTMLElement | null>(null);
const generating = ref(false);
const hint = ref('');

watch(
    () => props.invoice?.id,
    () => (hint.value = ''),
);

const fileName = computed(() => {
    const numero = props.invoice?.invoice_number ?? 'factura';

    return numero
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
});

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

async function download() {
    const dataUrl = await withNode((node) => toPng(node, { pixelRatio: 2 }));

    if (!dataUrl) {
        return;
    }

    const link = window.document.createElement('a');
    link.href = dataUrl;
    link.download = `${fileName.value}.png`;
    link.click();
}

/**
 * Los teléfonos se guardan como el operador y el número local (0412-1234567).
 * wa.me los quiere en formato internacional: el 0 inicial pasa a ser 58.
 */
function toInternational(phone?: string | null): string {
    const digits = `${phone ?? ''}`.replace(/\D/g, '');

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

function resumen(): string {
    const invoice = props.invoice!;
    const money = (value: number) => formatMoney(value, props.currency);

    const lines = [
        `*Factura ${invoice.invoice_number ?? ''}*`.trim(),
        invoice.party_name ?? '',
        formatDate(invoice.fecha) || props.periodo,
        '----------------',
        ...invoice.items.map(
            (item) =>
                `${item.producto ?? 'Sin producto'}   ${money(item.precio_total)}`,
        ),
        '----------------',
        `Total: ${money(invoice.totales.total)}`,
    ];

    if (invoice.totales.restante > 0) {
        lines.push(`Abonado: ${money(invoice.totales.abonado)}`);
        lines.push(
            `${invoice.tipo === 'compra' ? 'Por pagar' : 'Por cobrar'}: ${money(invoice.totales.restante)}`,
        );
    }

    return lines.filter(Boolean).join('\n');
}

async function sendWhatsapp() {
    hint.value = '';

    const blob = await withNode((node) => toBlob(node, { pixelRatio: 2 }));
    const text = resumen();
    const file = blob
        ? new File([blob], `${fileName.value}.png`, { type: 'image/png' })
        : null;

    // Móvil y navegadores que lo soportan: la imagen entra directo al chat.
    if (file && navigator.canShare?.({ files: [file] })) {
        try {
            await navigator.share({ files: [file], text, title: 'Factura' });

            return;
        } catch (error) {
            if ((error as DOMException)?.name === 'AbortError') {
                return;
            }
            // si no, sigue por el camino de escritorio
        }
    }

    // Escritorio: se copia la imagen para pegarla y se abre el chat con el texto.
    if (blob) {
        try {
            await navigator.clipboard.write([
                new ClipboardItem({ 'image/png': blob }),
            ]);
            hint.value = 'Imagen copiada — pégala en el chat con Ctrl/Cmd + V.';
        } catch {
            hint.value =
                'No se pudo copiar la imagen. Usá "Descargar imagen" y adjuntala.';
        }
    }

    const digits = toInternational(props.invoice?.telefono);
    const encoded = encodeURIComponent(text);

    window.open(
        digits
            ? `https://wa.me/${digits}?text=${encoded}`
            : `https://wa.me/?text=${encoded}`,
        '_blank',
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="w-fit max-w-fit">
            <DialogHeader>
                <DialogTitle>
                    Factura para
                    {{
                        invoice?.tipo === 'compra'
                            ? 'el proveedor'
                            : 'el cliente'
                    }}
                </DialogTitle>
            </DialogHeader>

            <div
                ref="ticketEl"
                class="flex justify-center overflow-hidden rounded-lg border"
            >
                <BudgetReceiptTicket
                    v-if="invoice"
                    :invoice="invoice"
                    :currency="currency"
                    :periodo="periodo"
                />
            </div>

            <p v-if="hint" class="text-xs text-muted-foreground">{{ hint }}</p>
            <p
                v-else-if="!invoice?.telefono"
                class="text-xs text-muted-foreground"
            >
                Este contacto no tiene teléfono en el Directorio: WhatsApp se va
                a abrir sin destinatario.
            </p>

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
