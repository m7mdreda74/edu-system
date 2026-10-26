<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
                                    <!-- Quiz Feedback -->
                                    <div v-if="row.kind === 'quiz' && row.attempt_id" class="min-w-[220px] space-y-2 text-start">
                                        <textarea
                                            :value="feedbackDraft(row)"
                                            class="input min-h-16 w-full text-xs"
                                            maxlength="1000"
                                            placeholder="ملاحظات للطالب"
                                            @input="feedbackDrafts[row.id] = $event.target.value"
                                        ></textarea>
                                        <button type="button" class="btn-primary btn-sm" @click="saveQuizFeedback(row)">حفظ الملاحظة</button>
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
        </div>
    </DashboardLayout>
</template>
