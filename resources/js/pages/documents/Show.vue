<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowRightLeft,
    MessageCircle,
    Plus,
    SquarePen,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
import DocumentController, {
    edit,
    index,
} from '@/actions/App/Http/Controllers/DocumentController';
import PaymentController from '@/actions/App/Http/Controllers/PaymentController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import MoneyBs from '@/components/MoneyBs.vue';
import PaymentMethodPicker from '@/components/PaymentMethodPicker.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import WhatsAppReceiptDialog from '@/components/WhatsAppReceiptDialog.vue';
import { formatDate } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { Document, DocumentStatus, PaymentMethod } from '@/types';

const props = defineProps<{
    document: Document;
    paidTotal: number;
    balance: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: dashboard() },
            { title: 'Ventas/Compras', href: index() },
            { title: 'Detalle', href: '#' },
        ],
    },
});

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

const expensesTotal = () =>
    (props.document.expenses ?? []).reduce(
        (sum, expense) => sum + Number(expense.amount),
        0,
    );

function convert() {
    if (
        confirm(`¿Convertir el presupuesto ${props.document.number} en orden?`)
    ) {
        router.post(DocumentController.convertToInvoice.url(props.document));
    }
}

function destroy() {
    if (
        confirm(
            `¿Eliminar el documento ${props.document.number}? Esta acción no se puede deshacer.`,
        )
    ) {
        router.delete(DocumentController.destroy.url(props.document));
    }
}

const whatsappOpen = ref(false);

const paymentDialogOpen = ref(false);
const selectedPaymentMethod = ref<PaymentMethod | null>(null);
const paymentForm = useForm({
    amount: '',
    payment_method_id: null as number | null,
    reference: '',
    paid_at: new Date().toISOString().slice(0, 10),
});

function openPaymentDialog() {
    paymentForm.reset();
    paymentForm.amount = String(props.balance);
    selectedPaymentMethod.value = null;
    paymentDialogOpen.value = true;
}

function submitPayment() {
    paymentForm.payment_method_id = selectedPaymentMethod.value?.id ?? null;
    paymentForm.post(PaymentController.store.url(props.document), {
        onSuccess: () => (paymentDialogOpen.value = false),
    });
}

function deletePayment(paymentId: number) {
    if (confirm('¿Eliminar este abono?')) {
        router.delete(
            PaymentController.destroy.url({
                document: props.document,
                payment: paymentId,
            }),
        );
    }
}
</script>

<template>
    <Head :title="document.number" />

    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="mb-1 flex items-center gap-2">
                    <Heading
                        :title="document.number"
                        :description="`${document.operation_type === 'venta' ? 'Venta' : 'Compra'} · ${document.document_type === 'factura' ? 'Orden' : 'Presupuesto'}`"
                    />
                    <Badge
                        :class="statusStyles[document.status]"
                        variant="secondary"
                    >
                        {{ statusLabels[document.status] }}
                    </Badge>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button variant="outline" @click="whatsappOpen = true">
                    <MessageCircle class="size-4" />
                    Comprobante WhatsApp
                </Button>
                <Button
                    v-if="
                        document.document_type === 'presupuesto' &&
                        document.status === 'pendiente'
                    "
                    variant="outline"
                    @click="convert"
                >
                    <ArrowRightLeft class="size-4" />
                    Convertir a orden
                </Button>
                <Button as-child variant="outline">
                    <Link :href="edit(document)">
                        <SquarePen class="size-4" />
                        Editar
                    </Link>
                </Button>
                <Button
                    variant="outline"
                    :disabled="document.document_type === 'factura'"
                    :title="
                        document.document_type === 'factura'
                            ? 'Las órdenes no se pueden eliminar'
                            : undefined
                    "
                    @click="destroy"
                >
                    <Trash2 class="size-4 text-destructive" />
                </Button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="flex flex-col gap-6 lg:col-span-2">
                <div class="rounded-xl border p-4">
                    <h3 class="mb-2 text-sm font-medium text-muted-foreground">
                        Contacto
                    </h3>
                    <p class="font-medium">{{ document.contact?.name }}</p>
                    <p
                        v-if="document.contact?.document"
                        class="text-sm text-muted-foreground"
                    >
                        Doc: {{ document.contact.document }}
                    </p>
                    <p
                        v-if="document.contact?.phone"
                        class="text-sm text-muted-foreground"
                    >
                        Tel: {{ document.contact.phone_country_code }}
                        {{ document.contact.phone }}
                    </p>
                </div>

                <div class="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Descripción</TableHead>
                                <TableHead>Cant.</TableHead>
                                <TableHead>Precio</TableHead>
                                <TableHead class="text-right"
                                    >Subtotal</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="item in document.items"
                                :key="item.id"
                            >
                                <TableCell>{{ item.description }}</TableCell>
                                <TableCell>{{
                                    Number(item.quantity)
                                }}</TableCell>
                                <TableCell>{{
                                    money(item.unit_price)
                                }}</TableCell>
                                <TableCell class="text-right">{{
                                    money(item.subtotal)
                                }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <div v-if="document.expenses?.length" class="rounded-xl border">
                    <div class="border-b p-4">
                        <h3 class="text-sm font-medium text-muted-foreground">
                            Gastos (registro interno)
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            Total: {{ money(expensesTotal()) }} — no afecta el
                            total ni se muestra al contacto, solo la ganancia.
                        </p>
                    </div>
                    <Table>
                        <TableBody>
                            <TableRow
                                v-for="expense in document.expenses"
                                :key="expense.id"
                            >
                                <TableCell>{{ expense.description }}</TableCell>
                                <TableCell class="text-right">{{
                                    money(expense.amount)
                                }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <p v-if="document.notes" class="text-sm text-muted-foreground">
                    {{ document.notes }}
                </p>
            </div>

            <div class="flex flex-col gap-6">
                <div class="rounded-xl border p-4">
                    <h3 class="mb-3 text-sm font-medium text-muted-foreground">
                        Resumen
                    </h3>
                    <div class="space-y-1.5 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span>{{ money(document.subtotal) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Impuestos</span>
                            <span>{{ money(document.tax_total) }}</span>
                        </div>
                        <div
                            class="flex justify-between text-base font-semibold"
                        >
                            <span>Total</span>
                            <span class="text-right">
                                {{ money(document.total) }}
                                <MoneyBs :amount="document.total" />
                            </span>
                        </div>

                        <template v-if="document.document_type === 'factura'">
                            <div class="my-2 border-t pt-2" />
                            <div class="flex justify-between">
                                <span class="text-muted-foreground"
                                    >Total abonado</span
                                >
                                <span>{{ money(paidTotal) }}</span>
                            </div>
                            <div
                                class="flex justify-between text-base font-semibold"
                            >
                                <span>Saldo restante</span>
                                <span class="text-right">
                                    {{ money(balance) }}
                                    <MoneyBs :amount="balance" />
                                </span>
                            </div>
                        </template>
                    </div>
                </div>

                <div
                    v-if="document.document_type === 'factura'"
                    class="rounded-xl border p-4"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-medium text-muted-foreground">
                            Abonos
                        </h3>
                        <Button
                            v-if="balance > 0"
                            size="sm"
                            variant="outline"
                            @click="openPaymentDialog"
                        >
                            <Plus class="size-4" />
                            Registrar abono
                        </Button>
                    </div>

                    <div
                        v-if="!document.payments?.length"
                        class="text-sm text-muted-foreground"
                    >
                        Sin abonos registrados.
                    </div>
                    <ul v-else class="space-y-2">
                        <li
                            v-for="payment in document.payments"
                            :key="payment.id"
                            class="flex items-center justify-between text-sm"
                        >
                            <div>
                                <p class="font-medium">
                                    {{ money(payment.amount) }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ payment.payment_method?.name }} ·
                                    {{ formatDate(payment.paid_at) }}
                                    <span v-if="payment.reference">
                                        · {{ payment.reference }}</span
                                    >
                                </p>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                @click="deletePayment(payment.id)"
                            >
                                <Trash2 class="size-4 text-destructive" />
                            </Button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <WhatsAppReceiptDialog
        v-model:open="whatsappOpen"
        :document="document"
        :paid-total="paidTotal"
        :balance="balance"
    />

    <Dialog v-model:open="paymentDialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Registrar abono</DialogTitle>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submitPayment">
                <div class="grid gap-2">
                    <Label for="amount">Monto</Label>
                    <Input
                        id="amount"
                        v-model="paymentForm.amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        placeholder="0.00"
                        required
                        autofocus
                    />
                    <InputError :message="paymentForm.errors.amount" />
                </div>

                <div class="grid gap-2">
                    <Label>Método de pago</Label>
                    <PaymentMethodPicker v-model="selectedPaymentMethod" />
                    <InputError
                        :message="paymentForm.errors.payment_method_id"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="reference">Referencia</Label>
                    <Input
                        id="reference"
                        v-model="paymentForm.reference"
                        placeholder="Nº de operación (opcional)"
                    />
                    <InputError :message="paymentForm.errors.reference" />
                </div>

                <div class="grid gap-2">
                    <Label for="paid_at">Fecha</Label>
                    <Input
                        id="paid_at"
                        v-model="paymentForm.paid_at"
                        type="date"
                        required
                    />
                    <InputError :message="paymentForm.errors.paid_at" />
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="paymentForm.processing">
                        <Spinner v-if="paymentForm.processing" />
                        Registrar
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
