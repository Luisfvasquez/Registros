<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    amount: number | string;
    class?: string;
}>();

const page = usePage();

const bsAmount = computed(() => {
    const rate = page.props.exchangeRate;

    return rate ? Number(props.amount) * Number(rate) : null;
});

function formatBs(value: number) {
    return new Intl.NumberFormat('es-VE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}
</script>

<template>
    <span
        v-if="bsAmount !== null"
        :class="['block text-xs text-muted-foreground', props.class]"
    >
        Bs. {{ formatBs(bsAmount) }}
    </span>
</template>
