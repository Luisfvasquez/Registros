<script setup lang="ts">
import { ChevronsUpDown } from '@lucide/vue';
import { onClickOutside, useDebounceFn } from '@vueuse/core';
import { onMounted, ref, watch } from 'vue';
import { search as searchContacts } from '@/actions/App/Http/Controllers/ContactController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { Contact } from '@/types';

const props = defineProps<{
    modelValue: Contact | null;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: Contact | null): void;
}>();

const query = ref('');
const results = ref<Contact[]>([]);
const open = ref(false);
const loading = ref(false);

async function runSearch(term: string) {
    loading.value = true;

    try {
        const response = await fetch(
            searchContacts.url({ query: { q: term } }),
        );
        results.value = await response.json();
    } finally {
        loading.value = false;
    }
}

const debouncedSearch = useDebounceFn(runSearch, 250);

watch(query, (value) => {
    open.value = true;
    debouncedSearch(value);
});

onMounted(() => runSearch(''));

function select(contact: Contact) {
    emit('update:modelValue', contact);
    query.value = '';
    open.value = false;
}

function clear() {
    emit('update:modelValue', null);
    query.value = '';
}

const root = ref<HTMLElement | null>(null);
onClickOutside(root, () => (open.value = false));
</script>

<template>
    <div ref="root" class="relative">
        <div
            v-if="props.modelValue"
            class="flex items-center justify-between gap-2 rounded-md border px-3 py-2"
        >
            <div class="min-w-0">
                <p class="truncate text-sm font-medium">
                    {{ props.modelValue.name }}
                </p>
                <p class="truncate text-xs text-muted-foreground">
                    {{
                        props.modelValue.document ||
                        props.modelValue.phone ||
                        'Sin datos adicionales'
                    }}
                </p>
            </div>
            <Button type="button" variant="ghost" size="sm" @click="clear"
                >Cambiar</Button
            >
        </div>

        <div v-else>
            <div class="relative">
                <Input
                    v-model="query"
                    placeholder="Buscar cliente o proveedor…"
                    autocomplete="off"
                    class="pr-9"
                    @focus="open = true"
                />
                <ChevronsUpDown
                    class="pointer-events-none absolute top-2.5 right-3 size-4 text-muted-foreground"
                />
            </div>

            <div
                v-if="open"
                class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-md border bg-popover p-1 shadow-md"
            >
                <p
                    v-if="loading"
                    class="px-2 py-1.5 text-sm text-muted-foreground"
                >
                    Buscando…
                </p>
                <p
                    v-else-if="results.length === 0"
                    class="px-2 py-1.5 text-sm text-muted-foreground"
                >
                    Sin resultados. Crea el contacto desde el módulo de
                    Contactos.
                </p>
                <button
                    v-for="contact in results"
                    :key="contact.id"
                    type="button"
                    :class="
                        cn(
                            'flex w-full items-center justify-between gap-2 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground',
                        )
                    "
                    @click="select(contact)"
                >
                    <span class="min-w-0 truncate">
                        {{ contact.name }}
                        <span class="text-muted-foreground"
                            >—
                            {{
                                contact.document ||
                                contact.phone ||
                                contact.type
                            }}</span
                        >
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>
