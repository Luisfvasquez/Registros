<script setup lang="ts">
import { Plus } from '@lucide/vue';
import { onClickOutside, useDebounceFn } from '@vueuse/core';
import { onMounted, ref, watch } from 'vue';
import {
    index as indexPaymentMethods,
    store as storePaymentMethod,
} from '@/actions/App/Http/Controllers/PaymentMethodController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { PaymentMethod } from '@/types';

const props = defineProps<{
    modelValue: PaymentMethod | null;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: PaymentMethod | null): void;
}>();

const query = ref('');
const results = ref<PaymentMethod[]>([]);
const open = ref(false);
const creating = ref(false);

async function runSearch(term: string) {
    const response = await fetch(
        indexPaymentMethods.url({ query: { q: term } }),
    );
    results.value = await response.json();
}

const debouncedSearch = useDebounceFn(runSearch, 250);

watch(query, (value) => {
    open.value = true;
    debouncedSearch(value);
});

onMounted(() => runSearch(''));

function select(paymentMethod: PaymentMethod) {
    emit('update:modelValue', paymentMethod);
    query.value = '';
    open.value = false;
}

function clear() {
    emit('update:modelValue', null);
    query.value = '';
}

async function createAndSelect() {
    const name = query.value.trim();

    if (!name) {
        return;
    }

    creating.value = true;

    try {
        const response = await fetch(storePaymentMethod.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN':
                    document.querySelector<HTMLMetaElement>(
                        'meta[name="csrf-token"]',
                    )?.content ?? '',
            },
            body: JSON.stringify({ name }),
        });

        if (response.ok) {
            const paymentMethod = await response.json();
            select(paymentMethod);
        }
    } finally {
        creating.value = false;
    }
}

const exactMatch = () =>
    results.value.some(
        (item) => item.name.toLowerCase() === query.value.trim().toLowerCase(),
    );

const root = ref<HTMLElement | null>(null);
onClickOutside(root, () => (open.value = false));
</script>

<template>
    <div ref="root" class="relative">
        <div
            v-if="props.modelValue"
            class="flex items-center justify-between gap-2 rounded-md border px-3 py-2"
        >
            <span class="text-sm font-medium">{{ props.modelValue.name }}</span>
            <Button type="button" variant="ghost" size="sm" @click="clear"
                >Cambiar</Button
            >
        </div>

        <div v-else>
            <Input
                v-model="query"
                placeholder="Buscar o crear método de pago…"
                autocomplete="off"
                @focus="open = true"
            />

            <div
                v-if="open"
                class="absolute z-20 mt-1 w-full overflow-hidden rounded-md border bg-popover shadow-md"
            >
                <div class="max-h-48 overflow-auto p-1">
                    <button
                        v-for="paymentMethod in results"
                        :key="paymentMethod.id"
                        type="button"
                        :class="
                            cn(
                                'flex w-full items-center rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground',
                            )
                        "
                        @click="select(paymentMethod)"
                    >
                        {{ paymentMethod.name }}
                    </button>
                    <p
                        v-if="results.length === 0"
                        class="px-2 py-1.5 text-sm text-muted-foreground"
                    >
                        Sin resultados.
                    </p>
                </div>

                <button
                    v-if="query.trim() && !exactMatch()"
                    type="button"
                    :disabled="creating"
                    class="flex w-full items-center gap-2 border-t px-3 py-2 text-left text-sm font-medium text-primary hover:bg-accent"
                    @click="createAndSelect"
                >
                    <Plus class="size-4" />
                    Crear "{{ query.trim() }}"
                </button>
            </div>
        </div>
    </div>
</template>
