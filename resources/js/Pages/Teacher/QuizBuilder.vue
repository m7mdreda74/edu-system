<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    quiz:       { type: Object, required: true },
    unit:       { type: Object, required: true },
    assignment: { type: Object, required: true },
    questions:  { type: Array, default: () => [] },
});

const MIN_OPTIONS = 2;
const MAX_OPTIONS = 6;
const { confirm } = useConfirm();

const OPTION_LABELS = ['الأولى', 'الثانية', 'الثالثة', 'الرابعة', 'الخامسة', 'السادسة'];

const curriculumHref = computed(() => route('teacher.curriculum', {
    assignment: props.assignment.id,
    term: props.unit.term_id,
}));

// ── Quiz settings ─────────────────────────────────────────────────

const settings = useForm({
    title:              props.quiz.title,
    time_limit_minutes: props.quiz.time_limit_minutes,
    available_from:     props.quiz.available_from,
    available_until:    props.quiz.available_until,
    passing_score:      props.quiz.passing_score,
    is_active:          props.quiz.is_active,
});

function saveSettings() {
    settings.put(route('teacher.quizzes.update', props.quiz.id), {
        preserveScroll: true,
        preserveState: true,
    });
}

async function destroyQuiz() {
    const ok = await confirm({
        title: 'حذف الاختبار',
        message: 'سيتم حذف الاختبار بكل أسئلته. لا يمكن التراجع عن هذا الإجراء.',
        confirmLabel: 'حذف نهائياً',
        variant: 'danger',
    });
    if (ok) router.delete(route('teacher.quizzes.destroy', props.quiz.id));
}

// ── Question drafts ───────────────────────────────────────────────
// The server replaces a question's options wholesale, so every save posts the
// whole question. Each row therefore edits against a local draft and only
// travels when the teacher presses حفظ.

let draftSeq = 0;

function makeOption(text = '', isCorrect = false) {
    return { option_text: text, is_correct: isCorrect };
}

function makeDraft(question = null) {
    return {
        key:           question ? `q${question.id}` : `new-${++draftSeq}`,
        id:            question?.id ?? null,
        question_text: question?.question_text ?? '',
        type:          question?.type ?? 'single',
        order:         question?.order ?? null,
        options:       question?.options?.length
            ? question.options.map((option) => makeOption(option.option_text, !!option.is_correct))
            : [makeOption(), makeOption()],
        dirty:  question === null,
        saving: false,
        errors: {},
    };
}

const drafts = ref([]);

/**
 * Rebuild from the server payload without throwing away work in progress:
 * a draft that is dirty or mid-flight keeps its local copy, and questions the
 * teacher has added but not yet saved stay pinned to the end of the list.
 */
function syncDrafts() {
    const pending = drafts.value.filter((draft) => draft.id === null);
    const kept = new Map(
        drafts.value
            .filter((draft) => draft.id !== null && (draft.dirty || draft.saving))
            .map((draft) => [draft.id, draft]),
    );

    drafts.value = [
        ...props.questions.map((question) => kept.get(question.id) ?? makeDraft(question)),
        ...pending,
    ];
}

watch(() => props.questions, syncDrafts, { immediate: true });

const savedDrafts = computed(() => drafts.value.filter((draft) => draft.id !== null));
const pendingCount = computed(() => drafts.value.length - savedDrafts.value.length);

// Collapsing is keyed by question id rather than kept on the draft, so a
// reload from the server does not silently unfold the whole list again.
const collapsedIds = ref([]);

function isOpen(draft) {
    return draft.id === null || ! collapsedIds.value.includes(draft.id);
}

function toggleOpen(draft) {
    if (draft.id === null) return;

    const index = collapsedIds.value.indexOf(draft.id);

    if (index === -1) {
        collapsedIds.value.push(draft.id);
    } else {
        collapsedIds.value.splice(index, 1);
    }
}

function collapseAll() {
    collapsedIds.value = props.questions.map((question) => question.id);
}

function expandAll() {
    collapsedIds.value = [];
}

const allCollapsed = computed(
    () => savedDrafts.value.length > 0 && savedDrafts.value.every((draft) => ! isOpen(draft)),
);

function positionOf(draft) {
    return savedDrafts.value.indexOf(draft) + 1;
}

function correctCount(draft) {
    return draft.options.filter((option) => option.is_correct).length;
}

// ── Editing ───────────────────────────────────────────────────────

function addQuestion() {
    drafts.value.push(makeDraft());
}

function addOption(draft) {
    if (draft.options.length >= MAX_OPTIONS) return;

    draft.options.push(makeOption());
    draft.dirty = true;
}

function removeOption(draft, index) {
    if (draft.options.length <= MIN_OPTIONS) return;

    draft.options.splice(index, 1);
    draft.dirty = true;
}

function markCorrect(draft, index) {
    draft.options.forEach((option, i) => { option.is_correct = i === index; });
    draft.dirty = true;
}

function toggleCorrect(draft, index) {
    draft.options[index].is_correct = ! draft.options[index].is_correct;
    draft.dirty = true;
}

function changeType(draft, type) {
    draft.type = type;

    // Narrowing to a single answer keeps the first tick and drops the rest,
    // which is what the server would refuse to store anyway.
    if (type === 'single') {
        let taken = false;

        draft.options.forEach((option) => {
            if (option.is_correct && ! taken) {
                taken = true;
            } else {
                option.is_correct = false;
            }
        });
    }

    draft.dirty = true;
}

/**
 * The same answer-key rules the server enforces, said out loud before the
 * teacher spends a round trip on them.
 */
function problemOf(draft) {
    if (! draft.question_text.trim()) {
        return 'اكتب نص السؤال أولاً.';
    }

    if (draft.options.length < MIN_OPTIONS) {
        return 'يجب أن يحتوي السؤال على إجابتين على الأقل.';
    }

    if (draft.options.some((option) => ! option.option_text.trim())) {
        return 'اكتب نص كل إجابة قبل الحفظ.';
    }

    const correct = correctCount(draft);

    if (correct === 0) {
        return 'حدد الإجابة الصحيحة أولاً.';
    }

    if (draft.type === 'single' && correct > 1) {
        return 'سؤال الاختيار الواحد يقبل إجابة صحيحة واحدة فقط.';
    }

    return null;
}

function questionPayload(draft, order = null) {
    return {
        question_text: draft.question_text,
        type:          draft.type,
        order:         order ?? draft.order,
        options:       draft.options.map((option) => ({
            option_text: option.option_text,
            is_correct:  option.is_correct,
        })),
    };
}

function saveQuestion(draft) {
    if (problemOf(draft) || draft.saving) return;

    draft.saving = true;
    draft.errors = {};

    const options = {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => { draft.errors = errors; },
        onFinish: () => { draft.saving = false; },
    };

    if (draft.id === null) {
        router.post(route('teacher.quizzes.questions.store', props.quiz.id), questionPayload(draft), {
            ...options,
            onSuccess: () => {
                drafts.value = drafts.value.filter((row) => row !== draft);
                syncDrafts();
            },
        });

        return;
    }

    router.put(route('teacher.questions.update', draft.id), questionPayload(draft), {
        ...options,
        onSuccess: () => {
            draft.dirty = false;
            syncDrafts();
        },
    });
}

async function destroyQuestion(draft) {
    if (draft.id === null) {
        drafts.value = drafts.value.filter((row) => row !== draft);
        return;
    }

    const ok = await confirm({
        title: 'حذف السؤال',
        message: 'سيتم حذف هذا السؤال وكل إجاباته.',
        confirmLabel: 'حذف',
        variant: 'danger',
    });
    if (!ok) return;

    router.delete(route('teacher.questions.destroy', draft.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: syncDrafts,
    });
}

function canMove(draft, delta) {
    const index = savedDrafts.value.indexOf(draft);

    return index !== -1 && index + delta >= 0 && index + delta < savedDrafts.value.length;
}

/**
 * A pair trades places by rewriting both `order` values from their positions in
 * the list — legacy rows can share an order number and would otherwise sit
 * still. The second save waits for the first so Inertia does not cancel it.
 */
function moveQuestion(draft, delta) {
    if (! canMove(draft, delta) || draft.saving) return;

    const from  = savedDrafts.value.indexOf(draft);
    const other = savedDrafts.value[from + delta];

    // Marked dirty so a reload landing mid-swap keeps both rows alive.
    draft.dirty = other.dirty = true;
    draft.saving = other.saving = true;

    const release = () => { draft.saving = other.saving = false; };

    router.put(route('teacher.questions.update', other.id), questionPayload(other, from + 1), {
        preserveScroll: true,
        preserveState: true,
        onError: release,
        onSuccess: () => {
            router.put(route('teacher.questions.update', draft.id), questionPayload(draft, from + delta + 1), {
                preserveScroll: true,
                preserveState: true,
                onFinish: release,
                onSuccess: () => {
                    draft.dirty = other.dirty = false;
                    syncDrafts();
                },
            });
        },
    });
}

// ── Display helpers ───────────────────────────────────────────────

function durationLabel(minutes) {
    return minutes ? `${minutes} دقيقة` : 'بلا حد';
}

function optionError(draft, index) {
    return draft.errors[`options.${index}.option_text`];
}
</script>

<template>
    <Head :title="`اختبار ${quiz.title}`" />

    <DashboardLayout>
        <div class="space-y-6">
            <!-- Header -->
            <header class="flex items-start justify-between gap-4 flex-wrap">
                <div class="min-w-0">
                    <nav class="text-xs text-surface-400 mb-1 flex items-center gap-1 flex-wrap">
                        <Link :href="route('teacher.teaching-schedule')" class="hover:text-primary-500">جدول التدريس</Link>
                        <span>/</span>
                        <Link :href="curriculumHref" class="hover:text-primary-500">بناء المنهج</Link>
                        <span>/</span>
                        <span>{{ unit.title }}</span>
                    </nav>

                    <h1 class="text-2xl font-black text-surface-900 dark:text-white flex items-center gap-2 flex-wrap">
                        <span>{{ quiz.title }}</span>
                        <span :class="quiz.is_active ? 'badge-green' : 'badge-gray'" class="text-[10px]">
                            {{ quiz.is_active ? 'مفعّل' : 'مسودة' }}
                        </span>
                    </h1>

                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                        {{ assignment.subject?.name }}
                        <span v-if="assignment.grade">— {{ assignment.grade.name }}</span>
                        <span v-if="unit.term">— {{ unit.term.name }}</span>
                        <span>— {{ quiz.is_unit_exam ? 'النموذج الإلكتروني لاختبار الوحدة' : `اختبار الدرس: ${quiz.lesson?.title}` }}</span>
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Link :href="curriculumHref" class="btn-ghost btn-sm">
                        <Icon name="arrowRight" class="w-4 h-4" />
                        <span class="ms-1">العودة للمنهج</span>
                    </Link>
                    <button type="button" class="btn-ghost btn-sm text-red-500" @click="destroyQuiz">
                        <Icon name="trash" class="w-4 h-4" />
                        <span class="ms-1">حذف الاختبار</span>
                    </button>
                </div>
            </header>

            <!-- Live counters -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="card p-4">
                    <div class="text-[11px] text-surface-400 mb-1">عدد الأسئلة</div>
                    <div class="text-2xl font-black text-surface-900 dark:text-white">{{ questions.length }}</div>
                    <div v-if="pendingCount" class="text-[11px] text-accent-600 dark:text-accent-400 mt-0.5">
                        + {{ pendingCount }} غير محفوظ
                    </div>
                </div>

                <div class="card p-4">
                    <div class="text-[11px] text-surface-400 mb-1">درجة النجاح المطلوبة</div>
                    <div class="text-2xl font-black text-surface-900 dark:text-white">{{ settings.passing_score }}%</div>
                </div>

                <div class="card p-4">
                    <div class="text-[11px] text-surface-400 mb-1">مدة الاختبار</div>
                    <div class="text-2xl font-black text-surface-900 dark:text-white">{{ durationLabel(quiz.time_limit_minutes) }}</div>
                </div>

                <div class="card p-4">
                    <div class="text-[11px] text-surface-400 mb-1">الإتاحة</div>
                    <div class="text-sm font-bold text-surface-900 dark:text-white leading-snug">{{ quiz.window_label }}</div>
                    <span :class="quiz.is_open ? 'badge-green' : 'badge-gray'" class="text-[10px] mt-1">
                        {{ quiz.is_open ? 'مفتوح الآن' : 'مغلق الآن' }}
                    </span>
                </div>
            </div>

            <p v-if="quiz.attempts_count" class="alert-warn text-sm">
                <Icon name="info" class="w-5 h-5 shrink-0" />
                <span>
                    جلس {{ quiz.attempts_count }} طالباً على هذا الاختبار بالفعل. تعديل الأسئلة أو الإجابات الصحيحة
                    لن يعيد تصحيح المحاولات السابقة.
                </span>
            </p>

            <p v-else-if="quiz.is_active && questions.length === 0" class="alert-warn text-sm">
                <Icon name="error" class="w-5 h-5 shrink-0" />
                <span>الاختبار مفعّل لكنه بلا أسئلة، ولن يستطيع الطالب دخوله حتى تضيف سؤالاً واحداً على الأقل.</span>
            </p>

            <p v-else-if="! quiz.is_active" class="alert-info text-sm">
                <Icon name="info" class="w-5 h-5 shrink-0" />
                <span>الاختبار مسودة ولا يظهر للطلاب. فعّله من إعدادات الاختبار بالأسفل بعد إضافة الأسئلة.</span>
            </p>

            <!-- Settings -->
            <form class="card p-5 space-y-4" @submit.prevent="saveSettings">
                <div class="flex items-center gap-2">
                    <Icon name="settings" class="w-5 h-5 text-primary-500" />
                    <h2 class="font-bold text-surface-900 dark:text-white">إعدادات الاختبار</h2>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label for="quiz-title" class="input-label">عنوان الاختبار</label>
                        <input id="quiz-title" v-model="settings.title" type="text" class="input"
                               :class="{ 'input-error': settings.errors.title }" required />
                        <p v-if="settings.errors.title" class="error-msg">{{ settings.errors.title }}</p>
                    </div>

                    <div>
                        <label for="quiz-duration" class="input-label">مدة الاختبار (بالدقائق)</label>
                        <input id="quiz-duration" v-model="settings.time_limit_minutes" type="number" min="1" max="600"
                               class="input" :class="{ 'input-error': settings.errors.time_limit_minutes }"
                               placeholder="بلا حد" />
                        <p class="input-hint">اتركه فارغاً ليحل الطالب بلا مؤقت.</p>
                        <p v-if="settings.errors.time_limit_minutes" class="error-msg">{{ settings.errors.time_limit_minutes }}</p>
                    </div>

                    <div>
                        <label for="quiz-passing" class="input-label">درجة النجاح (%)</label>
                        <input id="quiz-passing" v-model="settings.passing_score" type="number" min="0" max="100"
                               class="input" :class="{ 'input-error': settings.errors.passing_score }" required />
                        <p v-if="settings.errors.passing_score" class="error-msg">{{ settings.errors.passing_score }}</p>
                    </div>

                    <div>
                        <label for="quiz-from" class="input-label">متاح من</label>
                        <input id="quiz-from" v-model="settings.available_from" type="datetime-local" dir="ltr"
                               class="input" :class="{ 'input-error': settings.errors.available_from }" />
                        <p v-if="settings.errors.available_from" class="error-msg">{{ settings.errors.available_from }}</p>
                    </div>

                    <div>
                        <label for="quiz-until" class="input-label">متاح إلى</label>
                        <input id="quiz-until" v-model="settings.available_until" type="datetime-local" dir="ltr"
                               class="input" :class="{ 'input-error': settings.errors.available_until }" />
                        <p class="input-hint">اترك الحقلين فارغين ليبقى الاختبار متاحاً في أي وقت.</p>
                        <p v-if="settings.errors.available_until" class="error-msg">{{ settings.errors.available_until }}</p>
                    </div>

                    <label class="sm:col-span-2 flex items-center gap-2 text-sm text-surface-600 dark:text-surface-300">
                        <input v-model="settings.is_active" type="checkbox" class="rounded" />
                        <span>تفعيل الاختبار (يظهر للطلاب ضمن الوحدة)</span>
                    </label>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn-primary btn-sm" :disabled="settings.processing">
                        {{ settings.processing ? 'جارٍ الحفظ...' : 'حفظ الإعدادات' }}
                    </button>
                </div>
            </form>

            <!-- Questions -->
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <h2 class="font-bold text-surface-900 dark:text-white flex items-center gap-2">
                    <Icon name="courses" class="w-5 h-5 text-primary-500" />
                    <span>الأسئلة</span>
                    <span class="badge-gray text-[10px]">{{ drafts.length }}</span>
                </h2>

                <div class="flex items-center gap-2">
                    <button v-if="savedDrafts.length > 1" type="button" class="btn-ghost btn-sm"
                            @click="allCollapsed ? expandAll() : collapseAll()">
                        {{ allCollapsed ? 'توسيع الكل' : 'طيّ الكل' }}
                    </button>
                    <button type="button" class="btn-primary btn-sm" @click="addQuestion">
                        <Icon name="plus" class="w-4 h-4" />
                        <span class="ms-1">إضافة سؤال</span>
                    </button>
                </div>
            </div>

            <div v-if="drafts.length" class="space-y-4">
                <div v-for="draft in drafts" :key="draft.key" class="card p-4 sm:p-5 space-y-4">
                    <!-- Question header -->
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-lg bg-primary-50 dark:bg-primary-950 flex items-center justify-center text-primary-600 font-black text-xs shrink-0">
                            {{ draft.id === null ? '+' : positionOf(draft) }}
                        </div>

                        <button type="button" class="flex-1 min-w-0 text-start" @click="toggleOpen(draft)">
                            <div class="font-bold text-sm text-surface-900 dark:text-white line-clamp-2">
                                {{ draft.question_text.trim() || 'سؤال جديد' }}
                            </div>
                            <div class="text-[11px] text-surface-400 flex items-center gap-2 mt-0.5 flex-wrap">
                                <span>{{ draft.type === 'single' ? 'اختيار واحد' : 'اختيار متعدد' }}</span>
                                <span>· {{ draft.options.length }} إجابات</span>
                                <span>· {{ correctCount(draft) }} صحيحة</span>
                                <span v-if="draft.dirty" class="text-accent-600 dark:text-accent-400 font-bold">· غير محفوظ</span>
                            </div>
                        </button>

                        <div class="flex items-center gap-1 shrink-0">
                            <button v-if="draft.id !== null" type="button" class="btn-ghost btn-sm px-2"
                                    :disabled="! canMove(draft, -1) || draft.saving" title="تحريك لأعلى"
                                    @click="moveQuestion(draft, -1)">
                                <Icon name="arrowRight" class="w-4 h-4 -rotate-90" />
                            </button>
                            <button v-if="draft.id !== null" type="button" class="btn-ghost btn-sm px-2"
                                    :disabled="! canMove(draft, 1) || draft.saving" title="تحريك لأسفل"
                                    @click="moveQuestion(draft, 1)">
                                <Icon name="arrowRight" class="w-4 h-4 rotate-90" />
                            </button>
                            <button v-if="draft.id !== null" type="button" class="btn-ghost btn-sm px-2"
                                    :title="isOpen(draft) ? 'طيّ السؤال' : 'تعديل السؤال'" @click="toggleOpen(draft)">
                                <Icon :name="isOpen(draft) ? 'close' : 'edit'" class="w-4 h-4" />
                            </button>
                            <button type="button" class="btn-ghost btn-sm px-2 text-red-500" title="حذف السؤال"
                                    @click="destroyQuestion(draft)">
                                <Icon name="trash" class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Question editor -->
                    <div v-show="isOpen(draft)" class="space-y-4">
                        <div>
                            <label class="input-label">نص السؤال</label>
                            <textarea v-model="draft.question_text" rows="2" class="input"
                                      :class="{ 'input-error': draft.errors.question_text }"
                                      placeholder="اكتب السؤال كما يقرأه الطالب"
                                      @input="draft.dirty = true"></textarea>
                            <p v-if="draft.errors.question_text" class="error-msg">{{ draft.errors.question_text }}</p>
                        </div>

                        <div>
                            <label class="input-label">نوع السؤال</label>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm border"
                                        :class="draft.type === 'single'
                                            ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-200'
                                            : 'border-surface-200 dark:border-surface-700 text-surface-500'"
                                        @click="changeType(draft, 'single')">
                                    إجابة صحيحة واحدة
                                </button>
                                <button type="button" class="btn btn-sm border"
                                        :class="draft.type === 'multiple'
                                            ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-200'
                                            : 'border-surface-200 dark:border-surface-700 text-surface-500'"
                                        @click="changeType(draft, 'multiple')">
                                    أكثر من إجابة صحيحة
                                </button>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <label class="input-label mb-0">الإجابات</label>
                                <span class="text-[11px] text-surface-400">
                                    {{ draft.type === 'single' ? 'اختر الدائرة أمام الإجابة الصحيحة' : 'علّم كل إجابة صحيحة' }}
                                </span>
                            </div>

                            <div class="space-y-2">
                                <div v-for="(option, index) in draft.options" :key="index"
                                     class="flex items-start gap-2 rounded-xl border p-2 transition-colors"
                                     :class="option.is_correct
                                         ? 'border-green-300 bg-green-50 dark:border-green-800 dark:bg-green-950/20'
                                         : 'border-transparent'">
                                    <label class="flex items-center h-10 shrink-0 px-1 cursor-pointer">
                                        <input v-if="draft.type === 'single'" type="radio"
                                               :name="`correct-${draft.key}`" :checked="option.is_correct"
                                               class="w-4 h-4 text-primary-600 border-surface-300 focus:ring-accent-500"
                                               @change="markCorrect(draft, index)" />
                                        <input v-else type="checkbox" :checked="option.is_correct"
                                               class="w-4 h-4 rounded text-primary-600 border-surface-300 focus:ring-accent-500"
                                               @change="toggleCorrect(draft, index)" />
                                    </label>

                                    <div class="flex-1 min-w-0">
                                        <input v-model="option.option_text" type="text" class="input"
                                               :class="{ 'input-error': optionError(draft, index) }"
                                               :placeholder="`الإجابة ${OPTION_LABELS[index] ?? index + 1}`"
                                               @input="draft.dirty = true" />
                                        <p v-if="optionError(draft, index)" class="error-msg">{{ optionError(draft, index) }}</p>
                                    </div>

                                    <button type="button" class="btn-ghost btn-sm px-2 h-10 shrink-0 text-red-500"
                                            :disabled="draft.options.length <= MIN_OPTIONS"
                                            :title="draft.options.length <= MIN_OPTIONS ? 'الحد الأدنى إجابتان' : 'حذف الإجابة'"
                                            @click="removeOption(draft, index)">
                                        <Icon name="close" class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>

                            <p v-if="draft.errors.options" class="error-msg">{{ draft.errors.options }}</p>

                            <button type="button" class="btn-ghost btn-sm mt-2"
                                    :disabled="draft.options.length >= MAX_OPTIONS"
                                    :title="draft.options.length >= MAX_OPTIONS ? 'الحد الأقصى ست إجابات' : ''"
                                    @click="addOption(draft)">
                                <Icon name="plus" class="w-4 h-4" />
                                <span class="ms-1">إضافة إجابة</span>
                            </button>
                        </div>

                        <div class="flex items-center justify-end gap-3 flex-wrap border-t border-surface-100 dark:border-surface-800 pt-3">
                            <p v-if="problemOf(draft)" class="error-msg flex-1 min-w-0">
                                <Icon name="error" class="w-4 h-4 shrink-0" />
                                <span>{{ problemOf(draft) }}</span>
                            </p>
                            <p v-else-if="draft.dirty" class="text-xs text-surface-400 flex-1 min-w-0">
                                لم تُحفظ التعديلات بعد.
                            </p>

                            <button type="button" class="btn-primary btn-sm"
                                    :disabled="!! problemOf(draft) || draft.saving"
                                    @click="saveQuestion(draft)">
                                {{ draft.saving ? 'جارٍ الحفظ...' : (draft.id === null ? 'حفظ السؤال' : 'حفظ التعديلات') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="card p-12 text-center">
                <Icon name="courses" class="w-10 h-10 text-surface-300 mx-auto mb-3" />
                <h3 class="font-bold text-surface-700 dark:text-surface-200 mb-1">لا توجد أسئلة بعد</h3>
                <p class="text-sm text-surface-400 mb-4">أضف أول سؤال اختيار من متعدد ليصبح الاختبار جاهزاً للطلاب.</p>
                <button type="button" class="btn-primary btn-sm" @click="addQuestion">
                    <Icon name="plus" class="w-4 h-4" />
                    <span class="ms-1">إضافة سؤال</span>
                </button>
            </div>
        </div>
    </DashboardLayout>
</template>
