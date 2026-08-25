<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { RefreshCw } from '@lucide/vue';
import { computed, ref } from 'vue';
import { refresh } from '@/actions/App/Http/Controllers/ExchangeRateController';

const page = usePage();
const rate = computed(() => page.props.exchangeRate);
const refreshing = ref(false);

function refreshRate() {
    refreshing.value = true;
    router.post(
        refresh.url(),
        {},
        { onFinish: () => (refreshing.value = false) },
    );
}

function formatRate(value: number) {
    return new Intl.NumberFormat('es-VE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}
</script>

<template>
    <div
        class="flex shrink-0 items-center gap-1.5 rounded-md border border-amber-300 bg-amber-50 px-2 py-1 text-amber-800 sm:gap-2 sm:px-3 sm:py-1.5 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300"
    >
        <p class="m-0 text-xs whitespace-nowrap sm:text-sm">
            <strong
                ><span class="sm:hidden">BCV:</span
                ><span class="hidden sm:inline">Dólar BCV:</span></strong
            >
            <span class="font-bold"
                >Bs. {{ rate ? formatRate(rate) : 'N/D' }}</span
            >
        </p>

        <button
            type="button"
            :disabled="refreshing"
            title="Actualizar tasa ahora"
            class="shrink-0 rounded-full p-1 text-amber-700 transition-colors hover:bg-amber-200 focus:ring-2 focus:ring-amber-400 focus:outline-none disabled:opacity-50 dark:text-amber-300 dark:hover:bg-amber-900"
            @click="refreshRate"
        >
            <RefreshCw :class="['size-3.5', refreshing && 'animate-spin']" />
        </button>
    </div>
</template>
