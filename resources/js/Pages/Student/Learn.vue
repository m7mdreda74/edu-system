<script setup>
import { ref, computed, onUnmounted, watch } from 'vue';
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
const activeLessonIndex = ref(0);
const progressPercent   = ref(props.enrollment.progress_percent);
const sidebarOpen       = ref(true);
const isCompleted       = ref(props.enrollment.completed_at !== null);
const activeTab         = ref('description'); // description | worksheets

const activeLesson = computed(() => props.lessons[activeLessonIndex.value] ?? null);

const completedCount = computed(() =>
    props.lessons.filter(l => l.is_completed).length
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
    // Debounce — don't report same position twice
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

        // Mark lesson as completed locally for instant UI update
        if (watchedSeconds >= lesson.duration_seconds * 0.8) {
            localCompleted.value[lesson.id] = true;
        }
    } catch (e) {
        // Silent fail — progress will sync on next report
        console.warn('Progress update failed, will retry:', e.message);
    }
}

function selectLesson(index) {
    clearInterval(progressInterval);
    lastReportedSeconds = 0;
    activeLessonIndex.value = index;
}

function goNextLesson() {
    if (activeLessonIndex.value < props.lessons.length - 1) {
        selectLesson(activeLessonIndex.value + 1);
    }
}

function formatDuration(seconds) {
    if (!seconds) return '0:00';
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
}

watch(activeLesson, () => {
    if (player) {
        player.destroy();
        player = null;
    }
    
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
                    <div class="max-w-4xl mx-auto w-full aspect-video">
                        <video
                            v-if="activeLesson?.video_url"
                            ref="videoRef"
                            class="w-full h-full"
                            controls
                            crossorigin
                            playsinline
                        >
                            <source :src="activeLesson.video_url" type="video/mp4" />
                        </video>
                        <div v-else class="w-full h-full flex items-center justify-center text-surface-500">
                            <div class="text-center">
                                <div class="text-6xl mb-4">🎥</div>
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
                        <div v-if="localCompleted[activeLesson?.id]"
                             class="badge-green flex-shrink-0">
                            ✓ مكتمل
                        </div>
                    </div>

                    <!-- Tabs Header -->
                    <div class="flex border-b border-surface-800 mb-6">
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
                    </div>

                    <!-- Tab Content: Description -->
                    <div v-if="activeTab === 'description'" class="space-y-6">
                        <p class="text-sm text-surface-300 leading-relaxed">
                            {{ activeLesson?.description || 'لا يوجد وصف متاح لهذا الدرس حالياً.' }}
                        </p>

                        <div class="flex gap-3 pt-4">
                            <button
                                v-if="activeLessonIndex < lessons.length - 1"
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
                            {{ completedCount }} / {{ lessons.length }} مكتمل
                        </p>
                    </div>

                    <div class="flex-1">
                        <button
                            v-for="(lesson, idx) in lessons"
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
                                 :class="localCompleted[lesson.id]
                                    ? 'bg-green-500 text-white'
                                    : idx === activeLessonIndex
                                        ? 'bg-primary-500 text-white'
                                        : 'bg-surface-700 text-surface-400'"
                            >
                                <span v-if="localCompleted[lesson.id]">✓</span>
                                <span v-else>{{ idx + 1 }}</span>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium line-clamp-2"
                                     :class="idx === activeLessonIndex
                                        ? 'text-white'
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
