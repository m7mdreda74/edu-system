<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

defineProps({
    showText: { type: Boolean, default: true },
    compact:  { type: Boolean, default: false },
    full:     { type: Boolean, default: false },
});
</script>

<template>
    <div v-if="full" class="inline-flex items-center" dir="rtl">
        <img
            src="/images/logo.png"
            alt="بوابة المجد التعليمية"
            :class="compact ? 'h-10 w-auto' : 'h-14 w-auto'"
            class="object-contain drop-shadow-sm select-none"
        />
    </div>
    <div v-else class="inline-flex items-center gap-2.5" dir="rtl">
        <ApplicationLogo :class="compact ? 'h-9 w-9' : 'h-11 w-11'" class="shrink-0 drop-shadow-sm transition-transform duration-300 group-hover:scale-105" />
        <div v-if="showText" class="flex flex-col leading-none text-start select-none">
            <span :class="compact ? 'text-base font-extrabold' : 'text-xl font-black'" class="tracking-wide text-gradient-primary">بوابة المجد</span>
            <span v-if="!compact" class="mt-1 text-[10px] font-bold text-surface-500 dark:text-surface-400 tracking-wider">التعليمية</span>
        </div>
    </div>
</template>
