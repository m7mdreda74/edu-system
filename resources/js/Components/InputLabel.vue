<script setup>
const props = defineProps({
    value: {
        type: String,
    },
    for: {
        type: String,
        default: undefined,
    },
});
</script>

<template>
    <label :for="props.for" class="block text-sm font-semibold text-surface-700 dark:text-surface-300 mb-1.5">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
