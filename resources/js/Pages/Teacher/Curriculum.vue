<script setup>
import { ref, watch, nextTick } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import Icon from '@/Components/Icon.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    assignment:   { type: Object, required: true },
    terms:        { type: Array,  default: () => [] },
    activeTermId: { type: Number, default: null },
    units:        { type: Array,  default: () => [] },
    stats:        { type: Object, default: () => ({ units: 0, lessons: 0, complete_lessons: 0, ready_exams: 0 }) },
    directUploads: {
        type: Object,
        default: () => ({
            enabled: false,
            serverless: false,
            authorize_url: null,
            handle_url: '/api/blob-upload',
            max_bytes: 25 * 1024 * 1024,
        }),
    },
});

// ─── Shared visit options ────────────────────────────────────────────────────
// Every write is a `back()` redirect, so the page re-renders with fresh props.
// `preserveState` keeps the accordion open and the drafts alive across it.
const VISIT = { preserveScroll: true, preserveState: true };

const busy      = ref(null);   // key of the row currently saving
const rowErrors = ref({});     // key -> validation bag from the last failed save
const { confirm } = useConfirm();

function send(method, url, data, key, options = {}) {
    const visit = {
        ...VISIT,
        forceFormData: options.forceFormData ?? false,
        onError:   (errors) => { rowErrors.value[key] = errors; },
        onSuccess: () => { delete rowErrors.value[key]; options.onSuccess?.(); },
        onFinish:  () => { busy.value = null; },
    };

    busy.value = key;

    // `router.delete` takes no body — its second argument is already the options.
    method === 'delete' ? router.delete(url, visit) : router[method](url, data, visit);
}

/** One line under the slot is enough — the teacher fixes them one at a time. */
function firstError(key) {
    const bag = rowErrors.value[key];
    return bag ? Object.values(bag)[0] : null;
}

// ─── Local state ─────────────────────────────────────────────────────────────
const expanded     = ref({});    // unitId   -> open
const editingVideo = ref({});    // lessonId -> the url editor is showing
const unitDrafts   = ref({});
const lessonDrafts = ref({});
const showSkeleton = ref(false);
const showAddUnit  = ref(false);

let seenUnitIds = null;

// Declared before the units watcher so a term switch resets first and the new
// term's opening unit is the one that unfolds.
watch(() => props.activeTermId, () => {
    expanded.value = {};
    seenUnitIds    = null;
});

watch(() => props.units, (units) => {
    const nextUnits   = {};
    const nextLessons = {};

    units.forEach((unit) => {
        nextUnits[unit.id] = {
            title:           unit.title,
            description:     unit.description ?? '',
            paper_due_date:  unit.paper_exam?.due_date ?? '',
            paper_max_score: unit.paper_exam?.max_score ?? '',
        };

        unit.lessons.forEach((lesson) => {
            nextLessons[lesson.id] = {
                title:            lesson.title,
                video_url:        lesson.video_url ?? '',
                duration_seconds: lesson.duration_seconds ?? 0,
                due_date:         lesson.homework?.due_date ?? '',
                max_score:        lesson.homework?.max_score ?? '',
            };
        });
    });

    unitDrafts.value   = nextUnits;
    lessonDrafts.value = nextLessons;

    const ids = units.map((unit) => unit.id);
    if (seenUnitIds === null) {
        if (ids.length) expanded.value[ids[0]] = true;
    } else {
        // Exactly one new unit means the teacher just created it — open it.
        const added = ids.filter((id) => !seenUnitIds.includes(id));
        if (added.length === 1) expanded.value[added[0]] = true;
    }
    seenUnitIds = ids;
}, { immediate: true });

// ─── Terms ───────────────────────────────────────────────────────────────────
function goToTerm(termId) {
    if (termId === props.activeTermId) return;

    router.get(route('teacher.curriculum', { assignment: props.assignment.id, term: termId }), {}, {
        ...VISIT,
        only: ['terms', 'activeTermId', 'units', 'stats'],
    });
}

// ─── Skeleton generator ──────────────────────────────────────────────────────
const skeleton = useForm({
    academic_term_id: props.activeTermId,
    units_count:      4,
    lessons_per_unit: 4,
});

watch(() => props.activeTermId, (id) => { skeleton.academic_term_id = id; });

function generateSkeleton() {
    const target = skeleton.academic_term_id;

    skeleton.post(route('teacher.curriculum.skeleton', props.assignment.id), {
        ...VISIT,
        onSuccess: () => {
            showSkeleton.value = false;
            // Built into another term than the one on screen — follow the work.
            goToTerm(target);
        },
    });
}

// ─── Units ───────────────────────────────────────────────────────────────────
const newUnit = useForm({ academic_term_id: props.activeTermId, title: '', description: '' });

watch(() => props.activeTermId, (id) => { newUnit.academic_term_id = id; });

function addUnit() {
    newUnit.post(route('teacher.units.store', props.assignment.id), {
        ...VISIT,
        onSuccess: () => {
            newUnit.reset('title', 'description');
            newUnit.academic_term_id = props.activeTermId;
            showAddUnit.value = false;
        },
    });
}

function saveUnit(unit) {
    const draft = unitDrafts.value[unit.id];
    if (!draft) return;

    // Blurring an emptied field is a slip, not an edit — put the title back.
    if (!draft.title.trim()) {
        draft.title = unit.title;
        return;
    }

    if (draft.title.trim() === unit.title && draft.description === (unit.description ?? '')) return;

    send('put', route('teacher.units.update', unit.id), {
        title:       draft.title,
        description: draft.description,
    }, `unit:${unit.id}`);
}

function togglePublished(unit) {
    send('put', route('teacher.units.update', unit.id), { is_published: !unit.is_published }, `unit:${unit.id}`);
}

function moveUnit(unit, direction) {
    send('post', route('teacher.units.reorder', unit.id), { direction }, `unit:${unit.id}`);
}

async function removeUnit(unit) {
    const ok = await confirm({
        title: `حذف «${unit.title}»`,
        message: 'سيتم حذف الوحدة بكل دروسها واختباراتها. لا يمكن التراجع.',
        confirmLabel: 'حذف',
        variant: 'danger',
    });
    if (!ok) return;
    send('delete', route('teacher.units.destroy', unit.id), {}, `unit:${unit.id}`);
}

// ─── Lessons ─────────────────────────────────────────────────────────────────
const newLesson = ref({});   // unitId -> title being typed

function addLesson(unit) {
    const title = (newLesson.value[unit.id] ?? '').trim();
    if (!title) return;

    send('post', route('teacher.lessons.store', unit.id), { title }, `unit:${unit.id}:lesson`, {
        onSuccess: () => { newLesson.value[unit.id] = ''; },
    });
}

function saveLessonTitle(lesson) {
    const draft = lessonDrafts.value[lesson.id];
    if (!draft) return;

    if (!draft.title.trim()) {
        draft.title = lesson.title;
        return;
    }

    if (draft.title.trim() === lesson.title) return;

    send('put', route('teacher.lessons.update', lesson.id), { title: draft.title }, `lesson:${lesson.id}`);
}

function togglePreview(lesson) {
    send('put', route('teacher.lessons.update', lesson.id), { is_free_preview: !lesson.is_free_preview }, `lesson:${lesson.id}`);
}

async function removeLesson(lesson) {
    const ok = await confirm({
        title: `حذف «${lesson.title}»`,
        message: 'سيتم حذف الدرس نهائياً.',
        confirmLabel: 'حذف',
        variant: 'danger',
    });
    if (!ok) return;
    send('delete', route('teacher.lessons.destroy', lesson.id), {}, `lesson:${lesson.id}`);
}

// ─── Lesson slot: فيديو الشرح ────────────────────────────────────────────────
function openVideo(lesson) {
    editingVideo.value[lesson.id] = true;
    nextTick(() => document.getElementById(`video-${lesson.id}`)?.focus());
}

function saveVideo(lesson) {
    const draft = lessonDrafts.value[lesson.id];

    send('put', route('teacher.lessons.update', lesson.id), {
        video_url:        draft.video_url,
        duration_seconds: draft.duration_seconds === '' ? 0 : draft.duration_seconds,
    }, `lesson:${lesson.id}:video`, {
        onSuccess: () => { editingVideo.value[lesson.id] = false; },
    });
}

async function clearVideo(lesson) {
    const ok = await confirm({
        title: 'إزالة الفيديو',
        message: 'سيتم إزالة رابط الفيديو من هذا الدرس.',
        confirmLabel: 'إزالة',
        variant: 'warning',
    });
    if (!ok) return;
    send('put', route('teacher.lessons.update', lesson.id), { video_url: '', duration_seconds: 0 }, `lesson:${lesson.id}:video`, {
        onSuccess: () => { editingVideo.value[lesson.id] = false; },
    });
}

// ─── Lesson slot: ملزمة الشرح ────────────────────────────────────────────────
async function uploadBooklet(lesson, event) {
    const file = takeFile(event);
    if (!file) return;

    if (props.directUploads.enabled) {
        await uploadDirect(
            'booklet',
            lesson.id,
            file,
            route('teacher.lessons.booklet', lesson.id),
            `lesson:${lesson.id}:booklet`,
        );
        return;
    }

    if (blockUnconfiguredServerlessUpload(`lesson:${lesson.id}:booklet`)) return;

    send('post', route('teacher.lessons.booklet', lesson.id), { booklet: file }, `lesson:${lesson.id}:booklet`, { forceFormData: true });
}

// ─── Lesson slot: الواجب ─────────────────────────────────────────────────────
// Each control posts only the field it owns; an absent key keeps its stored
// value, so moving the due date never wipes the score.
async function uploadHomework(lesson, event) {
    const file = takeFile(event);
    if (!file) return;

    if (props.directUploads.enabled) {
        await uploadDirect(
            'homework',
            lesson.id,
            file,
            route('teacher.lessons.homework', lesson.id),
            `lesson:${lesson.id}:homework`,
        );
        return;
    }

    if (blockUnconfiguredServerlessUpload(`lesson:${lesson.id}:homework`)) return;

    send('post', route('teacher.lessons.homework', lesson.id), { file }, `lesson:${lesson.id}:homework`, { forceFormData: true });
}

function saveHomeworkField(lesson, payload) {
    send('post', route('teacher.lessons.homework', lesson.id), payload, `lesson:${lesson.id}:homework`);
}

// ─── Unit exams ──────────────────────────────────────────────────────────────
function createQuiz(unit) {
    // The settings endpoint is a full form — post the same defaults the skeleton
    // generator uses so the teacher lands straight in the question builder.
    router.post(route('teacher.quizzes.store', unit.id), {
        title:              `اختبار ${unit.title}`,
        time_limit_minutes: null,
        available_from:     null,
        available_until:    null,
        passing_score:      60,
        is_active:          false,
    }, { preserveScroll: true });
}

async function uploadPaperExam(unit, event) {
    const file = takeFile(event);
    if (!file) return;

    if (props.directUploads.enabled) {
        await uploadDirect(
            'exam',
            unit.id,
            file,
            route('teacher.units.paper-exam', unit.id),
            `unit:${unit.id}:paper`,
        );
        return;
    }

    if (blockUnconfiguredServerlessUpload(`unit:${unit.id}:paper`)) return;

    send('post', route('teacher.units.paper-exam', unit.id), { file }, `unit:${unit.id}:paper`, { forceFormData: true });
}

function savePaperExamField(unit, payload) {
    send('post', route('teacher.units.paper-exam', unit.id), payload, `unit:${unit.id}:paper`);
}

async function removeSheet(sheet, message) {
    const ok = await confirm({
        title: 'حذف الملف',
        message,
        confirmLabel: 'حذف',
        variant: 'danger',
    });
    if (!ok) return;
    send('delete', route('teacher.worksheets.destroy', { id: sheet.id }), {}, `sheet:${sheet.id}`);
}

// ─── Presentation helpers ────────────────────────────────────────────────────
function takeFile(event) {
    const file = event.target.files?.[0] ?? null;
    event.target.value = '';   // so picking the same file twice still fires
    return file;
}

function blockUnconfiguredServerlessUpload(key) {
    if (!props.directUploads.serverless) return false;

    rowErrors.value[key] = {
        upload: 'تخزين الملفات غير مهيأ في بيئة الإنتاج بعد.',
    };

    return true;
}

async function uploadDirect(kind, targetId, file, finalizeUrl, key) {
    rowErrors.value[key] = {};

    if (file.size > props.directUploads.max_bytes) {
        rowErrors.value[key] = { file: 'حجم الملف يجب ألا يتجاوز 25 ميجابايت.' };
        return;
    }

    busy.value = key;

    try {
        const pathname = directUploadPath(kind, targetId, file.name);
        const { data } = await axios.post(props.directUploads.authorize_url, {
            kind,
            target_id: targetId,
            pathname,
            file_size: file.size,
        });

        const { data: presigned } = await axios.post(props.directUploads.handle_url, {
            pathname,
            authorization: data.authorization,
        });
        const uploadResponse = await fetch(presigned.upload_url, {
            method: 'PUT',
            body: file,
        });

        if (!uploadResponse.ok) {
            throw new Error('Blob upload failed.');
        }

        const blob = await uploadResponse.json();

        if (!blob?.url || !blob?.pathname) {
            throw new Error('Blob upload returned an invalid response.');
        }

        send('post', finalizeUrl, {
            blob_url: blob.url,
            blob_pathname: blob.pathname,
        }, key);
    } catch (error) {
        const validationErrors = error.response?.data?.errors;
        const firstValidationError = validationErrors
            ? Object.values(validationErrors).flat()[0]
            : null;

        rowErrors.value[key] = {
            upload: firstValidationError
                ?? error.response?.data?.message
                ?? 'تعذر رفع الملف. حاول مرة أخرى.',
        };
        busy.value = null;
    }
}

function directUploadPath(kind, targetId, originalName) {
    const cleanName = originalName
        .normalize('NFKC')
        .replace(/[^\p{L}\p{N}._-]+/gu, '-')
        .replace(/^-+|-+$/g, '')
        .slice(-160) || 'upload.bin';

    const nonce = globalThis.crypto?.randomUUID?.()
        ?? `${Date.now()}-${Math.random().toString(16).slice(2)}`;

    return `curriculum/${props.assignment.teacher.id}/${kind}/${targetId}/${nonce}-${cleanName}`;
}

function fileName(path) {
    if (!path) return '';
    try {
        return decodeURIComponent(path.split('/').pop());
    } catch {
        return path.split('/').pop();
    }
}

function formatDuration(seconds) {
    if (!seconds) return 'المدة غير محددة';
    const minutes = Math.floor(seconds / 60);
    const hours   = Math.floor(minutes / 60);
    return hours > 0 ? `${hours}س ${minutes % 60}د` : `${minutes} دقيقة`;
}

const TONES = {
    ok:      'bg-green-100 text-green-700 ring-green-500/20 dark:bg-green-950/50 dark:text-green-300 dark:ring-green-500/25',
    partial: 'bg-accent-100 text-accent-800 ring-accent-500/25 dark:bg-accent-950/50 dark:text-accent-300 dark:ring-accent-500/25',
    off:     'bg-surface-100 text-surface-450 ring-surface-300/50 dark:bg-surface-800 dark:text-surface-500 dark:ring-surface-700/60',
};

/**
 * The five chips on the collapsed row. The electronic exam gets a middle state:
 * a skeleton quiz exists but is born inactive and empty, and a plain green chip
 * would tell the teacher it is done when no student can sit it.
 */
function chipsFor(unit) {
    const quiz = unit.electronic_exam;

    return [
        { key: 'video',    label: 'فيديو',    icon: 'video',      tone: unit.readiness.video ? 'ok' : 'off',    title: 'فيديو الشرح في كل درس' },
        { key: 'booklet',  label: 'ملزمة',    icon: 'book',       tone: unit.readiness.booklet ? 'ok' : 'off',  title: 'ملزمة الشرح في كل درس' },
        { key: 'homework', label: 'واجب',     icon: 'attachment', tone: unit.readiness.homework ? 'ok' : 'off', title: 'واجب لكل درس' },
        {
            key: 'quiz', label: 'إلكتروني', icon: 'calculator',
            tone:  quiz ? (quiz.is_ready ? 'ok' : 'partial') : 'off',
            title: quiz ? (quiz.is_ready ? 'النموذج الإلكتروني جاهز' : 'النموذج الإلكتروني بحاجة إلى أسئلة أو تفعيل') : 'لا يوجد نموذج إلكتروني',
        },
        { key: 'paper', label: 'ورقي', icon: 'file', tone: unit.readiness.paper_exam ? 'ok' : 'off', title: 'النموذج الورقي للاختبار' },
    ];
}

/** How much of the unit's upload work is done — video + ملزمة + واجب per lesson. */
function unitProgress(unit) {
    if (!unit.lessons.length) return 0;
    const done = unit.lessons.filter((lesson) => lesson.has_video && lesson.has_booklet && lesson.homework).length;
    return Math.round((done / unit.lessons.length) * 100);
}

const SLOT_HEAD  = 'flex items-center gap-1.5 text-[11px] font-bold text-surface-500 dark:text-surface-400 mb-2';
const SLOT_BOX   = 'rounded-xl border border-surface-200 dark:border-surface-700/70 bg-surface-50/70 dark:bg-surface-900/40 p-3 min-w-0';
const SLOT_EMPTY = 'w-full rounded-xl border-2 border-dashed border-surface-300 dark:border-surface-700 p-3 min-h-[76px] flex flex-col items-center justify-center gap-1 text-center cursor-pointer transition-colors hover:border-accent-500 hover:bg-accent-50/50 dark:hover:border-accent-500/70 dark:hover:bg-accent-950/20';
</script>

<template>
    <DashboardLayout>
        <Head :title="`بناء المنهج — ${assignment.subject?.name ?? ''}`" />

        <div class="space-y-6">
            <!-- ── Header ─────────────────────────────────────────────── -->
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <nav class="text-xs text-surface-400 mb-1 flex items-center gap-1 flex-wrap">
                        <Link :href="route('teacher.teaching-schedule')" class="hover:text-primary-500">جدول التدريس</Link>
                        <span>/</span>
                        <span class="text-surface-500 dark:text-surface-400">بناء المنهج</span>
                    </nav>
                    <h1 class="text-2xl sm:text-3xl font-black text-surface-900 dark:text-white flex items-center gap-2 flex-wrap">
                        <Icon name="courses" class="w-7 h-7 text-primary-500 shrink-0" />
                        <span>{{ assignment.subject?.name }}</span>
                        <span v-if="assignment.grade" class="badge-primary text-xs">{{ assignment.grade.name }}</span>
                    </h1>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                        الفصول والوحدات والدروس واختبارات كل وحدة — {{ assignment.teacher?.name }}
                    </p>
                </div>

                <button type="button" class="btn-primary btn-sm shrink-0" @click="showSkeleton = !showSkeleton">
                    <Icon name="plus" class="w-4 h-4" />
                    <span>{{ showSkeleton ? 'إغلاق مولّد الهيكل' : 'إنشاء هيكل الفصل' }}</span>
                </button>
            </header>

            <!-- ── No academic calendar ───────────────────────────────── -->
            <div v-if="!terms.length" class="alert-warn">
                <Icon name="calendar" class="w-5 h-5 shrink-0" />
                <span>لا توجد فصول دراسية معرّفة على المنصة بعد. اطلب من إدارة المنصة إضافة الفصول الدراسية أولاً.</span>
            </div>

            <!-- ── Term tabs ──────────────────────────────────────────── -->
            <div v-else class="flex gap-2 overflow-x-auto no-scrollbar pb-1">
                <button
                    v-for="term in terms"
                    :key="term.id"
                    type="button"
                    class="shrink-0 rounded-xl border-2 px-4 py-2.5 text-start transition-all duration-200"
                    :class="term.id === activeTermId
                        ? 'border-accent-500 bg-accent-50 dark:bg-accent-950/30 shadow-glow-accent'
                        : 'border-surface-200 dark:border-surface-700 hover:border-accent-400/60 bg-white/60 dark:bg-surface-900/40'"
                    @click="goToTerm(term.id)"
                >
                    <span class="flex items-center gap-2">
                        <span class="text-sm font-bold text-surface-900 dark:text-white">{{ term.full_name }}</span>
                        <span v-if="term.is_current" class="badge-green text-[10px]">الحالي</span>
                    </span>
                    <span class="block text-[11px] mt-0.5" :class="term.units_count ? 'text-primary-600 dark:text-primary-400' : 'text-surface-400'">
                        {{ term.units_count ? `${term.units_count} وحدة` : 'لا توجد وحدات' }}
                    </span>
                </button>
            </div>

            <!-- ── Summary strip ──────────────────────────────────────── -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <StatCard label="الوحدات" :value="stats.units" icon="courses" tone="primary" />
                <StatCard label="الدروس" :value="stats.lessons" icon="video" tone="accent" />
                <StatCard label="دروس مكتملة" :value="stats.complete_lessons" icon="success" tone="green" hint="فيديو + ملزمة + واجب" />
                <StatCard label="اختبارات جاهزة" :value="stats.ready_exams" icon="certificate" tone="primary" hint="إلكتروني مفعّل بأسئلة + ورقي مرفوع" />
            </div>

            <!-- ── Skeleton generator ─────────────────────────────────── -->
            <section v-if="showSkeleton && terms.length" class="card p-5 space-y-4">
                <div>
                    <h2 class="font-bold text-lg text-surface-900 dark:text-white">إنشاء هيكل الفصل</h2>
                    <p class="input-hint">
                        يُنشئ الوحدات ودروسها فارغة ومرقّمة، ومعها نموذج اختبار إلكتروني لكل وحدة، ثم ترفع أنت الفيديو والملزمة والواجب في كل درس.
                    </p>
                </div>

                <div class="grid sm:grid-cols-3 gap-4">
                    <div>
                        <label for="skeleton-term" class="input-label">الفصل الدراسي</label>
                        <select id="skeleton-term" v-model.number="skeleton.academic_term_id" class="input">
                            <option v-for="term in terms" :key="term.id" :value="term.id">{{ term.full_name }}</option>
                        </select>
                        <p v-if="skeleton.errors.academic_term_id" class="error-msg">{{ skeleton.errors.academic_term_id }}</p>
                    </div>

                    <div>
                        <label for="units-count" class="input-label">عدد الوحدات</label>
                        <input id="units-count" v-model.number="skeleton.units_count" type="number" min="1" max="20" class="input" />
                        <p v-if="skeleton.errors.units_count" class="error-msg">{{ skeleton.errors.units_count }}</p>
                    </div>

                    <div>
                        <label for="lessons-count" class="input-label">عدد الدروس في كل وحدة</label>
                        <input id="lessons-count" v-model.number="skeleton.lessons_per_unit" type="number" min="1" max="20" class="input" />
                        <p v-if="skeleton.errors.lessons_per_unit" class="error-msg">{{ skeleton.errors.lessons_per_unit }}</p>
                    </div>
                </div>

                <div class="alert-info text-xs">
                    <Icon name="info" class="w-4 h-4 shrink-0" />
                    <span>الإضافة تتم بعد الموجود: الترقيم يكمل من آخر وحدة في الفصل، ولا يُحذف أي شيء سبق بناؤه.</span>
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" class="btn-ghost btn-sm" @click="showSkeleton = false">إلغاء</button>
                    <button type="button" class="btn-primary btn-sm" :disabled="skeleton.processing" @click="generateSkeleton">
                        {{ skeleton.processing ? 'جارٍ الإنشاء...' : `إنشاء ${skeleton.units_count || 0} وحدة` }}
                    </button>
                </div>
            </section>

            <!-- ── Units accordion ────────────────────────────────────── -->
            <div v-if="units.length" class="space-y-3">
                <section v-for="(unit, unitIndex) in units" :key="unit.id" class="card">
                    <!-- Collapsed row -->
                    <div class="p-4 flex flex-wrap items-center gap-3">
                        <button
                            type="button"
                            class="flex items-center gap-3 flex-1 min-w-0 text-start"
                            @click="expanded[unit.id] = !expanded[unit.id]"
                        >
                            <span class="w-10 h-10 rounded-xl bg-primary-50 dark:bg-primary-950/60 text-primary-600 dark:text-primary-300 font-black flex items-center justify-center shrink-0">
                                {{ unit.order }}
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-surface-900 dark:text-white truncate">{{ unit.title }}</span>
                                    <span v-if="!unit.is_published" class="badge-gray text-[10px]">مخفية</span>
                                </span>
                                <span class="block text-[11px] text-surface-400 mt-0.5">
                                    {{ unit.lessons_count }} درس · اكتمل رفع {{ unitProgress(unit) }}٪
                                </span>
                                <span class="progress-bar block mt-1.5 max-w-[220px]">
                                    <span class="progress-bar-fill block" :style="{ width: `${unitProgress(unit)}%` }"></span>
                                </span>
                            </span>

                            <Icon
                                name="arrowRight"
                                class="w-4 h-4 text-surface-400 shrink-0 transition-transform duration-200"
                                :class="expanded[unit.id] ? '-rotate-90' : 'rotate-90'"
                            />
                        </button>

                        <!-- Readiness at a glance — no need to expand -->
                        <div class="flex flex-wrap gap-1.5 w-full sm:w-auto">
                            <span
                                v-for="chip in chipsFor(unit)"
                                :key="chip.key"
                                :title="chip.title"
                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold ring-1"
                                :class="TONES[chip.tone]"
                            >
                                <Icon :name="chip.icon" class="w-3 h-3" />
                                <span>{{ chip.label }}</span>
                            </span>
                        </div>
                    </div>

                    <!-- Expanded body -->
                    <div v-if="expanded[unit.id]" class="border-t border-surface-100 dark:border-surface-800 p-4 space-y-5">
                        <!-- Unit settings -->
                        <div class="grid md:grid-cols-2 gap-3">
                            <div>
                                <label :for="`unit-title-${unit.id}`" class="input-label">عنوان الوحدة</label>
                                <input
                                    :id="`unit-title-${unit.id}`"
                                    v-model="unitDrafts[unit.id].title"
                                    type="text"
                                    class="input"
                                    @keyup.enter="saveUnit(unit)"
                                    @blur="saveUnit(unit)"
                                />
                            </div>
                            <div>
                                <label :for="`unit-desc-${unit.id}`" class="input-label">وصف مختصر (اختياري)</label>
                                <input
                                    :id="`unit-desc-${unit.id}`"
                                    v-model="unitDrafts[unit.id].description"
                                    type="text"
                                    class="input"
                                    placeholder="ما الذي تغطيه هذه الوحدة؟"
                                    @keyup.enter="saveUnit(unit)"
                                    @blur="saveUnit(unit)"
                                />
                            </div>
                        </div>
                        <p v-if="firstError(`unit:${unit.id}`)" class="error-msg">{{ firstError(`unit:${unit.id}`) }}</p>

                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="btn-sm rounded-lg font-semibold transition-colors"
                                :class="unit.is_published
                                    ? 'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-950/50 dark:text-green-300'
                                    : 'bg-surface-100 text-surface-500 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-400'"
                                @click="togglePublished(unit)"
                            >
                                {{ unit.is_published ? 'منشورة للطلاب' : 'مخفية عن الطلاب' }}
                            </button>

                            <div class="flex items-center gap-1 ms-auto">
                                <button type="button" class="btn-ghost btn-sm" title="تحريك لأعلى" aria-label="تحريك الوحدة لأعلى" :disabled="unitIndex === 0" @click="moveUnit(unit, 'up')">
                                    <Icon name="arrowRight" class="w-4 h-4 -rotate-90" />
                                </button>
                                <button type="button" class="btn-ghost btn-sm" title="تحريك لأسفل" aria-label="تحريك الوحدة لأسفل" :disabled="unitIndex === units.length - 1" @click="moveUnit(unit, 'down')">
                                    <Icon name="arrowRight" class="w-4 h-4 rotate-90" />
                                </button>
                                <button type="button" class="btn-ghost btn-sm text-red-500 hover:bg-red-500/10" title="حذف الوحدة" aria-label="حذف الوحدة" @click="removeUnit(unit)">
                                    <Icon name="trash" class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <!-- ── Lessons ────────────────────────────────── -->
                        <div class="space-y-3">
                            <h3 class="text-sm font-bold text-surface-700 dark:text-surface-200 flex items-center gap-2">
                                <Icon name="video" class="w-4 h-4 text-primary-500" />
                                <span>دروس الوحدة</span>
                                <span class="badge-gray text-[10px]">{{ unit.lessons_count }}</span>
                            </h3>

                            <article
                                v-for="lesson in unit.lessons"
                                :key="lesson.id"
                                class="rounded-2xl border border-surface-200 dark:border-surface-700/70 p-3 sm:p-4 space-y-3"
                            >
                                <!-- Lesson header -->
                                <div class="flex items-center gap-2">
                                    <span class="w-8 h-8 rounded-lg bg-surface-100 dark:bg-surface-800 text-surface-500 dark:text-surface-300 text-xs font-black flex items-center justify-center shrink-0">
                                        {{ lesson.order }}
                                    </span>
                                    <input
                                        v-model="lessonDrafts[lesson.id].title"
                                        type="text"
                                        class="input py-1.5 text-sm font-bold flex-1 min-w-0"
                                        placeholder="عنوان الدرس"
                                        @keyup.enter="saveLessonTitle(lesson)"
                                        @blur="saveLessonTitle(lesson)"
                                    />
                                    <button
                                        type="button"
                                        class="btn-sm rounded-lg shrink-0"
                                        :class="lesson.is_free_preview
                                            ? 'bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-300'
                                            : 'bg-surface-100 text-surface-450 dark:bg-surface-800 dark:text-surface-500'"
                                        :title="lesson.is_free_preview ? 'معاينة مجانية — يفتحه أي طالب' : 'للمشتركين فقط'"
                                        @click="togglePreview(lesson)"
                                    >
                                        <Icon :name="lesson.is_free_preview ? 'unlock' : 'lock'" class="w-3.5 h-3.5" />
                                    </button>
                                    <button v-if="!lesson.is_live_recording" type="button" class="btn-ghost btn-sm text-red-500 hover:bg-red-500/10 shrink-0" title="حذف الدرس" @click="removeLesson(lesson)">
                                        <Icon name="trash" class="w-4 h-4" />
                                    </button>
                                </div>

                                <!-- The three slots -->
                                <div class="grid sm:grid-cols-3 gap-3">
                                    <!-- فيديو الشرح -->
                                    <div class="min-w-0">
                                        <div :class="SLOT_HEAD"><Icon name="video" class="w-3.5 h-3.5" /><span>فيديو الشرح</span></div>

                                        <div v-if="editingVideo[lesson.id]" :class="SLOT_BOX">
                                            <input
                                                :id="`video-${lesson.id}`"
                                                v-model="lessonDrafts[lesson.id].video_url"
                                                type="url"
                                                dir="ltr"
                                                class="input py-1.5 text-xs"
                                                placeholder="https://..."
                                                @keyup.enter="saveVideo(lesson)"
                                            />
                                            <input
                                                v-model.number="lessonDrafts[lesson.id].duration_seconds"
                                                type="number"
                                                min="0"
                                                class="input py-1.5 text-xs mt-2"
                                                placeholder="المدة بالثواني"
                                            />
                                            <p class="input-hint">المدة تُستخدم لحساب تقدّم الطالب.</p>
                                            <div class="flex gap-2 mt-2">
                                                <button type="button" class="btn-primary btn-sm flex-1" :disabled="busy === `lesson:${lesson.id}:video`" @click="saveVideo(lesson)">حفظ</button>
                                                <button type="button" class="btn-ghost btn-sm" @click="editingVideo[lesson.id] = false">إلغاء</button>
                                            </div>
                                        </div>

                                        <div v-else-if="lesson.has_video" :class="SLOT_BOX">
                                            <a :href="lesson.video_url" target="_blank" rel="noopener" dir="ltr" class="block text-xs text-primary-600 dark:text-primary-400 hover:underline truncate">
                                                {{ lesson.video_url }}
                                            </a>
                                            <p class="text-[11px] text-surface-400 mt-1">{{ formatDuration(lesson.duration_seconds) }}</p>
                                            <div v-if="!lesson.is_live_recording" class="flex gap-1 mt-2">
                                                <button type="button" class="btn-ghost btn-sm" @click="openVideo(lesson)">تعديل</button>
                                                <button type="button" class="btn-ghost btn-sm text-red-500" @click="clearVideo(lesson)">إزالة</button>
                                            </div>
                                            <p v-else class="text-[11px] text-primary-500 mt-2 font-semibold">تسجيل حصة محمي — الحذف متاح للإدارة فقط</p>
                                        </div>

                                        <button v-else type="button" :class="SLOT_EMPTY" aria-label="إضافة فيديو للدرس" @click="openVideo(lesson)">
                                            <Icon name="plus" class="w-5 h-5 text-surface-400" />
                                            <span class="text-[11px] font-semibold text-surface-500 dark:text-surface-400">أضف رابط الفيديو</span>
                                        </button>

                                        <p v-if="firstError(`lesson:${lesson.id}:video`)" class="error-msg">{{ firstError(`lesson:${lesson.id}:video`) }}</p>
                                    </div>

                                    <!-- ملزمة الشرح -->
                                    <div class="min-w-0">
                                        <div :class="SLOT_HEAD"><Icon name="book" class="w-3.5 h-3.5" /><span>ملزمة الشرح</span></div>

                                        <div v-if="lesson.has_booklet" :class="SLOT_BOX">
                                            <a :href="lesson.booklet_path" target="_blank" rel="noopener" class="flex items-center gap-1.5 text-xs text-primary-600 dark:text-primary-400 hover:underline min-w-0">
                                                <Icon name="download" class="w-3.5 h-3.5 shrink-0" />
                                                <span class="truncate">{{ fileName(lesson.booklet_path) }}</span>
                                            </a>
                                            <label class="btn-ghost btn-sm mt-2 cursor-pointer inline-flex">
                                                <input type="file" class="hidden" @change="uploadBooklet(lesson, $event)" />
                                                <span>استبدال الملف</span>
                                            </label>
                                        </div>

                                        <label v-else :class="SLOT_EMPTY">
                                            <input type="file" class="hidden" @change="uploadBooklet(lesson, $event)" />
                                            <Icon name="plus" class="w-5 h-5 text-surface-400" />
                                            <span class="text-[11px] font-semibold text-surface-500 dark:text-surface-400">ارفع الملزمة</span>
                                            <span class="text-[10px] text-surface-400">أي صيغة حتى 25 ميجابايت</span>
                                        </label>

                                        <p v-if="firstError(`lesson:${lesson.id}:booklet`)" class="error-msg">{{ firstError(`lesson:${lesson.id}:booklet`) }}</p>
                                    </div>

                                    <!-- الواجب -->
                                    <div class="min-w-0">
                                        <div :class="SLOT_HEAD"><Icon name="attachment" class="w-3.5 h-3.5" /><span>الواجب</span></div>

                                        <div v-if="lesson.homework" :class="SLOT_BOX">
                                            <a :href="lesson.homework.file_path" target="_blank" rel="noopener" class="flex items-center gap-1.5 text-xs text-primary-600 dark:text-primary-400 hover:underline min-w-0">
                                                <Icon name="download" class="w-3.5 h-3.5 shrink-0" />
                                                <span class="truncate">{{ fileName(lesson.homework.file_path) }}</span>
                                            </a>

                                            <div class="grid grid-cols-2 gap-2 mt-2">
                                                <input
                                                    v-model="lessonDrafts[lesson.id].due_date"
                                                    type="date"
                                                    class="input py-1 text-[11px]"
                                                    title="آخر موعد للتسليم"
                                                    @change="saveHomeworkField(lesson, { due_date: lessonDrafts[lesson.id].due_date })"
                                                />
                                                <input
                                                    v-model="lessonDrafts[lesson.id].max_score"
                                                    type="number"
                                                    min="1"
                                                    max="1000"
                                                    class="input py-1 text-[11px]"
                                                    placeholder="الدرجة"
                                                    title="الدرجة النهائية"
                                                    @change="saveHomeworkField(lesson, { max_score: lessonDrafts[lesson.id].max_score })"
                                                />
                                            </div>

                                            <p v-if="lesson.homework.submissions_count" class="text-[10px] text-surface-400 mt-1.5">
                                                {{ lesson.homework.submissions_count }} تسليم من الطلاب
                                            </p>

                                            <div class="flex gap-1 mt-2">
                                                <label class="btn-ghost btn-sm cursor-pointer inline-flex">
                                                    <input type="file" class="hidden" @change="uploadHomework(lesson, $event)" />
                                                    <span>استبدال</span>
                                                </label>
                                                <button type="button" class="btn-ghost btn-sm text-red-500" @click="removeSheet(lesson.homework, 'حذف واجب هذا الدرس؟')">حذف</button>
                                            </div>
                                        </div>

                                        <label v-else :class="SLOT_EMPTY">
                                            <input type="file" class="hidden" @change="uploadHomework(lesson, $event)" />
                                            <Icon name="plus" class="w-5 h-5 text-surface-400" />
                                            <span class="text-[11px] font-semibold text-surface-500 dark:text-surface-400">ارفع ملف الواجب</span>
                                            <span class="text-[10px] text-surface-400">الموعد والدرجة بعد الرفع</span>
                                        </label>

                                        <p v-if="firstError(`lesson:${lesson.id}:homework`)" class="error-msg">{{ firstError(`lesson:${lesson.id}:homework`) }}</p>
                                    </div>
                                </div>

                                <p v-if="firstError(`lesson:${lesson.id}`)" class="error-msg">{{ firstError(`lesson:${lesson.id}`) }}</p>
                            </article>

                            <!-- Add lesson -->
                            <div class="flex flex-wrap gap-2">
                                <input
                                    v-model="newLesson[unit.id]"
                                    type="text"
                                    class="input flex-1 min-w-[200px] py-2 text-sm"
                                    placeholder="عنوان درس جديد"
                                    @keyup.enter="addLesson(unit)"
                                />
                                <button type="button" class="btn-outline btn-sm" :disabled="busy === `unit:${unit.id}:lesson`" @click="addLesson(unit)">
                                    <Icon name="plus" class="w-4 h-4" />
                                    <span>إضافة درس</span>
                                </button>
                            </div>
                            <p v-if="firstError(`unit:${unit.id}:lesson`)" class="error-msg">{{ firstError(`unit:${unit.id}:lesson`) }}</p>
                        </div>

                        <!-- ── Unit exam ──────────────────────────────── -->
                        <div class="divider pt-4">
                            <h3 class="text-sm font-bold text-surface-700 dark:text-surface-200 flex items-center gap-2 mb-3">
                                <Icon name="certificate" class="w-4 h-4 text-accent-500" />
                                <span>اختبار الوحدة</span>
                            </h3>

                            <div class="grid md:grid-cols-2 gap-3">
                                <!-- النموذج الإلكتروني -->
                                <div class="min-w-0">
                                    <div :class="SLOT_HEAD"><Icon name="calculator" class="w-3.5 h-3.5" /><span>النموذج الإلكتروني</span></div>

                                    <div v-if="unit.electronic_exam" :class="SLOT_BOX">
                                        <div class="flex items-start justify-between gap-2">
                                            <h4 class="text-sm font-bold text-surface-900 dark:text-white truncate">{{ unit.electronic_exam.title }}</h4>
                                            <span class="text-[10px] font-bold rounded-full px-2 py-0.5 ring-1 shrink-0" :class="unit.electronic_exam.is_ready ? TONES.ok : TONES.partial">
                                                {{ unit.electronic_exam.is_ready ? 'جاهز' : 'غير مفعّل' }}
                                            </span>
                                        </div>

                                        <ul class="text-[11px] text-surface-500 dark:text-surface-400 mt-2 space-y-1">
                                            <li>{{ unit.electronic_exam.questions_count }} سؤال · درجة النجاح {{ unit.electronic_exam.passing_score }}٪</li>
                                            <li>{{ unit.electronic_exam.time_limit_minutes ? `المدة ${unit.electronic_exam.time_limit_minutes} دقيقة` : 'المدة: بلا حد' }}</li>
                                            <li>{{ unit.electronic_exam.window_label }}</li>
                                        </ul>

                                        <p v-if="!unit.electronic_exam.is_ready" class="text-[11px] text-accent-700 dark:text-accent-300 mt-2">
                                            {{ unit.electronic_exam.questions_count ? 'الاختبار غير مفعّل — فعّله من المُنشئ ليظهر للطلاب.' : 'لا توجد أسئلة بعد — أضف الأسئلة ثم فعّل الاختبار.' }}
                                        </p>

                                        <Link :href="route('teacher.quizzes.edit', unit.electronic_exam.id)" class="btn-primary btn-sm w-full mt-3">
                                            <Icon name="edit" class="w-4 h-4" />
                                            <span>{{ unit.electronic_exam.questions_count ? 'تعديل الأسئلة والإعدادات' : 'أضف الأسئلة' }}</span>
                                        </Link>
                                    </div>

                                    <button v-else type="button" :class="SLOT_EMPTY" aria-label="إضافة اختبار للوحدة" @click="createQuiz(unit)">
                                        <Icon name="plus" class="w-5 h-5 text-surface-400" />
                                        <span class="text-[11px] font-semibold text-surface-500 dark:text-surface-400">أنشئ النموذج الإلكتروني</span>
                                        <span class="text-[10px] text-surface-400">أسئلة اختيار من متعدد بمدة ونافذة إتاحة</span>
                                    </button>
                                </div>

                                <!-- النموذج الورقي -->
                                <div class="min-w-0">
                                    <div :class="SLOT_HEAD"><Icon name="file" class="w-3.5 h-3.5" /><span>النموذج الورقي</span></div>

                                    <div v-if="unit.paper_exam" :class="SLOT_BOX">
                                        <a :href="unit.paper_exam.file_path" target="_blank" rel="noopener" class="flex items-center gap-1.5 text-xs text-primary-600 dark:text-primary-400 hover:underline min-w-0">
                                            <Icon name="download" class="w-3.5 h-3.5 shrink-0" />
                                            <span class="truncate">{{ fileName(unit.paper_exam.file_path) }}</span>
                                        </a>

                                        <div class="grid grid-cols-2 gap-2 mt-2">
                                            <input
                                                v-model="unitDrafts[unit.id].paper_due_date"
                                                type="date"
                                                class="input py-1 text-[11px]"
                                                title="آخر موعد للتسليم"
                                                @change="savePaperExamField(unit, { due_date: unitDrafts[unit.id].paper_due_date })"
                                            />
                                            <input
                                                v-model="unitDrafts[unit.id].paper_max_score"
                                                type="number"
                                                min="1"
                                                max="1000"
                                                class="input py-1 text-[11px]"
                                                placeholder="الدرجة"
                                                title="الدرجة النهائية"
                                                @change="savePaperExamField(unit, { max_score: unitDrafts[unit.id].paper_max_score })"
                                            />
                                        </div>

                                        <p v-if="unit.paper_exam.submissions_count" class="text-[10px] text-surface-400 mt-1.5">
                                            {{ unit.paper_exam.submissions_count }} تسليم من الطلاب
                                        </p>

                                        <div class="flex gap-1 mt-2">
                                            <label class="btn-ghost btn-sm cursor-pointer inline-flex">
                                                <input type="file" class="hidden" @change="uploadPaperExam(unit, $event)" />
                                                <span>استبدال</span>
                                            </label>
                                            <button type="button" class="btn-ghost btn-sm text-red-500" @click="removeSheet(unit.paper_exam, 'حذف النموذج الورقي لهذه الوحدة؟')">حذف</button>
                                        </div>
                                    </div>

                                    <label v-else :class="SLOT_EMPTY">
                                        <input type="file" class="hidden" @change="uploadPaperExam(unit, $event)" />
                                        <Icon name="plus" class="w-5 h-5 text-surface-400" />
                                        <span class="text-[11px] font-semibold text-surface-500 dark:text-surface-400">ارفع النموذج الورقي</span>
                                        <span class="text-[10px] text-surface-400">يطبعه الطالب ويرفع إجابته</span>
                                    </label>

                                    <p v-if="firstError(`unit:${unit.id}:paper`)" class="error-msg">{{ firstError(`unit:${unit.id}:paper`) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- ── Empty term ─────────────────────────────────────────── -->
            <div v-else-if="terms.length" class="card p-10 sm:p-14 text-center">
                <Icon name="courses" class="w-12 h-12 text-surface-300 dark:text-surface-700 mx-auto mb-4" />
                <h3 class="font-bold text-lg text-surface-800 dark:text-surface-100 mb-2">لا توجد وحدات في هذا الفصل بعد</h3>
                <p class="text-sm text-surface-400 max-w-md mx-auto mb-6">
                    ابدأ بمولّد الهيكل: حدد عدد الوحدات وعدد الدروس في كل وحدة، وسيُنشئ لك الفصل كاملاً مرقّماً وجاهزاً لرفع الفيديوهات والملازم والواجبات.
                </p>
                <div class="flex flex-wrap justify-center gap-2">
                    <button type="button" class="btn-primary" @click="showSkeleton = true">
                        <Icon name="plus" class="w-4 h-4" />
                        <span>إنشاء هيكل الفصل</span>
                    </button>
                    <button type="button" class="btn-outline" @click="showAddUnit = true">إضافة وحدة واحدة</button>
                </div>
            </div>

            <!-- ── Add a single unit ──────────────────────────────────── -->
            <section v-if="terms.length" class="card p-4">
                <button v-if="!showAddUnit" type="button" class="w-full flex items-center justify-center gap-2 py-2 text-sm font-semibold text-surface-500 hover:text-primary-600 dark:text-surface-400 dark:hover:text-primary-400 transition-colors" @click="showAddUnit = true">
                    <Icon name="plus" class="w-4 h-4" />
                    <span>إضافة وحدة إلى هذا الفصل</span>
                </button>

                <form v-else class="space-y-3" @submit.prevent="addUnit">
                    <div class="grid md:grid-cols-2 gap-3">
                        <div>
                            <label for="new-unit-title" class="input-label">عنوان الوحدة</label>
                            <input id="new-unit-title" v-model="newUnit.title" type="text" class="input" placeholder="الوحدة الخامسة: الدوال" required />
                            <p v-if="newUnit.errors.title" class="error-msg">{{ newUnit.errors.title }}</p>
                        </div>
                        <div>
                            <label for="new-unit-desc" class="input-label">وصف مختصر (اختياري)</label>
                            <input id="new-unit-desc" v-model="newUnit.description" type="text" class="input" />
                            <p v-if="newUnit.errors.description" class="error-msg">{{ newUnit.errors.description }}</p>
                        </div>
                    </div>
                    <p v-if="newUnit.errors.academic_term_id" class="error-msg">{{ newUnit.errors.academic_term_id }}</p>
                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" class="btn-ghost btn-sm" @click="showAddUnit = false">إلغاء</button>
                        <button type="submit" class="btn-primary btn-sm" :disabled="newUnit.processing">
                            {{ newUnit.processing ? 'جارٍ الإضافة...' : 'إضافة الوحدة' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </DashboardLayout>
</template>
