<script setup>
import { ref, computed, onUnmounted, watch, onMounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import Plyr from 'plyr';
import 'plyr/dist/plyr.css';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    course:     { type: Object, required: true },
    lessons:    { type: Array,  required: true },
    enrollment: { type: Object, required: true },
    worksheets: { type: Array,  default: () => [] },
});

// ── State ──────────────────────────────────────────────────────
const localLessons      = ref([...props.lessons]);
const activeLessonIndex = ref(0);
const progressPercent   = ref(props.enrollment.progress_percent);
const sidebarOpen       = ref(true);
const isCompleted       = ref(props.enrollment.completed_at !== null);
const activeTab         = ref('description'); // description | worksheets | questions
const signedVideoUrl    = ref('');
const isVideoLoading    = ref(false);

const activeLesson = computed(() => localLessons.value[activeLessonIndex.value] ?? null);

const completedCount = computed(() =>
    localLessons.value.filter(l => l.is_completed).length
);

// Track local completion state for instant UI feedback
const localCompleted = ref(
    Object.fromEntries(props.lessons.map(l => [l.id, l.is_completed]))
);

// Worksheets associated with current lesson or general course
const filteredWorksheets = computed(() => {
    if (!activeLesson.value) return [];
    return props.worksheets.filter(w => w.lesson_id === activeLesson.value.id || w.lesson_id === null);
});

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
            route('student.lesson.progress', {
                slug:     props.course.slug,
                lessonId: lesson.id,
            }),
            { watched_seconds: watchedSeconds }
        );

        progressPercent.value = res.data.progress_percent;
        isCompleted.value     = res.data.is_completed;

        if (res.data.lessons) {
            localLessons.value = res.data.lessons;
        }

        if (watchedSeconds >= lesson.duration_seconds * 0.8) {
            localCompleted.value[lesson.id] = true;
        }
    } catch (e) {
        console.warn('Progress update failed, will retry:', e.message);
    }
}

function selectLesson(index) {
    const targetLesson = localLessons.value[index];
    if (targetLesson && targetLesson.is_locked) {
        alert('هذا الدرس مغلق. يجب مشاهدة الدرس السابق أولاً لفتحه.');
        return;
    }
    clearInterval(progressInterval);
    lastReportedSeconds = 0;
    activeLessonIndex.value = index;
}

function goNextLesson() {
    if (activeLessonIndex.value < localLessons.value.length - 1) {
        selectLesson(activeLessonIndex.value + 1);
    }
}

function formatDuration(seconds) {
    if (!seconds) return '0:00';
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
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
        const res = await axios.get(route('lessons.questions.index', { lessonId: activeLesson.value.id }));
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

        const res = await axios.post(route('lessons.questions.store', { lessonId: activeLesson.value.id }), {
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
        const res = await axios.post(route('lessons.questions.store', { lessonId: activeLesson.value.id }), {
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

watch(activeLesson, async () => {
    if (player) {
        player.destroy();
        player = null;
    }
    
    if (!activeLesson.value) {
        signedVideoUrl.value = '';
        return;
    }
    
    isVideoLoading.value = true;
    signedVideoUrl.value = '';
    
    try {
        const response = await axios.get(route('student.video.url', { lessonId: activeLesson.value.id }));
        signedVideoUrl.value = response.data.signed_url;
    } catch (e) {
        console.error('Failed to get signed URL:', e.message);
    } finally {
        isVideoLoading.value = false;
    }
    
    fetchQuestions();

    setTimeout(() => {
        if (videoRef.value) {
            player = new Plyr(videoRef.value, {
                controls: [
                    'play-large', 'play', 'progress', 'current-time', 
                    'mute', 'volume', 'captions', 'settings', 'pip', 'airplay', 'fullscreen'
                ],
                settings: ['captions', 'quality', 'speed', 'loop'],
                speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 2] },
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

// Homework files uploading state
const submitFiles = ref({});
function onFileChange(e, id) {
    submitFiles.value[id] = e.target.files[0];
}

function uploadHomework(id) {
    const file = submitFiles.value[id];
    if (!file) {
        alert('الرجاء اختيار ملف الحل أولاً.');
        return;
    }

    const formData = new FormData();
    formData.append('submitted_file', file);

    router.post(route('student.worksheets.submit', { slug: props.course.slug, worksheetId: id }), formData, {
        onSuccess: () => {
            alert('تم تسليم الواجب بنجاح!');
        }
    });
}
</script>

<template>
    <div class="min-h-screen bg-surface-950 flex flex-col" dir="rtl" lang="ar">

        <!-- ── Top Bar ───────────────────────────────────────── -->
        <header class="bg-surface-900 border-b border-surface-800 px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <Link :href="route('dashboard')" class="text-surface-400 hover:text-white transition-colors text-xl">
                    ←
                </Link>
                <div>
                    <div class="text-white font-bold text-sm line-clamp-1">{{ course.title }}</div>
                    <div class="text-xs text-surface-400">
                        {{ completedCount }} / {{ lessons.length }} درس مكتمل
                    </div>
                </div>
            </div>

            <!-- Overall progress -->
            <div class="hidden sm:flex items-center gap-3">
                <div class="w-32 progress-bar bg-surface-700">
                    <div class="progress-bar-fill" :style="{ width: progressPercent + '%' }"></div>
                </div>
                <span class="text-primary-400 font-bold text-sm">{{ progressPercent }}%</span>

                <button @click="sidebarOpen = !sidebarOpen"
                    class="btn-ghost text-sm text-surface-400 hover:text-white px-3 py-1.5">
                    {{ sidebarOpen ? 'أخفِ القائمة' : 'أظهر القائمة' }}
                </button>
            </div>
        </header>

        <!-- Completion Banner -->
        <Transition enter-active-class="animate-fade-up">
            <div v-if="isCompleted"
                 class="bg-green-600 text-white text-center py-3 px-4 font-bold text-sm flex items-center justify-center gap-3">
                تهانينا! أكملت الكورس بنجاح
                <Link :href="route('student.certificate', { enrollmentId: enrollment.id })"
                      class="underline hover:no-underline">
                    احصل على شهادتك
                </Link>
            </div>
        </Transition>

        <!-- ── Main Content ──────────────────────────────────── -->
        <div class="flex flex-1 overflow-hidden">

            <!-- Video Area -->
            <div class="flex-1 flex flex-col min-w-0">

                <!-- Video Player -->
                <div class="bg-black flex-shrink-0">
                    <div class="max-w-4xl mx-auto w-full aspect-video relative overflow-hidden">
                        <!-- Loading spinner -->
                        <div v-if="isVideoLoading" class="absolute inset-0 flex items-center justify-center bg-black/80 z-20">
                            <div class="flex flex-col items-center gap-3">
                                <div class="w-10 h-10 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
                                <div class="text-xs text-surface-400">جاري تحميل تشفير الفيديو الآمن...</div>
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

                        <video
                            v-if="signedVideoUrl"
                            :key="activeLesson.id"
                            ref="videoRef"
                            class="w-full h-full"
                            controls
                            crossorigin
                            playsinline
                        >
                            <source :src="signedVideoUrl" type="video/mp4" />
                        </video>
                        <div v-else-if="!isVideoLoading" class="w-full h-full flex items-center justify-center text-surface-500">
                            <div class="text-center flex flex-col items-center justify-center">
                                <Icon name="video" class="w-16 h-16 text-surface-500 mb-4" />
                                <p>الفيديو غير متاح حالياً</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lesson Info & Tabs -->
                <div class="flex-1 overflow-y-auto bg-surface-950 p-6 max-w-4xl mx-auto w-full">
                    <div class="flex items-start justify-between gap-4 mb-6">
                        <div>
                            <div class="text-xs text-surface-400 mb-1">
                                الدرس {{ activeLessonIndex + 1 }} من {{ lessons.length }}
                            </div>
                            <h2 class="text-xl font-bold text-white">{{ activeLesson?.title }}</h2>
                        </div>
                        <div class="flex items-center gap-3">
                            <form v-if="course.teacher" @submit.prevent="router.post(route('chat.start'), { course_id: course.id, teacher_id: course.teacher.id })">
                                <button type="submit" class="btn-sm bg-primary-600 hover:bg-primary-700 text-white font-bold rounded-xl flex items-center gap-1.5 transition-colors text-xs py-1.5 px-3">
                                    <Icon name="chat" class="w-3.5 h-3.5" />
                                    <span>راسل المدرس</span>
                                </button>
                            </form>

                            <div v-if="localCompleted[activeLesson?.id]"
                                 class="badge-green flex-shrink-0">
                                ✓ مكتمل
                            </div>
                        </div>
                    </div>

                    <!-- Tabs Header -->
                    <div class="flex border-b border-surface-800 mb-6 bg-surface-900/10 p-1 rounded-xl">
                        <button @click="activeTab = 'description'"
                                class="px-4 py-2 text-sm font-bold border-b-2 transition-all"
                                :class="activeTab === 'description' ? 'border-primary-500 text-primary-400' : 'border-transparent text-surface-400 hover:text-white'"
                        >
                            الشرح والمشاهدة
                        </button>
                        <button @click="activeTab = 'worksheets'"
                                class="px-4 py-2 text-sm font-bold border-b-2 transition-all"
                                :class="activeTab === 'worksheets' ? 'border-primary-500 text-primary-400' : 'border-transparent text-surface-400 hover:text-white'"
                        >
                            الملفات والواجبات ({{ filteredWorksheets.length }})
                        </button>
                        <button @click="activeTab = 'questions'"
                                class="px-4 py-2 text-sm font-bold border-b-2 transition-all"
                                :class="activeTab === 'questions' ? 'border-primary-500 text-primary-400' : 'border-transparent text-surface-400 hover:text-white'"
                        >
                            الأسئلة والنقاشات ({{ questions.length }})
                        </button>
                    </div>

                    <!-- Tab Content: Description -->
                    <div v-if="activeTab === 'description'" class="space-y-6">
                        <p class="text-sm text-surface-300 leading-relaxed">
                            {{ activeLesson?.description || 'لا يوجد وصف متاح لهذا الدرس حالياً.' }}
                        </p>

                        <div class="flex gap-3 pt-4">
                            <button
                                v-if="activeLessonIndex < localLessons.length - 1"
                                @click="goNextLesson"
                                class="btn-primary"
                                id="next-lesson-btn"
                            >
                                الدرس التالي ←
                            </button>
                            <Link
                                v-else
                                :href="route('dashboard')"
                                class="btn-outline text-white border-surface-600 hover:bg-surface-800"
                            >
                                العودة للداشبورد
                            </Link>
                        </div>
                    </div>

                    <!-- Tab Content: Worksheets -->
                    <div v-if="activeTab === 'worksheets'" class="space-y-6">
                        <div v-for="sheet in filteredWorksheets" :key="sheet.id" 
                             class="bg-surface-900 border border-surface-800 rounded-2xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4"
                        >
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <span :class="sheet.type === 'homework' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'bg-primary-500/10 text-primary-400 border border-primary-500/20'" class="text-[10px] px-2.5 py-0.5 rounded font-bold uppercase">
                                        {{ sheet.type === 'homework' ? 'واجب منزلي' : 'شيت دراسي' }}
                                    </span>
                                    <span v-if="sheet.requires_submission" class="text-[10px] bg-red-500/10 text-red-400 border border-red-500/20 px-2.5 py-0.5 rounded font-bold">
                                        يتطلب تسليماً
                                    </span>
                                </div>
                                <h4 class="font-bold text-white text-base mb-1">{{ sheet.title }}</h4>
                                <p v-if="sheet.due_date" class="text-xs text-surface-400">آخر موعد: {{ new Date(sheet.due_date).toLocaleDateString('ar') }}</p>
                            </div>

                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                                <!-- Download Link -->
                                <a :href="sheet.file_path" target="_blank" class="btn bg-surface-800 text-white hover:bg-surface-700 btn-sm text-center">
                                    تحميل الشيت 📥
                                </a>

                                <!-- Submit Homework Interface -->
                                <div v-if="sheet.type === 'homework' && sheet.requires_submission" class="flex flex-col gap-2">
                                    <!-- Status of submission -->
                                    <div v-if="sheet.submissions && sheet.submissions.length > 0" class="text-xs text-surface-300 bg-surface-950 p-3 rounded-xl border border-surface-800">
                                        <div class="font-bold text-green-400 mb-1">✓ تم تسليم الحل</div>
                                        <div v-if="sheet.submissions[0].score !== null" class="font-bold text-primary-400">
                                            الدرجة المرصودة: {{ sheet.submissions[0].score }} / {{ sheet.max_score }}
                                        </div>
                                        <div v-if="sheet.submissions[0].teacher_feedback" class="text-surface-400 mt-1 italic">
                                            تعليق المعلم: "{{ sheet.submissions[0].teacher_feedback }}"
                                        </div>
                                    </div>

                                    <!-- Upload solution form -->
                                    <div class="flex items-center gap-2">
                                        <input type="file" @change="onFileChange($event, sheet.id)" class="text-xs text-surface-400 bg-surface-950 border border-surface-800 rounded-xl px-2 py-1.5 w-44" />
                                        <button @click="uploadHomework(sheet.id)" class="btn-primary btn-sm">
                                            تسليم 📤
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="filteredWorksheets.length === 0" class="text-center py-10 text-surface-500">
                            لا توجد أوراق عمل أو ملفات مرفقة لهذا الدرس.
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
            </div>

            <!-- ── Lessons Sidebar ────────────────────────────── -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="w-0 opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="w-0 opacity-0"
            >
                <aside v-if="sidebarOpen"
                       class="w-80 flex-shrink-0 bg-surface-900 border-s border-surface-800 overflow-y-auto hidden md:flex flex-col">
                    <div class="p-4 border-b border-surface-800">
                        <h3 class="text-white font-bold text-sm">محتوى الكورس</h3>
                        <p class="text-surface-400 text-xs mt-1">
                            {{ completedCount }} / {{ localLessons.value.length }} مكتمل
                        </p>
                    </div>

                    <div class="flex-1">
                        <button
                            v-for="(lesson, idx) in localLessons"
                            :key="lesson.id"
                            @click="selectLesson(idx)"
                            class="w-full flex items-start gap-3 p-4 text-start transition-colors duration-150"
                            :class="idx === activeLessonIndex
                                ? 'bg-primary-900/50 border-s-2 border-primary-500'
                                : 'hover:bg-surface-800 border-s-2 border-transparent'"
                            :id="`lesson-btn-${lesson.id}`"
                        >
                            <!-- Status icon -->
                            <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5 text-xs"
                                 :class="lesson.is_locked 
                                    ? 'bg-surface-800 text-surface-600'
                                    : localCompleted[lesson.id]
                                        ? 'bg-green-500 text-white'
                                        : idx === activeLessonIndex
                                            ? 'bg-primary-500 text-white'
                                            : 'bg-surface-700 text-surface-400'"
                            >
                                <span v-if="lesson.is_locked">🔒</span>
                                <span v-else-if="localCompleted[lesson.id]">✓</span>
                                <span v-else>{{ idx + 1 }}</span>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium line-clamp-2"
                                     :class="lesson.is_locked
                                        ? 'text-surface-500'
                                        : idx === activeLessonIndex
                                            ? 'text-white font-bold'
                                            : 'text-surface-300'">
                                    {{ lesson.title }}
                                </div>
                                <div class="text-xs text-surface-500 mt-0.5 flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ formatDuration(lesson.duration_seconds) }}
                                </div>
                            </div>
                        </button>
                    </div>
                </aside>
            </Transition>
        </div>
    </div>
</template>
