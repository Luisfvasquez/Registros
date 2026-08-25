<script setup lang="ts">
defineProps<{
  colspan: number
}>()
</script>

<template>
  <tr>
    <td :colspan="colspan" class="text-muted-foreground p-8 text-center text-sm">
      <slot />
    </td>
  </tr>
</template>
