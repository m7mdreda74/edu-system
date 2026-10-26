<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { useConfirm } from '@/composables/useConfirm';
import { useClientPagination } from '@/composables/useClientPagination';

const props = defineProps({
    group:       { type: Object, required: true },
    worksheets:  { type: Array, required: true },
    lessons:     { type: Array, required: true },
    submissions: { type: Array, required: true },
});

const isModalOpen = ref(false);
const { confirm } = useConfirm();

const form = useForm({
    title: '',
    lesson_id: '',
    file: null,
    type: 'study', // study | homework
    requires_submission: false,
    due_date: '',
    max_score: '100',
    allow_late_submission: true,
    max_attempts: 1,
});

const gradeForm = useForm({
    score: '',
    teacher_feedback: '',
});

const activeTab = ref('sheets'); // sheets | submissions
const {
    data: paginatedSubmissions,
    pagination: submissionsPagination,
    setPage: setSubmissionsPage,
} = useClientPagination(computed(() => props.submissions));

function openAddModal() {
    form.reset();
    isModalOpen.value = true;
}

function handleFileUpload(e) {
    const file = e.target.files?.[0] ?? null;
    const allowedTypes = new Set([
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip',
        'image/png',
        'image/jpeg',
    ]);

    if (file && (!allowedTypes.has(file.type) || file.size > 10 * 1024 * 1024)) {
        form.file = null;
        e.target.value = '';
        form.setError('file', 'نوع الملف أو حجمه غير مسموح. الحد الأقصى 10 ميجابايت.');
        return;
    }

    form.file = file;
    form.clearErrors('file');
}

function submitWorksheet() {
    form.post(route('teacher.worksheets.store', { groupId: props.group.id }), {
        onSuccess: () => {
            isModalOpen.value = false;
            form.reset();
        }
    });
}

async function deleteWorksheet(id) {
    const ok = await confirm({
        title: 'حذف الشيت',
        message: 'هل أنت متأكد من حذف هذا الشيت؟ لا يمكن التراجع.',
        confirmLabel: 'حذف',
        variant: 'danger',
    });
    if (ok) router.delete(route('teacher.worksheets.destroy', { id }));
}

const gradingModal = ref({
    isOpen: false,
    submission: null,
});

function openGradingModal(sub) {
    gradingModal.value.submission = sub;
    gradeForm.reset();
    gradeForm.clearErrors();
    gradeForm.score = sub.score !== null ? sub.score : '';
    gradeForm.teacher_feedback = sub.teacher_feedback || '';
    gradingModal.value.isOpen = true;
}

function closeGradingModal() {
    gradingModal.value.isOpen = false;
    gradingModal.value.submission = null;
    gradeForm.reset();
}

function submitGradingModal() {
    if (! gradingModal.value.submission) return;
    const sub = gradingModal.value.submission;
    gradeForm.post(route('teacher.worksheets.grade', { submissionId: sub.id }), {
        preserveScroll: true,
        onSuccess: () => {
            closeGradingModal();
        },
    });
}

function applyFeedbackPreset(preset) {
    gradeForm.teacher_feedback = preset;
}
</script>

<template>
    <DashboardLayout>
        <Head :title="'إدارة أوراق العمل - ' + group.name" />

        <div class="dashboard-data-page">
            <!-- Header -->
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <Icon name="courses" class="w-8 h-8 text-primary-500" />
                        <span>أوراق العمل والواجبات</span>
                    </h1>
                    <p class="text-surface-500 mt-1">المجموعة: {{ group.name }} — {{ group.subject?.name }}</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('teacher.dashboard')" class="btn-ghost">← العودة للوحة</Link>
                    <button type="button" @click="openAddModal" class="btn-primary flex items-center gap-2">
                        <Icon name="plus" class="w-4 h-4" />
                        <span>رفع شيت/واجب جديد</span>
                    </button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-surface-200 dark:border-surface-800">
                <button type="button" @click="activeTab = 'sheets'"
                        class="px-6 py-3 font-bold text-sm border-b-2 transition-all"
                        :class="activeTab === 'sheets' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-surface-500 hover:text-surface-700'"
                >
                    الملفات المرفوعة ({{ worksheets.length }})
                </button>
                <button type="button" @click="activeTab = 'submissions'"
                        class="px-6 py-3 font-bold text-sm border-b-2 transition-all"
                        :class="activeTab === 'submissions' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-surface-500 hover:text-surface-700'"
                >
                    تسليمات الواجبات من الطلاب ({{ submissions.length }})
                </button>
            </div>

            <!-- Tab Content: Sheets List -->
            <div v-if="activeTab === 'sheets'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="sheet in worksheets" :key="sheet.id" class="card p-6 flex flex-col justify-between hover:shadow-lg transition-all duration-300">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <span :class="sheet.type === 'homework' ? 'badge-accent' : 'badge-primary'">
                                {{ sheet.type === 'homework' ? 'واجب منزلي' : 'شيت دراسي' }}
                            </span>
                            <span v-if="sheet.requires_submission" class="text-xs text-amber-600 bg-amber-50 dark:bg-amber-950/20 px-2 py-1 rounded">
                                يتطلب تسليم
                            </span>
                        </div>

                        <h3 class="text-lg font-bold text-surface-900 dark:text-white mb-2">{{ sheet.title }}</h3>
                        <p class="text-xs text-surface-400 mb-4">
                            الدرس المرتبط: {{ sheet.lesson ? sheet.lesson.title : 'عام للمجموعة' }}
                        </p>

                        <div class="text-xs text-surface-500 space-y-1 mb-6">
                            <div v-if="sheet.due_date">تاريخ التسليم: {{ new Date(sheet.due_date).toLocaleDateString('ar') }}</div>
                            <div v-if="sheet.max_score">الدرجة الكبرى: {{ sheet.max_score }}</div>
                        </div>
                    </div>

                    <div class="flex gap-2 border-t border-surface-100 dark:border-surface-800 pt-4">
                        <a :href="sheet.file_path" target="_blank" rel="noopener noreferrer" class="btn-outline btn-sm flex-1 flex items-center justify-center gap-1">
                            <span>تحميل الملف</span>
                        </a>
                        <button type="button" @click="deleteWorksheet(sheet.id)" class="btn-ghost btn-sm text-red-500 hover:bg-red-500/10 flex-1 flex items-center justify-center gap-1">
                            <Icon name="close" class="w-4 h-4" />
                            <span>حذف</span>
                        </button>
                    </div>
                </div>

                <div v-if="worksheets.length === 0" class="col-span-full card p-16 text-center text-surface-400">
                    <Icon name="courses" class="w-16 h-16 mx-auto text-surface-300 dark:text-surface-700 mb-4" />
                    <h3 class="text-lg font-bold text-surface-800 dark:text-surface-200 mb-2">لا توجد أوراق عمل</h3>
                    <p class="text-sm">ابدأ برفع أول شيت دراسي أو واجب منزلي لطلابك</p>
                </div>
            </div>

            <!-- Tab Content: Submissions List -->
            <div v-if="activeTab === 'submissions'" class="card data-table-card">
                <div class="data-table-scroll no-scrollbar">
                    <table class="data-table">
                        <thead class="bg-surface-50 dark:bg-surface-800 border-b border-surface-200 dark:border-surface-700">
                            <tr>
                                <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">الطالب</th>
                                <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">الواجب</th>
                                <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">تاريخ التسليم</th>
                                <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">الدرجة</th>
                                <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">التعليق والتقييم</th>
                                <th class="data-table-actions text-center px-6 py-4 font-bold text-surface-700 dark:text-surface-300">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                            <tr v-for="sub in paginatedSubmissions" :key="sub.id" class="hover:bg-surface-50/50 dark:hover:bg-surface-800/20">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-surface-900 dark:text-white">{{ sub.student?.name }}</div>
                                    <div class="text-xs text-surface-400">{{ sub.student?.email }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-surface-800 dark:text-surface-200">{{ sub.worksheet?.title }}</div>
                                    <a :href="sub.submitted_file_path" target="_blank" rel="noopener noreferrer" class="text-xs text-primary-500 hover:underline">عرض حل الطالب 🔗</a>
                                </td>
                                <td class="px-6 py-4 text-xs text-surface-500">
                                    <div>{{ new Date(sub.submitted_at).toLocaleString('ar-EG', { dateStyle: 'medium', timeStyle: 'short' }) }}</div>
                                    <span v-if="sub.is_late" class="inline-flex items-center gap-1 rounded bg-red-500/10 px-1.5 py-0.5 text-[10px] font-bold text-red-500 border border-red-500/20 mt-1">
                                        ⚠️ تسليم متأخر
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-semibold">
                                    <span v-if="sub.score !== null" class="text-green-600 dark:text-green-400">
                                        {{ sub.score }} / {{ sub.worksheet?.max_score }}
                                    </span>
                                    <span v-else class="text-amber-500">لم يصحح بعد</span>
                                </td>
                                <td class="px-6 py-4 text-xs text-surface-500 max-w-xs truncate" :title="sub.teacher_feedback">
                                    {{ sub.teacher_feedback || '—' }}
                                </td>
                                <td class="data-table-actions px-6 py-4 text-center">
                                    <button type="button" @click="openGradingModal(sub)"
                                            class="btn-primary btn-xs py-1.5 px-3 rounded-lg flex items-center gap-1.5 mx-auto"
                                    >
                                        <Icon name="edit" class="w-3.5 h-3.5" />
                                        <span>{{ sub.score !== null ? 'تعديل الدرجة' : 'رصد الدرجة' }}</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="submissions.length === 0" class="flex-1 p-16 text-center text-surface-400">
                    <Icon name="courses" class="w-16 h-16 mx-auto text-surface-300 dark:text-surface-700 mb-4" />
                    <h3 class="text-lg font-bold text-surface-800 dark:text-surface-200 mb-2">لا توجد تسليمات</h3>
                    <p class="text-sm">لم يقم الطلاب برفع أي حلول بعد</p>
                </div>
                <DataTablePagination
                    :paginator="submissionsPagination"
                    item-label="تسليم"
                    @page-change="setSubmissionsPage"
                />
            </div>

            <!-- Upload Modal -->
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div v-if="isModalOpen" class="modal-overlay z-50 bg-black/55 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label="رفع شيت أو واجب">
                    <div class="modal-panel-compact bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-3xl w-full max-w-lg p-6 shadow-2xl relative" dir="rtl">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-black text-surface-900 dark:text-white">رفع شيت أو واجب جديد</h3>
                            <button type="button" @click="isModalOpen = false" class="btn-ghost p-1 rounded-full" aria-label="إغلاق النافذة">
                                <Icon name="close" class="w-5 h-5 text-surface-500" />
                            </button>
                        </div>

                        <form @submit.prevent="submitWorksheet" class="space-y-4">
                            <div>
                                <label class="label mb-1">العنوان</label>
                                <input v-model="form.title" type="text" minlength="3" maxlength="255" required class="input" placeholder="مثال: واجب الدرس الأول - المصفوفات" />
                            </div>

                            <div>
                                <label class="label mb-1">الدرس المرتبط (اختياري)</label>
                                <select v-model="form.lesson_id" class="input">
                                    <option value="">عام للمجموعة كاملة</option>
                                    <option v-for="les in lessons" :key="les.id" :value="les.id">{{ les.title }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="label mb-1">نوع أوراق العمل</label>
                                <select v-model="form.type" required class="input">
                                    <option value="study">شيت دراسي/ملخص (للقراءة فقط)</option>
                                    <option value="homework">واجب منزلي (يتطلب حل من الطالب)</option>
                                </select>
                            </div>

                            <div v-if="form.type === 'homework'" class="flex items-center gap-2 pt-2">
                                <input v-model="form.requires_submission" type="checkbox" id="homework-require" class="rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                                <label for="homework-require" class="text-sm font-semibold text-surface-700 dark:text-surface-300">يتطلب من الطالب رفع ملف الحل</label>
                            </div>

                            <div v-if="form.type === 'homework' && form.requires_submission" class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="label mb-1">آخر موعد للتسليم</label>
                                        <input v-model="form.due_date" type="date" class="input" />
                                    </div>
                                    <div>
                                        <label class="label mb-1">الدرجة الكبرى</label>
                                        <input v-model="form.max_score" type="number" min="1" max="1000" required class="input" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4 items-center bg-surface-50 dark:bg-surface-850 p-3 rounded-xl border border-surface-200 dark:border-surface-800">
                                    <div>
                                        <label class="label mb-1">عدد المحاولات المسموحة</label>
                                        <input v-model="form.max_attempts" type="number" min="1" max="10" class="input" />
                                    </div>
                                    <div class="flex items-center gap-2 pt-4">
                                        <input v-model="form.allow_late_submission" type="checkbox" id="hw-allow-late" class="rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                                        <label for="hw-allow-late" class="text-xs font-bold text-surface-700 dark:text-surface-300">السماح بتسليم متأخر</label>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="label mb-1">ملف الشيت (PDF/Word/صورة)</label>
                                <input type="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.png,.jpg,.jpeg,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/zip,image/png,image/jpeg" required @change="handleFileUpload" class="input py-2" />
                            </div>

                            <div class="flex gap-3 pt-4">
                                <button type="submit" :disabled="form.processing" class="btn-primary flex-1">رفع الملف</button>
                                <button type="button" @click="isModalOpen = false" class="btn-ghost flex-1">إلغاء</button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>

            <!-- ── Interactive Teacher Grading Modal (Qatar LMS Style) ── -->
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div v-if="gradingModal.isOpen && gradingModal.submission" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
                    <div class="card p-6 w-full max-w-lg shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
                        <div class="flex items-center justify-between border-b border-surface-200 dark:border-surface-700 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="p-2 rounded-xl bg-primary-500/10 text-primary-600 dark:text-primary-400">
                                    <Icon name="edit" class="w-5 h-5" />
                                </span>
                                <div>
                                    <h2 class="text-lg font-black text-surface-900 dark:text-white">تصحيح ورصد الدرجة</h2>
                                    <p class="text-xs text-surface-500">{{ gradingModal.submission.worksheet?.title }}</p>
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
                                    <div class="font-bold text-sm text-surface-900 dark:text-white">{{ gradingModal.submission.student?.name }}</div>
                                    <div class="text-xs text-surface-400">{{ gradingModal.submission.student?.email }}</div>
                                </div>
                                <span v-if="gradingModal.submission.is_late" class="rounded bg-red-500/10 px-2 py-0.5 text-xs font-bold text-red-500 border border-red-500/20">
                                    ⚠️ تسليم متأخر
                                </span>
                                <span v-else class="rounded bg-green-500/10 px-2 py-0.5 text-xs font-bold text-green-600 border border-green-500/20">
                                    تسليم في الموعد
                                </span>
                            </div>

                            <!-- Attached File -->
                            <div v-if="gradingModal.submission.submitted_file_path" class="pt-2 border-t border-surface-200 dark:border-surface-700 flex items-center justify-between">
                                <span class="text-xs text-surface-500">الملف المرفق من الطالب:</span>
                                <a :href="gradingModal.submission.submitted_file_path" target="_blank" rel="noopener noreferrer" class="btn-primary btn-xs py-1 px-2.5 rounded-lg flex items-center gap-1 font-bold">
                                    <Icon name="download" class="w-3.5 h-3.5" />
                                    <span>عرض وتحميل حل الطالب ↗</span>
                                </a>
                            </div>
                        </div>

                        <!-- Grading Form -->
                        <form @submit.prevent="submitGradingModal" class="space-y-4">
                            <div>
                                <label class="label mb-1 font-bold">
                                    رصد الدرجة (من 0 إلى {{ gradingModal.submission.worksheet?.max_score || 100 }})
                                </label>
                                <input
                                    v-model.number="gradeForm.score"
                                    type="number"
                                    min="0"
                                    :max="gradingModal.submission.worksheet?.max_score || 100"
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

