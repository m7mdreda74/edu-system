<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    questions: { type: Object, default: () => ({ data: [], links: [], total: 0, meta: {} }) },
    filters: { type: Object, default: () => ({}) },
    subjects: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
    stages: { type: Array, default: () => [] },
    grades: { type: Array, default: () => [] },
    terms: { type: Array, default: () => [] },
});

const MIN_OPTIONS = 2;
const MAX_OPTIONS = 6;
const { confirm } = useConfirm();
const showForm = ref(false);
const editingId = ref(null);
const search = ref(props.filters.search ?? '');
const difficultyFilter = ref(props.filters.difficulty ?? '');
const typeFilter = ref(props.filters.type ?? '');
const visibilityFilter = ref(props.filters.visibility ?? '');

// Academic Classification Form State (Stage, Grade, Term)
const formStage = ref('');
const formGrade = ref('');
const formTerm = ref('');

const difficultyLabels = {
    easy: 'سهل',
    medium: 'متوسط',
    hard: 'متقدم',
};

const typeLabels = {
    single: 'اختيار واحد',
    multiple: 'اختيارات متعددة',
    true_false: 'صواب / خطأ',
    essay: 'سؤال مقالي / تحريري',
};

const visibilityLabels = {
    private: 'خاص بي',
    shared: 'مشترك مع المعلمين',
};

function makeOption(text = '', isCorrect = false) {
    return { option_text: text, is_correct: isCorrect };
}

function blankForm() {
    return {
        subject_id: '',
        curriculum_unit_id: '',
        lesson_id: '',
        topic: '',
        difficulty: 'medium',
        visibility: 'private',
        question_text: '',
        type: 'single',
        points: 1,
        allow_file_upload: false,
        model_answer: '',
        explanation: '',
        tags: '',
        options: [makeOption(), makeOption()],
    };
}

const form = useForm(blankForm());

form.transform((data) => ({
    ...data,
    subject_id: data.subject_id || null,
    curriculum_unit_id: data.curriculum_unit_id || null,
    lesson_id: data.lesson_id || null,
    tags: String(data.tags || '')
        .split(',')
        .map((tag) => tag.trim())
        .filter(Boolean),
}));

// Filter grades based on selected stage
const availableGrades = computed(() => {
    if (! formStage.value) return props.grades;
    return props.grades.filter((g) => g.stage === formStage.value);
});

// Filter curriculum units by stage, grade, term, and subject
const filteredUnits = computed(() => {
    return props.units.filter((unit) => {
        if (formStage.value && unit.stage !== formStage.value) return false;
        if (formGrade.value && Number(unit.grade_level_id) !== Number(formGrade.value)) return false;
        if (formTerm.value && Number(unit.academic_term_id) !== Number(formTerm.value)) return false;
        if (form.subject_id && Number(unit.subject?.id) !== Number(form.subject_id)) return false;
        return true;
    });
});

const selectedUnit = computed(() => props.units.find(
    (unit) => Number(unit.id) === Number(form.curriculum_unit_id),
));

const availableLessons = computed(() => selectedUnit.value?.lessons ?? []);

// Dynamic academic context summary names
const currentStageName = computed(() => {
    if (selectedUnit.value?.stage_name && selectedUnit.value.stage_name !== 'غير محدد') {
        return selectedUnit.value.stage_name;
    }
    const match = props.stages.find((s) => s.key === formStage.value);
    return match?.name || '';
});

const currentGradeName = computed(() => {
    if (selectedUnit.value?.grade_name && selectedUnit.value.grade_name !== 'غير محدد') {
        return selectedUnit.value.grade_name;
    }
    const match = props.grades.find((g) => Number(g.id) === Number(formGrade.value));
    return match?.name || '';
});

const currentTermName = computed(() => {
    if (selectedUnit.value?.term_name && selectedUnit.value.term_name !== 'غير محدد') {
        return selectedUnit.value.term_name;
    }
    const match = props.terms.find((t) => Number(t.id) === Number(formTerm.value));
    return match?.name || '';
});

const hasAcademicContext = computed(() => {
    return Boolean(currentStageName.value || currentGradeName.value || currentTermName.value);
});

function clearAcademicFilters() {
    formStage.value = '';
    formGrade.value = '';
    formTerm.value = '';
}

watch(() => formStage.value, (newStage) => {
    if (newStage && formGrade.value) {
        const gradeStillAvailable = availableGrades.value.some((g) => Number(g.id) === Number(formGrade.value));
        if (! gradeStillAvailable) {
            formGrade.value = '';
        }
    }
});

watch(() => form.curriculum_unit_id, (newUnitId) => {
    if (newUnitId) {
        const unit = props.units.find((u) => Number(u.id) === Number(newUnitId));
        if (unit) {
            if (unit.stage) formStage.value = unit.stage;
            if (unit.grade_level_id) formGrade.value = unit.grade_level_id;
            if (unit.academic_term_id) formTerm.value = unit.academic_term_id;
            if (unit.subject?.id && ! form.subject_id) form.subject_id = unit.subject.id;
        }
    }

    if (form.lesson_id && ! availableLessons.value.some((lesson) => Number(lesson.id) === Number(form.lesson_id))) {
        form.lesson_id = '';
    }
});

watch(() => props.filters, (next) => {
    search.value = next.search ?? '';
    difficultyFilter.value = next.difficulty ?? '';
    typeFilter.value = next.type ?? '';
    visibilityFilter.value = next.visibility ?? '';
});

function resetForm() {
    editingId.value = null;
    formStage.value = '';
    formGrade.value = '';
    formTerm.value = '';
    form.reset();
    form.clearErrors();
}

function openCreate() {
    resetForm();
    showForm.value = true;
}

function editQuestion(question) {
    editingId.value = question.id;
    form.subject_id = question.subject?.id ?? '';
    form.curriculum_unit_id = question.unit?.id ?? '';
    form.lesson_id = question.lesson?.id ?? '';
    form.topic = question.topic ?? '';
    form.difficulty = question.difficulty ?? 'medium';
    form.visibility = question.visibility ?? 'private';
    form.question_text = question.question_text ?? '';
    form.type = question.type ?? 'single';
    form.points = question.points ?? 1;
    form.allow_file_upload = Boolean(question.allow_file_upload);
    form.model_answer = question.model_answer ?? '';
    form.explanation = question.explanation ?? '';
    form.tags = (question.tags ?? []).join(', ');
    form.options = (question.options ?? []).map((option) => makeOption(option.option_text, !!option.is_correct));

    // Populate stage, grade, and term from unit or question
    if (question.unit?.id) {
        const u = props.units.find((unit) => Number(unit.id) === Number(question.unit.id));
        if (u) {
            formStage.value = u.stage || '';
            formGrade.value = u.grade_level_id || '';
            formTerm.value = u.academic_term_id || '';
        }
    }

    form.clearErrors();
    showForm.value = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function submit() {
    if (form.processing || problemOf()) return;

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            resetForm();
            showForm.value = false;
        },
    };

    if (editingId.value) {
        form.put(route('teacher.question-bank.update', editingId.value), options);
    } else {
        form.post(route('teacher.question-bank.store'), options);
    }
}

function cancelEdit() {
    resetForm();
    showForm.value = false;
}

function addOption() {
    if (form.options.length < MAX_OPTIONS) form.options.push(makeOption());
}

function removeOption(index) {
    if (form.options.length > MIN_OPTIONS) form.options.splice(index, 1);
}

function markCorrect(index) {
    form.options.forEach((opt, idx) => {
        opt.is_correct = idx === index;
    });
}

function changeType(newType) {
    form.type = newType;
    if (newType === 'true_false') {
        form.options = [
            makeOption('صواب', true),
            makeOption('خطأ', false),
        ];
    } else if (newType === 'single') {
        const alreadyCorrect = form.options.findIndex((opt) => opt.is_correct);
        form.options.forEach((opt, idx) => {
            opt.is_correct = idx === (alreadyCorrect !== -1 ? alreadyCorrect : 0);
        });
    }
}

function correctCount() {
    return form.options.filter((option) => option.is_correct).length;
}

function problemOf() {
    const textPlain = form.question_text.replace(/<[^>]+>/g, '').trim();
    if (! textPlain && ! form.question_text.trim()) return 'اكتب نص السؤال أولاً.';
    if (Number(form.points) < 1) return 'حدد نقطة واحدة على الأقل للسؤال.';

    if (form.type === 'essay') {
        return null;
    }

    if (form.options.length < MIN_OPTIONS) return 'أضف إجابتين على الأقل.';
    if (form.options.some((option) => ! option.option_text.trim())) return 'اكتب نص كل إجابة.';
    if (correctCount() === 0) return 'حدد إجابة صحيحة واحدة على الأقل.';
    if ((form.type === 'single' || form.type === 'true_false') && correctCount() > 1) return 'هذا السؤال يقبل إجابة صحيحة واحدة فقط.';

    return null;
}

function applyFilters() {
    router.get(route('teacher.question-bank'), {
        search: search.value || undefined,
        difficulty: difficultyFilter.value || undefined,
        type: typeFilter.value || undefined,
        visibility: visibilityFilter.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

async function deleteQuestion(question) {
    const ok = await confirm({
        title: 'حذف السؤال',
        message: 'سيتم حذف السؤال من بنكك. النسخ الموجودة داخل الاختبارات ستبقى محفوظة.',
        confirmLabel: 'حذف',
        variant: 'danger',
    });

    if (ok) router.delete(route('teacher.question-bank.destroy', question.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="بنك الأسئلة" />

    <DashboardLayout>
        <div class="space-y-6 pb-12">
            <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white">بنك الأسئلة</h1>
                    <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">
                        احفظ الأسئلة وصنفها حسب المرحلة والصف والفصل الدراسي، ثم انسخها إلى أي اختبار تملكه.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('teacher.dashboard')" class="btn-outline btn-sm">العودة للوحة المعلم</Link>
                    <button type="button" class="btn-primary btn-sm" @click="openCreate">
                        <Icon name="plus" class="h-4 w-4" />
                        <span>سؤال جديد</span>
                    </button>
                </div>
            </header>

            <section v-if="showForm" class="card space-y-5 border-primary-200 p-5 dark:border-primary-900">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-black text-surface-900 dark:text-white">{{ editingId ? 'تعديل سؤال محفوظ' : 'إضافة سؤال جديد إلى البنك' }}</h2>
                        <p class="mt-1 text-xs text-surface-400">حدد المرحلة والصف والفصل الدراسي لربط السؤال وتسهيل استرجاعه في الاختبارات.</p>
                    </div>
                    <button type="button" class="btn-ghost btn-sm" @click="cancelEdit">إلغاء</button>
                </div>

                <!-- ── Academic Classification Selector (Stage, Grade, Term) ──────── -->
                <div class="rounded-2xl border border-primary-100 bg-gradient-to-br from-primary-50/50 via-white to-surface-50 p-4 dark:border-primary-900/50 dark:from-primary-950/20 dark:via-surface-900 dark:to-surface-800/40 space-y-3">
                    <div class="flex items-center justify-between gap-2 border-b border-primary-100/70 pb-2 dark:border-primary-800/50">
                        <div class="flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-primary-600 text-white text-xs font-black shadow-sm">
                                🏫
                            </span>
                            <span class="text-xs font-black text-surface-900 dark:text-white">التصنيف الأكاديمي (المرحلة والصف والفصل الدراسي)</span>
                        </div>
                        <button
                            v-if="formStage || formGrade || formTerm"
                            type="button"
                            class="text-[11px] font-bold text-primary-600 hover:underline dark:text-primary-400"
                            @click="clearAcademicFilters"
                        >
                            إلغاء تحديد التصنيف
                        </button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <!-- 1. Stage (المرحلة الدراسية) -->
                        <div>
                            <label class="input-label text-xs flex items-center gap-1 font-bold">
                                <span>المرحلة الدراسية</span>
                            </label>
                            <select v-model="formStage" class="input">
                                <option value="">كل المراحل الدراسية</option>
                                <option v-for="stg in stages" :key="stg.key" :value="stg.key">{{ stg.name }}</option>
                            </select>
                        </div>

                        <!-- 2. Grade (الصف الدراسي) -->
                        <div>
                            <label class="input-label text-xs flex items-center gap-1 font-bold">
                                <span>الصف الدراسي</span>
                            </label>
                            <select v-model="formGrade" class="input">
                                <option value="">كل الصفوف الدراسية</option>
                                <option v-for="grd in availableGrades" :key="grd.id" :value="grd.id">{{ grd.name }}</option>
                            </select>
                        </div>

                        <!-- 3. Term (الفصل الدراسي) -->
                        <div>
                            <label class="input-label text-xs flex items-center gap-1 font-bold">
                                <span>الفصل الدراسي</span>
                            </label>
                            <select v-model="formTerm" class="input">
                                <option value="">كل الفصول الدراسية</option>
                                <option v-for="trm in terms" :key="trm.id" :value="trm.id">{{ trm.name }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Prominent Academic Details Banner -->
                    <div v-if="hasAcademicContext" class="flex flex-wrap items-center gap-2 pt-1 text-xs">
                        <span class="font-bold text-surface-500 text-[11px]">التصنيف النشط للسؤال:</span>
                        <span v-if="currentStageName" class="inline-flex items-center gap-1 rounded-lg bg-amber-50 px-2.5 py-1 font-bold text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800 shadow-2xs">
                            🏫 المرحلة: {{ currentStageName }}
                        </span>
                        <span v-if="currentGradeName" class="inline-flex items-center gap-1 rounded-lg bg-blue-50 px-2.5 py-1 font-bold text-blue-800 dark:bg-blue-950/40 dark:text-blue-300 border border-blue-200 dark:border-blue-800 shadow-2xs">
                            📚 الصف: {{ currentGradeName }}
                        </span>
                        <span v-if="currentTermName" class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2.5 py-1 font-bold text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 shadow-2xs">
                            🗓️ الفصل: {{ currentTermName }}
                        </span>
                    </div>
                </div>

                <!-- ── Subject, Unit, Lesson & Metadata ────────────────────── -->
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="input-label">المادة</label>
                        <select v-model="form.subject_id" class="input" :class="{ 'input-error': form.errors.subject_id }">
                            <option value="">بدون تصنيف مادة</option>
                            <option v-for="subject in subjects" :key="subject.id" :value="subject.id">{{ subject.name }}</option>
                        </select>
                        <p v-if="form.errors.subject_id" class="error-msg">{{ form.errors.subject_id }}</p>
                    </div>

                    <div>
                        <label class="input-label">الوحدة الدراسية</label>
                        <select v-model="form.curriculum_unit_id" class="input" :class="{ 'input-error': form.errors.curriculum_unit_id }">
                            <option value="">بدون ربط بوحدة</option>
                            <option v-for="unit in filteredUnits" :key="unit.id" :value="unit.id">
                                {{ unit.title }} — {{ unit.grade_name }} · {{ unit.term_name }} ({{ unit.stage_name }})
                            </option>
                        </select>
                        <p v-if="form.errors.curriculum_unit_id" class="error-msg">{{ form.errors.curriculum_unit_id }}</p>
                    </div>

                    <div>
                        <label class="input-label">الدرس</label>
                        <select v-model="form.lesson_id" class="input" :disabled="! availableLessons.length" :class="{ 'input-error': form.errors.lesson_id }">
                            <option value="">بدون ربط بدرس</option>
                            <option v-for="lesson in availableLessons" :key="lesson.id" :value="lesson.id">{{ lesson.title }}</option>
                        </select>
                        <p v-if="form.errors.lesson_id" class="error-msg">{{ form.errors.lesson_id }}</p>
                    </div>

                    <div>
                        <label class="input-label">الموضوع</label>
                        <input v-model="form.topic" type="text" class="input" maxlength="160" placeholder="مثال: قوانين نيوتن" />
                    </div>

                    <div>
                        <label class="input-label">مستوى الصعوبة</label>
                        <select v-model="form.difficulty" class="input">
                            <option value="easy">سهل</option>
                            <option value="medium">متوسط</option>
                            <option value="hard">متقدم</option>
                        </select>
                    </div>

                    <div>
                        <label class="input-label">الظهور</label>
                        <select v-model="form.visibility" class="input">
                            <option value="private">خاص بي</option>
                            <option value="shared">مشترك مع المعلمين</option>
                        </select>
                    </div>

                    <div>
                        <label class="input-label">النقاط</label>
                        <input v-model.number="form.points" type="number" min="1" max="100" class="input" />
                    </div>

                    <div class="sm:col-span-2">
                        <label class="input-label">الوسوم (مفصولة بفاصلة)</label>
                        <input v-model="form.tags" type="text" class="input" placeholder="سهل، مراجعة نهائية، اختبار قصير" />
                    </div>
                </div>

                <div>
                    <RichTextEditor
                        v-model="form.question_text"
                        label="نص السؤال (محرر نصي متقدم بتنسيقات Word)"
                        placeholder="اكتب نص السؤال ونسقه بالخطوط، الألوان، التظليل، الجداول، والقوائم..."
                    />
                    <p v-if="form.errors.question_text" class="error-msg">{{ form.errors.question_text }}</p>
                </div>

                <div class="space-y-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="input-label mb-0">نوع السؤال:</label>
                        <button type="button" class="btn-sm rounded-lg border px-3" :class="form.type === 'single' ? 'border-primary-500 bg-primary-50 text-primary-700 font-bold' : 'border-surface-200 text-surface-500'" @click="changeType('single')">إجابة واحدة</button>
                        <button type="button" class="btn-sm rounded-lg border px-3" :class="form.type === 'multiple' ? 'border-primary-500 bg-primary-50 text-primary-700 font-bold' : 'border-surface-200 text-surface-500'" @click="changeType('multiple')">إجابات متعددة</button>
                        <button type="button" class="btn-sm rounded-lg border px-3" :class="form.type === 'true_false' ? 'border-primary-500 bg-primary-50 text-primary-700 font-bold' : 'border-surface-200 text-surface-500'" @click="changeType('true_false')">صواب / خطأ</button>
                        <button type="button" class="btn-sm rounded-lg border px-3" :class="form.type === 'essay' ? 'border-primary-500 bg-primary-50 text-primary-700 font-bold' : 'border-surface-200 text-surface-500'" @click="changeType('essay')">سؤال مقالي / تحريري 📝</button>
                    </div>

                    <!-- Essay Specific Settings -->
                    <div v-if="form.type === 'essay'" class="space-y-4 rounded-xl border border-primary-200 bg-primary-50/20 p-4 dark:border-primary-900/50 dark:bg-primary-950/10">
                        <div class="flex items-center gap-3">
                            <input id="allow-file-upload-bank" v-model="form.allow_file_upload" type="checkbox" class="h-4 w-4 rounded text-primary-600 focus:ring-primary-500" />
                            <label for="allow-file-upload-bank" class="text-sm font-bold text-surface-800 dark:text-surface-100 cursor-pointer">
                                إتاحة رفع ملف إجابة ورقية من الطالب (صور حلول دفترية أو PDF) 📎
                            </label>
                        </div>
                        <p class="text-xs text-surface-400 ms-7">
                            سيتمكن الطالب أثناء الاختبار من تصوير إجابته الورقية ورفعها مع إمكانية كتابة نص الإجابة في المحرر.
                        </p>

                        <div class="flex items-start gap-2.5 p-3 rounded-xl bg-emerald-50/80 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/60 text-xs text-emerald-900 dark:text-emerald-200">
                            <span class="text-base flex-shrink-0">🖼️</span>
                            <div>
                                <strong class="block text-emerald-950 dark:text-emerald-100 font-bold mb-0.5">إدراج الصور والرسومات الهندسية والبيانية:</strong>
                                <span>يمكنك إدراج صور توضيحية أو مسائل مصورة في نص السؤال المقالي أو في نموذج الإجابة عبر زر «صورة 🖼️» في شريط أدوات المحرر أو بلصقها مباشرة من الحافظة (Ctrl+V).</span>
                            </div>
                        </div>

                        <div class="mt-4">
                            <RichTextEditor
                                v-model="form.model_answer"
                                label="نموذج الإجابة الاسترشادي ومعايير التصحيح (خاص بالمعلم)"
                                placeholder="اكتب عناصر الإجابة والدرجة المستحقة لكل عنصر لتسهيل التصحيح السريع لاحقاً..."
                            />
                        </div>
                    </div>

                    <!-- Objective Options Table -->
                    <div v-else class="grid gap-4 lg:grid-cols-[1fr_280px]">
                        <div>
                            <div class="space-y-2">
                                <div v-for="(option, index) in form.options" :key="index" class="flex items-center gap-2 rounded-xl border border-surface-100 p-2 dark:border-surface-800">
                                    <input v-if="form.type === 'single' || form.type === 'true_false'" type="radio" :name="`bank-correct-${editingId ?? 'new'}`" :checked="option.is_correct" @change="markCorrect(index)" />
                                    <input v-else v-model="option.is_correct" type="checkbox" class="rounded" />
                                    <input v-model="option.option_text" type="text" class="input" maxlength="500" :placeholder="`الإجابة ${index + 1}`" />
                                    <button type="button" class="btn-ghost btn-sm px-2 text-red-500" :disabled="form.type === 'true_false' || form.options.length <= MIN_OPTIONS" @click="removeOption(index)">
                                        <Icon name="close" class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                            <p v-if="form.errors.options" class="error-msg">{{ form.errors.options }}</p>
                            <button v-if="form.type !== 'true_false'" type="button" class="btn-ghost btn-sm mt-2" :disabled="form.options.length >= MAX_OPTIONS" @click="addOption">
                                <Icon name="plus" class="h-4 w-4" />
                                <span>إضافة إجابة</span>
                            </button>
                        </div>
                        <div>
                            <label class="input-label">شرح الإجابة للمعلم</label>
                            <textarea v-model="form.explanation" rows="7" maxlength="2000" class="input" placeholder="اختياري؛ لا يظهر للطالب من بنك الأسئلة"></textarea>
                        </div>
                    </div>
                </div>

                <p v-if="problemOf()" class="error-msg flex items-center gap-1">
                    <Icon name="error" class="h-4 w-4" /> {{ problemOf() }}
                </p>
                <p v-if="form.errors.question" class="error-msg">{{ form.errors.question }}</p>

                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-outline btn-sm" @click="cancelEdit">إلغاء</button>
                    <button type="button" class="btn-primary btn-sm" :disabled="form.processing || !! problemOf()" @click="submit">
                        {{ form.processing ? 'جارٍ الحفظ...' : (editingId ? 'حفظ التعديلات' : 'حفظ السؤال') }}
                    </button>
                </div>
            </section>

            <form class="card grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="applyFilters">
                <input v-model="search" type="search" class="input lg:col-span-2" placeholder="ابحث في نص السؤال أو الموضوع" />
                <select v-model="difficultyFilter" class="input">
                    <option value="">كل الصعوبات</option>
                    <option value="easy">سهل</option>
                    <option value="medium">متوسط</option>
                    <option value="hard">متقدم</option>
                </select>
                <select v-model="typeFilter" class="input">
                    <option value="">كل الأنواع</option>
                    <option value="single">اختيار واحد</option>
                    <option value="multiple">اختيارات متعددة</option>
                    <option value="true_false">صواب / خطأ</option>
                    <option value="essay">سؤال مقالي</option>
                </select>
                <div class="flex gap-2">
                    <select v-model="visibilityFilter" class="input min-w-0 flex-1">
                        <option value="">كل مستويات الظهور</option>
                        <option value="private">خاص بي</option>
                        <option value="shared">مشترك</option>
                    </select>
                    <button type="submit" class="btn-primary btn-sm">بحث</button>
                </div>
            </form>

            <div class="flex items-center justify-between gap-3">
                <p class="text-sm text-surface-500 dark:text-surface-400">{{ questions.total ?? questions.meta?.total ?? 0 }} سؤال محفوظ</p>
                <Link :href="route('teacher.question-bank')" class="text-xs font-bold text-primary-600 hover:underline">مسح الفلاتر</Link>
            </div>

            <section v-if="questions.data?.length" class="grid gap-4 lg:grid-cols-2">
                <article v-for="question in questions.data" :key="question.id" class="card flex flex-col gap-4 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="mb-2 flex flex-wrap gap-1.5 text-[10px]">
                                <span class="badge-primary">{{ typeLabels[question.type] || question.type }}</span>
                                <span class="badge-gray">{{ difficultyLabels[question.difficulty] }}</span>
                                <span :class="question.visibility === 'shared' ? 'badge-green' : 'badge-accent'">{{ visibilityLabels[question.visibility] }}</span>

                                <!-- Academic Badges for Question (Stage, Grade, Term) -->
                                <span v-if="question.stage_name" class="inline-flex items-center gap-1 rounded bg-amber-500/10 px-1.5 py-0.5 font-bold text-amber-700 dark:text-amber-300 border border-amber-500/20">
                                    🏫 {{ question.stage_name }}
                                </span>
                                <span v-if="question.grade_name" class="inline-flex items-center gap-1 rounded bg-blue-500/10 px-1.5 py-0.5 font-bold text-blue-700 dark:text-blue-300 border border-blue-500/20">
                                    📚 {{ question.grade_name }}
                                </span>
                                <span v-if="question.term_name" class="inline-flex items-center gap-1 rounded bg-emerald-500/10 px-1.5 py-0.5 font-bold text-emerald-700 dark:text-emerald-300 border border-emerald-500/20">
                                    🗓️ {{ question.term_name }}
                                </span>

                                <span v-if="question.allow_file_upload" class="rounded bg-sky-500/10 px-1.5 py-0.5 text-[10px] font-bold text-sky-600 dark:text-sky-400 border border-sky-500/20">
                                    📎 يقبل حل ورقي
                                </span>
                            </div>
                            <div class="prose prose-sm dark:prose-invert line-clamp-3 font-bold text-surface-900 dark:text-white" v-html="question.question_text"></div>
                        </div>
                        <Icon name="courses" class="h-5 w-5 shrink-0 text-primary-500" />
                    </div>

                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-surface-400">
                        <span>{{ question.subject?.name ?? 'بدون مادة' }}</span>
                        <span>{{ question.topic || 'بدون موضوع' }}</span>
                        <span>{{ question.points }} نقطة</span>
                        <span v-if="question.type === 'essay'">{{ question.allow_file_upload ? 'يقبل حل ورقي مصور' : 'حل كتابي تحريري' }}</span>
                        <span v-else>{{ question.options?.length ?? 0 }} إجابات</span>
                    </div>

                    <div class="flex flex-wrap gap-1" v-if="question.tags?.length">
                        <span v-for="tag in question.tags" :key="tag" class="rounded-full bg-surface-100 px-2 py-1 text-[10px] text-surface-500 dark:bg-surface-800 dark:text-surface-300">#{{ tag }}</span>
                    </div>

                    <div class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-surface-100 pt-3 dark:border-surface-800">
                        <span class="text-[11px] text-surface-400">{{ question.teacher?.name }}</span>
                        <div class="flex gap-2" v-if="question.can_edit">
                            <button type="button" class="btn-ghost btn-sm" @click="editQuestion(question)">تعديل</button>
                            <button type="button" class="btn-ghost btn-sm text-red-500" @click="deleteQuestion(question)">حذف</button>
                        </div>
                        <span v-else class="text-[11px] text-surface-400">سؤال مشترك للنسخ فقط</span>
                    </div>
                </article>
            </section>

            <div v-else class="card p-12 text-center">
                <Icon name="courses" class="mx-auto h-10 w-10 text-surface-300" />
                <h2 class="mt-3 font-black text-surface-800 dark:text-surface-100">لا توجد أسئلة مطابقة</h2>
                <p class="mt-1 text-sm text-surface-400">أنشئ سؤالًا جديدًا أو عدّل الفلاتر لرؤية الأسئلة المحفوظة.</p>
            </div>

            <nav v-if="questions.links?.length > 3" class="flex flex-wrap justify-center gap-1" aria-label="صفحات بنك الأسئلة">
                <Link
                    v-for="link in questions.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="rounded-lg px-3 py-2 text-xs"
                    :class="link.active ? 'bg-primary-600 text-white' : 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-300'"
                    :aria-disabled="! link.url"
                    v-html="link.label"
                />
            </nav>
        </div>
    </DashboardLayout>
</template>
