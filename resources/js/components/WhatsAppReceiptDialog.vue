<script setup lang="ts">
import { Download, MessageCircle } from '@lucide/vue';
import { toPng } from 'html-to-image';
import { ref } from 'vue';
import ReceiptTicket from '@/components/ReceiptTicket.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Document } from '@/types';

const props = defineProps<{
    document: Document;
    paidTotal: number;
    balance: number;
}>();

const open = defineModel<boolean>('open', { default: false });

const ticketRef = ref<InstanceType<typeof ReceiptTicket> | null>(null);
const generating = ref(false);

async function captureImage(): Promise<string | null> {
    const node = ticketRef.value?.$el as HTMLElement | undefined;

    if (!node) {
        return null;
    }

    generating.value = true;

    try {
        return await toPng(node, { pixelRatio: 2 });
    } finally {
        generating.value = false;
    }
}

async function download() {
    const dataUrl = await captureImage();

    if (!dataUrl) {
        return;
    }

    const link = window.document.createElement('a');
    link.href = dataUrl;
    link.download = `${props.document.number}.png`;
    link.click();
}

function whatsappSummary() {
    const lines = [
        `*${props.document.document_type === 'factura' ? 'Orden' : 'Presupuesto'} ${props.document.number}*`,
        `Total: ${new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(Number(props.document.total))}`,
    ];

    if (props.document.document_type === 'factura') {
        lines.push(
            `Saldo pendiente: ${new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(props.balance)}`,
        );
    }

    return lines.join('\n');
}

async function sendWhatsapp() {
    await download();

    const contact = props.document.contact;
    const digits =
        `${contact?.phone_country_code ?? ''}${contact?.phone ?? ''}`.replace(
            /\D/g,
            '',
        );
    const text = encodeURIComponent(whatsappSummary());
    const url = digits
        ? `https://wa.me/${digits}?text=${text}`
        : `https://wa.me/?text=${text}`;

    window.open(url, '_blank');
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="w-fit max-w-fit">
            <DialogHeader>
                <DialogTitle>Comprobante para WhatsApp</DialogTitle>
            </DialogHeader>

            <div class="flex justify-center overflow-hidden rounded-lg border">
                <ReceiptTicket
                    ref="ticketRef"
                    :document="document"
                    :paid-total="paidTotal"
                    :balance="balance"
                />
            </div>

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
