<script setup>
import { ref, watch, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
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
const level       = ref(props.filters.level ?? '');
const sort        = ref(props.filters.sort ?? 'latest');

const filteredSubjects = computed(() => {
    if (!gradeLevel.value) return props.subjects;
    return props.subjects.filter(s => s.grade_level === gradeLevel.value || s.grade_level === 'all');
});

// Debounce search to avoid request on every keystroke
const debouncedSearch = useDebounceFn(() => applyFilters(), 300);

watch(search, () => debouncedSearch());

function applyFilters() {
    router.get(route('courses.index'), {
        search:      search.value     || undefined,
        subject_id:  subjectId.value  || undefined,
        grade_level: gradeLevel.value || undefined,
        level:       level.value      || undefined,
        sort:        sort.value       || undefined,
    }, { preserveState: true, replace: true });
}

function clearFilters() {
    search.value     = '';
    subjectId.value  = '';
    gradeLevel.value = '';
    level.value      = '';
    sort.value       = 'latest';
    applyFilters();
}

const hasActiveFilters = () =>
    !!(search.value || subjectId.value || gradeLevel.value || level.value);
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
            <div class="flex flex-col lg:flex-row gap-8">

                <!-- ── Sidebar Filters ──────────────────────────── -->
                <aside class="w-full lg:w-64 flex-shrink-0">
                    <div class="card p-5 sticky top-20 space-y-5">
                        <div class="flex items-center justify-between">
                            <h2 class="font-bold text-surface-800 dark:text-white">تصفية النتائج</h2>
                            <button v-if="hasActiveFilters()"
                                @click="clearFilters"
                                class="text-xs text-red-500 hover:text-red-700 font-medium">
                                مسح الكل ✕
                            </button>
                        </div>

                        <!-- Search -->
                        <div>
                            <label class="input-label">بحث</label>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="ابحث عن كورس..."
                                class="input"
                                id="search-input"
                            />
                        </div>

                        <!-- Subject -->
                        <div>
                            <label class="input-label">المادة</label>
                            <select v-model="subjectId" @change="applyFilters" class="input" id="subject-filter">
                                <option value="">كل المواد</option>
                                <option v-for="s in filteredSubjects" :key="s.id" :value="s.id">
                                    {{ s.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Grade Level -->
                        <div>
                            <label class="input-label">الصف الدراسي</label>
                            <select v-model="gradeLevel" @change="applyFilters" class="input" id="grade-filter">
                                <option value="">كل الصفوف</option>
                                <option v-for="gl in $page.props.grade_levels?.filter(g => g.key !== 'all')" :key="gl.key" :value="gl.key">
                                    {{ gl.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Level -->
                        <div>
                            <label class="input-label">المستوى</label>
                            <select v-model="level" @change="applyFilters" class="input" id="level-filter">
                                <option value="">كل المستويات</option>
                                <option value="beginner">مبتدئ</option>
                                <option value="intermediate">متوسط</option>
                                <option value="advanced">متقدم</option>
                            </select>
                        </div>

                        <!-- Sort -->
                        <div>
                            <label class="input-label">الترتيب</label>
                            <select v-model="sort" @change="applyFilters" class="input" id="sort-filter">
                                <option value="latest">الأحدث</option>
                                <option value="popular">الأكثر شعبية</option>
                                <option value="price_asc">السعر: من الأقل</option>
                                <option value="price_desc">السعر: من الأعلى</option>
                            </select>
                        </div>
                    </div>
                </aside>

                <!-- ── Courses Grid ──────────────────────────────── -->
                <div class="flex-1 min-w-0">

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
                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5 mb-8">
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
            </div>
        </div>
    </AppLayout>
</template>
