<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    worksheets: { type: Array, default: () => [] },
    quizzes: { type: Array, default: () => [] },
    submissions: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
    libraryResources: { type: Array, default: () => [] },
    selectedGroupId: { type: Number, default: null },
    initialTab: { type: String, default: 'assessments' }, // assessments | quizzes | submissions
});

const { confirm } = useConfirm();

const activeTab = ref(props.initialTab);
const activeGroupFilter = ref(props.selectedGroupId || 'all');
const assessmentStatusFilter = ref('all'); // all | published | suspended

// Filtered lists
const filteredWorksheets = computed(() => {
    return props.worksheets.filter((w) => {
        if (activeGroupFilter.value !== 'all' && w.teaching_group_id && w.teaching_group_id !== Number(activeGroupFilter.value)) {
            return false;
        }
        if (assessmentStatusFilter.value === 'published' && !w.is_active) return false;
        if (assessmentStatusFilter.value === 'suspended' && w.is_active) return false;
        return true;
    });
});

const filteredQuizzes = computed(() => {
    return props.quizzes.filter((q) => {
        if (activeGroupFilter.value !== 'all' && q.teaching_group_id && q.teaching_group_id !== Number(activeGroupFilter.value)) {
            return false;
        }
        if (assessmentStatusFilter.value === 'published' && !q.is_active) return false;
        if (assessmentStatusFilter.value === 'suspended' && q.is_active) return false;
        return true;
    });
});

const pendingSubmissions = computed(() => props.submissions.filter((s) => !s.is_graded));

// ── Assessment Form & Modal ──────────────────────────────────────────
const isAssessmentModalOpen = ref(false);
const editingAssessmentId = ref(null);

const assessmentForm = useForm({
    title: '',
    type: 'paper_exam', // paper_exam | homework | study
    teaching_group_id: '',
    curriculum_unit_id: '',
    lesson_id: '',
    file: null,
    resource_id: '',
    available_from: '',
    due_date: '',
    allow_late_submission: false,
    max_attempts: 1,
    max_score: 100,
    is_active: true,
});

const availableLessons = computed(() => {
    if (!assessmentForm.curriculum_unit_id) return [];
    const unit = props.units.find((u) => u.id === Number(assessmentForm.curriculum_unit_id));
    return unit?.lessons || [];
});

function openCreateAssessmentModal() {
    editingAssessmentId.value = null;
    assessmentForm.reset();
    assessmentForm.type = 'paper_exam';
    assessmentForm.max_score = 100;
    assessmentForm.max_attempts = 1;
    assessmentForm.allow_late_submission = false;
    assessmentForm.is_active = true;
    if (activeGroupFilter.value !== 'all') {
        assessmentForm.teaching_group_id = activeGroupFilter.value;
    }
    if (props.units.length > 0) {
        assessmentForm.curriculum_unit_id = props.units[0].id;
    }
    isAssessmentModalOpen.value = true;
}

function openEditAssessmentModal(item) {
    editingAssessmentId.value = item.id;
    assessmentForm.reset();
    assessmentForm.title = item.title;
    assessmentForm.type = item.type;
    assessmentForm.teaching_group_id = item.teaching_group_id || '';
    assessmentForm.curriculum_unit_id = item.unit?.id || '';
    assessmentForm.lesson_id = item.lesson?.id || '';
    assessmentForm.available_from = item.available_from ? item.available_from.substring(0, 16) : '';
    assessmentForm.due_date = item.due_date || '';
    assessmentForm.allow_late_submission = item.allow_late_submission;
    assessmentForm.max_attempts = item.max_attempts;
    assessmentForm.max_score = item.max_score;
    assessmentForm.is_active = item.is_active;
    isAssessmentModalOpen.value = true;
}

function handleFileUpload(e) {
    const file = e.target.files?.[0] || null;
    assessmentForm.file = file;
}

function submitAssessmentForm() {
    if (editingAssessmentId.value) {
        assessmentForm.put(route('teacher.assessments.update', { worksheet: editingAssessmentId.value }), {
            onSuccess: () => {
                isAssessmentModalOpen.value = false;
                assessmentForm.reset();
            },
        });
    } else {
        assessmentForm.post(route('teacher.assessments.store'), {
            onSuccess: () => {
                isAssessmentModalOpen.value = false;
                assessmentForm.reset();
            },
        });
    }
}

async function toggleAssessmentStatus(item) {
    router.post(route('teacher.assessments.toggle-status', { worksheet: item.id }), {}, {
        preserveScroll: true,
    });
}

async function deleteAssessment(item) {
    const ok = await confirm({
        title: 'حذف التقييم',
        message: `هل أنت متأكد من حذف التقييم "${item.title}"؟ سيتم حذف جميع التسليمات المرتبطة به.`,
        confirmLabel: 'نعم، احذف',
        variant: 'danger',
    });
    if (ok) {
        router.delete(route('teacher.assessments.destroy', { worksheet: item.id }), {
            preserveScroll: true,
        });
    }
}

// ── Library Selection Modal ──────────────────────────────────────────
const isLibraryModalOpen = ref(false);
const selectedLibraryResource = ref(null);

function openLibraryModal() {
    selectedLibraryResource.value = null;
    isLibraryModalOpen.value = true;
}

function selectLibraryResource(res) {
    selectedLibraryResource.value = res;
    // Prefill assessment modal
    openCreateAssessmentModal();
    isLibraryModalOpen.value = false;
    assessmentForm.resource_id = res.id;
    assessmentForm.title = res.title;
    assessmentForm.type = 'paper_exam';
}

// ── Quizzes Controls ────────────────────────────────────────────────
async function toggleQuizStatus(quiz) {
    router.post(route('teacher.assessments.quizzes.toggle-status', { quiz: quiz.id }), {}, {
        preserveScroll: true,
    });
}

async function deleteQuiz(quiz) {
    const ok = await confirm({
        title: 'حذف الاختبار الإلكتروني',
        message: `هل أنت متأكد من حذف الاختبار "${quiz.title}"؟`,
        confirmLabel: 'نعم، احذف',
        variant: 'danger',
    });
    if (ok) {
        router.delete(route('teacher.quizzes.destroy', { quiz: quiz.id }), {
            preserveScroll: true,
        });
    }
}

// ── Grading Modal ───────────────────────────────────────────────────
const gradingModal = ref({
    isOpen: false,
    submission: null,
});

const gradeForm = useForm({
    score: '',
    teacher_feedback: '',
});

function openGradingModal(sub) {
    gradingModal.value.submission = sub;
    gradeForm.reset();
    gradeForm.score = sub.score !== null ? sub.score : '';
    gradeForm.teacher_feedback = sub.teacher_feedback || '';
    gradingModal.value.isOpen = true;
}

function closeGradingModal() {
    gradingModal.value.isOpen = false;
    gradingModal.value.submission = null;
    gradeForm.reset();
}

function submitGrading() {
    if (!gradingModal.value.submission) return;
    gradeForm.post(route('teacher.assessments.grade', { submission: gradingModal.value.submission.id }), {
        preserveScroll: true,
        onSuccess: () => {
            closeGradingModal();
        },
    });
}

function setPresetFeedback(text) {
    gradeForm.teacher_feedback = text;
}

function formatDate(val) {
    if (!val) return 'مفتوح بدون موعد';
    return new Date(val).toLocaleDateString('ar-QA', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatDateTime(val) {
    if (!val) return 'غير محدد';
    return new Date(val).toLocaleString('ar-QA', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="التقييمات والاختبارات - لوحة المعلم" />

    <DashboardLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black text-surface-900 dark:text-white flex items-center gap-2.5">
                        <span class="w-10 h-10 rounded-2xl bg-primary-500/10 text-primary-500 flex items-center justify-center">
                            <Icon name="assignment" class="w-6 h-6" />
                        </span>
                        <span>التقييمات والاختبارات</span>
                    </h1>
                    <p class="text-xs sm:text-sm text-surface-500 mt-1">
                        إنشاء وإسناد التقييمات المقالية والورقية والاختبارات الإلكترونية، وجدولة المواعيد والتصحيح.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        @click="openLibraryModal"
                        class="btn-outline btn-sm flex items-center gap-1.5 font-bold"
                    >
                        <Icon name="book" class="w-4 h-4 text-primary-500" />
                        <span>إضافة تقييم من المكتبة</span>
                    </button>

                    <button
                        type="button"
                        @click="openCreateAssessmentModal"
                        class="btn-primary btn-sm flex items-center gap-1.5 font-bold shadow-sm"
                    >
                        <Icon name="plus" class="w-4 h-4" />
                        <span>إنشاء تقييم جديد</span>
                    </button>
                </div>
            </div>

            <!-- Metric Summary Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <div class="card p-4 flex items-center justify-between">
                    <div>
                        <span class="text-xs text-surface-400 font-bold">التقييمات المسندة</span>
                        <div class="text-2xl font-black text-surface-900 dark:text-white mt-1">{{ worksheets.length }}</div>
                        <span class="text-[10px] text-surface-400">مقالي ودوري</span>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-primary-500/10 text-primary-500 flex items-center justify-center font-bold">
                        📑
                    </div>
                </div>

                <div class="card p-4 flex items-center justify-between">
                    <div>
                        <span class="text-xs text-surface-400 font-bold">الاختبارات الإلكترونية</span>
                        <div class="text-2xl font-black text-surface-900 dark:text-white mt-1">{{ quizzes.length }}</div>
                        <span class="text-[10px] text-surface-400">بنك الأسئلة</span>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-purple-500/10 text-purple-500 flex items-center justify-center font-bold">
                        📝
                    </div>
                </div>

                <div class="card p-4 flex items-center justify-between">
                    <div>
                        <span class="text-xs text-surface-400 font-bold">تسليمات بانتظار التصحيح</span>
                        <div class="text-2xl font-black text-amber-500 mt-1">{{ pendingSubmissions.length }}</div>
                        <span class="text-[10px] text-amber-500/80">إجابات طلاب مقالية</span>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-amber-500/10 text-amber-500 flex items-center justify-center font-bold">
                        ⏳
                    </div>
                </div>

                <div class="card p-4 flex items-center justify-between">
                    <div>
                        <span class="text-xs text-surface-400 font-bold">إجمالي التسليمات</span>
                        <div class="text-2xl font-black text-green-500 mt-1">{{ submissions.length }}</div>
                        <span class="text-[10px] text-green-500/80">تم استلامها</span>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-green-500/10 text-green-500 flex items-center justify-center font-bold">
                        ✓
                    </div>
                </div>
            </div>

            <!-- Filters Bar & Tabs -->
            <div class="card p-4 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-surface-100 dark:border-surface-800 pb-3">
                    <!-- Navigation Tabs -->
                    <div class="flex items-center gap-2 overflow-x-auto">
                        <button
                            type="button"
                            @click="activeTab = 'assessments'"
                            class="px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all whitespace-nowrap flex items-center gap-2"
                            :class="activeTab === 'assessments' ? 'bg-primary-600 text-white shadow-sm' : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200'"
                        >
                            <span>📑 التقييمات المقالية والورقية</span>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px]" :class="activeTab === 'assessments' ? 'bg-white/20' : 'bg-surface-200 dark:bg-surface-700'">{{ worksheets.length }}</span>
                        </button>

                        <button
                            type="button"
                            @click="activeTab = 'quizzes'"
                            class="px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all whitespace-nowrap flex items-center gap-2"
                            :class="activeTab === 'quizzes' ? 'bg-primary-600 text-white shadow-sm' : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200'"
                        >
                            <span>📝 الاختبارات الإلكترونية</span>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px]" :class="activeTab === 'quizzes' ? 'bg-white/20' : 'bg-surface-200 dark:bg-surface-700'">{{ quizzes.length }}</span>
                        </button>

                        <button
                            type="button"
                            @click="activeTab = 'submissions'"
                            class="px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all whitespace-nowrap flex items-center gap-2"
                            :class="activeTab === 'submissions' ? 'bg-primary-600 text-white shadow-sm' : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200'"
                        >
                            <span>✍️ تصحيح ورصد إجابات الطلاب</span>
                            <span v-if="pendingSubmissions.length" class="px-1.5 py-0.5 rounded-full text-[10px] bg-amber-500 text-white font-bold animate-pulse">{{ pendingSubmissions.length }}</span>
                        </button>
                    </div>

                    <!-- Group Filter -->
                    <div class="flex items-center gap-2 shrink-0">
                        <label class="text-xs font-bold text-surface-400">المجموعة:</label>
                        <select
                            v-model="activeGroupFilter"
                            class="text-xs rounded-xl bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 px-3 py-1.5"
                        >
                            <option value="all">كل المجموعات</option>
                            <option v-for="g in groups" :key="g.id" :value="g.id">
                                {{ g.name }} ({{ g.assignment?.subject?.name }})
                            </option>
                        </select>
                    </div>
                </div>

                <!-- ── TAB 1: ASSESSMENTS LIST ───────────────────────── -->
                <div v-if="activeTab === 'assessments'" class="space-y-3">
                    <div v-if="filteredWorksheets.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <article
                            v-for="sheet in filteredWorksheets"
                            :key="sheet.id"
                            class="card p-4 border border-surface-200 dark:border-surface-800 space-y-3 hover:border-primary-500/30 transition-all flex flex-col justify-between"
                        >
                            <div class="space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="badge-primary text-[10px] font-bold">
                                        {{ sheet.type_label }}
                                    </span>
                                    <span
                                        class="text-[10px] px-2 py-0.5 rounded-full font-bold"
                                        :class="sheet.is_active ? 'badge-green' : 'badge-gray'"
                                    >
                                        {{ sheet.status_label }}
                                    </span>
                                </div>

                                <h3 class="font-black text-base text-surface-900 dark:text-white leading-snug">
                                    {{ sheet.title }}
                                </h3>

                                <div class="text-xs text-surface-500 dark:text-surface-400 space-y-1">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-surface-400">المجموعة المسندة:</span>
                                        <span class="font-bold text-surface-700 dark:text-surface-200">
                                            {{ sheet.group?.name || 'كافة مجموعات المادة' }}
                                        </span>
                                    </div>
                                    <div v-if="sheet.lesson" class="text-[11px] text-surface-400">
                                        الدرس المرتبط: {{ sheet.lesson.title }}
                                    </div>
                                    <div class="text-[11px] text-surface-400">
                                        {{ sheet.subject?.name }} — الدرجة: {{ sheet.max_score }}
                                    </div>
                                </div>

                                <!-- Schedule Badges -->
                                <div class="p-2.5 rounded-xl bg-surface-50 dark:bg-surface-900/60 border border-surface-100 dark:border-surface-800 text-[11px] space-y-1">
                                    <div class="flex items-center justify-between text-surface-400">
                                        <span>موعد البدء:</span>
                                        <strong class="text-surface-700 dark:text-surface-200">{{ formatDateTime(sheet.available_from) }}</strong>
                                    </div>
                                    <div class="flex items-center justify-between text-surface-400">
                                        <span>الموعد النهائي:</span>
                                        <strong class="text-surface-700 dark:text-surface-200">{{ formatDate(sheet.due_date) }}</strong>
                                    </div>
                                    <div class="flex items-center justify-between text-surface-400">
                                        <span>المحاولات:</span>
                                        <strong class="text-surface-700 dark:text-surface-200">{{ sheet.max_attempts }}</strong>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions footer -->
                            <div class="pt-3 border-t border-surface-100 dark:border-surface-800 flex items-center justify-between gap-2 flex-wrap">
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        @click="toggleAssessmentStatus(sheet)"
                                        class="btn-xs font-bold rounded-lg px-2.5 py-1"
                                        :class="sheet.is_active ? 'btn-outline text-amber-500 border-amber-500/30' : 'btn-primary'"
                                    >
                                        {{ sheet.is_active ? 'تعليق' : 'نشر' }}
                                    </button>

                                    <button
                                        type="button"
                                        @click="openEditAssessmentModal(sheet)"
                                        class="btn-ghost btn-xs text-surface-400 hover:text-white"
                                        title="تعديل"
                                    >
                                        تعديل
                                    </button>

                                    <button
                                        type="button"
                                        @click="deleteAssessment(sheet)"
                                        class="btn-ghost btn-xs text-red-500 hover:bg-red-500/10"
                                        title="حذف"
                                    >
                                        حذف
                                    </button>
                                </div>

                                <div class="text-[11px] text-surface-400 flex items-center gap-1 font-bold">
                                    <span>تسليمات:</span>
                                    <span class="text-primary-500">{{ sheet.total_submissions }}</span>
                                    <span v-if="sheet.pending_submissions > 0" class="text-amber-500 font-bold">({{ sheet.pending_submissions }} بحاجة تصحيح)</span>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div v-else class="card p-12 text-center text-surface-400">
                        <Icon name="assignment" class="w-12 h-12 mx-auto mb-3 text-surface-400 opacity-40" />
                        <h3 class="text-base font-bold text-surface-700 dark:text-surface-200">لا توجد تقييمات منشورة بعد</h3>
                        <p class="text-xs text-surface-400 mt-1 mb-4">أنشئ تقييماً مقالياً أو ارفق ورقة أسئلة وأسندها لطلاب مجموعاتك.</p>
                        <button type="button" @click="openCreateAssessmentModal" class="btn-primary btn-sm">
                            + إنشاء أول تقييم
                        </button>
                    </div>
                </div>

                <!-- ── TAB 2: QUIZZES LIST ───────────────────────────── -->
                <div v-else-if="activeTab === 'quizzes'" class="space-y-3">
                    <div v-if="filteredQuizzes.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <article
                            v-for="quiz in filteredQuizzes"
                            :key="quiz.id"
                            class="card p-4 border border-surface-200 dark:border-surface-800 space-y-3 hover:border-primary-500/30 transition-all flex flex-col justify-between"
                        >
                            <div class="space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="badge-primary text-[10px] font-bold">
                                        اختبار إلكتروني
                                    </span>
                                    <span
                                        class="text-[10px] px-2 py-0.5 rounded-full font-bold"
                                        :class="quiz.is_active ? 'badge-green' : 'badge-gray'"
                                    >
                                        {{ quiz.status_label }}
                                    </span>
                                </div>

                                <h3 class="font-black text-base text-surface-900 dark:text-white leading-snug">
                                    {{ quiz.title }}
                                </h3>

                                <div class="text-xs text-surface-500 dark:text-surface-400 space-y-1">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-surface-400">المجموعة:</span>
                                        <span class="font-bold text-surface-700 dark:text-surface-200">
                                            {{ quiz.group?.name || 'كافة مجموعات المادة' }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-surface-400">
                                        {{ quiz.subject?.name }} · {{ quiz.unit?.title }}
                                    </div>
                                </div>

                                <div class="grid grid-cols-3 gap-2 text-center text-[10px] bg-surface-50 dark:bg-surface-900/60 p-2.5 rounded-xl border border-surface-100 dark:border-surface-800">
                                    <div>
                                        <span class="text-surface-400 block">الأسئلة</span>
                                        <strong class="text-sm font-bold text-surface-800 dark:text-surface-100">{{ quiz.questions_count }}</strong>
                                    </div>
                                    <div>
                                        <span class="text-surface-400 block">المدة</span>
                                        <strong class="text-sm font-bold text-surface-800 dark:text-surface-100">{{ quiz.time_limit_minutes ? quiz.time_limit_minutes + ' د' : 'مفتوح' }}</strong>
                                    </div>
                                    <div>
                                        <span class="text-surface-400 block">المحاولات</span>
                                        <strong class="text-sm font-bold text-surface-800 dark:text-surface-100">{{ quiz.attempts_count }}</strong>
                                    </div>
                                </div>
                            </div>

                            <!-- Quiz Actions -->
                            <div class="pt-3 border-t border-surface-100 dark:border-surface-800 flex items-center justify-between gap-2 flex-wrap">
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        @click="toggleQuizStatus(quiz)"
                                        class="btn-xs font-bold rounded-lg px-2.5 py-1"
                                        :class="quiz.is_active ? 'btn-outline text-amber-500 border-amber-500/30' : 'btn-primary'"
                                    >
                                        {{ quiz.is_active ? 'تعليق' : 'نشر' }}
                                    </button>

                                    <Link
                                        :href="quiz.edit_url"
                                        class="btn-primary btn-xs flex items-center gap-1 font-bold"
                                    >
                                        <span>بناء الأسئلة</span>
                                        <span>←</span>
                                    </Link>

                                    <button
                                        type="button"
                                        @click="deleteQuiz(quiz)"
                                        class="btn-ghost btn-xs text-red-500 hover:bg-red-500/10"
                                        title="حذف"
                                    >
                                        حذف
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div v-else class="card p-12 text-center text-surface-400">
                        <Icon name="courses" class="w-12 h-12 mx-auto mb-3 text-surface-400 opacity-40" />
                        <h3 class="text-base font-bold text-surface-700 dark:text-surface-200">لا توجد اختبارات إلكترونية بعد</h3>
                        <p class="text-xs text-surface-400 mt-1 mb-4">أنشئ اختبارات واضبط الأسئلة من بنك الأسئلة أو الاختبارات المباشرة.</p>
                        <Link :href="route('teacher.curriculum.index')" class="btn-primary btn-sm">
                            الذهاب للخطة الأكاديمية وبناء الاختبارات
                        </Link>
                    </div>
                </div>

                <!-- ── TAB 3: SUBMISSIONS & GRADING ──────────────────── -->
                <div v-else-if="activeTab === 'submissions'" class="space-y-3">
                    <div v-if="submissions.length" class="space-y-3">
                        <article
                            v-for="sub in submissions"
                            :key="sub.id"
                            class="card p-4 border border-surface-200 dark:border-surface-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
                        >
                            <div class="space-y-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h4 class="font-bold text-sm text-surface-900 dark:text-white">
                                        {{ sub.student_name }}
                                    </h4>
                                    <span class="text-xs text-surface-400 font-normal">({{ sub.student_email }})</span>
                                    <span
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                        :class="sub.is_graded ? 'badge-green' : 'badge-amber'"
                                    >
                                        {{ sub.is_graded ? 'تم رصد الدرجة' : 'بانتظار التصحيح' }}
                                    </span>
                                    <span v-if="sub.is_late" class="badge-red text-[10px] font-bold">
                                        ⚠️ تسليم متأخر
                                    </span>
                                </div>

                                <div class="text-xs text-surface-500">
                                    التقييم: <strong class="text-surface-700 dark:text-surface-200">{{ sub.worksheet_title }}</strong>
                                    · تاريخ التسليم: {{ formatDateTime(sub.submitted_at) }}
                                </div>

                                <div v-if="sub.is_graded" class="text-xs text-surface-400 flex items-center gap-2 pt-1">
                                    <span>الدرجة: <strong class="text-primary-500 font-black">{{ sub.score }} / {{ sub.max_score }}</strong></span>
                                    <span v-if="sub.teacher_feedback" class="italic text-surface-500">ملاحظاتك: "{{ sub.teacher_feedback }}"</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <a
                                    v-if="sub.file_url"
                                    :href="sub.file_url"
                                    target="_blank"
                                    rel="noopener"
                                    class="btn-outline btn-xs flex items-center gap-1 font-bold text-primary-500 border-primary-500/30"
                                >
                                    <Icon name="download" class="w-3.5 h-3.5" />
                                    <span>تحميل إجابة الطالب</span>
                                </a>

                                <button
                                    type="button"
                                    @click="openGradingModal(sub)"
                                    class="btn-primary btn-xs font-bold"
                                >
                                    {{ sub.is_graded ? 'تعديل الدرجة' : 'تصحيح ورصد الدرجة' }}
                                </button>
                            </div>
                        </article>
                    </div>

                    <div v-else class="card p-12 text-center text-surface-400">
                        <Icon name="assignment" class="w-12 h-12 mx-auto mb-3 opacity-40" />
                        <h3 class="text-base font-bold text-surface-700 dark:text-surface-200">لا توجد تسليمات حتى الآن</h3>
                        <p class="text-xs text-surface-400 mt-1">ستظهر إجابات وملفات حلول الطلاب هنا بمجرد تسليمهم للتقييمات المقالية والورقية.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Modal: Create / Edit Assessment ────────────────────────── -->
        <div
            v-if="isAssessmentModalOpen"
            class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
            @click.self="isAssessmentModalOpen = false"
        >
            <div class="bg-surface-900 border border-surface-700 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4 my-auto">
                <div class="flex items-center justify-between border-b border-surface-800 pb-3">
                    <h3 class="font-black text-lg text-white">
                        {{ editingAssessmentId ? 'تعديل التقييم' : 'إنشاء تقييم دراسي جديد' }}
                    </h3>
                    <button type="button" @click="isAssessmentModalOpen = false" class="text-surface-400 hover:text-white">
                        ✕
                    </button>
                </div>

                <form @submit.prevent="submitAssessmentForm" class="space-y-4 text-xs">
                    <!-- Title -->
                    <div>
                        <label class="block font-bold text-surface-300 mb-1">عنوان التقييم *</label>
                        <input
                            v-model="assessmentForm.title"
                            type="text"
                            required
                            placeholder="مثال: تقييم الوحدة الأولى في الجبر، تقييم الدرس الثاني"
                            class="w-full rounded-xl bg-surface-800 border border-surface-700 px-3 py-2 text-white"
                        />
                    </div>

                    <!-- Type -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-surface-300 mb-1">نوع التقييم *</label>
                            <select
                                v-model="assessmentForm.type"
                                class="w-full rounded-xl bg-surface-800 border border-surface-700 px-3 py-2 text-white"
                            >
                                <option value="paper_exam">تقييم مقالي / ورقي</option>
                                <option value="homework">تقييم دوري</option>
                                <option value="study">ملزمة / شيت للمراجعة</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-surface-300 mb-1">إسناد لمجموعة</label>
                            <select
                                v-model="assessmentForm.teaching_group_id"
                                class="w-full rounded-xl bg-surface-800 border border-surface-700 px-3 py-2 text-white"
                            >
                                <option value="">كافة المجموعات</option>
                                <option v-for="g in groups" :key="g.id" :value="g.id">
                                    {{ g.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Unit and Lesson binding -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-surface-300 mb-1">الوحدة الدراسية *</label>
                            <select
                                v-model="assessmentForm.curriculum_unit_id"
                                required
                                class="w-full rounded-xl bg-surface-800 border border-surface-700 px-3 py-2 text-white"
                            >
                                <option v-for="u in units" :key="u.id" :value="u.id">
                                    {{ u.title }} ({{ u.assignment?.subject?.name }})
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-surface-300 mb-1">ربط بدرس (اختياري)</label>
                            <select
                                v-model="assessmentForm.lesson_id"
                                class="w-full rounded-xl bg-surface-800 border border-surface-700 px-3 py-2 text-white"
                            >
                                <option value="">بدون ربط بدرس محدد</option>
                                <option v-for="l in availableLessons" :key="l.id" :value="l.id">
                                    {{ l.title }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- File Upload -->
                    <div>
                        <label class="block font-bold text-surface-300 mb-1">ملف الأسئلة / ورقة التقييم (PDF أو Word)</label>
                        <input
                            type="file"
                            accept=".pdf,.doc,.docx,.png,.jpg,.jpeg"
                            @change="handleFileUpload"
                            class="w-full rounded-xl bg-surface-800 border border-surface-700 px-3 py-2 text-surface-300"
                        />
                    </div>

                    <!-- Timing and Deadlines -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-surface-300 mb-1">موعد البدء (يفتح للطالب في)</label>
                            <input
                                v-model="assessmentForm.available_from"
                                type="datetime-local"
                                class="w-full rounded-xl bg-surface-800 border border-surface-700 px-3 py-2 text-white"
                            />
                            <span class="text-[10px] text-surface-500">لن يتمكن الطالب من فتح التقييم قبل هذا الموعد.</span>
                        </div>

                        <div>
                            <label class="block font-bold text-surface-300 mb-1">الموعد النهائي للتسليم</label>
                            <input
                                v-model="assessmentForm.due_date"
                                type="date"
                                class="w-full rounded-xl bg-surface-800 border border-surface-700 px-3 py-2 text-white"
                            />
                        </div>
                    </div>

                    <!-- Score and Attempts -->
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block font-bold text-surface-300 mb-1">الدرجة الكاملة</label>
                            <input
                                v-model="assessmentForm.max_score"
                                type="number"
                                min="1"
                                class="w-full rounded-xl bg-surface-800 border border-surface-700 px-3 py-2 text-white"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-surface-300 mb-1">عدد المحاولات</label>
                            <input
                                v-model="assessmentForm.max_attempts"
                                type="number"
                                min="1"
                                max="10"
                                class="w-full rounded-xl bg-surface-800 border border-surface-700 px-3 py-2 text-white"
                            />
                        </div>

                        <div class="flex items-center pt-5">
                            <label class="flex items-center gap-2 cursor-pointer font-bold text-surface-300">
                                <input
                                    v-model="assessmentForm.allow_late_submission"
                                    type="checkbox"
                                    class="rounded bg-surface-800 border-surface-700 text-primary-600"
                                />
                                <span>سماح بالتسليم المتأخر</span>
                            </label>
                        </div>
                    </div>

                    <!-- Active Toggle -->
                    <div class="flex items-center gap-2 pt-2 border-t border-surface-800">
                        <label class="flex items-center gap-2 cursor-pointer font-bold text-surface-300">
                            <input
                                v-model="assessmentForm.is_active"
                                type="checkbox"
                                class="rounded bg-surface-800 border-surface-700 text-primary-600"
                            />
                            <span>نشر التقييم فوراً ليظهر للطلاب المحددين</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-2 pt-3">
                        <button
                            type="button"
                            @click="isAssessmentModalOpen = false"
                            class="btn-ghost btn-sm"
                        >
                            إلغاء
                        </button>
                        <button
                            type="submit"
                            :disabled="assessmentForm.processing"
                            class="btn-primary btn-sm font-bold"
                        >
                            {{ editingAssessmentId ? 'حفظ التعديلات' : 'إنشاء وإسناد التقييم' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── Modal: Library Selector ───────────────────────────────── -->
        <div
            v-if="isLibraryModalOpen"
            class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
            @click.self="isLibraryModalOpen = false"
        >
            <div class="bg-surface-900 border border-surface-700 rounded-3xl max-w-xl w-full p-6 shadow-2xl space-y-4 my-auto">
                <div class="flex items-center justify-between border-b border-surface-800 pb-3">
                    <h3 class="font-black text-lg text-white">اختر ملف التقييم من مكتبة الموارد</h3>
                    <button type="button" @click="isLibraryModalOpen = false" class="text-surface-400 hover:text-white">✕</button>
                </div>

                <div v-if="libraryResources.length" class="space-y-2 max-h-80 overflow-y-auto">
                    <div
                        v-for="res in libraryResources"
                        :key="res.id"
                        @click="selectLibraryResource(res)"
                        class="p-3 rounded-2xl bg-surface-800/60 hover:bg-primary-500/10 border border-surface-700 hover:border-primary-500/40 cursor-pointer flex items-center justify-between transition-all"
                    >
                        <div class="flex items-center gap-3">
                            <span class="text-xl">📄</span>
                            <div>
                                <h4 class="font-bold text-sm text-white">{{ res.title }}</h4>
                                <span class="text-[10px] text-surface-400">{{ res.type || 'ملف مورد تعليمي' }}</span>
                            </div>
                        </div>
                        <span class="btn-primary btn-xs font-bold">اختيار وإنشاء التقييم ←</span>
                    </div>
                </div>

                <div v-else class="p-8 text-center text-surface-400 text-xs">
                    لم تقم برفع موارد تعليمية في مكتبتك بعد. يمكنك رفع ملف مباشرة عند إنشاء التقييم.
                </div>
            </div>
        </div>

        <!-- ── Modal: Grading & Feedback ─────────────────────────────── -->
        <div
            v-if="gradingModal.isOpen"
            class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
            @click.self="closeGradingModal"
        >
            <div class="bg-surface-900 border border-surface-700 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4 my-auto">
                <div class="flex items-center justify-between border-b border-surface-800 pb-3">
                    <div>
                        <h3 class="font-black text-base text-white">رصد وتصحيح التقييم</h3>
                        <p class="text-xs text-surface-400 mt-0.5">
                            الطالب: {{ gradingModal.submission?.student_name }}
                        </p>
                    </div>
                    <button type="button" @click="closeGradingModal" class="text-surface-400 hover:text-white">✕</button>
                </div>

                <form @submit.prevent="submitGrading" class="space-y-4 text-xs">
                    <!-- Student answer preview link -->
                    <div v-if="gradingModal.submission?.file_url" class="p-3 rounded-xl bg-surface-800/80 border border-surface-700 flex items-center justify-between">
                        <span class="text-surface-300">ملف إجابة الطالب المرفوع:</span>
                        <a
                            :href="gradingModal.submission.file_url"
                            target="_blank"
                            rel="noopener"
                            class="btn-primary btn-xs font-bold flex items-center gap-1"
                        >
                            <Icon name="download" class="w-3.5 h-3.5" />
                            <span>معاينة وتحميل</span>
                        </a>
                    </div>

                    <!-- Score Input -->
                    <div>
                        <label class="block font-bold text-surface-300 mb-1">
                            الدرجة المستحقة (من {{ gradingModal.submission?.max_score }}) *
                        </label>
                        <input
                            v-model="gradeForm.score"
                            type="number"
                            min="0"
                            :max="gradingModal.submission?.max_score || 1000"
                            required
                            placeholder="أدخل الدرجة"
                            class="w-full rounded-xl bg-surface-800 border border-surface-700 px-3 py-2 text-white font-bold text-base"
                        />
                    </div>

                    <!-- Teacher Feedback -->
                    <div>
                        <label class="block font-bold text-surface-300 mb-1">ملاحظات وتقييم المعلم للطالب</label>
                        <textarea
                            v-model="gradeForm.teacher_feedback"
                            rows="3"
                            placeholder="اكتب ملاحظاتك التوجيهية وتصويب الإجابات المقالية هنا..."
                            class="w-full rounded-xl bg-surface-800 border border-surface-700 px-3 py-2 text-white leading-relaxed"
                        ></textarea>

                        <!-- Feedback Presets -->
                        <div class="flex flex-wrap gap-1.5 mt-2">
                            <button
                                type="button"
                                @click="setPresetFeedback('إجابة نموذجية وممتازة، بارك الله فيك!')"
                                class="px-2 py-0.5 rounded-lg bg-surface-800 text-[10px] text-surface-400 hover:text-white"
                            >
                                إجابة نموذجية
                            </button>
                            <button
                                type="button"
                                @click="setPresetFeedback('عمل رائع مع بعض الملاحظات البسيطة.')"
                                class="px-2 py-0.5 rounded-lg bg-surface-800 text-[10px] text-surface-400 hover:text-white"
                            >
                                عمل رائع
                            </button>
                            <button
                                type="button"
                                @click="setPresetFeedback('يرجى مراجعة شرح الدرس وإعادة قراءة الخطوات بتأنٍ.')"
                                class="px-2 py-0.5 rounded-lg bg-surface-800 text-[10px] text-surface-400 hover:text-white"
                            >
                                بحاجة لمراجعة
                            </button>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-surface-800">
                        <button type="button" @click="closeGradingModal" class="btn-ghost btn-sm">إلغاء</button>
                        <button type="submit" :disabled="gradeForm.processing" class="btn-primary btn-sm font-bold">
                            حفظ ورصد التقييم
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </DashboardLayout>
</template>
