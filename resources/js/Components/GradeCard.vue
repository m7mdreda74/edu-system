<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

defineProps({
    grade: { type: Object, required: true },
    fullWidth: { type: Boolean, default: false },
});
</script>

<template>
    <Link
        :href="route('grades.show', { key: grade.key })"
        :class="[
            'grade-card card-hover p-5 text-center group flex flex-col items-center justify-between transition-all duration-300',
            { 'grade-card--full': fullWidth },
        ]"
    >
        <div class="w-14 h-14 shrink-0 rounded-full bg-accent-50/70 dark:bg-accent-950/40 text-primary-600 dark:text-primary-400 flex items-center justify-center group-hover:scale-110 group-hover:bg-accent-100 dark:group-hover:bg-accent-900/50 transition-all duration-300 border border-accent-500/10">
            <Icon name="student" class="w-7 h-7 group-hover:animate-float" />
        </div>

        <div class="h-10 w-full flex items-center justify-center font-bold text-surface-800 dark:text-surface-100 text-sm leading-snug line-clamp-2 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
            {{ grade.name }}
        </div>

        <div class="h-5 w-full flex items-center justify-center mt-1">
            <span v-if="grade.track_label" class="badge-primary text-[10px] max-w-full truncate">
                {{ grade.track_label }}
            </span>
        </div>

        <div class="h-5 flex items-center justify-center gap-1.5 mt-1">
            <span class="badge-gray text-[10px] whitespace-nowrap">{{ grade.subjects_count }} مادة</span>
            <span v-if="grade.teachers_count" class="badge-green text-[10px] whitespace-nowrap">
                {{ grade.teachers_count }} معلم
            </span>
        </div>
    </Link>
</template>
