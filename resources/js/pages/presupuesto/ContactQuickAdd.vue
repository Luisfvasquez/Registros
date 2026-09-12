<script setup lang="ts">
import { UserPlus } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import lines from '@/routes/presupuesto/lines';
import type { BudgetContactType, BudgetLine } from '@/types';
import { api, firstError } from './sheet';

/**
 * Alta rápida de un proveedor o cliente sin salir de la hoja de compras o
 * ventas. Queda en el Directorio, así que sirve para todos los períodos.
 */
const props = defineProps<{
    periodId: number;
    tipo: BudgetContactType;
    label: string;
}>();

const emit = defineEmits<{ created: [contact: BudgetLine] }>();

const open = ref(false);
const saving = ref(false);
const form = ref({ party_name: '', telefono: '', notas: '' });

async function submit(): Promise<void> {
    if (form.value.party_name.trim() === '') {
        toast.error('Escribí el nombre.');

        return;
    }

    saving.value = true;

    try {
        const { line } = await api<{ line: BudgetLine }>(
            lines.store.url(props.periodId),
            'POST',
            {
                section: 'contacto',
                tipo: props.tipo,
                party_name: form.value.party_name.trim(),
                telefono: form.value.telefono.trim() || null,
                notas: form.value.notas.trim() || null,
            },
        );

        emit('created', line);
        toast.success(`${props.label} agregado al Directorio.`);
        form.value = { party_name: '', telefono: '', notas: '' };
        open.value = false;
    } catch (error) {
        toast.error(firstError(error));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <button
        type="button"
        class="inline-flex items-center gap-1 rounded border border-neutral-300 bg-white px-2 py-1 text-[12px] font-medium text-neutral-700 transition hover:bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-200 dark:hover:bg-neutral-800"
        @click="open = true"
    >
        <UserPlus class="size-3.5" />
        Nuevo {{ label }}
    </button>

    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Nuevo {{ label }}</DialogTitle>
                <DialogDescription>
                    Se guarda en el Directorio y queda disponible en todos los
                    períodos.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-3" @submit.prevent="submit">
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Nombre</span>
                    <input
                        v-model="form.party_name"
                        type="text"
                        autofocus
                        class="h-9 rounded border border-neutral-300 bg-white px-2 dark:border-neutral-700 dark:bg-neutral-950"
                    />
                </label>

                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Teléfono</span>
                    <input
                        v-model="form.telefono"
                        type="text"
                        class="h-9 rounded border border-neutral-300 bg-white px-2 dark:border-neutral-700 dark:bg-neutral-950"
                    />
                </label>

                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Notas</span>
                    <input
                        v-model="form.notas"
                        type="text"
                        class="h-9 rounded border border-neutral-300 bg-white px-2 dark:border-neutral-700 dark:bg-neutral-950"
                    />
                </label>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="open = false">
                        Cancelar
                    </Button>
                    <Button type="submit" :disabled="saving">Guardar</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
