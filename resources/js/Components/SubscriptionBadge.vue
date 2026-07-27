<script setup>
import Icon from '@/Components/Icon.vue';

defineProps({
    subscribed: { type: Boolean, default: false },
    // What the student is subscribed *with*, when there is one to name.
    withName:   { type: String, default: null },
    size:       { type: String, default: 'sm' }, // sm | md
});
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full font-bold whitespace-nowrap"
        :class="[
            size === 'md' ? 'px-3 py-1.5 text-xs' : 'px-2.5 py-1 text-[10px]',
            subscribed
                ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300'
                : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
        ]"
    >
        <span
            class="rounded-full shrink-0"
            :class="[
                size === 'md' ? 'w-2 h-2' : 'w-1.5 h-1.5',
                subscribed ? 'bg-green-500' : 'bg-red-500',
            ]"
        ></span>

        <Icon v-if="subscribed" name="success" :class="size === 'md' ? 'w-3.5 h-3.5' : 'w-3 h-3'" />

        <template v-if="subscribed">
            <span v-if="withName">مشترك مع {{ withName }}</span>
            <span v-else>مشترك</span>
        </template>
        <template v-else>
            <span v-if="withName">غير مشترك</span>
            <span v-else>غير مشترك</span>
        </template>
    </span>
</template>
