<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    assignments: { type: Array, default: () => [] },
    rows: { type: Object, default: () => ({ data: [], links: [] }) },
    summary: { type: Object, default: () => ({}) },
    selectedAssignment: { type: [Number, String], default: null },
});

const rowItems = computed(() => props.rows?.data ?? []);
const paginationLinks = computed(() => props.rows?.links ?? []);

function selectAssignment(event) {
    const assignment = event.target.value;
    router.get(route('teacher.gradebook'), assignment ? { assignment } : {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('ar-QA', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function scoreLabel(row) {
    if (row.earned === null || row.maximum === null) return 'بانتظار التصحيح';
    return `${row.earned} / ${row.maximum}`;
}

const feedbackDrafts = ref({});

function feedbackDraft(row) {
    if (!(row.id in feedbackDrafts.value)) {
        feedbackDrafts.value[row.id] = row.feedback || '';
    }

    return feedbackDrafts.value[row.id];
}

function saveQuizFeedback(row) {
    router.post(route('teacher.gradebook.quiz-feedback', { attemptId: row.attempt_id }), {
        teacher_feedback: feedbackDraft(row),
    }, { preserveScroll: true });
}

// ── Interactive Grading Modal for Worksheets / Paper Exams ──
const gradingModal = ref({
    isOpen: false,
    row: null,
});

const gradeForm = useForm({
    score: '',
    teacher_feedback: '',
});

function openGradingModal(row) {
    gradingModal.value.row = row;
    gradeForm.reset();
    gradeForm.clearErrors();
    gradeForm.score = row.earned !== null ? row.earned : '';
    gradeForm.teacher_feedback = row.feedback || '';
    gradingModal.value.isOpen = true;
}

function closeGradingModal() {
    gradingModal.value.isOpen = false;
    gradingModal.value.row = null;
    gradeForm.reset();
}

function submitGradingModal() {
    if (! gradingModal.value.row) return;
    const row = gradingModal.value.row;
    gradeForm.post(route('teacher.worksheets.grade', { submissionId: row.submission_id }), {
        preserveScroll: true,
        onSuccess: () => {
            closeGradingModal();
        },
    });
}

function applyFeedbackPreset(preset) {
    gradeForm.teacher_feedback = preset;
}

// ── Interactive Quiz Attempt Grading Modal (Essay & Review) ──
const quizGradingModal = ref({
    isOpen: false,
    loading: false,
    saving: false,
    row: null,
    attempt: null,
    questions: [],
    error: '',
});

const quizGradeForm = ref({
    scores: {},
    question_feedback: {},
    teacher_feedback: '',
});

async function openQuizGradingModal(row) {
    quizGradingModal.value.row = row;
    quizGradingModal.value.isOpen = true;
    quizGradingModal.value.loading = true;
    quizGradingModal.value.error = '';

    try {
        const res = await axios.get(route('teacher.gradebook.quiz-details', { attemptId: row.attempt_id }));
        quizGradingModal.value.attempt = res.data.attempt;
        quizGradingModal.value.questions = res.data.questions;

        const scores = {};
        const qFeedback = {};
        res.data.questions.forEach((q) => {
            scores[q.id] = q.earned_points !== null ? q.earned_points : (q.is_essay ? 0 : (q.student_answer?.earned_points ?? 0));
            qFeedback[q.id] = q.teacher_feedback || '';
        });

        quizGradeForm.value = {
            scores,
            question_feedback: qFeedback,
            teacher_feedback: res.data.attempt.teacher_feedback || '',
        };
    } catch (e) {
        quizGradingModal.value.error = 'فشل تحميل بيانات الاختبار، حاول مجدداً.';
    } finally {
        quizGradingModal.value.loading = false;
    }
}

function closeQuizGradingModal() {
    quizGradingModal.value.isOpen = false;
    quizGradingModal.value.row = null;
    quizGradingModal.value.attempt = null;
    quizGradingModal.value.questions = [];
}

const quizTotalEarned = computed(() => {
    if (! quizGradingModal.value.questions?.length) return 0;
    return quizGradingModal.value.questions.reduce((sum, q) => {
        const score = Number(quizGradeForm.value.scores[q.id] ?? 0);
        return sum + (isNaN(score) ? 0 : score);
    }, 0);
});

const quizTotalMax = computed(() => {
    if (! quizGradingModal.value.questions?.length) return 0;
    return quizGradingModal.value.questions.reduce((sum, q) => sum + Number(q.points || 0), 0);
});

const quizPercentage = computed(() => {
    if (! quizTotalMax.value) return 0;
    return Math.round((quizTotalEarned.value / quizTotalMax.value) * 100);
});

function submitQuizGrading() {
    if (quizGradingModal.value.saving) return;

    for (const q of quizGradingModal.value.questions) {
        const score = Number(quizGradeForm.value.scores[q.id] ?? 0);
        if (score < 0 || score > q.points) {
            alert(`درجة السؤال ${q.order} يجب أن تكون بين 0 و ${q.points} نقطة.`);
            return;
        }
    }

    quizGradingModal.value.saving = true;
    router.post(route('teacher.gradebook.quiz-grade', { attemptId: quizGradingModal.value.attempt.id }), {
        scores: quizGradeForm.value.scores,
        question_feedback: quizGradeForm.value.question_feedback,
        teacher_feedback: quizGradeForm.value.teacher_feedback,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            closeQuizGradingModal();
        },
        onFinish: () => {
            quizGradingModal.value.saving = false;
        },
    });
}

const statusLabels = {
    graded: 'تم التصحيح',
    needs_grading: 'يحتاج تصحيح',
};

const statusClasses = {
    graded: 'badge-green',
    needs_grading: 'badge-accent',
};
</script>

<template>
    <Head title="سجل الدرجات" />

    <DashboardLayout>
        <div class="space-y-7">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white">سجل الدرجات</h1>
                    <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">
                        نظرة موحّدة على الواجبات المسلّمة والاختبارات التي أجراها طلابك.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a
                        :href="route('teacher.gradebook.export', selectedAssignment ? { assignment: selectedAssignment } : {})"
                        class="btn-outline btn-sm flex items-center gap-1.5 whitespace-nowrap"
                        download
                    >
                        <Icon name="download" class="h-4 w-4" />
                        تصدير CSV
                    </a>
                    <label for="gradebook-assignment" class="sr-only">تصفية المادة</label>
                    <select
                        id="gradebook-assignment"
                        class="input text-sm"
                        :value="selectedAssignment || ''"
                        @change="selectAssignment"
                    >
                        <option value="">كل المواد والصفوف</option>
                        <option v-for="assignment in assignments" :key="assignment.id" :value="assignment.id">
                            {{ assignment.subject?.name }} — {{ assignment.grade?.name }}
                        </option>
                    </select>
                </div>
            </header>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                <div class="card p-4">
                    <span class="block text-xl font-black text-surface-900 dark:text-white">{{ summary.assessments ?? 0 }}</span>
                    <span class="text-[11px] text-surface-500">التقييمات</span>
                </div>
                <div class="card p-4">
                    <span class="block text-xl font-black text-surface-900 dark:text-white">{{ summary.students ?? 0 }}</span>
                    <span class="text-[11px] text-surface-500">الطلاب</span>
                </div>
                <div class="card p-4">
                    <span class="block text-xl font-black text-green-600">{{ summary.graded ?? 0 }}</span>
                    <span class="text-[11px] text-surface-500">تم التصحيح</span>
                </div>
                <div class="card p-4">
                    <span class="block text-xl font-black text-amber-600">{{ summary.needs_grading ?? 0 }}</span>
                    <span class="text-[11px] text-surface-500">تحتاج تصحيح</span>
                </div>
                <div class="card p-4 sm:col-span-2 lg:col-span-3">
                    <span class="block text-xl font-black text-primary-600">
                        {{ summary.overall_percentage === null || summary.overall_percentage === undefined ? '—' : `${summary.overall_percentage}%` }}
                    </span>
                    <span class="text-[11px] text-surface-500">النسبة من السجلات ذات الدرجة فقط</span>
                </div>
            </div>

            <section v-if="rowItems.length" class="card overflow-hidden">
                <div class="flex items-center gap-2 border-b border-surface-100 p-5 dark:border-surface-800">
                    <Icon name="chart" class="h-5 w-5 text-primary-500" />
                    <h2 class="font-black text-surface-900 dark:text-white">تفاصيل التقييمات</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-50 text-xs text-surface-500 dark:bg-surface-900/60 dark:text-surface-400">
                            <tr>
                                <th class="px-5 py-3 text-start font-bold">الطالب</th>
                                <th class="px-5 py-3 text-start font-bold">التقييم</th>
                                <th class="px-5 py-3 text-start font-bold">المادة</th>
                                <th class="px-5 py-3 text-start font-bold">الدرجة</th>
                                <th class="px-5 py-3 text-start font-bold">الحالة</th>
                                <th class="px-5 py-3 text-start font-bold">التاريخ</th>
                                <th class="px-5 py-3 text-end font-bold">إجراء</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                            <tr v-for="row in rowItems" :key="row.id" class="align-top">
                                <td class="px-5 py-4">
                                    <div class="font-bold text-surface-900 dark:text-white">{{ row.student?.name || 'طالب غير معروف' }}</div>
                                    <div class="mt-0.5 text-xs text-surface-400">{{ row.student?.email }}</div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="font-bold text-surface-900 dark:text-white">{{ row.title }}</div>
                                    <div class="flex items-center gap-2 mt-1 flex-wrap">
                                        <span class="inline-block text-[11px] text-surface-400">{{ row.kind_label }}</span>
                                        <a v-if="row.download_url" :href="row.download_url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-[11px] font-bold text-primary-600 dark:text-primary-400 hover:underline">
                                            <span>حل الطالب 📎</span>
                                            <span>↗</span>
                                        </a>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-surface-600 dark:text-surface-300">{{ row.subject?.name || '—' }}</td>
                                <td class="px-5 py-4">
                                    <div class="font-black text-surface-900 dark:text-white">{{ scoreLabel(row) }}</div>
                                    <div v-if="row.percentage !== null" class="mt-0.5 text-xs text-surface-500">{{ row.percentage }}%</div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span :class="statusClasses[row.status]" class="text-[10px]">{{ statusLabels[row.status] }}</span>
                                        <span v-if="row.is_late" class="rounded bg-red-500/10 px-1.5 py-0.5 text-[10px] font-bold text-red-500 border border-red-500/20">
                                            ⚠️ تسليم متأخر
                                        </span>
                                    </div>
                                    <p v-if="row.feedback" class="mt-2 max-w-xs text-xs text-surface-500">{{ row.feedback }}</p>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-xs text-surface-500">{{ formatDate(row.assessed_at) }}</td>
                                <td class="px-5 py-4 text-end">
                                    <!-- Quiz Feedback & Grading Action -->
                                    <div v-if="row.kind === 'quiz' && row.attempt_id" class="flex flex-col items-end gap-1.5 min-w-[150px]">
                                        <button
                                            type="button"
                                            @click="openQuizGradingModal(row)"
                                            class="btn-primary btn-sm flex items-center gap-1.5 font-bold w-full justify-center"
                                            :class="row.status === 'needs_grading' ? 'bg-amber-600 hover:bg-amber-700 text-white animate-pulse' : ''"
                                        >
                                            <Icon name="edit" class="w-3.5 h-3.5" />
                                            <span>{{ row.status === 'needs_grading' ? '✍️ تصحيح المقالي' : 'مراجعة وتعديل الدرجات' }}</span>
                                        </button>
                                        <div class="w-full flex items-center gap-1">
                                            <input
                                                :value="feedbackDraft(row)"
                                                type="text"
                                                class="input text-[11px] h-7 px-2 flex-1"
                                                placeholder="ملاحظة سريعة..."
                                                @input="feedbackDrafts[row.id] = $event.target.value"
                                            />
                                            <button
                                                type="button"
                                                class="btn-ghost btn-xs text-primary-600 h-7 px-1.5"
                                                title="حفظ الملاحظة"
                                                @click="saveQuizFeedback(row)"
                                            >
                                                حفظ
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Worksheet / Paper Exam Grading Action -->
                                    <div v-else-if="row.submission_id" class="flex flex-col items-end gap-1.5">
                                        <button
                                            type="button"
                                            @click="openGradingModal(row)"
                                            class="btn-primary btn-sm flex items-center gap-1.5 font-bold"
                                        >
                                            <Icon name="edit" class="w-3.5 h-3.5" />
                                            <span>{{ row.status === 'graded' ? 'تعديل الدرجة' : 'رصد الدرجة' }}</span>
                                        </button>
                                        <Link
                                            v-if="row.assignment_id"
                                            :href="route('teacher.curriculum', { assignment: row.assignment_id })"
                                            class="btn-ghost btn-xs text-surface-400 hover:text-surface-600"
                                        >
                                            فتح المنهج
                                        </Link>
                                    </div>

                                    <Link
                                        v-else-if="row.assignment_id"
                                        :href="route('teacher.curriculum', { assignment: row.assignment_id })"
                                        class="btn-ghost btn-sm"
                                    >
                                        فتح المنهج
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav
                    v-if="paginationLinks.length > 3"
                    class="flex flex-wrap items-center justify-center gap-1 border-t border-surface-100 p-4 dark:border-surface-800"
                    aria-label="تنقل سجل الدرجات"
                >
                    <template v-for="link in paginationLinks" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            :class="['btn-ghost btn-sm', link.active ? 'bg-primary-500/10 text-primary-600' : '']"
                            preserve-state
                            preserve-scroll
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="btn-sm cursor-not-allowed text-surface-300 dark:text-surface-600"
                            v-html="link.label"
                        />
                    </template>
                </nav>
            </section>

            <div v-else class="card p-10 text-center">
                <Icon name="chart" class="mx-auto mb-3 h-10 w-10 text-surface-300" />
                <h2 class="font-bold text-surface-800 dark:text-surface-100">لا توجد سجلات تقييم بعد</h2>
                <p class="mt-1 text-sm text-surface-400">
                    ستظهر هنا الواجبات المسلّمة والاختبارات المكتملة فقط؛ الواجب غير المسلّم لا يتحول تلقائيًا إلى صفر.
                </p>
            </div>

            <!-- ── Interactive Teacher Grading Modal (Qatar LMS Style) ── -->
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div v-if="gradingModal.isOpen && gradingModal.row" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
                    <div class="card p-6 w-full max-w-lg shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
                        <div class="flex items-center justify-between border-b border-surface-200 dark:border-surface-700 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="p-2 rounded-xl bg-primary-500/10 text-primary-600 dark:text-primary-400">
                                    <Icon name="edit" class="w-5 h-5" />
                                </span>
                                <div>
                                    <h2 class="text-lg font-black text-surface-900 dark:text-white">تصحيح ورصد الدرجة</h2>
                                    <p class="text-xs text-surface-500">{{ gradingModal.row.title }} ({{ gradingModal.row.kind_label }})</p>
                                </div>
                            </div>
                            <button type="button" @click="closeGradingModal" class="btn-ghost btn-sm p-1.5 text-surface-400 hover:text-surface-600">
                                <Icon name="close" class="w-5 h-5" />
                            </button>
                        </div>

                        <!-- Student Info Card -->
                        <div class="p-3.5 rounded-xl bg-surface-50 dark:bg-surface-850 border border-surface-200 dark:border-surface-800 space-y-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="font-bold text-sm text-surface-900 dark:text-white">{{ gradingModal.row.student?.name }}</div>
                                    <div class="text-xs text-surface-400">{{ gradingModal.row.student?.email }}</div>
                                </div>
                                <span v-if="gradingModal.row.is_late" class="rounded bg-red-500/10 px-2 py-0.5 text-xs font-bold text-red-500 border border-red-500/20">
                                    ⚠️ تسليم متأخر
                                </span>
                                <span v-else class="rounded bg-green-500/10 px-2 py-0.5 text-xs font-bold text-green-600 border border-green-500/20">
                                    تسليم في الموعد
                                </span>
                            </div>

                            <!-- Attached File -->
                            <div v-if="gradingModal.row.download_url" class="pt-2 border-t border-surface-200 dark:border-surface-700 flex items-center justify-between">
                                <span class="text-xs text-surface-500">الملف المرفق من الطالب:</span>
                                <a :href="gradingModal.row.download_url" target="_blank" rel="noopener noreferrer" class="btn-primary btn-xs py-1 px-2.5 rounded-lg flex items-center gap-1 font-bold">
                                    <Icon name="download" class="w-3.5 h-3.5" />
                                    <span>عرض وتحميل حل الطالب ↗</span>
                                </a>
                            </div>
                        </div>

                        <!-- Grading Form -->
                        <form @submit.prevent="submitGradingModal" class="space-y-4">
                            <div>
                                <label class="label mb-1 font-bold">
                                    رصد الدرجة (من 0 إلى {{ gradingModal.row.maximum || 100 }})
                                </label>
                                <input
                                    v-model.number="gradeForm.score"
                                    type="number"
                                    min="0"
                                    :max="gradingModal.row.maximum || 100"
                                    required
                                    class="input text-lg font-bold"
                                    :class="{ 'input-error': gradeForm.errors.score }"
                                    placeholder="أدخل درجة الطالب..."
                                />
                                <p v-if="gradeForm.errors.score" class="error-msg">{{ gradeForm.errors.score }}</p>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="label mb-0 font-bold">ملاحظات وتقييم المعلم للطالب</label>
                                    <span class="text-[11px] text-surface-400">تصل للطالب وولي أمره</span>
                                </div>
                                <textarea
                                    v-model="gradeForm.teacher_feedback"
                                    rows="3"
                                    maxlength="1000"
                                    class="input text-xs"
                                    :class="{ 'input-error': gradeForm.errors.teacher_feedback }"
                                    placeholder="أضف توجيهات للطالب أو إشادة بنقاط القوة..."
                                ></textarea>
                                <p v-if="gradeForm.errors.teacher_feedback" class="error-msg">{{ gradeForm.errors.teacher_feedback }}</p>

                                <!-- Quick Suggestions -->
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    <button
                                        type="button"
                                        @click="applyFeedbackPreset('ممتاز! إجابة نموذجية ومتقنة 🌟')"
                                        class="text-[11px] bg-surface-100 hover:bg-surface-200 dark:bg-surface-800 dark:hover:bg-surface-700 text-surface-700 dark:text-surface-300 rounded-lg px-2.5 py-1 transition-colors"
                                    >
                                        🌟 ممتاز ومتقن
                                    </button>
                                    <button
                                        type="button"
                                        @click="applyFeedbackPreset('عمل جيد، أحسنت مع مراعاة الملاحظات 👍')"
                                        class="text-[11px] bg-surface-100 hover:bg-surface-200 dark:bg-surface-800 dark:hover:bg-surface-700 text-surface-700 dark:text-surface-300 rounded-lg px-2.5 py-1 transition-colors"
                                    >
                                        👍 عمل جيد
                                    </button>
                                    <button
                                        type="button"
                                        @click="applyFeedbackPreset('يرجى مراجعة النقاط غير المكتملة وإعادة التدريب 💡')"
                                        class="text-[11px] bg-surface-100 hover:bg-surface-200 dark:bg-surface-800 dark:hover:bg-surface-700 text-surface-700 dark:text-surface-300 rounded-lg px-2.5 py-1 transition-colors"
                                    >
                                        💡 بحاجة لمراجعة
                                    </button>
                                </div>
                            </div>

                            <div class="flex gap-3 pt-2">
                                <button type="submit" :disabled="gradeForm.processing" class="btn-primary flex-1 py-2.5 font-bold">
                                    {{ gradeForm.processing ? 'جارٍ الاعتماد...' : 'اعتماد ورصد الدرجة' }}
                                </button>
                                <button type="button" @click="closeGradingModal" class="btn-ghost flex-1 py-2.5">
                                    إلغاء
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>

            <!-- Interactive Quiz Attempt Grading Modal (Essay & Review) -->
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="quizGradingModal.isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
                    <div class="bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-3xl max-w-4xl w-full max-h-[92vh] flex flex-col shadow-2xl overflow-hidden animate-scale-in">
                        <!-- Modal Header -->
                        <div class="px-6 py-4 border-b border-surface-200 dark:border-surface-800 flex items-center justify-between bg-surface-50/50 dark:bg-surface-900/50">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl bg-primary-100 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400 flex items-center justify-center font-bold text-lg shadow-sm">
                                    📝
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-black text-lg text-surface-900 dark:text-surface-100">
                                            تصحيح ومراجعة إجابات الاختبار
                                        </h3>
                                        <span
                                            v-if="quizGradingModal.attempt"
                                            :class="quizGradingModal.attempt.status === 'needs_grading'
                                                ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300'
                                                : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'"
                                            class="text-xs px-2.5 py-0.5 rounded-full font-bold"
                                        >
                                            {{ quizGradingModal.attempt.status === 'needs_grading' ? 'يحتاج تصحيح' : 'تم التصحيح' }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-surface-500 dark:text-surface-400 mt-0.5">
                                        الطالب: <span class="font-bold text-surface-700 dark:text-surface-200">{{ quizGradingModal.row?.student_name }}</span>
                                        • الاختبار: <span class="font-bold text-surface-700 dark:text-surface-200">{{ quizGradingModal.row?.assessment_title }}</span>
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="closeQuizGradingModal"
                                class="w-9 h-9 rounded-xl hover:bg-surface-100 dark:hover:bg-surface-800 text-surface-400 hover:text-surface-700 dark:hover:text-surface-200 flex items-center justify-center transition-colors"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <!-- Modal Body -->
                        <div class="flex-1 overflow-y-auto p-6 space-y-6">
                            <!-- Loading State -->
                            <div v-if="quizGradingModal.loading" class="py-16 text-center space-y-3">
                                <div class="w-10 h-10 border-4 border-primary-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
                                <p class="text-sm text-surface-500">جارٍ جلب إجابات الطالب والملفات المرفوعة...</p>
                            </div>

                            <!-- Error State -->
                            <div v-else-if="quizGradingModal.error" class="p-6 text-center bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900 rounded-2xl">
                                <p class="text-rose-600 dark:text-rose-400 text-sm font-bold">{{ quizGradingModal.error }}</p>
                                <button
                                    type="button"
                                    @click="openQuizGradingModal(quizGradingModal.row)"
                                    class="mt-3 px-4 py-1.5 text-xs rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700 transition"
                                >
                                    إعادة المحاولة
                                </button>
                            </div>

                            <!-- Main Content -->
                            <div v-else class="space-y-6">
                                <!-- Score & Status Live Bar -->
                                <div class="bg-gradient-to-r from-primary-50 via-surface-50 to-primary-50 dark:from-primary-950/30 dark:via-surface-900 dark:to-primary-950/30 border border-primary-200/60 dark:border-primary-900/50 rounded-2xl p-4 flex flex-wrap items-center justify-between gap-4 shadow-sm">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 rounded-2xl bg-primary-600 text-white flex items-center justify-center font-black text-xl shadow-md shadow-primary-500/20">
                                            {{ quizPercentage }}%
                                        </div>
                                        <div>
                                            <div class="text-xs text-surface-500 dark:text-surface-400 font-bold">الدرجة المحسوبة الحالية</div>
                                            <div class="text-lg font-black text-surface-900 dark:text-surface-100">
                                                {{ quizTotalEarned }} <span class="text-sm font-normal text-surface-400">/ {{ quizTotalMax }} نقطة</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <div class="text-right">
                                            <span class="text-xs text-surface-400 block">نسبة النجاح المطلوبة</span>
                                            <span class="text-xs font-bold text-surface-600 dark:text-surface-300">
                                                {{ quizGradingModal.attempt?.passing_score || 50 }}%
                                            </span>
                                        </div>
                                        <div
                                            :class="quizPercentage >= (quizGradingModal.attempt?.passing_score || 50)
                                                ? 'bg-emerald-500 text-white'
                                                : 'bg-rose-500 text-white'"
                                            class="px-3.5 py-1.5 rounded-xl font-black text-xs shadow-sm flex items-center gap-1.5"
                                        >
                                            <span>{{ quizPercentage >= (quizGradingModal.attempt?.passing_score || 50) ? 'ناجح' : 'راسب / لم يجتز' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Questions List -->
                                <div class="space-y-5">
                                    <div
                                        v-for="q in quizGradingModal.questions"
                                        :key="q.id"
                                        class="bg-surface-50/70 dark:bg-surface-800/40 border rounded-2xl p-5 transition-all space-y-4"
                                        :class="q.is_essay ? 'border-primary-300 dark:border-primary-800/60 shadow-sm' : 'border-surface-200 dark:border-surface-800'"
                                    >
                                        <!-- Question Header -->
                                        <div class="flex items-center justify-between flex-wrap gap-2 border-b border-surface-200/60 dark:border-surface-800 pb-3">
                                            <div class="flex items-center gap-2">
                                                <span class="w-7 h-7 rounded-xl bg-surface-200 dark:bg-surface-700 text-surface-800 dark:text-surface-200 font-black text-xs flex items-center justify-center">
                                                    {{ q.order }}
                                                </span>
                                                <span
                                                    v-if="q.is_essay"
                                                    class="text-xs px-2.5 py-0.5 rounded-lg font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 flex items-center gap-1"
                                                >
                                                    ✍️ سؤال مقالي حر
                                                </span>
                                                <span
                                                    v-else
                                                    class="text-xs px-2.5 py-0.5 rounded-lg font-bold bg-surface-200 text-surface-700 dark:bg-surface-700 dark:text-surface-300"
                                                >
                                                    {{ q.type === 'single' ? 'اختيار من متعدد' : (q.type === 'multiple' ? 'اختيارات متعددة' : 'صح أو خطأ') }}
                                                </span>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <span class="text-xs text-surface-500 font-bold">الدرجة القصوى:</span>
                                                <span class="text-xs font-black px-2 py-0.5 bg-primary-100 dark:bg-primary-950 text-primary-700 dark:text-primary-300 rounded-lg">
                                                    {{ q.points }} درجات
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Question Rich HTML Content (Formulation from Teacher) -->
                                        <div
                                            class="prose prose-sm dark:prose-invert max-w-none text-surface-900 dark:text-surface-100 leading-relaxed font-medium bg-surface-0 dark:bg-surface-900/60 p-4 rounded-xl border border-surface-200/50 dark:border-surface-800/80"
                                            v-html="q.question_text"
                                        ></div>

                                        <!-- Model Answer Box (If Teacher Provided) -->
                                        <div v-if="q.model_answer" class="bg-blue-50/60 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-900/40 rounded-xl p-3.5 space-y-1">
                                            <div class="text-[11px] font-black text-blue-700 dark:text-blue-300 flex items-center gap-1.5">
                                                <span>💡 نموذج الإجابة الاسترشادي / معايير التصحيح:</span>
                                            </div>
                                            <div
                                                class="prose prose-xs dark:prose-invert max-w-none text-xs text-blue-900 dark:text-blue-200"
                                                v-html="q.model_answer"
                                            ></div>
                                        </div>

                                        <!-- Student's Submission Section -->
                                        <div class="bg-surface-0 dark:bg-surface-900 p-4 rounded-xl border border-surface-200 dark:border-surface-800 space-y-3">
                                            <div class="text-xs font-black text-surface-700 dark:text-surface-300 flex items-center gap-1.5">
                                                <span>👤 إجابة الطالب:</span>
                                            </div>

                                            <!-- For Essay Questions -->
                                            <div v-if="q.is_essay" class="space-y-3">
                                                <!-- Written Text Answer -->
                                                <div v-if="q.student_answer && (q.student_answer.written_answer || q.student_answer.text)" class="p-3 bg-surface-50 dark:bg-surface-800/50 rounded-lg text-sm text-surface-800 dark:text-surface-200 whitespace-pre-line border border-surface-200/60 dark:border-surface-700/60">
                                                    {{ q.student_answer.written_answer || q.student_answer.text }}
                                                </div>
                                                <div v-else class="text-xs text-surface-400 italic">
                                                    لم يتم إدخال إجابة نصية مكتوبة.
                                                </div>

                                                <!-- Paper Solution Attachment (If uploaded) -->
                                                <div v-if="q.has_paper_file && q.file_download_url" class="flex items-center justify-between p-3 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900/40 rounded-xl">
                                                    <div class="flex items-center gap-2.5">
                                                        <span class="text-xl">📎</span>
                                                        <div>
                                                            <div class="text-xs font-bold text-emerald-900 dark:text-emerald-200">
                                                                {{ q.fileName || 'ملف الحل الورقي المرفوع من الطالب' }}
                                                            </div>
                                                            <span class="text-[11px] text-emerald-600 dark:text-emerald-400">ملف ورقي مرفوع للمراجعة والتصحيح</span>
                                                        </div>
                                                    </div>
                                                    <a
                                                        :href="q.file_download_url"
                                                        target="_blank"
                                                        download
                                                        class="px-3 py-1.5 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors flex items-center gap-1 shadow-sm"
                                                    >
                                                        <span>تحميل / معاينة الملف</span>
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                    </a>
                                                </div>
                                            </div>

                                            <!-- For Objective Questions (MCQ / True-False) -->
                                            <div v-else class="space-y-2">
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                                                    <div
                                                        v-for="opt in q.options"
                                                        :key="opt.id"
                                                        class="p-2.5 rounded-lg border flex items-center justify-between"
                                                        :class="[
                                                            opt.is_correct ? 'bg-emerald-50/80 dark:bg-emerald-950/20 border-emerald-300 dark:border-emerald-800 text-emerald-900 dark:text-emerald-200' : 'bg-surface-50 dark:bg-surface-800/40 border-surface-200 dark:border-surface-700 text-surface-600 dark:text-surface-400',
                                                            (Array.isArray(q.student_answer?.selected_option_ids) ? q.student_answer.selected_option_ids.includes(opt.id) : q.student_answer?.selected_option_id == opt.id) ? 'ring-2 ring-primary-500 font-bold' : ''
                                                        ]"
                                                    >
                                                        <span>{{ opt.text }}</span>
                                                        <span v-if="Array.isArray(q.student_answer?.selected_option_ids) ? q.student_answer.selected_option_ids.includes(opt.id) : q.student_answer?.selected_option_id == opt.id" class="text-[10px] px-1.5 py-0.5 rounded bg-primary-600 text-white">
                                                            اختيار الطالب
                                                        </span>
                                                        <span v-else-if="opt.is_correct" class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold">
                                                            ✓ الإجابة الصحيحة
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Grading Controls For this Question -->
                                        <div class="pt-2 border-t border-surface-200/60 dark:border-surface-800 grid grid-cols-1 sm:grid-cols-3 gap-3 items-center">
                                            <div>
                                                <label class="block text-xs font-bold text-surface-700 dark:text-surface-300 mb-1">
                                                    الدرجة المستحقة (من {{ q.points }}):
                                                </label>
                                                <div class="relative">
                                                    <input
                                                        type="number"
                                                        v-model.number="quizGradeForm.scores[q.id]"
                                                        min="0"
                                                        :max="q.points"
                                                        step="0.5"
                                                        class="input text-base font-black w-full"
                                                        :class="quizGradeForm.scores[q.id] > q.points ? 'border-rose-500 focus:border-rose-500' : ''"
                                                    />
                                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-surface-400">
                                                        / {{ q.points }}
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="sm:col-span-2">
                                                <label class="block text-xs font-bold text-surface-700 dark:text-surface-300 mb-1">
                                                    ملاحظات المعلم على هذا السؤال (اختياري):
                                                </label>
                                                <input
                                                    type="text"
                                                    v-model="quizGradeForm.question_feedback[q.id]"
                                                    placeholder="مثال: أحسنت في صياغة الفكرة الأولى، ركز على التعليل..."
                                                    class="input text-xs w-full"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Overall Teacher Feedback -->
                                <div class="bg-surface-50 dark:bg-surface-800/40 border border-surface-200 dark:border-surface-800 rounded-2xl p-4 space-y-2">
                                    <label class="block text-xs font-bold text-surface-800 dark:text-surface-200">
                                        💬 التقييم والملاحظة العامة للطالب (تظهر له ولولي أمره):
                                    </label>
                                    <textarea
                                        v-model="quizGradeForm.teacher_feedback"
                                        rows="3"
                                        placeholder="اكتب توجيهاتك أو تشجيعك للطالب بناءً على أدائه العام في الاختبار..."
                                        class="input text-sm w-full"
                                    ></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="px-6 py-4 border-t border-surface-200 dark:border-surface-800 flex items-center justify-between gap-4 bg-surface-50/50 dark:bg-surface-900/50">
                            <div class="text-sm font-bold text-surface-700 dark:text-surface-300">
                                المجموع الكلي: <span class="font-black text-primary-600 dark:text-primary-400 text-lg">{{ quizTotalEarned }}</span> / {{ quizTotalMax }} نقطة ({{ quizPercentage }}%)
                            </div>
                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    @click="closeQuizGradingModal"
                                    class="btn-ghost py-2.5 px-4 text-xs font-bold"
                                >
                                    إلغاء
                                </button>
                                <button
                                    type="button"
                                    @click="submitQuizGrading"
                                    :disabled="quizGradingModal.saving || quizGradingModal.loading"
                                    class="btn-primary py-2.5 px-6 text-xs font-bold flex items-center gap-2 shadow-lg shadow-primary-500/25"
                                >
                                    <svg v-if="quizGradingModal.saving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                    <span>{{ quizGradingModal.saving ? 'جارٍ الحفظ والاعتماد...' : 'اعتماد ورصد الدرجة وإشعار الطالب' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>
    </DashboardLayout>
</template>
