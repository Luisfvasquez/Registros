<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import DocumentController, {
    index,
} from '@/actions/App/Http/Controllers/DocumentController';
import ContactPicker from '@/components/ContactPicker.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import MoneyBs from '@/components/MoneyBs.vue';
import PaymentMethodPicker from '@/components/PaymentMethodPicker.vue';
import ProductPicker from '@/components/ProductPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import type { Contact, Document, PaymentMethod, Product } from '@/types';

const props = defineProps<{
    document: Document | null;
    defaults: { operation_type?: string; document_type?: string };
}>();

const isEditing = computed(() => props.document !== null);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: dashboard() },
            { title: 'Ventas/Compras', href: index() },
            { title: 'Nuevo documento', href: '#' },
        ],
    },
});

const selectedContact = ref<Contact | null>(props.document?.contact ?? null);

type ItemRow = {
    product_id: number | null;
    description: string;
    quantity: number;
    unit_price: number;
    tax_rate: number;
    category?: string | null;
};

function emptyItem(): ItemRow {
    return {
        product_id: null,
        description: '',
        quantity: 1,
        unit_price: 0,
        tax_rate: 0,
        category: null,
    };
}

function initialItems(): ItemRow[] {
    return props.document?.items?.length
        ? props.document.items.map((item) => ({
              product_id: item.product_id,
              description: item.description,
              quantity: Number(item.quantity),
              unit_price: Number(item.unit_price),
              tax_rate: Number(item.tax_rate),
              category: item.product?.category?.name ?? null,
          }))
        : [emptyItem()];
}

type ExpenseRow = {
    description: string;
    amount: number;
};

function initialExpenses(): ExpenseRow[] {
    return (props.document?.expenses ?? []).map((expense) => ({
        description: expense.description,
        amount: Number(expense.amount),
    }));
}

type PaymentRow = {
    paymentMethod: PaymentMethod | null;
    amount: number;
    reference: string;
};

function emptyPayment(): PaymentRow {
    return { paymentMethod: null, amount: 0, reference: '' };
}

const expenses = ref<ExpenseRow[]>(initialExpenses());
const payments = ref<PaymentRow[]>([]);

const form = useForm({
    operation_type:
        props.document?.operation_type ??
        props.defaults.operation_type ??
        'venta',
    document_type:
        props.document?.document_type ??
        props.defaults.document_type ??
        'presupuesto',
    contact_id: props.document?.contact_id ?? (null as number | null),
    issue_date:
        props.document?.issue_date ?? new Date().toISOString().slice(0, 10),
    notes: props.document?.notes ?? '',
    items: initialItems(),
});

function addItem() {
    form.items.push(emptyItem());
}

function removeItem(index: number) {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
}

function addExpense() {
    expenses.value.push({ description: '', amount: 0 });
}

function removeExpense(index: number) {
    expenses.value.splice(index, 1);
}

function addPayment() {
    payments.value.push(emptyPayment());
}

function removePayment(index: number) {
    payments.value.splice(index, 1);
}

function onProductSelected(row: ItemRow, product: Product) {
    row.product_id = product.id;
    row.category = product.category?.name ?? null;
    row.unit_price = Number(
        form.operation_type === 'compra'
            ? product.purchase_cost
            : product.sale_price,
    );
}

const lineSubtotal = (row: ItemRow) => row.quantity * row.unit_price;
const lineTax = (row: ItemRow) => lineSubtotal(row) * (row.tax_rate / 100);

const itemsSubtotal = computed(() =>
    form.items.reduce((sum, row) => sum + lineSubtotal(row), 0),
);
const taxTotal = computed(() =>
    form.items.reduce((sum, row) => sum + lineTax(row), 0),
);
const expensesTotal = computed(() =>
    expenses.value.reduce((sum, row) => sum + Number(row.amount || 0), 0),
);
// Expenses are internal-only and never added to the document total billed to the contact.
const total = computed(() => itemsSubtotal.value + taxTotal.value);
const paymentsTotal = computed(() =>
    payments.value.reduce((sum, row) => sum + Number(row.amount || 0), 0),
);

function money(value: number) {
    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
    }).format(value);
}

function submit() {
    form.contact_id = selectedContact.value?.id ?? null;

    const payload = {
        expenses: expenses.value
            .filter((row) => row.description.trim() !== '')
            .map((row) => ({
                description: row.description,
                amount: row.amount,
            })),
        payments: payments.value
            .filter((row) => row.paymentMethod !== null && row.amount > 0)
            .map((row) => ({
                payment_method_id: row.paymentMethod?.id,
                amount: row.amount,
                reference: row.reference || null,
            })),
    };

    const cleanItems = form.items.map((item) => ({
        product_id: item.product_id,
        description: item.description,
        quantity: item.quantity,
        unit_price: item.unit_price,
        tax_rate: item.tax_rate,
    }));

    if (isEditing.value && props.document) {
        form.transform((data) => ({
            ...data,
            items: cleanItems,
            expenses: payload.expenses,
        })).put(DocumentController.update.url(props.document));
    } else {
        form.transform((data) => ({
            ...data,
            items: cleanItems,
            ...payload,
        })).post(DocumentController.store.url());
    }
}
</script>

<template>
    <Head :title="isEditing ? 'Editar documento' : 'Nuevo documento'" />

    <div class="flex flex-col gap-6">
        <Heading
            :title="
                isEditing
                    ? `Editar ${props.document?.number}`
                    : 'Nuevo documento'
            "
            description="Presupuesto u orden de venta / compra"
        />

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <div
                class="grid gap-4 rounded-xl border p-4 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div class="grid gap-2">
                    <Label for="operation_type">Operación</Label>
                    <Select v-model="form.operation_type">
                        <SelectTrigger id="operation_type" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="venta">Venta</SelectItem>
                            <SelectItem value="compra">Compra</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-2">
                    <Label for="document_type">Tipo de documento</Label>
                    <Select v-model="form.document_type">
                        <SelectTrigger id="document_type" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="presupuesto"
                                >Presupuesto</SelectItem
                            >
                            <SelectItem value="factura">Orden</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-2">
                    <Label for="issue_date">Fecha</Label>
                    <Input
                        id="issue_date"
                        v-model="form.issue_date"
                        type="date"
                        required
                    />
                    <InputError :message="form.errors.issue_date" />
                </div>

                <div class="grid gap-2 sm:col-span-2 lg:col-span-1">
                    <Label>Cliente / Proveedor</Label>
                    <ContactPicker v-model="selectedContact" />
                    <InputError :message="form.errors.contact_id" />
                </div>
            </div>

            <div class="rounded-xl border">
                <div class="flex items-center justify-between border-b p-4">
                    <h3 class="text-sm font-medium">Ítems</h3>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addItem"
                    >
                        <Plus class="size-4" />
                        Agregar ítem
                    </Button>
                </div>

                <div
                    class="hidden grid-cols-12 gap-2 px-3 pt-3 text-xs font-medium text-muted-foreground sm:grid"
                >
                    <span class="col-span-5">Producto / descripción</span>
                    <span class="col-span-2">Cantidad</span>
                    <span class="col-span-2">Precio unitario</span>
                    <span class="col-span-1">% Impuesto</span>
                    <span class="col-span-1 text-right">Subtotal</span>
                </div>

                <div class="divide-y">
                    <div
                        v-for="(row, index) in form.items"
                        :key="index"
                        class="grid grid-cols-2 gap-3 p-3 sm:grid-cols-12 sm:items-start sm:gap-2"
                    >
                        <div class="col-span-2 sm:col-span-5">
                            <ProductPicker
                                v-model="row.description"
                                placeholder="Producto / descripción"
                                @select="
                                    (product) => onProductSelected(row, product)
                                "
                            />
                            <p
                                v-if="row.category"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                Categoría: {{ row.category }}
                            </p>
                            <InputError
                                :message="
                                    (form.errors as Record<string, string>)[
                                        `items.${index}.description`
                                    ]
                                "
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <Label
                                class="mb-1 block text-xs font-normal text-muted-foreground sm:hidden"
                                >Cantidad</Label
                            >
                            <Input
                                v-model.number="row.quantity"
                                type="number"
                                min="0.01"
                                step="0.01"
                                placeholder="Cant."
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <Label
                                class="mb-1 block text-xs font-normal text-muted-foreground sm:hidden"
                                >Precio unitario</Label
                            >
                            <Input
                                v-model.number="row.unit_price"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="Precio"
                            />
                        </div>
                        <div class="sm:col-span-1">
                            <Label
                                class="mb-1 block text-xs font-normal text-muted-foreground sm:hidden"
                                >% Impuesto</Label
                            >
                            <Input
                                v-model.number="row.tax_rate"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="% Imp."
                            />
                        </div>
                        <div
                            class="flex items-center justify-between text-sm font-medium sm:col-span-1 sm:justify-end sm:pt-2 sm:text-right"
                        >
                            <span
                                class="text-xs font-normal text-muted-foreground sm:hidden"
                                >Subtotal</span
                            >
                            <span>{{ money(lineSubtotal(row)) }}</span>
                        </div>
                        <div
                            class="col-span-2 flex justify-end sm:col-span-1 sm:pt-1"
                        >
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Quitar ítem"
                                @click="removeItem(index)"
                            >
                                <Trash2 class="size-4 text-destructive" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border">
                <div class="flex items-center justify-between border-b p-4">
                    <div>
                        <h3 class="text-sm font-medium">Gastos</h3>
                        <p class="text-xs text-muted-foreground">
                            Registro interno de costos (envío, comisión, etc.).
                            No se suman al total ni se muestran al cliente —
                            solo afectan el cálculo de ganancias.
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addExpense"
                    >
                        <Plus class="size-4" />
                        Agregar gasto
                    </Button>
                </div>

                <p
                    v-if="expenses.length === 0"
                    class="p-4 text-sm text-muted-foreground"
                >
                    Sin gastos registrados.
                </p>

                <div v-else>
                    <div
                        class="hidden grid-cols-12 gap-2 px-3 pt-3 text-xs font-medium text-muted-foreground sm:grid"
                    >
                        <span class="col-span-8">Descripción del gasto</span>
                        <span class="col-span-3">Monto</span>
                    </div>
                    <div class="divide-y">
                        <div
                            v-for="(expense, index) in expenses"
                            :key="index"
                            class="flex flex-col gap-3 p-3 sm:grid sm:grid-cols-12 sm:items-center sm:gap-2"
                        >
                            <div class="sm:col-span-8">
                                <Label
                                    class="mb-1 block text-xs font-normal text-muted-foreground sm:hidden"
                                    >Descripción del gasto</Label
                                >
                                <Input
                                    v-model="expense.description"
                                    placeholder="Ej: Envío a domicilio"
                                />
                            </div>
                            <div class="flex items-end gap-2 sm:contents">
                                <div class="flex-1 sm:col-span-3">
                                    <Label
                                        class="mb-1 block text-xs font-normal text-muted-foreground sm:hidden"
                                        >Monto</Label
                                    >
                                    <Input
                                        v-model.number="expense.amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="0.00"
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Quitar gasto"
                                    class="sm:col-span-1 sm:justify-self-end"
                                    @click="removeExpense(index)"
                                >
                                    <Trash2 class="size-4 text-destructive" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="!isEditing && form.document_type === 'factura'"
                class="rounded-xl border"
            >
                <div class="flex items-center justify-between border-b p-4">
                    <div>
                        <h3 class="text-sm font-medium">Abonos</h3>
                        <p class="text-xs text-muted-foreground">
                            Registrá el pago si ya se cobró (podés combinar
                            varios métodos, ej: efectivo + tarjeta).
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addPayment"
                    >
                        <Plus class="size-4" />
                        Agregar abono
                    </Button>
                </div>

                <p
                    v-if="payments.length === 0"
                    class="p-4 text-sm text-muted-foreground"
                >
                    Sin abonos al crear el documento.
                </p>

                <div v-else>
                    <div
                        class="hidden grid-cols-12 gap-2 px-3 pt-3 text-xs font-medium text-muted-foreground sm:grid"
                    >
                        <span class="col-span-5">Método de pago</span>
                        <span class="col-span-3">Monto</span>
                        <span class="col-span-3">Referencia</span>
                    </div>
                    <div class="divide-y">
                        <div
                            v-for="(payment, index) in payments"
                            :key="index"
                            class="flex flex-col gap-3 p-3 sm:grid sm:grid-cols-12 sm:items-center sm:gap-2"
                        >
                            <div class="sm:col-span-5">
                                <Label
                                    class="mb-1 block text-xs font-normal text-muted-foreground sm:hidden"
                                    >Método de pago</Label
                                >
                                <PaymentMethodPicker
                                    v-model="payment.paymentMethod"
                                />
                            </div>
                            <div class="flex items-end gap-2 sm:contents">
                                <div class="flex-1 sm:col-span-3">
                                    <Label
                                        class="mb-1 block text-xs font-normal text-muted-foreground sm:hidden"
                                        >Monto</Label
                                    >
                                    <Input
                                        v-model.number="payment.amount"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        placeholder="0.00"
                                    />
                                </div>
                                <div class="flex-1 sm:col-span-3">
                                    <Label
                                        class="mb-1 block text-xs font-normal text-muted-foreground sm:hidden"
                                        >Referencia</Label
                                    >
                                    <Input
                                        v-model="payment.reference"
                                        placeholder="Nº operación"
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Quitar abono"
                                    class="sm:col-span-1 sm:justify-self-end"
                                    @click="removePayment(index)"
                                >
                                    <Trash2 class="size-4 text-destructive" />
                                </Button>
                            </div>
                        </div>
                    </div>
                    <p
                        class="border-t px-4 py-2 text-right text-sm text-muted-foreground"
                    >
                        Total abonado:
                        <span class="font-medium text-foreground">{{
                            money(paymentsTotal)
                        }}</span>
                    </p>
                </div>
                <InputError
                    class="px-4 pb-3"
                    :message="(form.errors as Record<string, string>).payments"
                />
            </div>

            <div class="rounded-xl border">
                <div
                    class="ml-auto flex w-full max-w-xs flex-col gap-1 p-4 text-sm"
                >
                    <div class="flex justify-between text-muted-foreground">
                        <span>Subtotal ítems</span>
                        <span>{{ money(itemsSubtotal) }}</span>
                    </div>
                    <div class="flex justify-between text-muted-foreground">
                        <span>Impuestos</span>
                        <span>{{ money(taxTotal) }}</span>
                    </div>
                    <div class="flex justify-between text-base font-semibold">
                        <span>Total</span>
                        <span class="text-right">
                            {{ money(total) }}
                            <MoneyBs :amount="total" />
                        </span>
                    </div>
                    <p
                        v-if="expensesTotal > 0"
                        class="pt-1 text-xs text-muted-foreground"
                    >
                        Gastos registrados (no incluidos):
                        {{ money(expensesTotal) }}
                    </p>
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="notes">Notas</Label>
                <Textarea
                    id="notes"
                    v-model="form.notes"
                    placeholder="Observaciones opcionales…"
                />
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    Guardar documento
                </Button>
                <Button as-child variant="ghost">
                    <Link :href="index()">Cancelar</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
