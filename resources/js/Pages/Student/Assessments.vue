<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    assignments: { type: Array, default: () => [] },
    assignmentSummary: { type: Object, default: () => ({}) },
    initialTab: { type: String, default: 'all' },
});

// Filters: 'all' | 'homework' | 'electronic' | 'paper'
const activeMode = ref(props.initialTab === 'homework' ? 'homework' : 'all');
const activeScope = ref('all');   // all | lesson | unit | midterm | comprehensive
const activeStatus = ref('all');  // all | available | in_progress | completed | upcoming

// Paper exam upload state
const submitFiles = ref({});
const uploadingSheet = ref(null);
const uploadNotices = ref({});

function onFileChange(event, sheetId) {
    const file = event.target.files?.[0];
    if (!file) return;

    if (file.size > 10 * 1024 * 1024) {
        uploadNotices.value[sheetId] = 'حجم الملف يتجاوز الحد الأقصى (10 ميجابايت).';
        return;
    }

    submitFiles.value[sheetId] = file;
    uploadNotices.value[sheetId] = '';
}

function uploadAnswer(sheetId) {
    const file = submitFiles.value[sheetId];
    if (!file) {
        uploadNotices.value[sheetId] = 'الرجاء اختيار ملف الحل أولاً.';
        return;
    }

    const formData = new FormData();
    formData.append('submitted_file', file);

    uploadingSheet.value = sheetId;

    router.post(route('student.assessments.submit-paper', { worksheetId: sheetId }), formData, {
        preserveScroll: true,
        onFinish: () => {
            uploadingSheet.value = null;
            submitFiles.value[sheetId] = null;
        },
    });
}

// Combine exams and periodic/paper assessments into a single unified list with deduplication
const unifiedItems = computed(() => {
    const seen = new Set();
    const result = [];

    for (const item of (props.items || [])) {
        const key = `${item.mode}-${item.id}`;
        if (!seen.has(key)) {
            seen.add(key);
            result.push(item);
        }
    }

    for (const hw of (props.assignments || [])) {
        const key = `homework-${hw.id}`;
        const paperKey = `paper-${hw.id}`;
        if (!seen.has(key) && !seen.has(paperKey)) {
            seen.add(key);
            result.push({
                id: hw.id,
                title: hw.title,
                mode: 'homework',
                scope: 'lesson',
                scope_label: 'تقييم الدرس',
                type_label: 'تقييم دوري',
                subject: hw.subject,
                teacher: hw.teacher,
                unit: null,
                lesson: hw.lesson,
                lesson_id: hw.lesson_id,
                group: hw.group,
                group_id: hw.group_id,
                term_id: hw.term_id,
                due_date: hw.due_date,
                available_from: hw.available_from ?? null,
                max_score: hw.max_score,
                allow_late_submission: hw.allow_late_submission ?? true,
                max_attempts: hw.max_attempts ?? 1,
                requires_submission: true,
                can_start: hw.can_start ?? true,
                can_submit: hw.can_submit ?? true,
                file_url: hw.worksheet_file,
                is_late: hw.is_late,
                submitted_late: hw.submitted_late,
                status: hw.status === 'pending' ? 'available' : hw.status,
                raw_status: hw.status,
                submission: hw.submission ? {
                    id: hw.submission.id,
                    submitted_at: hw.submission.submitted_at,
                    file_url: hw.submission.submitted_file_url ?? hw.submission.file_path,
                    score: hw.submission.score,
                    is_graded: hw.submission.score !== null,
                    score_percent: hw.submission.score !== null && hw.max_score ? Math.round((hw.submission.score / hw.max_score) * 100) : null,
                    teacher_feedback: hw.submission.teacher_feedback,
                    is_late: hw.submission.is_late || hw.submitted_late,
                } : null,
            });
        }
    }

    return result;
});

const totalUnifiedCount = computed(() => unifiedItems.value.length);
const homeworkCount = computed(() => unifiedItems.value.filter(i => i.mode === 'homework').length);
const electronicCount = computed(() => unifiedItems.value.filter(i => i.mode === 'electronic').length);
const paperCount = computed(() => unifiedItems.value.filter(i => i.mode === 'paper').length);
const totalCompletedCount = computed(() => (props.summary.completed ?? 0) + (props.assignmentSummary?.graded ?? 0));

const filteredItems = computed(() => {
    return unifiedItems.value.filter((item) => {
        // Mode filter
        if (activeMode.value !== 'all' && item.mode !== activeMode.value) {
            return false;
        }
        // Scope filter
        if (activeScope.value !== 'all') {
            if (activeScope.value === 'lesson' && !item.lesson_id && item.scope !== 'lesson') {
                return false;
            }
            if (activeScope.value !== 'lesson' && item.scope !== activeScope.value) {
                return false;
            }
        }
        // Status filter
        if (activeStatus.value !== 'all' && item.status !== activeStatus.value) {
            return false;
        }
        return true;
    });
});

const statusLabels = {
    available: 'متاح للحل الآن',
    in_progress: 'قيد المراجعة / بانتظار التصحيح',
    submitted: 'تم التسليم',
    completed: 'مكتمل ومصحح',
    graded: 'تم التصحيح',
    late: 'متأخر في التسليم',
    upcoming: 'يفتح قريباً',
    closed: 'منتهي الصلاحية',
    not_ready: 'قيد التجهيز',
};

const statusClasses = {
    available: 'badge-green',
    in_progress: 'badge-primary',
    submitted: 'badge-accent',
    completed: 'badge-accent',
    graded: 'badge-green',
    late: 'badge-red',
    upcoming: 'badge-gray',
    closed: 'badge-gray',
    not_ready: 'badge-red',
};

function dateLabel(value) {
    if (!value) return 'متاح في أي وقت';

    return new Date(value).toLocaleString('ar-QA', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatDateOnly(value) {
    if (!value) return 'غير محدد';
    return new Date(value).toLocaleDateString('ar-QA', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}
</script>

<template>
    <Head title="التقييمات والاختبارات" />

    <DashboardLayout>
        <div class="space-y-8 pb-12">

            <!-- ── Page Header ────────────────────────────────────── -->
            <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl sm:text-3xl font-black text-surface-900 dark:text-white">
                            التقييمات والاختبارات
                        </h1>
                        <span class="badge-primary text-xs font-bold">{{ totalUnifiedCount }} عنصر</span>
                    </div>
                    <p class="mt-1 text-sm text-surface-500 dark:text-surface-400 max-w-2xl leading-relaxed">
                        مركز موحد يضم التقييمات الدورية، واختبارات الوحدات، والتقييمات الإلكترونية والورقية المرتبطة بموادك المشترك بها.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('dashboard')" class="btn-outline btn-sm">العودة للرئيسية</Link>
                    <Link :href="route('student.resources')" class="btn-primary btn-sm flex items-center gap-1.5 font-bold">
                        <Icon name="book" class="w-3.5 h-3.5" />
                        <span>الموارد التعليمية</span>
                    </Link>
                </div>
            </header>

            <!-- ── Flash Message ──────────────────────────────────── -->
            <div v-if="$page.props.flash?.success"
                 class="p-4 rounded-2xl bg-green-500/10 border border-green-500/20 text-green-600 dark:text-green-300 font-bold text-sm flex items-center gap-2">
                <Icon name="success" class="w-5 h-5 text-green-500 shrink-0" />
                <span>{{ $page.props.flash.success }}</span>
            </div>

            <!-- ── Summary Stats ──────────────────────────────────── -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                <div class="card p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] text-surface-400 font-bold">إجمالي التقييمات والاختبارات</span>
                        <Icon name="assignment" class="h-4 w-4 text-primary-500" />
                    </div>
                    <div class="mt-2 text-2xl font-black text-surface-900 dark:text-white">{{ totalUnifiedCount }}</div>
                    <div class="mt-1 text-[10px] text-surface-400">تقييمات واختبارات</div>
                </div>

                <div class="card p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] text-surface-400 font-bold">تقييمات دورية</span>
                        <span class="text-sm">📚</span>
                    </div>
                    <div class="mt-2 text-2xl font-black text-amber-600 dark:text-amber-400">{{ homeworkCount }}</div>
                    <div class="mt-1 text-[10px] text-surface-400">مربوطة بدروسك</div>
                </div>

                <div class="card p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] text-surface-400 font-bold">اختبارات إلكترونية</span>
                        <span class="text-sm">📝</span>
                    </div>
                    <div class="mt-2 text-2xl font-black text-primary-600 dark:text-primary-400">{{ electronicCount }}</div>
                    <div class="mt-1 text-[10px] text-surface-400">تصحيح فوري وآلي</div>
                </div>

                <div class="card p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] text-surface-400 font-bold">تقييمات مقالية / ورقية</span>
                        <span class="text-sm">📑</span>
                    </div>
                    <div class="mt-2 text-2xl font-black text-accent-600 dark:text-accent-400">{{ paperCount }}</div>
                    <div class="mt-1 text-[10px] text-surface-400">رفع حل ومراجعة المعلم</div>
                </div>

                <div class="card p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] text-surface-400 font-bold">مكتملة ومصححة</span>
                        <Icon name="success" class="h-4 w-4 text-green-500" />
                    </div>
                    <div class="mt-2 text-2xl font-black text-green-600 dark:text-green-400">{{ totalCompletedCount }}</div>
                    <div class="mt-1 text-[10px] text-surface-400">تم إنجازها وتصحيحها</div>
                </div>
            </div>

            <!-- ── Filters Toolbar ────────────────────────────────── -->
            <div class="card p-4 sm:p-5 space-y-4">

                <!-- 1. النوع (Mode: الكل / دورية / إلكتروني / مقالي) -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                    <span class="text-xs font-bold text-surface-500 dark:text-surface-400 shrink-0 w-24">التبويب والنوع:</span>
                    <div class="flex items-center gap-2 flex-wrap">
                        <button
                            type="button"
                            @click="activeMode = 'all'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all"
                            :class="activeMode === 'all'
                                ? 'bg-surface-900 text-white dark:bg-white dark:text-surface-900 shadow-sm'
                                : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200'"
                        >
                            الكل ({{ totalUnifiedCount }})
                        </button>
                        <button
                            type="button"
                            @click="activeMode = 'electronic'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                            :class="activeMode === 'electronic'
                                ? 'bg-primary-600 text-white shadow-sm'
                                : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200'"
                        >
                            <span>📝 اختبارات إلكترونية</span>
                            <span class="text-[10px] opacity-80">({{ electronicCount }})</span>
                        </button>
                        <button
                            type="button"
                            @click="activeMode = 'paper'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                            :class="activeMode === 'paper'
                                ? 'bg-accent-600 text-white shadow-sm'
                                : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200'"
                        >
                            <span>📑 تقييمات مقالية / ورقية</span>
                            <span class="text-[10px] opacity-80">({{ paperCount }})</span>
                        </button>
                        <button
                            type="button"
                            @click="activeMode = 'homework'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                            :class="activeMode === 'homework'
                                ? 'bg-amber-600 text-white shadow-sm'
                                : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200'"
                        >
                            <span>📚 تقييمات دورية</span>
                            <span class="text-[10px] opacity-80">({{ homeworkCount }})</span>
                        </button>
                    </div>
                </div>

                <!-- 2. النطاق والارتباط (Scope) -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 pt-2 border-t border-surface-100 dark:border-surface-800">
                    <span class="text-xs font-bold text-surface-500 dark:text-surface-400 shrink-0 w-24">نطاق الارتباط:</span>
                    <div class="flex items-center gap-2 flex-wrap">
                        <button
                            type="button"
                            @click="activeScope = 'all'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all"
                            :class="activeScope === 'all'
                                ? 'bg-primary-700 text-white shadow-sm'
                                : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200'"
                        >
                            كل النطاقات
                        </button>
                        <button
                            type="button"
                            @click="activeScope = 'lesson'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1"
                            :class="activeScope === 'lesson'
                                ? 'bg-primary-700 text-white shadow-sm'
                                : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200'"
                        >
                            <span>📖 مرتبطة بالدروس</span>
                        </button>
                        <button
                            type="button"
                            @click="activeScope = 'unit'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1"
                            :class="activeScope === 'unit'
                                ? 'bg-primary-700 text-white shadow-sm'
                                : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200'"
                        >
                            <span>📁 اختبارات الوحدات</span>
                        </button>
                        <button
                            type="button"
                            @click="activeScope = 'midterm'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1"
                            :class="activeScope === 'midterm'
                                ? 'bg-primary-700 text-white shadow-sm'
                                : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200'"
                        >
                            <span>📚 أجزاء المنهج</span>
                        </button>
                        <button
                            type="button"
                            @click="activeScope = 'comprehensive'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1"
                            :class="activeScope === 'comprehensive'
                                ? 'bg-primary-700 text-white shadow-sm'
                                : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200'"
                        >
                            <span>🏆 اختبارات شاملة</span>
                        </button>
                    </div>
                </div>

                <!-- 3. حالة الحل (Status) -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 pt-2 border-t border-surface-100 dark:border-surface-800">
                    <span class="text-xs font-bold text-surface-500 dark:text-surface-400 shrink-0 w-24">حالة الإنجاز:</span>
                    <div class="flex items-center gap-2 flex-wrap">
                        <button
                            v-for="st in [
                                { key: 'all', label: 'الكل' },
                                { key: 'available', label: 'متاح للحل الآن' },
                                { key: 'in_progress', label: 'قيد الحل / التسليم' },
                                { key: 'completed', label: 'مكتمل ومصحح' },
                                { key: 'upcoming', label: 'قادم قريباً' },
                            ]"
                            :key="st.key"
                            type="button"
                            @click="activeStatus = st.key"
                            class="px-3 py-1 rounded-xl text-xs font-bold transition-colors"
                            :class="activeStatus === st.key
                                ? 'bg-surface-800 text-white dark:bg-surface-200 dark:text-surface-900'
                                : 'bg-surface-100 dark:bg-surface-800 text-surface-500 dark:text-surface-400 hover:text-surface-800'"
                        >
                            {{ st.label }}
                        </button>
                    </div>
                </div>

            </div>

            <!-- ── Cards List ────────────────────────────────────── -->
            <section v-if="filteredItems.length" class="grid gap-5 lg:grid-cols-2">
                <article
                    v-for="item in filteredItems"
                    :key="`${item.mode}-${item.id}`"
                    class="card p-5 sm:p-6 border transition-all hover:shadow-md flex flex-col justify-between"
                    :class="{
                        'border-amber-500/30 dark:border-amber-500/20 bg-amber-500/[0.01]': item.mode === 'homework',
                        'border-accent-200/80 dark:border-accent-900/50': item.mode === 'paper',
                        'border-primary-200/80 dark:border-primary-900/50': item.mode === 'electronic',
                    }"
                >
                    <div>
                        <!-- Header Badges -->
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="flex items-center gap-2 flex-wrap">
                                <!-- Mode Badge -->
                                <span
                                    class="text-[11px] px-2.5 py-0.5 rounded-full font-black flex items-center gap-1"
                                    :class="{
                                        'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800': item.mode === 'homework',
                                        'bg-accent-50 text-accent-700 dark:bg-accent-950/60 dark:text-accent-300 border border-accent-200 dark:border-accent-800': item.mode === 'paper',
                                        'bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300 border border-primary-200 dark:border-primary-800': item.mode === 'electronic',
                                    }"
                                >
                                    <span>{{ item.mode === 'homework' ? '📚' : (item.mode === 'paper' ? '📑' : '📝') }}</span>
                                    <span>{{ item.type_label }}</span>
                                </span>

                                <!-- Lesson Link Badge -->
                                <span
                                    v-if="item.lesson"
                                    class="text-[11px] px-2.5 py-0.5 rounded-full font-bold bg-primary-100 text-primary-800 dark:bg-primary-950/60 dark:text-primary-300 border border-primary-200 dark:border-primary-800 flex items-center gap-1"
                                >
                                    <span>📖</span>
                                    <span>الدرس: {{ item.lesson.title }}</span>
                                </span>

                                <!-- Scope Badge if unit or comprehensive -->
                                <span
                                    v-else-if="item.scope_label"
                                    class="text-[11px] px-2.5 py-0.5 rounded-full font-bold"
                                    :class="item.scope === 'comprehensive'
                                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'
                                        : (item.scope === 'midterm' ? 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300' : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300')"
                                >
                                    {{ item.scope_label }}
                                </span>

                                <!-- Late Submission Option Badge -->
                                <span
                                    class="text-[10px] px-2 py-0.5 rounded font-bold"
                                    :class="item.allow_late_submission ? 'bg-green-500/10 text-green-600 dark:text-green-400 border border-green-500/20' : 'bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20'"
                                >
                                    {{ item.allow_late_submission ? '✓ مسموح بالتسليم المتأخر' : '🚫 لا يُسمح بالتسليم المتأخر' }}
                                </span>

                                <!-- Max attempts badge -->
                                <span class="text-[10px] px-2 py-0.5 rounded font-bold bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 border border-surface-200 dark:border-surface-700">
                                    المحاولات: {{ item.max_attempts ?? (item.mode === 'electronic' ? 3 : 1) }}
                                </span>
                            </div>

                            <span :class="statusClasses[item.status] ?? 'badge-gray'" class="shrink-0 text-[11px] font-bold">
                                {{ statusLabels[item.status] ?? item.status }}
                            </span>
                        </div>

                        <!-- Title & Meta -->
                        <h2 class="text-lg font-black text-surface-900 dark:text-white leading-snug">
                            {{ item.title }}
                        </h2>
                        <div class="mt-1 flex items-center gap-2 text-xs text-surface-500 dark:text-surface-400 flex-wrap">
                            <span class="font-bold text-surface-700 dark:text-surface-200">{{ item.subject?.name }}</span>
                            <span v-if="item.teacher">·</span>
                            <span v-if="item.teacher">المعلم: {{ item.teacher.name }}</span>
                            <span v-if="item.group?.name">·</span>
                            <span v-if="item.group?.name">{{ item.group.name }}</span>
                        </div>

                        <!-- ── 1. Electronic Exam Details (📝) ─────────── -->
                        <div v-if="item.mode === 'electronic'" class="mt-5 space-y-3">
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center">
                                <div class="rounded-xl bg-surface-50 dark:bg-surface-800/60 p-2.5">
                                    <span class="block text-[10px] text-surface-400">الأسئلة</span>
                                    <strong class="mt-0.5 block text-sm font-black text-surface-800 dark:text-surface-100">{{ item.questions_count }}</strong>
                                </div>
                                <div class="rounded-xl bg-surface-50 dark:bg-surface-800/60 p-2.5">
                                    <span class="block text-[10px] text-surface-400">المدة</span>
                                    <strong class="mt-0.5 block text-sm font-black text-surface-800 dark:text-surface-100">
                                        {{ item.time_limit_minutes ? item.time_limit_minutes + ' د' : 'مفتوح' }}
                                    </strong>
                                </div>
                                <div class="rounded-xl bg-surface-50 dark:bg-surface-800/60 p-2.5">
                                    <span class="block text-[10px] text-surface-400">درجة النجاح</span>
                                    <strong class="mt-0.5 block text-sm font-black text-surface-800 dark:text-surface-100">{{ item.passing_score }}%</strong>
                                </div>
                                <div class="rounded-xl bg-surface-50 dark:bg-surface-800/60 p-2.5">
                                    <span class="block text-[10px] text-surface-400">المحاولات المتبقية</span>
                                    <strong class="mt-0.5 block text-sm font-black text-surface-800 dark:text-surface-100">{{ item.remaining_attempts }}</strong>
                                </div>
                            </div>

                            <!-- Start time notice if upcoming -->
                            <div v-if="item.status === 'upcoming' || !item.can_start && item.available_from"
                                 class="p-3 rounded-xl bg-primary-500/10 border border-primary-500/20 text-primary-600 dark:text-primary-300 text-xs flex items-center gap-2">
                                <Icon name="clock" class="w-4 h-4 shrink-0" />
                                <span>⏳ يبدأ هذا الاختبار في: <strong>{{ dateLabel(item.available_from) }}</strong> (مغلق حالياً حتى حلول الموعد المحدد).</span>
                            </div>

                            <!-- Best Attempt Result -->
                            <div v-if="item.best_attempt" class="p-3 rounded-xl bg-green-500/10 border border-green-500/20 text-xs flex items-center justify-between gap-3">
                                <div>
                                    <span class="text-surface-500">أعلى نتيجة حققتها: </span>
                                    <strong class="text-green-600 dark:text-green-400 font-black text-sm">{{ item.best_attempt.score }}%</strong>
                                    <span class="ms-1 font-bold" :class="item.best_attempt.passed ? 'text-green-500' : 'text-red-400'">
                                        ({{ item.best_attempt.passed ? 'اجتياز بنجاح' : 'لم يجتز' }})
                                    </span>
                                </div>
                                <span class="text-[10px] text-surface-400">{{ dateLabel(item.best_attempt.submitted_at) }}</span>
                            </div>

                            <p v-if="item.best_attempt?.teacher_feedback" class="text-xs text-surface-500 dark:text-surface-400 italic bg-surface-50 dark:bg-surface-800/40 p-2.5 rounded-xl border border-surface-200 dark:border-surface-700">
                                ملاحظات المعلم: "{{ item.best_attempt.teacher_feedback }}"
                            </p>
                        </div>

                        <!-- ── 2. Paper & Periodic Assessment Details (📑 / 📚) ───────── -->
                        <div v-else class="mt-5 space-y-4">
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-center text-xs">
                                <div class="rounded-xl bg-surface-50 dark:bg-surface-800/60 p-2.5">
                                    <span class="block text-[10px] text-surface-400">الدرجة الكاملة</span>
                                    <strong class="mt-0.5 block text-sm font-black text-surface-800 dark:text-surface-100">
                                        {{ item.max_score ? item.max_score + ' درجة' : '100 درجة' }}
                                    </strong>
                                </div>
                                <div class="rounded-xl bg-surface-50 dark:bg-surface-800/60 p-2.5">
                                    <span class="block text-[10px] text-surface-400">آخر موعد للتسليم</span>
                                    <strong class="mt-0.5 block text-xs font-bold text-surface-800 dark:text-surface-100" :class="item.is_late && !item.submission ? 'text-red-500' : ''">
                                        {{ item.due_date ? formatDateOnly(item.due_date) : 'مفتوح' }}
                                    </strong>
                                </div>
                                <div class="rounded-xl bg-surface-50 dark:bg-surface-800/60 p-2.5 col-span-2 sm:col-span-1">
                                    <span class="block text-[10px] text-surface-400">موعد البدء</span>
                                    <strong class="mt-0.5 block text-xs font-bold text-surface-800 dark:text-surface-100">
                                        {{ item.available_from ? dateLabel(item.available_from) : 'متاح فوراً' }}
                                    </strong>
                                </div>
                            </div>

                            <!-- Start time notice if upcoming -->
                            <div v-if="item.status === 'upcoming' || (!item.can_start && item.available_from)"
                                 class="p-3 rounded-xl bg-primary-500/10 border border-primary-500/20 text-primary-600 dark:text-primary-300 text-xs flex items-center gap-2">
                                <Icon name="clock" class="w-4 h-4 shrink-0" />
                                <span>⏳ يبدأ هذا التقييم في: <strong>{{ dateLabel(item.available_from) }}</strong> (مغلق حالياً حتى حلول موعد البدء المحدد من المعلم).</span>
                            </div>

                            <!-- Overdue warning -->
                            <div v-if="!item.submission && item.is_late"
                                 class="p-2.5 rounded-xl border text-xs flex items-center gap-2"
                                 :class="item.allow_late_submission ? 'bg-amber-500/10 border-amber-500/20 text-amber-500' : 'bg-red-500/10 border-red-500/20 text-red-500'">
                                <Icon name="alert-triangle" class="w-4 h-4 shrink-0" />
                                <span>{{ item.allow_late_submission ? '⚠️ انتهى موعد التسليم المحدد — مسموح بالتسليم المتأخر.' : '🚫 انتهى موعد التسليم المحدد ولا يُسمح بالتسليم بعد انتهاء الوقت.' }}</span>
                            </div>

                            <!-- Download Questions Sheet -->
                            <div v-if="item.file_url" class="p-3 rounded-xl bg-surface-50 dark:bg-surface-800/60 border border-surface-200 dark:border-surface-700 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="text-lg">📄</span>
                                    <div class="min-w-0">
                                        <div class="text-xs font-bold text-surface-900 dark:text-white truncate">ورقة أسئلة التقييم</div>
                                        <div class="text-[10px] text-surface-400">حمّل الملف واكتب إجاباتك تمهيداً لرفعها وتسليمها</div>
                                    </div>
                                </div>
                                <a :href="item.file_url" target="_blank" rel="noopener" class="btn btn-xs bg-primary-600 hover:bg-primary-700 text-white font-bold shrink-0 flex items-center gap-1">
                                    <Icon name="download" class="w-3.5 h-3.5" />
                                    <span>تحميل الأسئلة</span>
                                </a>
                            </div>

                            <!-- Submission State & Teacher Feedback -->
                            <div v-if="item.submission" class="p-3.5 rounded-xl bg-surface-900/5 dark:bg-surface-950/60 border border-surface-200 dark:border-surface-800 text-xs space-y-2">
                                <div class="flex items-center justify-between gap-2 flex-wrap">
                                    <div class="flex items-center gap-1.5 text-green-600 dark:text-green-400 font-bold">
                                        <Icon name="success" class="w-4 h-4" />
                                        <span>تم التسليم</span>
                                        <span class="text-surface-400 font-normal">({{ dateLabel(item.submission.submitted_at) }})</span>
                                    </div>
                                    <a v-if="item.submission.file_url" :href="item.submission.file_url" target="_blank" rel="noopener" class="text-primary-600 dark:text-primary-400 hover:underline font-bold inline-flex items-center gap-1 text-[11px]">
                                        <span>عرض الملف المرفوع</span>
                                        <span>↗</span>
                                    </a>
                                </div>

                                <div v-if="item.submission.is_graded" class="pt-1 border-t border-surface-200 dark:border-surface-700">
                                    <div class="flex items-center gap-2">
                                        <span class="text-surface-500">الدرجة المرصودة:</span>
                                        <strong class="text-sm font-black text-primary-600 dark:text-primary-400">
                                            {{ item.submission.score }} / {{ item.max_score }}
                                        </strong>
                                        <span v-if="item.submission.score_percent !== null" class="text-[11px] font-bold text-surface-400">
                                            ({{ item.submission.score_percent }}%)
                                        </span>
                                    </div>
                                    <p v-if="item.submission.teacher_feedback" class="mt-1 text-surface-600 dark:text-surface-300 italic">
                                        ملاحظات المعلم: "{{ item.submission.teacher_feedback }}"
                                    </p>
                                </div>
                                <div v-else class="text-surface-400 text-[11px]">
                                    في انتظار مراجعة وتصحيح المعلم للدرجة.
                                </div>
                            </div>

                            <!-- Upload Answer Form (Direct in-page submission, button strictly "تسليم") -->
                            <div v-if="item.requires_submission && item.can_submit !== false && item.can_start !== false && item.status !== 'upcoming'" class="space-y-1.5 pt-1">
                                <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                                    <input
                                        type="file"
                                        accept=".pdf,.docx,.png,.jpg,.jpeg"
                                        :key="`pe-input-${item.id}-${item.submission?.submitted_at ?? 'new'}`"
                                        @change="onFileChange($event, item.id)"
                                        class="text-xs text-surface-500 bg-surface-50 dark:bg-surface-850 border border-surface-200 dark:border-surface-700 rounded-xl px-3 py-2 flex-1 min-w-0"
                                    />
                                    <button
                                        type="button"
                                        @click="uploadAnswer(item.id)"
                                        :disabled="uploadingSheet === item.id"
                                        class="btn-primary btn-sm shrink-0 font-bold min-w-[70px]"
                                    >
                                        {{ uploadingSheet === item.id ? 'جارٍ التسليم...' : 'تسليم' }}
                                    </button>
                                </div>
                                <p class="text-[10px] text-surface-400">
                                    صيغ مسموحة: PDF أو Word أو صورة (بحد أقصى 10 ميجابايت).
                                    <span v-if="item.submission" class="text-amber-500 font-bold">إعادة التسليم تستبدل الملف السابق وتنتظر التصحيح من جديد.</span>
                                </p>
                                <p v-if="uploadNotices[item.id]" class="text-[11px] text-red-500 font-bold">
                                    {{ uploadNotices[item.id] }}
                                </p>
                            </div>
                            <div v-else-if="item.status === 'upcoming' || item.can_start === false" class="p-2.5 rounded-xl bg-surface-100 dark:bg-surface-800 text-xs text-surface-400">
                                ⏳ لم يبدأ موعد التقييم بعد — سيُفتح رابط التسليم تلقائياً عند حلول موعد البدء.
                            </div>
                            <div v-else-if="item.requires_submission && item.can_submit === false" class="p-2.5 rounded-xl bg-surface-100 dark:bg-surface-800 text-xs text-surface-400">
                                🚫 التسليم مغلق لهذا التقييم (انتهى وقت التقييم ولا يُسمح بالتسليم المتأخر أو تم استنفاد المحاولات المتاحة).
                            </div>
                        </div>
                    </div>

                    <!-- Footer Action Bars -->
                    <!-- Electronic Footer -->
                    <div v-if="item.mode === 'electronic'" class="mt-6 pt-4 border-t border-surface-100 dark:border-surface-800 flex items-center justify-between gap-3 text-xs">
                        <div class="text-surface-500 dark:text-surface-400 text-[11px]">
                            <span v-if="item.available_from">يبدأ في: {{ dateLabel(item.available_from) }}</span>
                            <span v-else>متاح للبدء في أي وقت</span>
                        </div>
                        <div v-if="item.status === 'upcoming' || (!item.can_start && item.available_from)">
                            <button type="button" disabled class="btn-outline btn-sm opacity-50 cursor-not-allowed text-xs font-bold">
                                قادم قريباً
                            </button>
                        </div>
                        <div v-else-if="item.status === 'closed' || (!item.can_start && item.remaining_attempts <= 0)">
                            <span class="badge-gray text-xs font-bold">منتهي</span>
                        </div>
                        <Link v-else :href="route('student.quiz', { quizId: item.id })" class="btn-primary btn-sm flex items-center gap-1 font-bold">
                            <span>ابدأ الاختبار الإلكتروني</span>
                            <span>←</span>
                        </Link>
                    </div>

                    <!-- Worksheet / Paper Footer -->
                    <div v-else class="mt-6 pt-4 border-t border-surface-100 dark:border-surface-800 flex items-center justify-between gap-3 text-xs">
                        <a v-if="item.file_url" :href="item.file_url" target="_blank" rel="noopener" class="btn-outline btn-sm flex items-center gap-1">
                            <Icon name="download" class="w-3.5 h-3.5" />
                            <span>تحميل ورقة الأسئلة</span>
                        </a>
                        <div v-else class="text-surface-400 text-[11px]">لا يوجد ملف أسئلة مرفق</div>

                        <div class="text-xs font-bold text-surface-500">
                            <span v-if="item.submission" class="text-green-600 dark:text-green-400">✓ تم التسليم بنجاح</span>
                            <span v-else-if="item.can_submit" class="text-primary-600 dark:text-primary-400">متاح للتسليم الآن أعلاه</span>
                            <span v-else class="text-surface-400">التسليم مغلق</span>
                        </div>
                    </div>
                </article>
            </section>

            <!-- ── Empty State ────────────────────────────────────── -->
            <div v-else class="card p-12 text-center max-w-lg mx-auto">
                <div class="w-16 h-16 rounded-2xl bg-primary-50 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400 flex items-center justify-center mx-auto mb-4">
                    <Icon name="chart" class="w-8 h-8" />
                </div>
                <h2 class="text-lg font-black text-surface-900 dark:text-white mb-1">
                    لا توجد تقييمات أو اختبارات تطابق هذا التصنيف
                </h2>
                <p class="text-xs text-surface-500 dark:text-surface-400 leading-relaxed mb-6">
                    جرّب تغيير خيارات التصفية (الكل / إلكتروني / مقالي / دوري)، أو استعرض مواردك التعليمية لمتابعة الدروس المنشورة أولاً.
                </p>
                <div class="flex items-center justify-center gap-2">
                    <button type="button" @click="activeMode = 'all'; activeScope = 'all'; activeStatus = 'all'" class="btn-outline btn-sm">
                        إعادة تعيين الفلاتر
                    </button>
                    <Link :href="route('student.resources')" class="btn-primary btn-sm">
                        الموارد التعليمية
                    </Link>
                </div>
            </div>

        </div>
    </DashboardLayout>
</template>
