<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

defineProps({
    showText: { type: Boolean, default: true },
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <div class="inline-flex items-center gap-2" dir="rtl">
        <ApplicationLogo :class="compact ? 'h-8 w-8' : 'h-10 w-10'" class="shrink-0" />
        <div v-if="showText" class="flex flex-col leading-none text-start">
            <span class="text-lg font-extrabold tracking-wide text-gradient-primary">التفوق</span>
            <span v-if="!compact" class="mt-1 text-[10px] font-medium text-surface-500 dark:text-surface-400">منصة تعليمية</span>
        </div>
    </div>
</template>
