<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        class="btn inline-flex bg-red-600 text-white hover:bg-red-700 focus:ring-red-500/40 disabled:cursor-not-allowed disabled:opacity-50"
    >
        <slot />
    </button>
</template>
