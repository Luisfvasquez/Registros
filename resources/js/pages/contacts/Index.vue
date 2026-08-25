<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Plus, Search, SquarePen, Trash2 } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { ref, watch } from 'vue';
import ContactController, {
    index,
} from '@/actions/App/Http/Controllers/ContactController';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import type { Contact, PaginatedData } from '@/types';

const props = defineProps<{
    contacts: PaginatedData<Contact>;
    filters: { search?: string; type?: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Contactos', href: index() },
        ],
    },
});

const typeLabels: Record<string, string> = {
    cliente: 'Cliente',
    proveedor: 'Proveedor',
    ambos: 'Cliente y proveedor',
};

const search = ref(props.filters.search ?? '');
const type = ref(props.filters.type ?? 'all');

const runFilter = useDebounceFn(() => {
    router.get(
        index().url,
        {
            search: search.value || undefined,
            type: type.value === 'all' ? undefined : type.value,
        },
        { preserveState: true, replace: true },
    );
}, 300);

watch([search, type], runFilter);

const dialogOpen = ref(false);
const editing = ref<Contact | null>(null);

const form = useForm({
    type: 'cliente',
    name: '',
    document: '',
    phone_country_code: '+54',
    phone: '',
    email: '',
    address: '',
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    dialogOpen.value = true;
}

function openEdit(contact: Contact) {
    editing.value = contact;
    form.type = contact.type;
    form.name = contact.name;
    form.document = contact.document ?? '';
    form.phone_country_code = contact.phone_country_code ?? '+54';
    form.phone = contact.phone ?? '';
    form.email = contact.email ?? '';
    form.address = contact.address ?? '';
    form.clearErrors();
    dialogOpen.value = true;
}

function submit() {
    if (editing.value) {
        form.put(ContactController.update.url(editing.value), {
            onSuccess: () => (dialogOpen.value = false),
        });
    } else {
        form.post(ContactController.store.url(), {
            onSuccess: () => (dialogOpen.value = false),
        });
    }
}

function destroy(contact: Contact) {
    if (confirm(`¿Eliminar el contacto "${contact.name}"?`)) {
        router.delete(ContactController.destroy.url(contact));
    }
}
</script>

<template>
    <Head title="Contactos" />

    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <Heading
                title="Contactos"
                description="Clientes y proveedores del negocio"
            />
            <Button @click="openCreate">
                <Plus class="size-4" />
                Nuevo contacto
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
                    placeholder="Buscar por nombre, documento o teléfono…"
                    class="pl-9"
                />
            </div>
            <Select v-model="type">
                <SelectTrigger class="w-full sm:w-48"
                    ><SelectValue placeholder="Tipo"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Todos</SelectItem>
                    <SelectItem value="cliente">Clientes</SelectItem>
                    <SelectItem value="proveedor">Proveedores</SelectItem>
                    <SelectItem value="ambos">Cliente y proveedor</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div class="rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Nombre</TableHead>
                        <TableHead>Tipo</TableHead>
                        <TableHead class="hidden md:table-cell"
                            >Documento</TableHead
                        >
                        <TableHead class="hidden sm:table-cell"
                            >Teléfono</TableHead
                        >
                        <TableHead class="hidden lg:table-cell"
                            >Email</TableHead
                        >
                        <TableHead class="text-right">Acciones</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="contacts.data.length === 0" :colspan="6">
                        No hay contactos todavía.
                    </TableEmpty>
                    <TableRow
                        v-for="contact in contacts.data"
                        :key="contact.id"
                    >
                        <TableCell class="font-medium">
                            <Link
                                :href="ContactController.show.url(contact)"
                                class="hover:underline"
                            >
                                {{ contact.name }}
                            </Link>
                        </TableCell>
                        <TableCell
                            ><Badge variant="secondary">{{
                                typeLabels[contact.type]
                            }}</Badge></TableCell
                        >
                        <TableCell class="hidden md:table-cell">{{
                            contact.document || '—'
                        }}</TableCell>
                        <TableCell class="hidden sm:table-cell">{{
                            contact.phone
                                ? `${contact.phone_country_code ?? ''} ${contact.phone}`
                                : '—'
                        }}</TableCell>
                        <TableCell class="hidden lg:table-cell">{{
                            contact.email || '—'
                        }}</TableCell>
                        <TableCell class="text-right">
                            <Button
                                variant="ghost"
                                size="icon"
                                @click="openEdit(contact)"
                            >
                                <SquarePen class="size-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                @click="destroy(contact)"
                            >
                                <Trash2 class="size-4 text-destructive" />
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination :paginator="contacts" />
    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{
                    editing ? 'Editar contacto' : 'Nuevo contacto'
                }}</DialogTitle>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="type">Tipo</Label>
                    <Select v-model="form.type">
                        <SelectTrigger id="type" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="cliente">Cliente</SelectItem>
                            <SelectItem value="proveedor">Proveedor</SelectItem>
                            <SelectItem value="ambos"
                                >Cliente y proveedor</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.type" />
                </div>

                <div class="grid gap-2">
                    <Label for="name">Nombre / Razón social</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        placeholder="Ej: Juan Pérez"
                        required
                        autofocus
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="document">Documento / Identificación</Label>
                    <Input
                        id="document"
                        v-model="form.document"
                        placeholder="Ej: 30-12345678-9"
                    />
                    <InputError :message="form.errors.document" />
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <div class="grid gap-2">
                        <Label for="phone_country_code">Código país</Label>
                        <Input
                            id="phone_country_code"
                            v-model="form.phone_country_code"
                            placeholder="+54"
                        />
                    </div>
                    <div class="col-span-2 grid gap-2">
                        <Label for="phone">Teléfono (WhatsApp)</Label>
                        <Input
                            id="phone"
                            v-model="form.phone"
                            placeholder="9351..."
                        />
                    </div>
                </div>
                <InputError :message="form.errors.phone" />

                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        placeholder="cliente@ejemplo.com"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="address">Dirección</Label>
                    <Input
                        id="address"
                        v-model="form.address"
                        placeholder="Calle, número, ciudad"
                    />
                    <InputError :message="form.errors.address" />
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
