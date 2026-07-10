<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    course: { type: Object, required: true },
});

// Price display helpers — prices come from server in halala/cents
const originalPriceFormatted = computed(() => {
    if (!props.course.price) return null;
    return formatQAR(props.course.price);
});

const effectivePriceFormatted = computed(() => {
    const p = props.course.discount_price ?? props.course.price;
    return p === 0 ? 'مجاني' : formatQAR(p);
});

const hasDiscount = computed(() =>
    props.course.discount_price != null && props.course.discount_price < props.course.price
);

const durationFormatted = computed(() => {
    if (!props.course.total_duration) return null;
    const hours   = Math.floor(props.course.total_duration / 3600);
    const minutes = Math.floor((props.course.total_duration % 3600) / 60);
    if (hours > 0) return `${hours}س ${minutes}د`;
    return `${minutes} دقيقة`;
});

function formatQAR(halala) {
    return new Intl.NumberFormat('ar-QA', {
        style: 'currency',
        currency: 'QAR',
        minimumFractionDigits: 0,
    }).format(halala / 100);
}

const levelLabels = { beginner: 'مبتدئ', intermediate: 'متوسط', advanced: 'متقدم' };
const levelColors = {
    beginner:     'badge-green',
    intermediate: 'badge-primary',
    advanced:     'badge-accent',
};
</script>

<template>
    <Link :href="route('courses.show', { slug: course.slug })" class="course-card block">
        <!-- Thumbnail -->
        <div class="relative overflow-hidden aspect-video bg-surface-200 dark:bg-surface-700">
            <img
                v-if="course.thumbnail"
                :src="course.thumbnail"
                :alt="course.title"
                class="course-card-img transition-transform duration-300 hover:scale-105"
                loading="lazy"
            />
            <div v-else class="course-card-img flex items-center justify-center">
                <span class="text-4xl opacity-30">📚</span>
            </div>

            <!-- Discount badge -->
            <div v-if="hasDiscount"
                 class="absolute top-2 start-2 badge-accent text-xs font-bold">
                خصم {{ Math.round((1 - course.discount_price / course.price) * 100) }}%
            </div>

            <!-- Free preview badge -->
            <div class="absolute top-2 end-2 badge bg-black/60 text-white text-xs">
                {{ course.total_lessons || 0 }} درس
            </div>
        </div>

        <!-- Body -->
        <div class="course-card-body">

            <!-- Subject + Grade -->
            <div class="flex items-center gap-2 flex-wrap">
                <span v-if="course.subject" class="badge-gray text-xs">
                    {{ course.subject.name }}
                </span>
                <span v-if="course.grade_level && course.grade_level !== 'all'"
                      class="badge-primary text-xs">
                    {{ { grade_10: 'عاشر', grade_11: 'حادي عشر', grade_12: 'ثاني عشر' }[course.grade_level] }}
                </span>
            </div>

            <!-- Title -->
            <h3 class="font-bold text-surface-900 dark:text-white text-sm leading-snug line-clamp-2">
                {{ course.title }}
            </h3>

            <!-- Teacher -->
            <div v-if="course.teacher" class="flex items-center gap-2">
                <div class="avatar-sm bg-primary-100 dark:bg-primary-900 text-xs">
                    <img v-if="course.teacher.avatar"
                         :src="course.teacher.avatar"
                         :alt="course.teacher.name"
                         class="w-full h-full object-cover">
                    <span v-else class="text-primary-700 font-bold">
                        {{ course.teacher.name?.charAt(0) }}
                    </span>
                </div>
                <span class="text-xs text-surface-500 dark:text-surface-400">
                    {{ course.teacher.name }}
                </span>
            </div>

            <!-- Duration + Level -->
            <div class="flex items-center gap-2 text-xs text-surface-400 dark:text-surface-500">
                <span v-if="durationFormatted">⏱ {{ durationFormatted }}</span>
                <span v-if="course.level" :class="levelColors[course.level]">
                    {{ levelLabels[course.level] }}
                </span>
            </div>

            <!-- Price -->
            <div class="flex items-center gap-2 pt-1 border-t border-surface-100 dark:border-surface-700 mt-auto">
                <span class="text-lg font-black text-primary-700 dark:text-primary-400">
                    {{ effectivePriceFormatted }}
                </span>
                <span v-if="hasDiscount"
                      class="text-xs text-surface-400 line-through">
                    {{ originalPriceFormatted }}
                </span>
            </div>
        </div>
    </Link>
</template>
