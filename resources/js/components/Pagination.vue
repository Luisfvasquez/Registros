<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';
import type { PaginatedData } from '@/types';

defineProps<{
    paginator: PaginatedData<unknown>;
}>();
</script>

<template>
    <div
        v-if="paginator.last_page > 1"
        class="flex flex-wrap items-center justify-between gap-2 text-sm"
    >
        <p class="text-muted-foreground">
            Mostrando {{ paginator.from }}–{{ paginator.to }} de
            {{ paginator.total }}
        </p>
        <div class="flex items-center gap-1">
            <Link
                v-for="(link, index) in paginator.links"
                :key="index"
                :href="link.url ?? '#'"
                :class="
                    cn(
                        'rounded-md px-3 py-1.5 text-sm',
                        link.active
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-accent',
                        !link.url && 'pointer-events-none opacity-40',
                    )
                "
            >
                <span v-html="link.label" />
            </Link>
        </div>
    </div>
</template>
