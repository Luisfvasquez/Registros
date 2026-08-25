<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Search, SquarePen, Trash2 } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { ref, watch } from 'vue';
import ProductController, {
    index,
} from '@/actions/App/Http/Controllers/ProductController';
import CategoryPicker from '@/components/CategoryPicker.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
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
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import type { Category, PaginatedData, Product } from '@/types';

const props = defineProps<{
    products: PaginatedData<Product>;
    filters: { search?: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Productos', href: index() },
        ],
    },
});

const search = ref(props.filters.search ?? '');

const runFilter = useDebounceFn(() => {
    router.get(
        index().url,
        { search: search.value || undefined },
        { preserveState: true, replace: true },
    );
}, 300);

watch(search, runFilter);

const dialogOpen = ref(false);
const editing = ref<Product | null>(null);
const selectedCategory = ref<Category | null>(null);

const form = useForm({
    category_id: null as number | null,
    name: '',
    sku: '',
    sale_price: '',
    purchase_cost: '',
});

function openCreate() {
    editing.value = null;
    selectedCategory.value = null;
    form.reset();
    form.clearErrors();
    dialogOpen.value = true;
}

function openEdit(product: Product) {
    editing.value = product;
    selectedCategory.value = product.category ?? null;
    form.category_id = product.category_id;
    form.name = product.name;
    form.sku = product.sku ?? '';
    form.sale_price = product.sale_price;
    form.purchase_cost = product.purchase_cost;
    form.clearErrors();
    dialogOpen.value = true;
}

function submit() {
    form.category_id = selectedCategory.value?.id ?? null;

    if (editing.value) {
        form.put(ProductController.update.url(editing.value), {
            onSuccess: () => (dialogOpen.value = false),
        });
    } else {
        form.post(ProductController.store.url(), {
            onSuccess: () => (dialogOpen.value = false),
        });
    }
}

function destroy(product: Product) {
    if (confirm(`¿Eliminar el producto "${product.name}"?`)) {
        router.delete(ProductController.destroy.url(product));
    }
}

function money(value: string) {
    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
    }).format(Number(value));
}
</script>

<template>
    <Head title="Productos" />

    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <Heading
                title="Productos"
                description="Catálogo de productos y servicios"
            />
            <Button @click="openCreate">
                <Plus class="size-4" />
                Nuevo producto
            </Button>
        </div>

        <div class="relative w-full max-w-xs">
            <Search
                class="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground"
            />
            <Input
                v-model="search"
                placeholder="Buscar por nombre o SKU…"
                class="pl-9"
            />
        </div>

        <div class="rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Nombre</TableHead>
                        <TableHead class="hidden sm:table-cell"
                            >Categoría</TableHead
                        >
                        <TableHead class="hidden md:table-cell">SKU</TableHead>
                        <TableHead>Precio de venta</TableHead>
                        <TableHead class="hidden md:table-cell"
                            >Costo de compra</TableHead
                        >
                        <TableHead class="text-right">Acciones</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="products.data.length === 0" :colspan="6">
                        No hay productos todavía.
                    </TableEmpty>
                    <TableRow
                        v-for="product in products.data"
                        :key="product.id"
                    >
                        <TableCell class="font-medium">{{
                            product.name
                        }}</TableCell>
                        <TableCell class="hidden sm:table-cell">
                            <Badge
                                v-if="product.category"
                                variant="secondary"
                                >{{ product.category.name }}</Badge
                            >
                            <span v-else class="text-muted-foreground">—</span>
                        </TableCell>
                        <TableCell class="hidden md:table-cell">{{
                            product.sku || '—'
                        }}</TableCell>
                        <TableCell>{{ money(product.sale_price) }}</TableCell>
                        <TableCell class="hidden md:table-cell">{{
                            money(product.purchase_cost)
                        }}</TableCell>
                        <TableCell class="text-right">
                            <Button
                                variant="ghost"
                                size="icon"
                                @click="openEdit(product)"
                            >
                                <SquarePen class="size-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                @click="destroy(product)"
                            >
                                <Trash2 class="size-4 text-destructive" />
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination :paginator="products" />
    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{
                    editing ? 'Editar producto' : 'Nuevo producto'
                }}</DialogTitle>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">Nombre</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        placeholder="Ej: Tornillo 3mm"
                        required
                        autofocus
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label>Categoría</Label>
                    <CategoryPicker v-model="selectedCategory" />
                    <InputError :message="form.errors.category_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="sku">Código / SKU</Label>
                    <Input
                        id="sku"
                        v-model="form.sku"
                        placeholder="Ej: TOR-3MM-001"
                    />
                    <InputError :message="form.errors.sku" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    
                    <div class="grid gap-2">
                        <Label for="purchase_cost">Costo de compra</Label>
                        <Input
                            id="purchase_cost"
                            v-model="form.purchase_cost"
                            type="number"
                            step="0.01"
                            min="0" 
                            placeholder="0.00"
                            required
                        />
                        <InputError :message="form.errors.purchase_cost" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="sale_price">Precio de venta</Label>
                        <Input
                            id="sale_price"
                            v-model="form.sale_price"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            required
                        />
                        <InputError :message="form.errors.sale_price" />
                    </div>
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">
                        <Spinner v-if="form.processing" />
                        Guardar
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
