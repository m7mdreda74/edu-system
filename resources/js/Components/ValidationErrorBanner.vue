<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

const page = usePage();

const message = computed(() => {
    const errors = page.props.errors ?? {};
    const first = Object.values(errors).flat().find((value) => value);

    return typeof first === 'string' ? first : null;
});
</script>

<template>
    <div v-if="message" class="alert-error shadow-card pointer-events-auto flex items-center gap-2" role="alert" dir="rtl">
        <Icon name="error" class="w-5 h-5 text-red-500 shrink-0" />
        <span>{{ message }}</span>
    </div>
</template>
