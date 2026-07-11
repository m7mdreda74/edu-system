<script setup>
import { ref, watch, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import AppLayout from '@/Layouts/AppLayout.vue';
import CourseCard from '@/Components/CourseCard.vue';

const props = defineProps({
    courses:  { type: Object, required: true },  // LengthAwarePaginator
    subjects: { type: Array,  default: () => [] },
    filters:  { type: Object, default: () => ({}) },
});

// Local filter state — debounced search
const search      = ref(props.filters.search ?? '');
const subjectId   = ref(props.filters.subject_id ?? '');
const gradeLevel  = ref(props.filters.grade_level ?? '');
const stage       = ref(props.filters.stage ?? '');
const level       = ref(props.filters.level ?? '');
const sort        = ref(props.filters.sort ?? 'latest');

const filteredSubjects = computed(() => {
    let result = props.subjects;
    if (gradeLevel.value) {
        result = result.filter(s => s.grade_level === gradeLevel.value || s.grade_level === 'all');
    } else if (stage.value) {
        result = result.filter(s => {
            if (s.grade_level === 'all') return true;
            const gl = page.props.grade_levels?.find(g => g.key === s.grade_level);
            return gl && gl.stage === stage.value;
        });
    }
    return result;
});

// Filter grade levels based on selected stage
const page = usePage();
const filteredGradeLevels = computed(() => {
    const gls = page.props.grade_levels || [];
    if (!stage.value) return gls.filter(g => g.key !== 'all');
    return gls.filter(g => g.stage === stage.value && g.key !== 'all');
});

function onStageChange() {
    // If current gradeLevel is not in the filtered grade levels, reset it
    const hasGrade = filteredGradeLevels.value.some(g => g.key === gradeLevel.value);
    if (!hasGrade) {
        gradeLevel.value = '';
    }
    applyFilters();
}

// Debounce search to avoid request on every keystroke
const debouncedSearch = useDebounceFn(() => applyFilters(), 300);

watch(search, () => debouncedSearch());

function applyFilters() {
    router.get(route('courses.index'), {
        search:      search.value     || undefined,
        subject_id:  subjectId.value  || undefined,
        grade_level: gradeLevel.value || undefined,
        stage:       stage.value      || undefined,
        level:       level.value      || undefined,
        sort:        sort.value       || undefined,
    }, { preserveState: true, replace: true });
}

function clearFilters() {
    search.value     = '';
    subjectId.value  = '';
    gradeLevel.value = '';
    stage.value      = '';
    level.value      = '';
    sort.value       = 'latest';
    applyFilters();
}

const hasActiveFilters = () =>
    !!(search.value || subjectId.value || gradeLevel.value || stage.value || level.value);

const stageLabels = {
    primary: 'المرحلة الابتدائية',
    preparatory: 'المرحلة الإعدادية',
    secondary: 'المرحلة الثانوية',
};
function getStageLabel(key) {
    return stageLabels[key] || key;
}
const levelLabels = {
    beginner: 'مبتدئ',
    intermediate: 'متوسط',
    advanced: 'متقدم',
};
</script>

<template>
    <AppLayout>
        <Head title="الكورسات" />

        <!-- ── Page Header ──────────────────────────────────────── -->
        <div class="bg-gradient-to-b from-surface-100 to-white dark:from-surface-900 dark:to-surface-950 py-12">
            <div class="container-app px-4">
                <h1 class="text-4xl font-black text-surface-900 dark:text-white mb-2">الكورسات</h1>
                <p class="text-surface-500 dark:text-surface-400">
                    {{ courses.total }} كورس متاح — اختر ما يناسبك
                </p>
            </div>
        </div>

        <div class="container-app px-4 py-8">
            <!-- Active Filters Bar -->
            <div v-if="hasActiveFilters()" class="flex flex-wrap items-center gap-2 mb-6 p-4 rounded-xl bg-surface-50 dark:bg-surface-900/40 border border-surface-100 dark:border-surface-800/60" dir="rtl">
                <span class="text-xs font-bold text-surface-500 dark:text-surface-450">التصفية النشطة:</span>
                
                <!-- Search tag -->
                <span v-if="search" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-primary-50 text-primary-700 border border-primary-200 dark:bg-primary-950/40 dark:text-primary-300 dark:border-primary-900/50">
                    <span>البحث: "{{ search }}"</span>
                    <button @click="search = ''; applyFilters()" class="hover:text-red-500 font-bold">×</button>
                </span>

                <!-- Subject tag -->
                <span v-if="subjectId" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-primary-50 text-primary-700 border border-primary-200 dark:bg-primary-950/40 dark:text-primary-300 dark:border-primary-900/50">
                    <span>المادة: {{ props.subjects.find(s => s.id === Number(subjectId))?.name }}</span>
                    <button @click="subjectId = ''; applyFilters()" class="hover:text-red-500 font-bold">×</button>
                </span>

                <!-- Stage tag -->
                <span v-if="stage" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-primary-50 text-primary-700 border border-primary-200 dark:bg-primary-950/40 dark:text-primary-300 dark:border-primary-900/50">
                    <span>المرحلة: {{ getStageLabel(stage) }}</span>
                    <button @click="stage = ''; applyFilters()" class="hover:text-red-500 font-bold">×</button>
                </span>

                <!-- Grade Level tag -->
                <span v-if="gradeLevel" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-primary-50 text-primary-700 border border-primary-200 dark:bg-primary-950/40 dark:text-primary-300 dark:border-primary-900/50">
                    <span>الصف: {{ page.props.grade_levels?.find(gl => gl.key === gradeLevel)?.name }}</span>
                    <button @click="gradeLevel = ''; applyFilters()" class="hover:text-red-500 font-bold">×</button>
                </span>

                <!-- Level tag -->
                <span v-if="level" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-primary-50 text-primary-700 border border-primary-200 dark:bg-primary-950/40 dark:text-primary-300 dark:border-primary-900/50">
                    <span>المستوى: {{ levelLabels[level] }}</span>
                    <button @click="level = ''; applyFilters()" class="hover:text-red-500 font-bold">×</button>
                </span>

                <button @click="clearFilters" class="text-xs text-red-500 hover:text-red-650 font-bold mr-auto">
                    مسح الكل ×
                </button>
            </div>

            <!-- Results count -->
            <div class="flex items-center justify-between mb-6">
                <p class="text-sm text-surface-500 dark:text-surface-400">
                    عرض {{ courses.from }}–{{ courses.to }} من {{ courses.total }} نتيجة
                </p>
            </div>

            <!-- Empty state -->
            <div v-if="courses.data.length === 0"
                 class="card p-16 text-center flex flex-col items-center justify-center">
                <div class="p-4 bg-surface-100 dark:bg-surface-800 text-surface-400 rounded-full mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-surface-700 dark:text-white mb-2">لا توجد نتائج</h3>
                <p class="text-surface-500 dark:text-surface-400 mb-6">جرّب تغيير معايير البحث</p>
                <button @click="clearFilters" class="btn-primary">عرض كل الكورسات</button>
            </div>

            <!-- Grid -->
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5 mb-8">
                        <CourseCard
                            v-for="course in courses.data"
                            :key="course.id"
                            :course="course"
                        />
                    </div>

                    <!-- Pagination -->
                    <div v-if="courses.last_page > 1"
                         class="flex items-center justify-center gap-2 flex-wrap">
                        <Link
                            v-for="link in courses.links"
                            :key="link.label"
                            :href="link.url ?? '#'"
                            class="px-3.5 py-2 rounded-lg text-sm font-medium transition-colors duration-150"
                            :class="link.active
                                ? 'bg-primary-600 text-white'
                                : link.url
                                    ? 'bg-white dark:bg-surface-800 text-surface-700 dark:text-surface-200 hover:bg-surface-100 dark:hover:bg-surface-700 border border-surface-200 dark:border-surface-600'
                                    : 'opacity-40 cursor-not-allowed bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-600 text-surface-400'"
                            :aria-disabled="!link.url"
                            preserve-scroll
                        >
                            <span v-html="link.label"></span>
                        </Link>
                    </div>
        </div>
    </AppLayout>
</template>
