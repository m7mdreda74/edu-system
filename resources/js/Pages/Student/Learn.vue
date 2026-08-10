<script setup>
import { ref, computed, onUnmounted, watch, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import Plyr from 'plyr';
import 'plyr/dist/plyr.css';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    group:        { type: Object, required: true },
    terms:        { type: Array,  default: () => [] },
    activeTermId: { type: Number, default: null },
    units:        { type: Array,  default: () => [] },
    progress:     { type: Object, required: true },
});

// ── State ──────────────────────────────────────────────────────
const localUnits       = ref([...props.units]);
const activeLessonId   = ref(null);
const expandedUnits    = ref({});      // { [unitId]: true }
const progressPercent  = ref(props.progress.percent);
const certificateReady = ref(props.progress.certificate_ready);
const treeOpen         = ref(true);
const activeTab        = ref('description'); // description | files | questions
const signedVideoUrl   = ref('');
const videoProvider    = ref('');
const isVideoLoading   = ref(false);
const lockNotice       = ref('');

// Local completion mirror, so a lesson ticks green the moment the video ends
// rather than waiting for the next payload.
const localCompleted = ref({});

// The tree flattened once, each lesson carrying its unit — used for "next
// lesson" navigation and for looking the active lesson up by id.
const flatLessons = computed(() =>
    localUnits.value.flatMap(unit => unit.lessons.map(lesson => ({ ...lesson, unit })))
);

const activeLesson = computed(() =>
    flatLessons.value.find(lesson => lesson.id === activeLessonId.value) ?? null
);

const activeUnit   = computed(() => activeLesson.value?.unit ?? null);
const totalLessons = computed(() => flatLessons.value.length);

const isDone = (lesson) => localCompleted.value[lesson.id] ?? lesson.is_completed;

const completedCount = computed(() => flatLessons.value.filter(isDone).length);

const activeTerm = computed(() =>
    props.terms.find(term => term.id === props.activeTermId) ?? null
);

const attachmentCount = computed(() => {
    const lesson = activeLesson.value;
    if (!lesson) return 0;
    return (lesson.booklet_path ? 1 : 0) + (lesson.homework ? 1 : 0);
});

function seedCompleted() {
    const map = {};
    for (const lesson of flatLessons.value) {
        map[lesson.id] = lesson.is_completed;
    }
    localCompleted.value = map;
}

/** Open on the first unfinished lesson the student is actually allowed to watch. */
function pickDefaultLesson() {
    const open   = flatLessons.value.filter(lesson => !lesson.is_locked);
    const target = open.find(lesson => !lesson.is_completed) ?? open[0] ?? flatLessons.value[0] ?? null;

    activeLessonId.value = target?.id ?? null;

    if (target) {
        expandedUnits.value = { ...expandedUnits.value, [target.unit.id]: true };
    }
}

seedCompleted();
pickDefaultLesson();

// ── Term tabs ──────────────────────────────────────────────────
function selectTerm(termId) {
    if (termId === props.activeTermId) return;

    router.get(
        route('student.learn', { groupId: props.group.id }),
        { term: termId },
        { preserveState: true, preserveScroll: true },
    );
}

// A new payload (term switch, or a redirect back from an upload) replaces the
// tree; the lesson being watched survives it whenever it is still on screen.
watch(() => props.units, (next) => {
    localUnits.value = [...next];
    seedCompleted();

    if (!flatLessons.value.some(lesson => lesson.id === activeLessonId.value)) {
        expandedUnits.value = {};
        pickDefaultLesson();
    }
});

watch(() => props.progress, (next) => {
    progressPercent.value  = next.percent;
    certificateReady.value = next.certificate_ready;
});

// ── Units & lessons ────────────────────────────────────────────
function toggleUnit(unitId) {
    expandedUnits.value = { ...expandedUnits.value, [unitId]: !expandedUnits.value[unitId] };
}

function lockReason(unit, lesson) {
    if (unit.is_locked) return 'هذه الوحدة مغلقة — أكمل دروس الوحدة السابقة أولاً.';
    if (lesson)         return 'هذا الدرس مغلق — أكمل الدرس السابق أولاً.';
    return '';
}

function selectLesson(unit, lesson) {
    if (lesson.is_locked) {
        lockNotice.value = lockReason(unit, lesson);
        return;
    }

    lockNotice.value     = '';
    activeLessonId.value = lesson.id;
    activeTab.value      = 'description';
}

const nextLesson = computed(() => {
    const index = flatLessons.value.findIndex(lesson => lesson.id === activeLessonId.value);
    if (index === -1) return null;

    return flatLessons.value.slice(index + 1).find(lesson => !lesson.is_locked) ?? null;
});

const hasLaterLessons = computed(() => {
    const index = flatLessons.value.findIndex(lesson => lesson.id === activeLessonId.value);
    return index !== -1 && index < flatLessons.value.length - 1;
});

function goNextLesson() {
    const target = nextLesson.value;
    if (target) selectLesson(target.unit, target);
}

// ── Unit exams ─────────────────────────────────────────────────
/** Why the "ابدأ الاختبار" button is dead, in the student's own words. */
function examBlockReason(exam) {
    if (!exam.is_open)              return exam.window_label || 'هذا الاختبار غير متاح حالياً.';
    if (exam.questions_count === 0) return 'لم يضف المدرس أسئلة لهذا النموذج بعد.';
    if (exam.attempts_left === 0)   return 'استنفدت كل محاولاتك في هذا الاختبار.';
    return '';
}

// ── Progress Tracking ──────────────────────────────────────────
let progressInterval = null;
let lastReportedSeconds = 0;
const videoRef = ref(null);
let player = null;

function onVideoPlay() {
    // Report progress every 10 seconds while playing
    progressInterval = setInterval(() => {
        if (player && player.playing) {
            reportProgress(Math.floor(player.currentTime));
        }
    }, 10_000);
}

function onVideoEnd() {
    clearInterval(progressInterval);
    if (player) {
        reportProgress(Math.floor(player.duration));
    }
}

function onVideoPause() {
    if (player) {
        reportProgress(Math.floor(player.currentTime));
    }
}

async function reportProgress(watchedSeconds) {
    if (watchedSeconds === lastReportedSeconds) return;
    lastReportedSeconds = watchedSeconds;

    const lesson = activeLesson.value;
    if (!lesson) return;

    try {
        const res = await axios.post(
            route('student.material.progress', {
                groupId:    props.group.id,
                materialId: lesson.id,
            }),
            { watched_seconds: watchedSeconds }
        );

        progressPercent.value  = res.data.progress_percent;
        certificateReady.value = res.data.certificate_ready;

        // Finishing a lesson can unlock the next one and the whole next unit,
        // so the ping hands back the term's tree rebuilt.
        if (Array.isArray(res.data.units)) {
            localUnits.value = res.data.units;

            for (const item of flatLessons.value) {
                localCompleted.value[item.id] = item.is_completed || (localCompleted.value[item.id] ?? false);
            }
        }

        if (watchedSeconds >= lesson.duration_seconds * 0.8) {
            localCompleted.value[lesson.id] = true;
        }
    } catch (e) {
        console.warn('Progress update failed, will retry:', e.message);
    }
}

function formatDuration(seconds) {
    if (!seconds) return '0:00';
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
}

function formatDate(value) {
    if (!value) return '';
    return new Date(value).toLocaleDateString('ar-EG', { year: 'numeric', month: 'long', day: 'numeric' });
}

function formatDateTime(value) {
    if (!value) return '';
    return new Date(value).toLocaleString('ar-EG', { dateStyle: 'medium', timeStyle: 'short' });
}

// Watermark Logic
const watermarkStyle = ref({
    top: '15%',
    left: '15%',
});

let watermarkInterval = null;

function updateWatermarkPosition() {
    const topVal = Math.floor(Math.random() * 60) + 15;
    const leftVal = Math.floor(Math.random() * 60) + 15;
    watermarkStyle.value = {
        top: `${topVal}%`,
        left: `${leftVal}%`,
    };
}

onMounted(() => {
    updateWatermarkPosition();
    watermarkInterval = setInterval(updateWatermarkPosition, 7000);
});

// Q&A Forum Logic
const questions = ref([]);
const newQuestionContent = ref('');
const includeTimestamp = ref(false);
const replyContents = ref({});

async function fetchQuestions() {
    if (!activeLesson.value) return;
    try {
        const res = await axios.get(route('materials.questions.index', { materialId: activeLesson.value.id }));
        questions.value = res.data;
    } catch (e) {
        console.error('Failed to fetch Q&A questions:', e);
    }
}

async function submitQuestion() {
    if (!newQuestionContent.value.trim() || !activeLesson.value) return;
    try {
        let timestamp = null;
        if (includeTimestamp.value && player) {
            timestamp = Math.floor(player.currentTime);
        }

        const res = await axios.post(route('materials.questions.store', { materialId: activeLesson.value.id }), {
            content: newQuestionContent.value,
            video_timestamp: timestamp,
        });

        questions.value.unshift({
            ...res.data,
            replies: [],
        });
        newQuestionContent.value = '';
        includeTimestamp.value = false;
    } catch (e) {
        alert('حدث خطأ أثناء طرح السؤال، يرجى المحاولة لاحقاً.');
    }
}

async function submitReply(questionId) {
    const replyText = replyContents.value[questionId];
    if (!replyText || !replyText.trim() || !activeLesson.value) return;

    try {
        const res = await axios.post(route('materials.questions.store', { materialId: activeLesson.value.id }), {
            content: replyText,
            parent_id: questionId,
        });

        const q = questions.value.find(item => item.id === questionId);
        if (q) {
            if (!q.replies) q.replies = [];
            q.replies.push(res.data);
        }
        replyContents.value[questionId] = '';
    } catch (e) {
        alert('حدث خطأ أثناء إضافة الرد، يرجى المحاولة لاحقاً.');
    }
}

function seekTo(seconds) {
    if (player) {
        player.currentTime = seconds;
        player.play();
    }
}

function formatQuestionTime(seconds) {
    if (seconds === null || seconds === undefined) return '';
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `عند الدقيقة ${m}:${String(s).padStart(2, '0')}`;
}

// Keyed on the id, not on the lesson object: the tree is rebuilt on every
// progress ping and a fresh object would tear the player down mid-playback.
watch(activeLessonId, async (lessonId) => {
    if (player) {
        player.destroy();
        player = null;
    }

    clearInterval(progressInterval);
    lastReportedSeconds  = 0;
    signedVideoUrl.value = '';
    videoProvider.value  = '';
    questions.value      = [];

    const lesson = activeLesson.value;

    if (!lesson) {
        isVideoLoading.value = false;
        return;
    }

    fetchQuestions();

    // A lesson can be booklet-only; there is no signed URL to ask for.
    if (!lesson.has_video) {
        isVideoLoading.value = false;
        return;
    }

    isVideoLoading.value = true;

    try {
        const response = await axios.get(route('student.video.url', { materialId: lesson.id }));
        // The student may have clicked on while the request was in flight.
        if (activeLessonId.value !== lessonId) return;
        videoProvider.value  = response.data.provider ?? 'file';
        // Both 'file' and 'youtube_proxy' providers return a signed_url
        // that points to our server — never the raw external URL.
        signedVideoUrl.value = response.data.signed_url ?? '';
    } catch (e) {
        console.error('Failed to get signed URL:', e.message);
    } finally {
        isVideoLoading.value = false;
    }

    setTimeout(() => {
        if (videoRef.value) {
            player = new Plyr(videoRef.value, {
                autoplay: false,
                playsinline: true,
                controls: [
                    'play-large', 'play', 'progress', 'current-time',
                    'mute', 'volume', 'captions', 'settings', 'pip', 'airplay', 'fullscreen'
                ],
                settings: ['captions', 'quality', 'speed', 'loop'],
                speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 2] },
                keyboard: { focused: true, global: false },
            });

            player.on('play', onVideoPlay);
            player.on('pause', onVideoPause);
            player.on('ended', onVideoEnd);
        }
    }, 100);
}, { immediate: true });

onUnmounted(() => {
    clearInterval(progressInterval);
    clearInterval(watermarkInterval);
    if (player) {
        player.destroy();
    }
});

// ── Answer uploads (homework and paper exams share one endpoint) ─
const submitFiles     = ref({});
const uploadNotices   = ref({});
const uploadingSheet  = ref(null);

function onFileChange(e, sheetId) {
    submitFiles.value[sheetId]   = e.target.files[0] ?? null;
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

    router.post(route('student.worksheets.submit', { groupId: props.group.id, worksheetId: sheetId }), formData, {
        preserveScroll: true,
        onFinish: () => {
            uploadingSheet.value      = null;
            submitFiles.value[sheetId] = null;
        },
    });
}
</script>

<template>
    <div class="min-h-screen bg-surface-950 flex flex-col" dir="rtl" lang="ar">
        <Head :title="`${group.subject?.name ?? 'الدراسة'} — ${group.name}`" />

        <!-- ── Top Bar ───────────────────────────────────────── -->
        <header class="bg-surface-900 border-b border-surface-800 px-4 py-3 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <Link :href="route('dashboard')" class="text-surface-400 hover:text-white transition-colors text-xl">
                    ←
                </Link>
                <div class="min-w-0">
                    <div class="text-white font-bold text-sm line-clamp-1">{{ group.subject?.name }} — {{ group.name }}</div>
                    <div class="text-xs text-surface-400">
                        {{ completedCount }} / {{ totalLessons }} درس مكتمل
                        <span v-if="activeTerm" class="text-surface-500"> · {{ activeTerm.name }}</span>
                    </div>
                </div>
            </div>

            <!-- Overall progress -->
            <div class="hidden sm:flex items-center gap-3 flex-shrink-0">
                <div class="w-32 progress-bar bg-surface-700">
                    <div class="progress-bar-fill" :style="{ width: progressPercent + '%' }"></div>
                </div>
                <span class="text-primary-400 font-bold text-sm">{{ progressPercent }}%</span>

                <button @click="treeOpen = !treeOpen"
                    class="btn-ghost text-sm text-surface-400 hover:text-white px-3 py-1.5 hidden lg:inline-flex">
                    {{ treeOpen ? 'أخفِ المنهج' : 'أظهر المنهج' }}
                </button>
            </div>
        </header>

        <!-- Completion Banner -->
        <Transition enter-active-class="animate-fade-up">
            <div v-if="certificateReady"
                 class="bg-green-600 text-white text-center py-3 px-4 font-bold text-sm flex items-center justify-center gap-3">
                تهانينا! أكملت محتوى المجموعة بنجاح
                <Link :href="route('student.certificate', { groupId: group.id })"
                      class="underline hover:no-underline">
                    احصل على شهادتك
                </Link>
            </div>
        </Transition>

        <!-- Flash from an answer upload -->
        <div v-if="$page.props.flash?.success"
             class="bg-green-500/10 border-b border-green-500/20 text-green-300 text-center py-2.5 px-4 text-sm font-semibold">
            {{ $page.props.flash.success }}
        </div>

        <!-- ── Main Content ──────────────────────────────────── -->
        <div class="flex flex-1 flex-col lg:flex-row lg:overflow-hidden">

            <!-- Lesson Area -->
            <div class="flex-1 flex flex-col min-w-0 lg:overflow-y-auto">

                <!-- Video Player -->
                <div class="bg-black flex-shrink-0">
                    <div class="max-w-4xl mx-auto w-full aspect-video relative overflow-hidden">
                        <!-- Loading spinner -->
                        <div v-if="isVideoLoading" class="absolute inset-0 flex items-center justify-center bg-black/80 z-20">
                            <div class="flex flex-col items-center gap-3">
                                <div class="w-10 h-10 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
                                <div class="text-xs text-surface-400">جاري تجهيز مشغل الفيديو داخل المنصة...</div>
                            </div>
                        </div>

                        <!-- Dynamic Watermark overlay -->
                        <div v-if="signedVideoUrl"
                             class="absolute pointer-events-none select-none z-10 transition-all duration-1000 text-[10px] sm:text-xs font-semibold text-white/10 dark:text-white/10 drop-shadow-sm flex flex-col items-center gap-0.5 bg-black/5 px-2 py-0.5 rounded"
                             :style="watermarkStyle"
                        >
                            <span>{{ $page.props.auth.user?.name }}</span>
                            <span>{{ $page.props.auth.user?.email }}</span>
                        </div>

                        <!-- Video Player: HTML5 + Plyr for all types (file & youtube_proxy) -->
                        <video
                            v-if="signedVideoUrl"
                            :key="activeLesson?.id"
                            ref="videoRef"
                            class="w-full h-full"
                            controls
                            crossorigin
                            playsinline
                        >
                            <source :src="signedVideoUrl" type="video/mp4" />
                        </video>
                        <div v-else-if="!isVideoLoading" class="w-full h-full flex items-center justify-center text-surface-500 px-6">
                            <div class="text-center flex flex-col items-center justify-center">
                                <Icon name="video" class="w-16 h-16 text-surface-500 mb-4" />
                                <p v-if="!activeLesson">لم يُنشر محتوى هذا الفصل بعد.</p>
                                <p v-else-if="!activeLesson.has_video">لا يوجد فيديو شرح لهذا الدرس — راجع الملزمة والواجب.</p>
                                <p v-else>الفيديو غير متاح حالياً</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lesson Info & Tabs -->
                <div v-if="activeLesson" class="flex-1 bg-surface-950 p-4 sm:p-6 max-w-4xl mx-auto w-full">
                    <div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
                        <div class="min-w-0">
                            <div class="text-xs text-surface-400 mb-1 flex items-center gap-1.5 flex-wrap">
                                <span class="text-primary-400 font-bold">{{ activeUnit?.title }}</span>
                                <span>·</span>
                                <span>الدرس {{ activeLesson.order }} من {{ activeUnit?.lessons_count }}</span>
                                <span v-if="activeLesson.duration_seconds">·</span>
                                <span v-if="activeLesson.duration_seconds">{{ formatDuration(activeLesson.duration_seconds) }}</span>
                            </div>
                            <h2 class="text-xl font-bold text-white">{{ activeLesson.title }}</h2>
                        </div>
                        <div class="flex items-center gap-3">
                            <form v-if="group.teacher" @submit.prevent="router.post(route('chat.start'), { teaching_assignment_id: group.teaching_assignment_id, teacher_id: group.teacher.id })">
                                <button type="submit" class="btn-sm bg-primary-600 hover:bg-primary-700 text-white font-bold rounded-xl flex items-center gap-1.5 transition-colors text-xs py-1.5 px-3">
                                    <Icon name="chat" class="w-3.5 h-3.5" />
                                    <span>راسل المدرس</span>
                                </button>
                            </form>

                            <div v-if="isDone(activeLesson)"
                                 class="text-xs font-bold px-2.5 py-1 rounded-full bg-green-500/10 text-green-400 border border-green-500/20 flex-shrink-0">
                                ✓ مكتمل
                            </div>
                        </div>
                    </div>

                    <!-- Tabs Header -->
                    <div class="flex border-b border-surface-800 mb-6 bg-surface-900/10 p-1 rounded-xl overflow-x-auto">
                        <button @click="activeTab = 'description'"
                                class="px-4 py-2 text-sm font-bold border-b-2 transition-all whitespace-nowrap"
                                :class="activeTab === 'description' ? 'border-primary-500 text-primary-400' : 'border-transparent text-surface-400 hover:text-white'"
                        >
                            الشرح والمشاهدة
                        </button>
                        <button @click="activeTab = 'files'"
                                class="px-4 py-2 text-sm font-bold border-b-2 transition-all whitespace-nowrap"
                                :class="activeTab === 'files' ? 'border-primary-500 text-primary-400' : 'border-transparent text-surface-400 hover:text-white'"
                        >
                            الملزمة والواجب ({{ attachmentCount }})
                        </button>
                        <button @click="activeTab = 'questions'"
                                class="px-4 py-2 text-sm font-bold border-b-2 transition-all whitespace-nowrap"
                                :class="activeTab === 'questions' ? 'border-primary-500 text-primary-400' : 'border-transparent text-surface-400 hover:text-white'"
                        >
                            الأسئلة والنقاشات ({{ questions.length }})
                        </button>
                    </div>

                    <!-- Tab Content: Description -->
                    <div v-if="activeTab === 'description'" class="space-y-6">
                        <p class="text-sm text-surface-300 leading-relaxed whitespace-pre-line">
                            {{ activeLesson.description || 'لا يوجد وصف متاح لهذا الدرس حالياً.' }}
                        </p>

                        <div class="flex flex-wrap items-center gap-3 pt-4">
                            <button
                                v-if="nextLesson"
                                @click="goNextLesson"
                                class="btn-primary"
                                id="next-lesson-btn"
                            >
                                الدرس التالي ←
                            </button>
                            <div v-else-if="hasLaterLessons"
                                 class="text-xs text-accent-300 flex items-center gap-1.5 bg-accent-500/5 border border-accent-500/20 rounded-xl px-3 py-2">
                                <Icon name="lock" class="w-3.5 h-3.5 flex-shrink-0" />
                                أكمل مشاهدة هذا الدرس ليُفتح الدرس التالي.
                            </div>
                            <Link
                                v-else
                                :href="route('dashboard')"
                                class="btn-outline text-white border-surface-600 hover:bg-surface-800"
                            >
                                العودة للداشبورد
                            </Link>
                        </div>
                    </div>

                    <!-- Tab Content: Booklet & Homework -->
                    <div v-if="activeTab === 'files'" class="space-y-5">

                        <!-- ملزمة الشرح -->
                        <div class="bg-surface-900 border border-surface-800 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="flex items-start gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-xl bg-primary-500/10 text-primary-400 flex items-center justify-center flex-shrink-0">
                                    <Icon name="book" class="w-5 h-5" />
                                </div>
                                <div class="min-w-0">
                                    <h4 class="font-bold text-white text-base">ملزمة شرح الدرس</h4>
                                    <p class="text-xs text-surface-400 mt-0.5">
                                        {{ activeLesson.booklet_path ? 'ملف الشرح المكتوب الذي رفعه المدرس لهذا الدرس.' : 'لم يرفع المدرس ملزمة لهذا الدرس بعد.' }}
                                    </p>
                                </div>
                            </div>
                            <a v-if="activeLesson.booklet_path"
                               :href="activeLesson.booklet_path"
                               target="_blank"
                               rel="noopener"
                               class="btn btn-sm bg-surface-800 text-white hover:bg-surface-700 flex-shrink-0">
                                <Icon name="download" class="w-4 h-4" />
                                تحميل الملزمة
                            </a>
                        </div>

                        <!-- الواجب -->
                        <div v-if="activeLesson.homework" class="bg-surface-900 border border-surface-800 rounded-2xl p-5 space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 mb-2 flex-wrap">
                                        <span class="text-[10px] px-2.5 py-0.5 rounded font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                            واجب الدرس
                                        </span>
                                        <span v-if="activeLesson.homework.requires_submission"
                                              class="text-[10px] px-2.5 py-0.5 rounded font-bold bg-red-500/10 text-red-400 border border-red-500/20">
                                            يتطلب تسليماً
                                        </span>
                                    </div>
                                    <h4 class="font-bold text-white text-base">{{ activeLesson.homework.title }}</h4>
                                    <div class="text-xs text-surface-400 mt-1 flex items-center gap-3 flex-wrap">
                                        <span v-if="activeLesson.homework.due_date">آخر موعد: {{ formatDate(activeLesson.homework.due_date) }}</span>
                                        <span v-if="activeLesson.homework.max_score">الدرجة الكاملة: {{ activeLesson.homework.max_score }}</span>
                                    </div>
                                </div>
                                <a :href="activeLesson.homework.file_path"
                                   target="_blank"
                                   rel="noopener"
                                   class="btn btn-sm bg-surface-800 text-white hover:bg-surface-700 flex-shrink-0">
                                    <Icon name="download" class="w-4 h-4" />
                                    تحميل الواجب
                                </a>
                            </div>

                            <!-- Submission state -->
                            <div v-if="activeLesson.homework.submission"
                                 class="bg-surface-950 border border-surface-800 rounded-xl p-4 text-xs space-y-1.5">
                                <div class="font-bold text-green-400 flex items-center gap-1.5">
                                    <Icon name="success" class="w-4 h-4" />
                                    تم تسليم الحل
                                    <span class="text-surface-500 font-normal">{{ formatDateTime(activeLesson.homework.submission.submitted_at) }}</span>
                                </div>
                                <a :href="activeLesson.homework.submission.file_path" target="_blank" rel="noopener"
                                   class="text-primary-400 hover:text-primary-300 inline-flex items-center gap-1">
                                    <Icon name="file" class="w-3.5 h-3.5" />
                                    عرض الملف الذي سلّمته
                                </a>
                                <div v-if="activeLesson.homework.submission.is_graded" class="font-bold text-primary-400 pt-1">
                                    الدرجة المرصودة: {{ activeLesson.homework.submission.score }}
                                    <span v-if="activeLesson.homework.max_score">/ {{ activeLesson.homework.max_score }}</span>
                                </div>
                                <div v-else class="text-surface-400 pt-1">في انتظار تصحيح المدرس.</div>
                                <div v-if="activeLesson.homework.submission.teacher_feedback" class="text-surface-400 italic">
                                    تعليق المدرس: "{{ activeLesson.homework.submission.teacher_feedback }}"
                                </div>
                            </div>

                            <!-- Upload / re-upload -->
                            <div v-if="activeLesson.homework.requires_submission" class="space-y-2">
                                <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                                    <input
                                        type="file"
                                        accept=".pdf,.docx,.png,.jpg,.jpeg"
                                        :key="`hw-${activeLesson.homework.id}-${activeLesson.homework.submission?.submitted_at ?? 'new'}`"
                                        @change="onFileChange($event, activeLesson.homework.id)"
                                        class="text-xs text-surface-400 bg-surface-950 border border-surface-800 rounded-xl px-3 py-2 flex-1 min-w-0"
                                    />
                                    <button @click="uploadAnswer(activeLesson.homework.id)"
                                            :disabled="uploadingSheet === activeLesson.homework.id"
                                            class="btn-primary btn-sm flex-shrink-0">
                                        {{ uploadingSheet === activeLesson.homework.id
                                            ? 'جارٍ الرفع...'
                                            : (activeLesson.homework.submission ? 'إعادة التسليم' : 'تسليم الحل') }}
                                    </button>
                                </div>
                                <p class="text-[11px] text-surface-500">
                                    PDF أو Word أو صورة، بحد أقصى 10 ميجابايت.
                                    <span v-if="activeLesson.homework.submission" class="text-accent-300">إعادة التسليم تلغي الدرجة والتعليق السابقين.</span>
                                </p>
                                <p v-if="uploadNotices[activeLesson.homework.id]" class="text-[11px] text-red-400">
                                    {{ uploadNotices[activeLesson.homework.id] }}
                                </p>
                                <p v-if="$page.props.errors?.submitted_file" class="text-[11px] text-red-400">
                                    {{ $page.props.errors.submitted_file }}
                                </p>
                            </div>
                        </div>

                        <div v-if="!activeLesson.booklet_path && !activeLesson.homework"
                             class="text-center py-10 text-surface-500 text-sm">
                            لا توجد ملزمة أو واجب مرفق بهذا الدرس.
                        </div>
                    </div>

                    <!-- Tab Content: Q&A -->
                    <div v-if="activeTab === 'questions'" class="space-y-6">
                        <!-- Post Question Form -->
                        <form @submit.prevent="submitQuestion" class="space-y-3 bg-surface-900/40 p-4 rounded-2xl border border-surface-800">
                            <h4 class="font-bold text-sm text-white">اطرح سؤالاً أو استفساراً حول هذا الدرس:</h4>
                            <textarea v-model="newQuestionContent" required rows="3" class="input p-3 text-sm bg-surface-950 border-surface-800 text-white rounded-xl focus:ring-primary-500/20" placeholder="اكتب سؤالك هنا..."></textarea>
                            <div class="flex items-center justify-between gap-3 flex-wrap">
                                <label v-if="player" class="flex items-center gap-2 text-xs text-surface-400 cursor-pointer">
                                    <input type="checkbox" v-model="includeTimestamp" class="rounded border-surface-800 text-primary-600 bg-surface-950" />
                                    <span>ربط السؤال بالتوقيت الحالي للفيديو ({{ formatDuration(Math.floor(player.currentTime)) }})</span>
                                </label>
                                <button type="submit" class="btn-primary btn-sm px-4">نشر السؤال 💬</button>
                            </div>
                        </form>

                        <!-- Questions List -->
                        <div class="space-y-4">
                            <div v-for="q in questions" :key="q.id" class="card p-5 bg-surface-900/20 border-surface-800 space-y-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-950/20 text-primary-600 font-bold flex items-center justify-center text-xs">
                                            {{ q.user?.name?.charAt(0) }}
                                        </div>
                                        <div>
                                            <div class="font-bold text-xs text-white">{{ q.user?.name }}</div>
                                            <div class="text-[10px] text-surface-500 mt-0.5">منذ {{ new Date(q.created_at).toLocaleDateString('ar-EG') }}</div>
                                        </div>
                                    </div>
                                    <button v-if="q.video_timestamp !== null" @click="seekTo(q.video_timestamp)" class="text-xs text-primary-400 hover:text-primary-300 font-bold flex items-center gap-1">
                                        ⏱️ {{ formatQuestionTime(q.video_timestamp) }}
                                    </button>
                                </div>

                                <p class="text-sm text-surface-300 leading-relaxed">{{ q.content }}</p>

                                <div v-if="q.replies?.length" class="space-y-3 border-t border-surface-800 pt-3 ms-6">
                                    <div v-for="reply in q.replies" :key="reply.id" class="flex items-start gap-3 bg-surface-900/10 p-3 rounded-xl">
                                        <div class="w-7 h-7 rounded-full bg-surface-800 text-surface-400 font-bold flex items-center justify-center text-[10px]">
                                            {{ reply.user?.name?.charAt(0) }}
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="font-bold text-xs text-white">{{ reply.user?.name }}</span>
                                                <span class="text-[9px] text-surface-500">منذ {{ new Date(reply.created_at).toLocaleDateString('ar-EG') }}</span>
                                            </div>
                                            <p class="text-xs text-surface-300">{{ reply.content }}</p>
                                        </div>
                                    </div>
                                </div>

                                <form @submit.prevent="submitReply(q.id)" class="flex gap-2 ms-6 border-t border-surface-800/50 pt-3">
                                    <input v-model="replyContents[q.id]" required class="input py-1 px-3 text-xs bg-surface-950 border-surface-800 text-white rounded-xl flex-1 focus:ring-primary-500/20" placeholder="اكتب رداً أو توضيحاً..." />
                                    <button type="submit" class="btn-outline btn-sm py-1 px-3 text-xs">رد</button>
                                </form>
                            </div>

                            <div v-if="questions.length === 0" class="text-center py-10 text-surface-500">
                                لا توجد أسئلة حتى الآن للدرس. كن أول من يطرح سؤالاً!
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Nothing published in this term at all -->
                <div v-else class="flex-1 flex items-center justify-center p-10 text-center">
                    <div class="max-w-sm">
                        <Icon name="courses" class="w-14 h-14 text-surface-700 mx-auto mb-4" />
                        <h3 class="text-white font-bold mb-2">لا يوجد محتوى منشور بعد</h3>
                        <p class="text-sm text-surface-400">
                            لم ينشر المدرس وحدات هذا الفصل الدراسي حتى الآن. جرّب فصلاً آخر من التبويبات، أو راسل المدرس.
                        </p>
                    </div>
                </div>
            </div>

            <!-- ── Curriculum Tree ────────────────────────────── -->
            <aside class="w-full lg:w-96 flex-shrink-0 bg-surface-900 border-t lg:border-t-0 lg:border-s border-surface-800 lg:overflow-y-auto flex flex-col"
                   :class="treeOpen ? 'lg:flex' : 'lg:hidden'">

                <!-- Term tabs -->
                <div class="p-4 border-b border-surface-800 space-y-3">
                    <div>
                        <h3 class="text-white font-bold text-sm">منهج المادة</h3>
                        <p class="text-surface-400 text-xs mt-1">
                            {{ localUnits.length }} وحدة · {{ completedCount }} / {{ totalLessons }} درس مكتمل
                        </p>
                    </div>

                    <div v-if="terms.length" class="flex gap-1.5 overflow-x-auto pb-1">
                        <button v-for="term in terms" :key="term.id"
                                @click="selectTerm(term.id)"
                                :title="term.full_name"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-colors text-start leading-tight"
                                :class="term.id === activeTermId
                                    ? 'bg-primary-600 text-white'
                                    : 'bg-surface-950 text-surface-400 hover:bg-surface-800 hover:text-white'">
                            <span class="flex items-center gap-1.5">
                                {{ term.name }}
                                <span v-if="term.is_current" class="w-1.5 h-1.5 rounded-full bg-accent-400" title="الفصل الحالي"></span>
                            </span>
                            <span class="block text-[10px] font-normal opacity-70">{{ term.year_label }} · {{ term.units_count }} وحدة</span>
                        </button>
                    </div>
                </div>

                <!-- Lock notice -->
                <div v-if="lockNotice" class="m-3 rounded-xl bg-accent-500/10 border border-accent-500/25 text-accent-200 text-xs px-3 py-2.5 flex items-start gap-2">
                    <Icon name="lock" class="w-4 h-4 flex-shrink-0 mt-0.5" />
                    <span class="flex-1">{{ lockNotice }}</span>
                    <button @click="lockNotice = ''" class="text-accent-300/70 hover:text-white flex-shrink-0">
                        <Icon name="close" class="w-3.5 h-3.5" />
                    </button>
                </div>

                <!-- Units accordion -->
                <div class="flex-1">
                    <div v-for="unit in localUnits" :key="unit.id" class="border-b border-surface-800">

                        <!-- Collapsed row -->
                        <button @click="toggleUnit(unit.id)"
                                class="w-full flex items-center gap-3 px-4 py-3.5 text-start transition-colors"
                                :class="unit.is_locked ? 'hover:bg-surface-800/40' : 'hover:bg-surface-800'">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center text-xs font-bold flex-shrink-0"
                                 :class="unit.is_locked
                                    ? 'bg-surface-800 text-surface-600'
                                    : unit.is_completed
                                        ? 'bg-green-500/15 text-green-400'
                                        : 'bg-primary-500/15 text-primary-400'">
                                <Icon v-if="unit.is_locked" name="lock" class="w-4 h-4" />
                                <span v-else>{{ unit.order }}</span>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-bold line-clamp-1"
                                     :class="unit.is_locked ? 'text-surface-500' : 'text-white'">
                                    {{ unit.title }}
                                </div>
                                <div class="text-[11px] text-surface-500 mt-0.5">
                                    {{ unit.completed_lessons_count }} / {{ unit.lessons_count }} درس
                                    <span v-if="unit.is_locked" class="text-accent-400/80"> · مغلقة</span>
                                </div>
                                <div class="h-1 rounded-full bg-surface-800 overflow-hidden mt-1.5">
                                    <div class="h-full rounded-full transition-all duration-500"
                                         :class="unit.is_completed ? 'bg-green-500' : 'bg-primary-500'"
                                         :style="{ width: (unit.lessons_count ? (unit.completed_lessons_count / unit.lessons_count) * 100 : 0) + '%' }">
                                    </div>
                                </div>
                            </div>

                            <svg class="w-4 h-4 text-surface-500 flex-shrink-0 transition-transform duration-200"
                                 :class="expandedUnits[unit.id] ? 'rotate-180' : ''"
                                 xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- Expanded body -->
                        <div v-show="expandedUnits[unit.id]" class="bg-surface-950/40 pb-3">

                            <!-- Why the whole unit is shut -->
                            <div v-if="unit.is_locked"
                                 class="mx-3 mt-3 rounded-xl bg-accent-500/10 border border-accent-500/25 text-accent-200 text-[11px] px-3 py-2 flex items-start gap-2">
                                <Icon name="lock" class="w-3.5 h-3.5 flex-shrink-0 mt-0.5" />
                                هذه الوحدة مغلقة — أكمل كل دروس الوحدة السابقة لفتحها.
                            </div>

                            <p v-if="unit.description" class="px-4 pt-3 text-[11px] text-surface-400 leading-relaxed">
                                {{ unit.description }}
                            </p>

                            <!-- Lessons -->
                            <div class="pt-1">
                                <button v-for="lesson in unit.lessons" :key="lesson.id"
                                        @click="selectLesson(unit, lesson)"
                                        :id="`lesson-btn-${lesson.id}`"
                                        class="w-full flex items-start gap-3 px-4 py-2.5 text-start transition-colors duration-150"
                                        :class="lesson.id === activeLessonId
                                            ? 'bg-primary-900/40 border-s-2 border-primary-500'
                                            : 'hover:bg-surface-800/60 border-s-2 border-transparent'">
                                    <!-- Status icon -->
                                    <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5 text-[11px]"
                                         :class="lesson.is_locked
                                            ? 'bg-surface-800 text-surface-600'
                                            : isDone(lesson)
                                                ? 'bg-green-500 text-white'
                                                : lesson.id === activeLessonId
                                                    ? 'bg-primary-500 text-white'
                                                    : 'bg-surface-700 text-surface-400'">
                                        <Icon v-if="lesson.is_locked" name="lock" class="w-3 h-3" />
                                        <span v-else-if="isDone(lesson)">✓</span>
                                        <span v-else>{{ lesson.order }}</span>
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-medium line-clamp-2"
                                             :class="lesson.is_locked
                                                ? 'text-surface-500'
                                                : lesson.id === activeLessonId
                                                    ? 'text-white font-bold'
                                                    : 'text-surface-300'">
                                            {{ lesson.title }}
                                        </div>

                                        <div class="text-[11px] text-surface-500 mt-0.5 flex items-center gap-2 flex-wrap">
                                            <span class="flex items-center gap-1">
                                                <Icon name="clock" class="w-3 h-3" />
                                                {{ formatDuration(lesson.duration_seconds) }}
                                            </span>
                                            <span v-if="lesson.booklet_path" class="flex items-center gap-0.5 text-primary-400/80">
                                                <Icon name="book" class="w-3 h-3" /> ملزمة
                                            </span>
                                            <span v-if="lesson.homework" class="flex items-center gap-0.5"
                                                  :class="lesson.homework.submission ? 'text-green-400/80' : 'text-amber-400/80'">
                                                <Icon name="file" class="w-3 h-3" />
                                                {{ lesson.homework.submission ? 'واجب مُسلَّم' : 'واجب' }}
                                            </span>
                                            <span v-if="lesson.is_free_preview" class="text-accent-400/90">معاينة مجانية</span>
                                        </div>

                                        <!-- Locked lessons say why, without needing a click -->
                                        <div v-if="lesson.is_locked" class="text-[10px] text-accent-400/80 mt-1 flex items-center gap-1">
                                            <Icon name="lock" class="w-2.5 h-2.5" />
                                            {{ unit.is_locked ? 'الوحدة السابقة غير مكتملة' : 'أكمل الدرس السابق لفتحه' }}
                                        </div>
                                    </div>
                                </button>

                                <p v-if="!unit.lessons.length" class="px-4 py-3 text-[11px] text-surface-500">
                                    لم يضف المدرس دروس هذه الوحدة بعد.
                                </p>
                            </div>

                            <!-- ── Unit exams ─────────────────────── -->
                            <div class="px-3 pt-3 space-y-2.5">
                                <div class="text-[11px] font-bold text-surface-400 px-1">اختبار الوحدة</div>

                                <!-- النموذج الإلكتروني -->
                                <div v-if="unit.electronic_exam"
                                     class="rounded-xl border border-surface-800 bg-surface-950/70 p-3 space-y-2">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-primary-500/10 text-primary-400 flex items-center justify-center flex-shrink-0">
                                            <Icon name="chart" class="w-4 h-4" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="text-xs font-bold text-white">النموذج الإلكتروني</div>
                                            <div class="text-[10px] text-surface-500 line-clamp-1">{{ unit.electronic_exam.title }}</div>
                                        </div>
                                        <span class="text-[10px] px-2 py-0.5 rounded font-bold flex-shrink-0"
                                              :class="unit.electronic_exam.is_open
                                                ? 'bg-green-500/10 text-green-400 border border-green-500/20'
                                                : 'bg-surface-800 text-surface-500 border border-surface-700'">
                                            {{ unit.electronic_exam.is_open ? 'متاح' : 'مغلق' }}
                                        </span>
                                    </div>

                                    <div class="text-[11px] text-surface-400 flex items-center gap-2 flex-wrap">
                                        <span>{{ unit.electronic_exam.questions_count }} سؤال</span>
                                        <span>·</span>
                                        <span>{{ unit.electronic_exam.time_limit_minutes ? `${unit.electronic_exam.time_limit_minutes} دقيقة` : 'بلا حد زمني' }}</span>
                                        <span>·</span>
                                        <span>النجاح {{ unit.electronic_exam.passing_score }}%</span>
                                    </div>

                                    <div class="text-[10px] text-surface-500 flex items-start gap-1">
                                        <Icon name="calendar" class="w-3 h-3 flex-shrink-0 mt-0.5" />
                                        <span>{{ unit.electronic_exam.window_label }}</span>
                                    </div>

                                    <!-- Best mark so far -->
                                    <div v-if="unit.electronic_exam.best_attempt"
                                         class="rounded-lg bg-surface-900 border border-surface-800 px-2.5 py-2 text-[11px] flex items-center justify-between gap-2">
                                        <span class="text-surface-400">أفضل نتيجة</span>
                                        <span class="font-bold flex items-center gap-1.5"
                                              :class="unit.electronic_exam.best_attempt.passed ? 'text-green-400' : 'text-red-400'">
                                            {{ unit.electronic_exam.best_attempt.score }}%
                                            <span class="text-[10px] font-normal">
                                                {{ unit.electronic_exam.best_attempt.passed ? '· ناجح' : '· لم تجتز' }}
                                            </span>
                                        </span>
                                    </div>

                                    <div class="text-[10px] text-surface-500">
                                        المحاولات المتبقية: {{ unit.electronic_exam.attempts_left }} من {{ unit.electronic_exam.attempts_left + unit.electronic_exam.attempts_count }}
                                    </div>

                                    <Link v-if="unit.electronic_exam.can_start"
                                          :href="route('student.quiz', { quizId: unit.electronic_exam.id })"
                                          class="btn-primary btn-sm w-full justify-center">
                                        {{ unit.electronic_exam.attempts_count > 0 ? 'إعادة المحاولة' : 'ابدأ الاختبار' }}
                                    </Link>
                                    <div v-else class="space-y-1.5">
                                        <button type="button" disabled
                                                class="btn btn-sm w-full justify-center bg-surface-800 text-surface-500 border border-surface-700 cursor-not-allowed">
                                            <Icon name="lock" class="w-3.5 h-3.5" />
                                            غير متاح الآن
                                        </button>
                                        <p class="text-[10px] text-accent-300/90 flex items-start gap-1">
                                            <Icon name="info" class="w-3 h-3 flex-shrink-0 mt-0.5" />
                                            {{ examBlockReason(unit.electronic_exam) }}
                                        </p>
                                        <Link :href="route('student.quiz', { quizId: unit.electronic_exam.id })"
                                              class="block text-center text-[10px] text-primary-400 hover:text-primary-300">
                                            عرض تفاصيل الاختبار
                                        </Link>
                                    </div>
                                </div>

                                <!-- النموذج الورقي -->
                                <div v-if="unit.paper_exam"
                                     class="rounded-xl border border-surface-800 bg-surface-950/70 p-3 space-y-2">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-accent-500/10 text-accent-400 flex items-center justify-center flex-shrink-0">
                                            <Icon name="file" class="w-4 h-4" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="text-xs font-bold text-white">النموذج الورقي</div>
                                            <div class="text-[10px] text-surface-500 line-clamp-1">{{ unit.paper_exam.title }}</div>
                                        </div>
                                    </div>

                                    <div v-if="unit.paper_exam.due_date || unit.paper_exam.max_score"
                                         class="text-[11px] text-surface-400 flex items-center gap-2 flex-wrap">
                                        <span v-if="unit.paper_exam.due_date">آخر موعد: {{ formatDate(unit.paper_exam.due_date) }}</span>
                                        <span v-if="unit.paper_exam.due_date && unit.paper_exam.max_score">·</span>
                                        <span v-if="unit.paper_exam.max_score">الدرجة الكاملة: {{ unit.paper_exam.max_score }}</span>
                                    </div>

                                    <a :href="unit.paper_exam.file_path" target="_blank" rel="noopener"
                                       class="btn btn-sm w-full justify-center bg-surface-800 text-white hover:bg-surface-700">
                                        <Icon name="download" class="w-3.5 h-3.5" />
                                        تحميل ورقة الأسئلة
                                    </a>

                                    <!-- Submission state -->
                                    <div v-if="unit.paper_exam.submission"
                                         class="rounded-lg bg-surface-900 border border-surface-800 px-2.5 py-2 text-[11px] space-y-1">
                                        <div class="font-bold text-green-400 flex items-center gap-1">
                                            <Icon name="success" class="w-3.5 h-3.5" />
                                            تم تسليم إجابتك
                                        </div>
                                        <div class="text-surface-500 text-[10px]">{{ formatDateTime(unit.paper_exam.submission.submitted_at) }}</div>
                                        <div v-if="unit.paper_exam.submission.is_graded" class="font-bold text-primary-400">
                                            الدرجة: {{ unit.paper_exam.submission.score }}
                                            <span v-if="unit.paper_exam.max_score">/ {{ unit.paper_exam.max_score }}</span>
                                        </div>
                                        <div v-else class="text-surface-400">في انتظار تصحيح المدرس.</div>
                                        <div v-if="unit.paper_exam.submission.teacher_feedback" class="text-surface-400 italic">
                                            "{{ unit.paper_exam.submission.teacher_feedback }}"
                                        </div>
                                    </div>

                                    <!-- Upload answers -->
                                    <div v-if="unit.paper_exam.requires_submission" class="space-y-1.5">
                                        <input
                                            type="file"
                                            accept=".pdf,.docx,.png,.jpg,.jpeg"
                                            :key="`pe-${unit.paper_exam.id}-${unit.paper_exam.submission?.submitted_at ?? 'new'}`"
                                            @change="onFileChange($event, unit.paper_exam.id)"
                                            class="w-full text-[11px] text-surface-400 bg-surface-900 border border-surface-800 rounded-lg px-2 py-1.5"
                                        />
                                        <button @click="uploadAnswer(unit.paper_exam.id)"
                                                :disabled="uploadingSheet === unit.paper_exam.id"
                                                class="btn-primary btn-sm w-full justify-center">
                                            {{ uploadingSheet === unit.paper_exam.id
                                                ? 'جارٍ الرفع...'
                                                : (unit.paper_exam.submission ? 'إعادة رفع الإجابة' : 'ارفع إجابتك') }}
                                        </button>
                                        <p v-if="uploadNotices[unit.paper_exam.id]" class="text-[10px] text-red-400">
                                            {{ uploadNotices[unit.paper_exam.id] }}
                                        </p>
                                        <p class="text-[10px] text-surface-500">PDF أو Word أو صورة، بحد أقصى 10 ميجابايت.</p>
                                    </div>
                                </div>

                                <p v-if="!unit.electronic_exam && !unit.paper_exam"
                                   class="text-[11px] text-surface-500 px-1 pb-1">
                                    لم يضف المدرس اختبار هذه الوحدة بعد.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div v-if="!localUnits.length" class="p-6 text-center text-xs text-surface-500">
                        لا توجد وحدات منشورة في هذا الفصل الدراسي.
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
