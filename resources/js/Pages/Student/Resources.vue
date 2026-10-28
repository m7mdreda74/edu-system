<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    subjects:  { type: Array, default: () => [] },
    resources: { type: Array, default: () => [] },
});

// Active subject tab
const activeSubjectId = ref(props.subjects[0]?.id ?? null);

const currentSubject = computed(() =>
    props.subjects.find((s) => s.id === activeSubjectId.value) ?? props.subjects[0] ?? null
);

// Lesson search & filters
const searchQuery = ref('');
const activeFilter = ref('all'); // 'all' | 'video' | 'booklet' | 'quiz'
const selectedSemester = ref('all'); // 'all' | '1' | '2'

const availableSemesters = computed(() => {
    if (!currentSubject.value) return [];
    const map = new Map();
    currentSubject.value.units.forEach((u) => {
        if (u.term_name) {
            map.set(u.term_number || 1, u.term_name);
        }
    });
    return Array.from(map.entries()).map(([num, name]) => ({ num, name }));
});

// Lesson accordion state: { [lessonId]: boolean } - default expanded
const expandedLessons = ref({});

function toggleLesson(lessonId) {
    expandedLessons.value[lessonId] = !isExpanded(lessonId);
}

function isExpanded(lessonId) {
    return expandedLessons.value[lessonId] !== false; // expanded by default
}

function expandAll() {
    if (!currentSubject.value) return;
    currentSubject.value.units.forEach((unit) => {
        unit.lessons.forEach((lesson) => {
            expandedLessons.value[lesson.id] = true;
        });
    });
}

function collapseAll() {
    if (!currentSubject.value) return;
    currentSubject.value.units.forEach((unit) => {
        unit.lessons.forEach((lesson) => {
            expandedLessons.value[lesson.id] = false;
        });
    });
}

// Filtered units & lessons
const filteredUnits = computed(() => {
    if (!currentSubject.value) return [];
    const query = searchQuery.value.trim().toLowerCase();

    return currentSubject.value.units
        .filter((unit) => {
            if (selectedSemester.value !== 'all' && String(unit.term_number || 1) !== String(selectedSemester.value)) {
                return false;
            }
            return true;
        })
        .map((unit) => {
            const lessons = unit.lessons.filter((lesson) => {
                // Filter by search query
                const matchesQuery = !query
                    || lesson.title.toLowerCase().includes(query)
                    || (lesson.description && lesson.description.toLowerCase().includes(query));

                if (!matchesQuery) return false;

                // Filter by content type
                if (activeFilter.value === 'video') {
                    return lesson.videos && lesson.videos.length > 0;
                }
                if (activeFilter.value === 'booklet') {
                    return lesson.booklets && lesson.booklets.length > 0;
                }
                if (activeFilter.value === 'quiz') {
                    return lesson.assessments && lesson.assessments.length > 0;
                }

                return true;
            });

            return {
                ...unit,
                lessons,
            };
        })
        .filter((unit) => unit.lessons.length > 0 || !query);
});

// Video Modal Player
const activeVideo = ref(null);

function openVideoModal(video, lessonTitle) {
    activeVideo.value = {
        ...video,
        lessonTitle,
    };
}

function closeVideoModal() {
    activeVideo.value = null;
}

function getYouTubeEmbed(url) {
    if (!url) return null;
    const match = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([\w-]{11})/);
    return match ? `https://www.youtube-nocookie.com/embed/${match[1]}?autoplay=1&rel=0` : null;
}

function isWordFile(ext) {
    return ['doc', 'docx'].includes((ext || '').toLowerCase());
}

function isPdfFile(ext) {
    return (ext || '').toLowerCase() === 'pdf';
}

const KNOWN_ICONS = ['calculator', 'atom', 'flask', 'dna', 'book', 'language', 'landmark', 'globe', 'student', 'users', 'chart', 'settings', 'video'];
const iconFor = (icon) => (KNOWN_ICONS.includes(icon) ? icon : 'book');
</script>

<template>
    <Head title="الموارد التعليمية والمذكرات" />

    <DashboardLayout>
        <div class="space-y-6">

            <!-- ── Top Header ─────────────────────────────────────────── -->
            <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-6 bg-primary-600 rounded-full"></span>
                        <h1 class="text-2xl font-black text-surface-900 dark:text-white">الموارد التعليمية</h1>
                        <span v-if="subjects.length" class="badge-accent text-xs font-bold">{{ subjects.length }} مواد</span>
                    </div>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                        استعرض دروس كل مادة مشترِك بها، وشاهد الفيديوهات وحمّل الملازم (PDF/Word) وابدأ التقييمات الإلكترونية.
                    </p>
                </div>

                <div v-if="currentSubject && currentSubject.group_id" class="flex items-center gap-2">
                    <Link
                        :href="route('student.learn', { groupId: currentSubject.group_id })"
                        class="btn-primary btn-sm flex items-center gap-2 shadow-sm font-bold"
                    >
                        <Icon name="chart" class="w-4 h-4" />
                        <span>قاعة التعلم التفاعلية ومتابعة تقدمك</span>
                    </Link>
                </div>
            </header>

            <!-- ── No Subscriptions Empty State ───────────────────────── -->
            <div v-if="!subjects.length" class="card p-12 text-center max-w-xl mx-auto">
                <div class="w-16 h-16 rounded-2xl bg-primary-50 dark:bg-primary-950/50 flex items-center justify-center text-primary-600 dark:text-primary-400 mx-auto mb-4">
                    <Icon name="book" class="w-8 h-8" />
                </div>
                <h2 class="text-lg font-black text-surface-900 dark:text-white mb-2">لست مشتركاً في أي مادة حالياً</h2>
                <p class="text-sm text-surface-500 dark:text-surface-400 mb-6 leading-relaxed">
                    عندما تشترك مع معلم في مواد صفك الدراسي، ستظهر لك هنا جميع الوحدات والدروس بكافة الفيديوهات، وملازم الشرح بصيغتي PDF و Word، والاختبارات الإلكترونية الخاصة بكل درس.
                </p>
                <div class="flex items-center justify-center gap-3">
                    <Link :href="route('student.my-grade')" class="btn-primary btn-sm flex items-center gap-2">
                        <Icon name="courses" class="w-4 h-4" />
                        <span>استعراض مواد صفي والمعلمين</span>
                    </Link>
                </div>
            </div>

            <!-- ── Main Content (When Subscriptions Exist) ─────────────── -->
            <div v-else class="space-y-6">

                <!-- ── 1. Tabs for each subscribed subject ────────────────── -->
                <div class="overflow-x-auto pb-1 -mx-2 px-2 scrollbar-thin">
                    <div class="flex items-center gap-2.5 min-w-max">
                        <button
                            v-for="sub in subjects"
                            :key="sub.id"
                            type="button"
                            @click="activeSubjectId = sub.id"
                            class="flex items-center gap-3.5 px-4 py-3 rounded-2xl border transition-all text-start"
                            :class="activeSubjectId === sub.id
                                ? 'bg-primary-600 text-white border-primary-600 shadow-md shadow-primary-500/20 scale-[1.02]'
                                : 'bg-surface-0 dark:bg-surface-800 text-surface-700 dark:text-surface-200 border-surface-200 dark:border-surface-700 hover:border-primary-400 dark:hover:border-primary-500 hover:bg-surface-50 dark:hover:bg-surface-700/60'"
                        >
                            <div
                                class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 font-black text-sm"
                                :class="activeSubjectId === sub.id
                                    ? 'bg-white/20 text-white'
                                    : 'bg-primary-50 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400'"
                            >
                                <Icon :name="iconFor(sub.icon)" class="w-5 h-5" />
                            </div>

                            <div class="min-w-0">
                                <div class="font-black text-sm leading-tight flex items-center gap-2">
                                    <span>{{ sub.name }}</span>
                                    <span
                                        v-if="sub.grade_level_name"
                                        class="text-[10px] px-1.5 py-0.5 rounded font-normal"
                                        :class="activeSubjectId === sub.id ? 'bg-white/20 text-white' : 'badge-gray'"
                                    >
                                        {{ sub.grade_level_name }}
                                    </span>
                                </div>
                                <div
                                    class="text-xs mt-1 truncate"
                                    :class="activeSubjectId === sub.id ? 'text-white/80' : 'text-surface-400'"
                                >
                                    {{ sub.teacher?.name }}
                                </div>
                            </div>

                            <div
                                class="ms-2 ps-3 border-s text-[11px] font-bold text-center shrink-0"
                                :class="activeSubjectId === sub.id ? 'border-white/25 text-white/90' : 'border-surface-200 dark:border-surface-700 text-surface-500'"
                            >
                                <div>{{ sub.lessons_count }}</div>
                                <div class="text-[9px] font-normal">دروس</div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- ── 2. Current Subject Banner & Stats ────────────────── -->
                <div v-if="currentSubject" class="card overflow-hidden border-primary-200/60 dark:border-primary-900/40 bg-gradient-to-br from-surface-0 via-surface-0 to-primary-50/20 dark:from-surface-800 dark:via-surface-800 dark:to-primary-950/20 p-5 sm:p-6">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <!-- Teacher Info -->
                        <div class="flex items-center gap-4">
                            <div class="avatar-lg rounded-2xl ring-2 ring-primary-500/20 shrink-0 overflow-hidden bg-primary-100 dark:bg-primary-950 flex items-center justify-center font-black text-xl text-primary-600">
                                <img
                                    v-if="currentSubject.teacher?.avatar"
                                    :src="currentSubject.teacher.avatar"
                                    :alt="currentSubject.teacher.name"
                                    class="w-full h-full object-cover"
                                />
                                <span v-else>{{ currentSubject.teacher?.name?.charAt(0) }}</span>
                            </div>
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h2 class="text-lg font-black text-surface-900 dark:text-white">
                                        {{ currentSubject.name }} — {{ currentSubject.teacher?.name }}
                                    </h2>
                                    <span class="badge-primary text-[11px]">مادة مشترَكة</span>
                                </div>
                                <p v-if="currentSubject.teacher?.headline" class="text-xs text-surface-500 dark:text-surface-400 mt-1">
                                    {{ currentSubject.teacher.headline }}
                                </p>
                            </div>
                        </div>

                        <!-- 4 Quick Stats Badges -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 shrink-0">
                            <div class="px-3.5 py-2.5 rounded-xl bg-surface-50 dark:bg-surface-900/60 border border-surface-200/60 dark:border-surface-700/60 text-center">
                                <div class="text-base font-black text-surface-900 dark:text-white">{{ currentSubject.lessons_count }}</div>
                                <div class="text-[10px] text-surface-400 mt-0.5">دروس المنهج</div>
                            </div>
                            <div class="px-3.5 py-2.5 rounded-xl bg-red-50/60 dark:bg-red-950/20 border border-red-200/60 dark:border-red-900/40 text-center">
                                <div class="text-base font-black text-red-600 dark:text-red-400 flex items-center justify-center gap-1">
                                    <span>🎬</span>
                                    <span>{{ currentSubject.total_videos_count }}</span>
                                </div>
                                <div class="text-[10px] text-red-700/70 dark:text-red-400/70 mt-0.5">فيديوهات شرح</div>
                            </div>
                            <div class="px-3.5 py-2.5 rounded-xl bg-blue-50/60 dark:bg-blue-950/20 border border-blue-200/60 dark:border-blue-900/40 text-center">
                                <div class="text-base font-black text-blue-600 dark:text-blue-400 flex items-center justify-center gap-1">
                                    <span>📑</span>
                                    <span>{{ currentSubject.total_booklets_count }}</span>
                                </div>
                                <div class="text-[10px] text-blue-700/70 dark:text-blue-400/70 mt-0.5">ملازم وشيتات</div>
                            </div>
                            <div class="px-3.5 py-2.5 rounded-xl bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200/60 dark:border-amber-900/40 text-center">
                                <div class="text-base font-black text-amber-600 dark:text-amber-400 flex items-center justify-center gap-1">
                                    <span>📝</span>
                                    <span>{{ currentSubject.total_assessments_count }}</span>
                                </div>
                                <div class="text-[10px] text-amber-700/70 dark:text-amber-400/70 mt-0.5">تقييمات إلكترونية</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── 3. Search and Content Filters ──────────────────────── -->
                <div v-if="currentSubject" class="flex flex-col sm:flex-row items-center justify-between gap-3">
                    <!-- Search Input -->
                    <div class="relative w-full sm:w-80">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="ابحث عن درس أو وحدة..."
                            class="input pl-9 pr-9 text-xs py-2 w-full"
                        />
                        <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-surface-400">
                            <Icon name="search" class="w-4 h-4" />
                        </span>
                        <button
                            v-if="searchQuery"
                            type="button"
                            @click="searchQuery = ''"
                            class="absolute inset-y-0 left-0 pl-3 flex items-center text-surface-400 hover:text-surface-600"
                        >
                            <Icon name="close" class="w-3.5 h-3.5" />
                        </button>
                    </div>

                    <!-- Filter pills -->
                    <div class="flex items-center gap-1.5 flex-wrap w-full sm:w-auto">
                        <button
                            type="button"
                            @click="activeFilter = 'all'"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold transition-colors"
                            :class="activeFilter === 'all'
                                ? 'bg-surface-900 text-white dark:bg-white dark:text-surface-900'
                                : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200'"
                        >
                            الكل
                        </button>
                        <button
                            type="button"
                            @click="activeFilter = 'video'"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold transition-colors flex items-center gap-1"
                            :class="activeFilter === 'video'
                                ? 'bg-red-600 text-white'
                                : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200'"
                        >
                            <span>🎬 فيديوهات</span>
                        </button>
                        <button
                            type="button"
                            @click="activeFilter = 'booklet'"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold transition-colors flex items-center gap-1"
                            :class="activeFilter === 'booklet'
                                ? 'bg-blue-600 text-white'
                                : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200'"
                        >
                            <span>📑 ملازم (PDF/Word)</span>
                        </button>
                        <button
                            type="button"
                            @click="activeFilter = 'quiz'"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold transition-colors flex items-center gap-1"
                            :class="activeFilter === 'quiz'
                                ? 'bg-amber-600 text-white'
                                : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200'"
                        >
                            <span>📝 تقييمات إلكترونية</span>
                        </button>

                        <div class="ms-auto flex items-center gap-1 text-[11px] text-surface-400">
                            <button type="button" @click="expandAll" class="hover:underline">فتح الكل</button>
                            <span>·</span>
                            <button type="button" @click="collapseAll" class="hover:underline">طي الكل</button>
                        </div>
                    </div>
                </div>

                <!-- ── Semester Filter Tabs (خطة فصلية) ────────────────── -->
                <div v-if="currentSubject && availableSemesters.length > 1" class="flex items-center gap-2 overflow-x-auto pb-1">
                    <span class="text-xs font-bold text-surface-500 dark:text-surface-400 shrink-0">الفصل الدراسي:</span>
                    <button
                        type="button"
                        @click="selectedSemester = 'all'"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all"
                        :class="selectedSemester === 'all'
                            ? 'bg-primary-600 text-white shadow-sm'
                            : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200'"
                    >
                        جميع الفصول
                    </button>
                    <button
                        v-for="sem in availableSemesters"
                        :key="sem.num"
                        type="button"
                        @click="selectedSemester = String(sem.num)"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all"
                        :class="selectedSemester === String(sem.num)
                            ? 'bg-primary-600 text-white shadow-sm'
                            : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200'"
                    >
                        {{ sem.name }}
                    </button>
                </div>

                <!-- ── 4. Units & Lessons Accordion ───────────────────────── -->
                <div v-if="currentSubject" class="space-y-6">

                    <!-- Loop through Units -->
                    <div
                        v-for="unit in filteredUnits"
                        :key="unit.id"
                        class="space-y-4"
                    >
                        <!-- Unit Header -->
                        <div class="flex items-center justify-between gap-3 px-2 py-1">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-accent-500"></span>
                                <h3 class="font-black text-base text-surface-900 dark:text-white">
                                    {{ unit.title }}
                                </h3>
                                <span v-if="unit.term_name" class="badge-primary text-[10px]">{{ unit.term_name }}</span>
                                <span class="badge-gray text-[10px]">{{ unit.lessons.length }} دروس</span>
                            </div>
                            <p v-if="unit.description" class="text-xs text-surface-400 hidden sm:block truncate max-w-md">
                                {{ unit.description }}
                            </p>
                        </div>

                        <!-- Lessons in this Unit -->
                        <div class="space-y-3">
                            <article
                                v-for="lesson in unit.lessons"
                                :key="lesson.id"
                                class="card overflow-hidden border transition-all"
                                :class="isExpanded(lesson.id) ? 'border-primary-300 dark:border-primary-800 shadow-sm' : 'border-surface-200 dark:border-surface-700'"
                            >
                                <!-- Lesson Header Bar (Click to toggle) -->
                                <button
                                    type="button"
                                    @click="toggleLesson(lesson.id)"
                                    class="w-full flex items-center justify-between gap-3 p-4 sm:p-5 text-start hover:bg-surface-50 dark:hover:bg-surface-800/40 transition-colors"
                                >
                                    <div class="flex items-center gap-3.5 min-w-0">
                                        <!-- Checklist badge matching sketch: (✔) vs (⭕) -->
                                        <div
                                            v-if="lesson.is_completed"
                                            class="w-8 h-8 rounded-full bg-green-500 text-white flex items-center justify-center shrink-0 font-black text-sm shadow-sm ring-4 ring-green-100 dark:ring-green-950/50"
                                            title="درس مكتمل"
                                        >
                                            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <div
                                            v-else
                                            class="w-8 h-8 rounded-full border-2 border-surface-400 dark:border-surface-500 bg-surface-50 dark:bg-surface-800/60 flex items-center justify-center shrink-0 text-surface-400"
                                            title="قيد الانتظار لم يكتمل بعد"
                                        >
                                            <!-- Open circle (⭕) as in sketch -->
                                        </div>

                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="font-black text-sm" :class="lesson.is_completed ? 'text-green-600 dark:text-green-400' : 'text-primary-600 dark:text-primary-400'">
                                                    درس {{ lesson.order }}
                                                </span>
                                                <span class="text-surface-400">:</span>
                                                <h4 class="font-black text-sm text-surface-900 dark:text-white">
                                                    {{ lesson.title }}
                                                </h4>
                                                <span v-if="lesson.is_completed" class="badge-success text-[10px] font-bold py-0.5 px-2">
                                                    مكتمل ✓
                                                </span>
                                                <span v-else class="text-[10px] px-2 py-0.5 rounded-full font-bold bg-surface-100 dark:bg-surface-800 text-surface-500">
                                                    لم يكتمل بعد
                                                </span>
                                                <span v-if="lesson.duration_formatted !== '—'" class="text-[10px] text-surface-400 flex items-center gap-1 font-semibold">
                                                    <span>⏱️</span>
                                                    <span>{{ lesson.duration_formatted }}</span>
                                                </span>
                                            </div>
                                            <p v-if="lesson.description" class="text-xs text-surface-500 dark:text-surface-400 mt-1 line-clamp-1">
                                                {{ lesson.description }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Content indicator tags & Chevron -->
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span
                                            v-if="lesson.videos.length"
                                            class="text-[10px] px-2 py-0.5 rounded-full font-bold bg-red-50 text-red-600 dark:bg-red-950/40 dark:text-red-400 flex items-center gap-1"
                                            title="يوجد فيديو شرح لهذا الدرس"
                                        >
                                            <span>🎬</span>
                                            <span class="hidden sm:inline">فيديو</span>
                                        </span>

                                        <span
                                            v-if="lesson.booklets.length"
                                            class="text-[10px] px-2 py-0.5 rounded-full font-bold bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400 flex items-center gap-1"
                                            title="توجد ملازم وشيتات لهذا الدرس"
                                        >
                                            <span>📑</span>
                                            <span>{{ lesson.booklets.length }} ملازم</span>
                                        </span>

                                        <span
                                            v-if="lesson.assessments.length"
                                            class="text-[10px] px-2 py-0.5 rounded-full font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 flex items-center gap-1"
                                            title="يوجد تقييم إلكتروني لهذا الدرس"
                                        >
                                            <span>📝</span>
                                            <span class="hidden sm:inline">تقييم إلكتروني</span>
                                        </span>

                                        <Icon
                                            name="chevronDown"
                                            class="w-4 h-4 text-surface-400 transition-transform duration-200"
                                            :class="isExpanded(lesson.id) ? 'rotate-180' : ''"
                                        />
                                    </div>
                                </button>

                                <!-- ── Lesson Body (Videos, Booklets PDF/Word, Assessments) ── -->
                                <div
                                    v-if="isExpanded(lesson.id)"
                                    class="border-t border-surface-100 dark:border-surface-800 p-4 sm:p-5 bg-surface-50/50 dark:bg-surface-900/30"
                                >
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                                        <!-- 1. الفيديوهات (Videos) -->
                                        <div class="space-y-3">
                                            <div class="flex items-center gap-2 text-xs font-black text-red-600 dark:text-red-400">
                                                <span class="w-6 h-6 rounded-lg bg-red-100 dark:bg-red-950/60 flex items-center justify-center">🎬</span>
                                                <span>فيديوهات الشرح</span>
                                            </div>

                                            <div v-if="lesson.videos.length" class="space-y-2">
                                                <div
                                                    v-for="video in lesson.videos"
                                                    :key="video.id"
                                                    class="p-3.5 rounded-xl bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 shadow-xs flex flex-col justify-between gap-3"
                                                >
                                                    <div>
                                                        <div class="font-bold text-xs text-surface-900 dark:text-white line-clamp-2">
                                                            {{ video.title }}
                                                        </div>
                                                        <div class="text-[10px] text-surface-400 mt-1 flex items-center gap-2">
                                                            <span>المدة: {{ video.duration_formatted }}</span>
                                                            <span v-if="video.is_live" class="text-red-500 font-bold">تسجيل حصة لايف</span>
                                                        </div>
                                                    </div>

                                                    <div class="flex items-center gap-2 pt-1">
                                                        <button
                                                            type="button"
                                                            @click="openVideoModal(video, lesson.title)"
                                                            class="btn-primary btn-xs flex-1 justify-center gap-1 font-bold"
                                                        >
                                                            <Icon name="play" class="w-3.5 h-3.5" />
                                                            <span>مشاهدة الفيديو</span>
                                                        </button>

                                                        <Link
                                                            v-if="video.learn_url"
                                                            :href="video.learn_url"
                                                            class="btn-outline btn-xs px-2"
                                                            title="الانتقال للمشاهدة في قاعة التعلم الشاملة"
                                                        >
                                                            <Icon name="external" class="w-3.5 h-3.5" />
                                                        </Link>
                                                    </div>
                                                </div>
                                            </div>

                                            <div v-else class="p-4 rounded-xl border border-dashed border-surface-200 dark:border-surface-700 text-center text-xs text-surface-400">
                                                لا يوجد فيديو مسجل لهذا الدرس حالياً
                                            </div>
                                        </div>

                                        <!-- 2. الملازم والشيتات (PDF / Word) -->
                                        <div class="space-y-3">
                                            <div class="flex items-center gap-2 text-xs font-black text-blue-600 dark:text-blue-400">
                                                <span class="w-6 h-6 rounded-lg bg-blue-100 dark:bg-blue-950/60 flex items-center justify-center">📑</span>
                                                <span>الملازم والملفات (PDF / Word)</span>
                                            </div>

                                            <div v-if="lesson.booklets.length" class="space-y-2">
                                                <div
                                                    v-for="file in lesson.booklets"
                                                    :key="file.id"
                                                    class="p-3.5 rounded-xl bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 shadow-xs flex items-center justify-between gap-3"
                                                >
                                                    <div class="flex items-center gap-2.5 min-w-0">
                                                        <!-- PDF vs Word Badge Icon -->
                                                        <div
                                                            class="w-8 h-8 rounded-lg flex items-center justify-center font-black text-[10px] shrink-0"
                                                            :class="isWordFile(file.extension)
                                                                ? 'bg-blue-100 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400'
                                                                : 'bg-red-100 dark:bg-red-950/60 text-red-600 dark:text-red-400'"
                                                        >
                                                            {{ isWordFile(file.extension) ? 'DOC' : 'PDF' }}
                                                        </div>

                                                        <div class="min-w-0">
                                                            <div class="font-bold text-xs text-surface-900 dark:text-white truncate">
                                                                {{ file.title }}
                                                            </div>
                                                            <div class="text-[10px] text-surface-400 mt-0.5 flex items-center gap-1.5">
                                                                <span>{{ file.type_label }}</span>
                                                                <span v-if="file.due_date" class="text-accent-600 font-bold">تسليم: {{ file.due_date }}</span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <a
                                                        v-if="file.file_url"
                                                        :href="file.file_url"
                                                        target="_blank"
                                                        class="btn-outline btn-xs shrink-0 flex items-center gap-1 font-bold text-primary-600 dark:text-primary-400"
                                                        :title="`تحميل ${file.title}`"
                                                    >
                                                        <Icon name="download" class="w-3.5 h-3.5" />
                                                        <span class="hidden sm:inline">تحميل</span>
                                                    </a>
                                                </div>
                                            </div>

                                            <div v-else class="p-4 rounded-xl border border-dashed border-surface-200 dark:border-surface-700 text-center text-xs text-surface-400">
                                                سيقوم المعلم برفع ملزمة هذا الدرس قريباً
                                            </div>
                                        </div>

                                        <!-- 3. التقييم الإلكتروني (Electronic Assessment / Quiz) -->
                                        <div class="space-y-3">
                                            <div class="flex items-center gap-2 text-xs font-black text-amber-600 dark:text-amber-400">
                                                <span class="w-6 h-6 rounded-lg bg-amber-100 dark:bg-amber-950/60 flex items-center justify-center">📝</span>
                                                <span>التقييم الإلكتروني</span>
                                            </div>

                                            <div v-if="lesson.assessments.length" class="space-y-2">
                                                <div
                                                    v-for="quiz in lesson.assessments"
                                                    :key="quiz.id"
                                                    class="p-3.5 rounded-xl bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 shadow-xs flex flex-col justify-between gap-3"
                                                >
                                                    <div>
                                                        <div class="font-bold text-xs text-surface-900 dark:text-white line-clamp-1">
                                                            {{ quiz.title }}
                                                        </div>
                                                        <div class="text-[10px] text-surface-400 mt-1 flex items-center gap-2 flex-wrap">
                                                            <span>{{ quiz.questions_count }} أسئلة</span>
                                                            <span v-if="quiz.time_limit_minutes">· {{ quiz.time_limit_minutes }} دقيقة</span>
                                                            <span>· نجاح {{ quiz.passing_score }}%</span>
                                                        </div>

                                                        <!-- Previous attempt result -->
                                                        <div v-if="quiz.last_score !== null && quiz.last_score !== undefined" class="mt-2 text-[11px] font-bold">
                                                            <span
                                                                class="px-2 py-0.5 rounded-full inline-block"
                                                                :class="quiz.passed
                                                                    ? 'bg-green-100 text-green-700 dark:bg-green-950/60 dark:text-green-300'
                                                                    : 'bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-300'"
                                                            >
                                                                {{ quiz.passed ? '✅ ناجح' : '⚠️ محاولة سابقة' }} — درجتك: {{ quiz.last_score }}%
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <Link
                                                            v-if="quiz.is_open"
                                                            :href="quiz.start_url"
                                                            class="btn-primary btn-xs w-full justify-center gap-1.5 font-bold"
                                                        >
                                                            <Icon name="quiz" class="w-3.5 h-3.5" />
                                                            <span>{{ quiz.attempts_used > 0 ? 'إعادة التقييم الإلكتروني' : 'ابدأ التقييم الإلكتروني' }}</span>
                                                        </Link>
                                                        <div v-else class="text-center text-[10px] text-surface-400 py-1 bg-surface-100 dark:bg-surface-800 rounded-lg">
                                                            التقييم مغلق حالياً
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div v-else class="p-4 rounded-xl border border-dashed border-surface-200 dark:border-surface-700 text-center text-xs text-surface-400">
                                                لا يوجد كويز إلكتروني مخصص لهذا الدرس
                                            </div>
                                        </div>

                                    </div>

                                    <!-- Lesson progress quick action -->
                                    <div class="mt-4 pt-3 border-t border-surface-200 dark:border-surface-700/80 flex items-center justify-between gap-3 flex-wrap">
                                        <div class="flex items-center gap-2 text-xs">
                                            <span class="font-bold text-surface-500">حالة الدرس:</span>
                                            <span v-if="lesson.is_completed" class="text-green-600 dark:text-green-400 font-black flex items-center gap-1">
                                                <span>(✔) مكتمل</span>
                                            </span>
                                            <span v-else class="text-surface-500 dark:text-surface-400 font-bold flex items-center gap-1">
                                                <span>(⭕) قيد الانتظار لم يكتمل</span>
                                            </span>
                                        </div>
                                        <Link
                                            v-if="currentSubject.group_id"
                                            :href="route('student.learn', { groupId: currentSubject.group_id, progress: 1 })"
                                            class="btn-outline btn-xs flex items-center gap-1.5 font-bold text-primary-600 dark:text-primary-400 border-primary-200 dark:border-primary-800 hover:bg-primary-50 dark:hover:bg-primary-950/40"
                                        >
                                            <Icon name="chart" class="w-3.5 h-3.5" />
                                            <span>خريطة المنهج ومستوى الإنجاز</span>
                                        </Link>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <!-- Unit Comprehensive Exam or Booklets (if any) -->
                        <div v-if="unit.unit_exam || (unit.unit_booklets && unit.unit_booklets.length)" class="p-4 rounded-2xl bg-amber-500/5 border border-amber-300/40 dark:border-amber-900/40 flex flex-col sm:flex-row items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-600 flex items-center justify-center font-bold">🏆</span>
                                <div>
                                    <div class="font-bold text-xs text-surface-900 dark:text-white">
                                        {{ unit.unit_exam ? unit.unit_exam.title : `ملازم ونماذج مراجعة ${unit.title}` }}
                                    </div>
                                    <div class="text-[10px] text-surface-400 mt-0.5">
                                        {{ unit.unit_exam ? `${unit.unit_exam.questions_count} سؤال شامل على كامل الوحدة` : 'نماذج امتحانات وملفات شاملة' }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <Link
                                    v-if="unit.unit_exam && unit.unit_exam.is_open"
                                    :href="unit.unit_exam.start_url"
                                    class="btn-accent btn-xs font-bold"
                                >
                                    ابدأ اختبار الوحدة
                                </Link>
                                <a
                                    v-for="ub in unit.unit_booklets"
                                    :key="ub.id"
                                    :href="ub.file_url"
                                    target="_blank"
                                    class="btn-outline btn-xs"
                                >
                                    تحميل {{ ub.title }}
                                </a>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- ── 5. Extra Subject Resources (from Resource Library) ─── -->
                <div v-if="currentSubject && currentSubject.extra_resources && currentSubject.extra_resources.length" class="card p-5 space-y-4">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-5 bg-accent-500 rounded-full"></span>
                        <h3 class="font-black text-sm text-surface-900 dark:text-white">
                            ملازم ومصادر إثرائية إضافية لمادة {{ currentSubject.name }}
                        </h3>
                    </div>

                    <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-3">
                        <div
                            v-for="res in currentSubject.extra_resources"
                            :key="res.id"
                            class="p-3.5 rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50/50 dark:bg-surface-800/40 flex items-start justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <h4 class="font-bold text-xs text-surface-900 dark:text-white truncate">{{ res.title }}</h4>
                                <p class="text-[10px] text-surface-400 mt-0.5">{{ res.category }} · {{ res.size_label }}</p>
                                <p v-if="res.description" class="text-xs text-surface-500 mt-1 line-clamp-2">{{ res.description }}</p>
                            </div>

                            <a
                                v-if="res.file_url"
                                :href="res.file_url"
                                target="_blank"
                                class="btn-primary btn-xs shrink-0"
                            >
                                تحميل
                            </a>
                        </div>
                    </div>
                </div>

            </div>

        </div>

        <!-- ── Video Player Modal ─────────────────────────────────────── -->
        <div
            v-if="activeVideo"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-xs animate-in fade-in duration-200"
            @click.self="closeVideoModal"
        >
            <div class="relative w-full max-w-4xl bg-surface-900 rounded-2xl overflow-hidden shadow-2xl border border-surface-700">
                <!-- Modal Header -->
                <div class="flex items-center justify-between p-4 border-b border-surface-800 bg-surface-950 text-white">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                        <h3 class="font-black text-sm truncate">{{ activeVideo.title }}</h3>
                        <span v-if="activeVideo.lessonTitle" class="text-xs text-surface-400 truncate hidden sm:inline">
                            — {{ activeVideo.lessonTitle }}
                        </span>
                    </div>

                    <button
                        type="button"
                        @click="closeVideoModal"
                        class="w-8 h-8 rounded-full bg-surface-800 hover:bg-surface-700 flex items-center justify-center text-surface-300 hover:text-white transition-colors"
                        aria-label="إغلاق الفيديو"
                    >
                        <Icon name="close" class="w-4 h-4" />
                    </button>
                </div>

                <!-- Video Frame -->
                <div class="aspect-video bg-black flex items-center justify-center">
                    <!-- YouTube / External URL -->
                    <iframe
                        v-if="getYouTubeEmbed(activeVideo.video_url || activeVideo.stream_url)"
                        :src="getYouTubeEmbed(activeVideo.video_url || activeVideo.stream_url)"
                        class="w-full h-full"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                    ></iframe>

                    <!-- HTML5 / Direct Video -->
                    <video
                        v-else
                        controls
                        autoplay
                        class="w-full h-full"
                        :src="activeVideo.stream_url || activeVideo.video_url"
                    >
                        متصفحك لا يدعم تشغيل هذا الفيديو مباشرة.
                    </video>
                </div>

                <!-- Modal Footer -->
                <div class="p-3 bg-surface-950 border-t border-surface-800 flex items-center justify-between text-xs text-surface-400">
                    <div>
                        <span>المدة: {{ activeVideo.duration_formatted }}</span>
                    </div>

                    <Link
                        v-if="activeVideo.learn_url"
                        :href="activeVideo.learn_url"
                        class="text-xs text-primary-400 hover:underline flex items-center gap-1 font-bold"
                    >
                        <span>متابعة الشرح في قاعة الدرس المتكاملة</span>
                        <span>←</span>
                    </Link>
                </div>
            </div>
        </div>

    </DashboardLayout>
</template>
