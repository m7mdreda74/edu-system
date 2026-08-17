<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
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
        class="btn-primary disabled:cursor-not-allowed disabled:opacity-50"
    >
        <slot />
    </button>
</template>
