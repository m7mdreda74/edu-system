<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="btn-outline disabled:cursor-not-allowed disabled:opacity-50"
    >
        <slot />
    </button>
</template>
