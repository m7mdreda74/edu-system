<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    questions: { type: Object, default: () => ({ data: [], links: [], total: 0, meta: {} }) },
    filters: { type: Object, default: () => ({}) },
    subjects: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
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

const difficultyLabels = {
    easy: 'سهل',
    medium: 'متوسط',
    hard: 'متقدم',
};

const typeLabels = {
    single: 'اختيار واحد',
    multiple: 'اختيارات متعددة',
    true_false: 'صواب / خطأ',
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

const selectedUnit = computed(() => props.units.find(
    (unit) => Number(unit.id) === Number(form.curriculum_unit_id),
));

const availableLessons = computed(() => selectedUnit.value?.lessons ?? []);

watch(() => form.curriculum_unit_id, () => {
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
    form.explanation = question.explanation ?? '';
    form.tags = (question.tags ?? []).join(', ');
    form.options = (question.options ?? []).map((option) => makeOption(option.option_text, !!option.is_correct));
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
    form.options.forEach((option, optionIndex) => { option.is_correct = optionIndex === index; });
}

function toggleCorrect(index) {
    form.options[index].is_correct = ! form.options[index].is_correct;
}

function changeType(type) {
    form.type = type;

    if (type === 'true_false') {
        form.options = [
            makeOption('صواب', true),
            makeOption('خطأ', false),
        ];
    } else if (type === 'single') {
        let found = false;
        form.options.forEach((option) => {
            if (option.is_correct && ! found) {
                found = true;
            } else {
                option.is_correct = false;
            }
        });
        if (! found && form.options.length) {
            form.options[0].is_correct = true;
        }
    }
}

function correctCount() {
    return form.options.filter((option) => option.is_correct).length;
}

function problemOf() {
    if (! form.question_text.trim()) return 'اكتب نص السؤال أولاً.';
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
                        احفظ الأسئلة القابلة لإعادة الاستخدام، ثم انسخها إلى أي اختبار تملكه.
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
                        <h2 class="font-black text-surface-900 dark:text-white">{{ editingId ? 'تعديل سؤال محفوظ' : 'إضافة سؤال إلى البنك' }}</h2>
                        <p class="mt-1 text-xs text-surface-400">سيتم نسخ السؤال كـ snapshot داخل الاختبار عند استخدامه.</p>
                    </div>
                    <button type="button" class="btn-ghost btn-sm" @click="cancelEdit">إلغاء</button>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
                            <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.title }} · {{ unit.subject?.name }}</option>
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
                        <input v-model="form.topic" type="text" class="input" maxlength="160" placeholder="مثال: الكسور" />
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
                    <div>
                        <label class="input-label">الوسوم</label>
                        <input v-model="form.tags" type="text" class="input" placeholder="سهل، مراجعة، وحدة 1" />
                    </div>
                </div>

                <div>
                    <label class="input-label">نص السؤال</label>
                    <textarea v-model="form.question_text" rows="3" maxlength="1000" class="input" :class="{ 'input-error': form.errors.question_text }" placeholder="اكتب السؤال كما سيظهر للطالب"></textarea>
                    <p v-if="form.errors.question_text" class="error-msg">{{ form.errors.question_text }}</p>
                </div>

                <div class="grid gap-4 lg:grid-cols-[1fr_280px]">
                    <div>
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <label class="input-label mb-0">نوع السؤال</label>
                            <button type="button" class="btn-sm rounded-lg border px-3" :class="form.type === 'single' ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-surface-200 text-surface-500'" @click="changeType('single')">إجابة واحدة</button>
                            <button type="button" class="btn-sm rounded-lg border px-3" :class="form.type === 'multiple' ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-surface-200 text-surface-500'" @click="changeType('multiple')">إجابات متعددة</button>
                            <button type="button" class="btn-sm rounded-lg border px-3" :class="form.type === 'true_false' ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-surface-200 text-surface-500'" @click="changeType('true_false')">صواب / خطأ</button>
                        </div>
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

                <p v-if="problemOf()" class="error-msg flex items-center gap-1">
                    <Icon name="error" class="h-4 w-4" /> {{ problemOf() }}
                </p>
                <p v-if="form.errors.question" class="error-msg">{{ form.errors.question }}</p>

                <div class="flex justify-end">
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
                        <div class="min-w-0">
                            <div class="mb-2 flex flex-wrap gap-1.5 text-[10px]">
                                <span class="badge-primary">{{ typeLabels[question.type] }}</span>
                                <span class="badge-gray">{{ difficultyLabels[question.difficulty] }}</span>
                                <span :class="question.visibility === 'shared' ? 'badge-green' : 'badge-accent'">{{ visibilityLabels[question.visibility] }}</span>
                            </div>
                            <h2 class="line-clamp-3 font-black text-surface-900 dark:text-white">{{ question.question_text }}</h2>
                        </div>
                        <Icon name="courses" class="h-5 w-5 shrink-0 text-primary-500" />
                    </div>

                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-surface-400">
                        <span>{{ question.subject?.name ?? 'بدون مادة' }}</span>
                        <span>{{ question.topic || 'بدون موضوع' }}</span>
                        <span>{{ question.points }} نقطة</span>
                        <span>{{ question.options?.length ?? 0 }} إجابات</span>
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
