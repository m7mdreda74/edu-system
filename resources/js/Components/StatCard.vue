<script setup>
import { computed, ref, watch } from 'vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    label:  { type: String, required: true },
    value:  { type: [Number, String], default: 0 },
    icon:   { type: String, default: 'chart' },
    tone:   { type: String, default: 'primary' }, // primary | accent | green | red | slate
    hint:   { type: String, default: null },
    href:   { type: String, default: null },
    // Renders the value as money rather than a plain count.
    money:  { type: Boolean, default: false },
});

const TONES = {
    primary: 'bg-primary-50 text-primary-600 dark:bg-primary-950/40 dark:text-primary-400',
    accent:  'bg-accent-50 text-accent-600 dark:bg-accent-950/40 dark:text-accent-400',
    green:   'bg-green-50 text-green-600 dark:bg-green-950/40 dark:text-green-400',
    red:     'bg-red-50 text-red-600 dark:bg-red-950/40 dark:text-red-400',
    slate:   'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-300',
};

const formatter = new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 });

const display = computed(() => {
    if (typeof props.value === 'string') return props.value;
    return props.money
        ? `${formatter.format((props.value ?? 0) / 100)} ر.ق.`
        : formatter.format(props.value ?? 0);
});

// Flash the number when it changes, so a live update is noticed rather than
// silently swapped under the reader's eyes.
const justChanged = ref(false);
let timer = null;

watch(() => props.value, (next, previous) => {
    if (previous === undefined || next === previous) return;

    justChanged.value = true;
    clearTimeout(timer);
    timer = setTimeout(() => (justChanged.value = false), 1200);
});

const tag = computed(() => (props.href ? 'a' : 'div'));
</script>

<template>
    <component
        :is="tag"
        :href="href"
        class="card p-5 flex items-center gap-4 transition-all duration-300"
        :class="href ? 'card-hover cursor-pointer' : ''"
    >
        <div class="p-3.5 rounded-2xl shrink-0" :class="TONES[tone] ?? TONES.primary">
            <Icon :name="icon" class="w-6 h-6" />
        </div>

        <div class="min-w-0">
            <div
                class="text-2xl font-black text-surface-900 dark:text-white truncate transition-colors duration-300"
                :class="justChanged ? 'text-primary-600 dark:text-primary-400' : ''"
            >
                {{ display }}
            </div>
            <div class="text-[11px] text-surface-500 dark:text-surface-400 mt-0.5">{{ label }}</div>
            <div v-if="hint" class="text-[10px] text-surface-400 mt-0.5">{{ hint }}</div>
        </div>
    </component>
</template>
