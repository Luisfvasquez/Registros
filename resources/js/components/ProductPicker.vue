<script setup lang="ts">
import { onClickOutside, useDebounceFn } from '@vueuse/core';
import { onMounted, ref, watch } from 'vue';
import { search as searchProducts } from '@/actions/App/Http/Controllers/ProductController';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { Product } from '@/types';

const props = defineProps<{
    modelValue: string;
    placeholder?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'select', product: Product): void;
}>();

const query = ref(props.modelValue);
const results = ref<Product[]>([]);
const open = ref(false);

watch(
    () => props.modelValue,
    (value) => {
        if (value !== query.value) {
            query.value = value;
        }
    },
);

async function runSearch(term: string) {
    const response = await fetch(searchProducts.url({ query: { q: term } }));
    results.value = await response.json();
}

const debouncedSearch = useDebounceFn(runSearch, 250);

function onInput() {
    emit('update:modelValue', query.value);
    open.value = true;
    debouncedSearch(query.value);
}

onMounted(() => runSearch(''));

function select(product: Product) {
    query.value = product.name;
    emit('update:modelValue', product.name);
    emit('select', product);
    open.value = false;
}

const root = ref<HTMLElement | null>(null);
onClickOutside(root, () => (open.value = false));
</script>

<template>
    <div ref="root" class="relative">
        <Input
            v-model="query"
            :placeholder="placeholder ?? 'Descripción / producto…'"
            autocomplete="off"
            @input="onInput"
            @focus="
                open = true;
                debouncedSearch(query);
            "
        />

        <div
            v-if="open && results.length > 0"
            class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-md border bg-popover p-1 shadow-md"
        >
            <button
                v-for="product in results"
                :key="product.id"
                type="button"
                :class="
                    cn(
                        'flex w-full items-center justify-between gap-2 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground',
                    )
                "
                @click="select(product)"
            >
                <span class="min-w-0 truncate">{{ product.name }}</span>
                <span
                    class="flex shrink-0 items-center gap-2 text-xs text-muted-foreground"
                >
                    <span v-if="product.category">{{
                        product.category.name
                    }}</span>
                    <span v-if="product.sku">{{ product.sku }}</span>
                </span>
            </button>
        </div>
    </div>
</template>
