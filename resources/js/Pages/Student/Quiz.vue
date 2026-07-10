<script setup>
import { ref, computed, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    quiz:               { type: Object, required: true },
    questions:          { type: Array,  required: true },
    attempts:           { type: Array,  default: () => [] },
    remainingAttempts:  { type: Number, default: 0 },
});

// ── State ──────────────────────────────────────────────────────
const phase          = ref('intro');   // intro | taking | result
const attemptId      = ref(null);
const answers        = ref({});        // { [questionId]: [optionId, ...] }
const currentIdx     = ref(0);
const result         = ref(null);
const error          = ref('');
const timeLeft       = ref(null);
let   timerInterval  = null;

const currentQuestion = computed(() => props.questions[currentIdx.value]);

const isAnswered = (questionId) =>
    (answers.value[questionId]?.length ?? 0) > 0;

const allAnswered = computed(() =>
    props.questions.every(q => isAnswered(q.id))
);

const progressPercent = computed(() =>
    Math.round(((currentIdx.value + 1) / props.questions.length) * 100)
);

// ── Timer ──────────────────────────────────────────────────────
function startTimer() {
    if (!props.quiz.time_limit_minutes) return;

    timeLeft.value = props.quiz.time_limit_minutes * 60;

    timerInterval = setInterval(() => {
        timeLeft.value--;
        if (timeLeft.value <= 0) {
            clearInterval(timerInterval);
            submitQuiz(); // Auto-submit on time out
        }
    }, 1000);
}

const timeLeftFormatted = computed(() => {
    if (timeLeft.value === null) return null;
    const m = Math.floor(timeLeft.value / 60);
    const s = timeLeft.value % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
});

const isTimeRunningOut = computed(() =>
    timeLeft.value !== null && timeLeft.value <= 60
);

// ── Anti-Cheat ──────────────────────────────────────────────────
const violationsCount = ref(0);

function blockEvent(e) {
    e.preventDefault();
    alert('تنبيه: النسخ واللصق والنقر بزر الماوس الأيمن غير مسموح به أثناء الاختبار للحفاظ على النزاهة 🚫');
}

async function handleVisibilityChange() {
    if (document.hidden && phase.value === 'taking') {
        violationsCount.value++;
        
        try {
            await axios.post(route('student.quiz.violation', { quizId: props.quiz.id }), {
                attempt_id: attemptId.value,
            });
        } catch (e) {
            console.warn('Failed to log violation:', e);
        }

        alert(`تحذير هام ⚠️: لقد غادرت صفحة الاختبار! تم تسجيل مخالفة (${violationsCount.value}/3). سيتم تسليم الاختبار تلقائياً إذا تكرر ذلك 3 مرات.`);
        
        if (violationsCount.value >= 3) {
            alert('تم إنهاء الاختبار وتسليمه تلقائياً لتجاوزك الحد المسموح به من المخالفات.');
            submitQuiz();
        }
    }
}

function preventCheatingEvents() {
    document.addEventListener('copy', blockEvent);
    document.addEventListener('cut', blockEvent);
    document.addEventListener('paste', blockEvent);
    document.addEventListener('contextmenu', blockEvent);
    document.addEventListener('visibilitychange', handleVisibilityChange);
}

function restoreCheatingEvents() {
    document.removeEventListener('copy', blockEvent);
    document.removeEventListener('cut', blockEvent);
    document.removeEventListener('paste', blockEvent);
    document.removeEventListener('contextmenu', blockEvent);
    document.removeEventListener('visibilitychange', handleVisibilityChange);
}

// ── Actions ────────────────────────────────────────────────────
async function startQuiz() {
    error.value = '';
    violationsCount.value = 0;
    try {
        const res = await axios.post(route('student.quiz.start', { quizId: props.quiz.id }));
        attemptId.value = res.data.attempt_id;
        phase.value     = 'taking';
        answers.value   = {};
        currentIdx.value = 0;
        startTimer();
        preventCheatingEvents();
    } catch (e) {
        error.value = e.response?.data?.error ?? 'حدث خطأ، حاول مجدداً';
    }
}

function selectOption(questionId, optionId, type) {
    if (type === 'single' || type === 'true_false') {
        // Single select: replace answer
        answers.value[questionId] = [optionId];
    } else {
        // Multiple select: toggle
        const current = answers.value[questionId] ?? [];
        const idx     = current.indexOf(optionId);
        if (idx === -1) {
            answers.value[questionId] = [...current, optionId];
        } else {
            answers.value[questionId] = current.filter(id => id !== optionId);
        }
    }
}

function isSelected(questionId, optionId) {
    return (answers.value[questionId] ?? []).includes(optionId);
}

function goNext() {
    if (currentIdx.value < props.questions.length - 1) {
        currentIdx.value++;
    }
}

function goPrev() {
    if (currentIdx.value > 0) {
        currentIdx.value--;
    }
}

async function submitQuiz() {
    clearInterval(timerInterval);
    restoreCheatingEvents();
    error.value = '';

    try {
        const res = await axios.post(route('student.quiz.submit', { quizId: props.quiz.id }), {
            attempt_id: attemptId.value,
            answers:    answers.value,
        });

        result.value = res.data;
        phase.value  = 'result';
    } catch (e) {
        error.value = e.response?.data?.error ?? 'فشل إرسال الاختبار، حاول مجدداً';
    }
}

onUnmounted(() => {
    clearInterval(timerInterval);
    restoreCheatingEvents();
});
</script>

<template>
    <AppLayout>
        <Head :title="quiz.title" />

        <div class="container-app px-4 py-10 max-w-3xl">

            <!-- ── Intro Phase ──────────────────────────────── -->
            <div v-if="phase === 'intro'">
                <div class="card p-8 text-center mb-6 flex flex-col items-center justify-center">
                    <div class="p-4 bg-surface-100 dark:bg-surface-800 rounded-full text-primary-500 mb-4">
                        <Icon name="courses" class="w-12 h-12" />
                    </div>
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white mb-2">{{ quiz.title }}</h1>

                    <div class="grid grid-cols-3 gap-4 my-6">
                        <div class="card p-4 text-center">
                            <div class="text-2xl font-bold text-primary-700 dark:text-primary-400">
                                {{ quiz.questions_count }}
                            </div>
                            <div class="text-xs text-surface-500 dark:text-surface-400 mt-1">سؤال</div>
                        </div>
                        <div class="card p-4 text-center">
                            <div class="text-2xl font-bold text-primary-700 dark:text-primary-400">
                                {{ quiz.passing_score }}%
                            </div>
                            <div class="text-xs text-surface-500 dark:text-surface-400 mt-1">درجة النجاح</div>
                        </div>
                        <div class="card p-4 text-center">
                            <div class="text-2xl font-bold text-primary-700 dark:text-primary-400">
                                {{ quiz.time_limit_minutes ? quiz.time_limit_minutes + ' د' : '∞' }}
                            </div>
                            <div class="text-xs text-surface-500 dark:text-surface-400 mt-1">الوقت</div>
                        </div>
                    </div>

                    <div class="text-sm text-surface-500 dark:text-surface-400 mb-6">
                        المحاولات المتبقية: <strong class="text-primary-700 dark:text-primary-400">{{ remainingAttempts }}</strong>
                    </div>

                    <!-- Previous attempts -->
                    <div v-if="attempts.length" class="mb-6 text-start">
                        <h3 class="font-semibold text-sm text-surface-700 dark:text-surface-300 mb-2">محاولاتك السابقة:</h3>
                        <div v-for="(att, i) in attempts" :key="att.id"
                             class="flex items-center justify-between p-3 rounded-xl mb-2"
                             :class="att.passed ? 'bg-green-50 dark:bg-green-950/30' : 'bg-red-50 dark:bg-red-950/30'"
                        >
                            <span class="text-sm font-medium">محاولة {{ i + 1 }}</span>
                            <div class="flex items-center gap-2">
                                <span class="font-bold" :class="att.passed ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400'">
                                    {{ att.score }}%
                                </span>
                                <span v-if="att.passed" class="badge-green text-xs">نجاح</span>
                                <span v-else class="badge-red text-xs">رسوب</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="error" class="alert-error mb-4">
                        {{ error }}
                    </div>

                    <button
                        v-if="remainingAttempts > 0"
                        @click="startQuiz"
                        class="btn-primary btn-lg w-full"
                        id="start-quiz-btn"
                    >
                        ابدأ الاختبار
                    </button>
                    <div v-else class="alert-error">
                        استنفذت جميع محاولاتك.
                    </div>
                </div>
            </div>

            <!-- ── Taking Phase ─────────────────────────────── -->
            <div v-if="phase === 'taking'">

                <!-- Header -->
                <div class="flex items-center justify-between mb-6">
                    <div class="text-sm text-surface-500 dark:text-surface-400">
                        سؤال {{ currentIdx + 1 }} / {{ questions.length }}
                    </div>

                    <!-- Timer -->
                    <div v-if="timeLeftFormatted"
                         class="font-mono font-bold px-3 py-1.5 rounded-lg text-sm"
                         :class="isTimeRunningOut
                            ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-400 animate-pulse'
                            : 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300'"
                    >
                        ⏱ {{ timeLeftFormatted }}
                    </div>
                </div>

                <!-- Progress -->
                <div class="progress-bar mb-6">
                    <div class="progress-bar-fill" :style="{ width: progressPercent + '%' }"></div>
                </div>

                <!-- Question -->
                <div class="card p-6 mb-4">
                    <div class="text-xs text-primary-600 dark:text-primary-400 font-semibold mb-2">
                        {{ currentQuestion?.type === 'multiple' ? 'اختر أكثر من إجابة' : 'اختر الإجابة الصحيحة' }}
                    </div>
                    <h2 class="text-lg font-bold text-surface-900 dark:text-white leading-relaxed mb-6">
                        {{ currentQuestion?.text }}
                    </h2>

                    <!-- Options -->
                    <div class="space-y-3">
                        <button
                            v-for="option in currentQuestion?.options"
                            :key="option.id"
                            @click="selectOption(currentQuestion.id, option.id, currentQuestion.type)"
                            class="w-full text-start p-4 rounded-xl border-2 transition-all duration-150 font-medium text-sm"
                            :class="isSelected(currentQuestion.id, option.id)
                                ? 'border-primary-500 bg-primary-50 text-primary-800 dark:bg-primary-950 dark:text-primary-200 dark:border-primary-400'
                                : 'border-surface-200 dark:border-surface-700 hover:border-primary-300 dark:hover:border-primary-600 text-surface-700 dark:text-surface-200 bg-white dark:bg-surface-800'"
                            :id="`option-${option.id}`"
                        >
                            <span class="flex items-center gap-3">
                                <span class="w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0"
                                      :class="isSelected(currentQuestion.id, option.id)
                                        ? 'border-primary-500 bg-primary-500'
                                        : 'border-surface-300 dark:border-surface-600'"
                                >
                                    <span v-if="isSelected(currentQuestion.id, option.id)"
                                          class="w-2 h-2 rounded-full bg-white"></span>
                                </span>
                                {{ option.text }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="flex items-center justify-between gap-3">
                    <button
                        @click="goPrev"
                        :disabled="currentIdx === 0"
                        class="btn-ghost"
                        :class="{ 'opacity-40 cursor-not-allowed': currentIdx === 0 }"
                    >
                        ← السابق
                    </button>

                    <!-- Question dots -->
                    <div class="flex gap-1.5 flex-wrap justify-center">
                        <button
                            v-for="(q, idx) in questions"
                            :key="q.id"
                            @click="currentIdx = idx"
                            class="w-3 h-3 rounded-full transition-all duration-150"
                            :class="idx === currentIdx
                                ? 'bg-primary-600 scale-125'
                                : isAnswered(q.id)
                                    ? 'bg-primary-300 dark:bg-primary-700'
                                    : 'bg-surface-300 dark:bg-surface-600'"
                            :title="`سؤال ${idx + 1}`"
                        />
                    </div>

                    <button
                        v-if="currentIdx < questions.length - 1"
                        @click="goNext"
                        class="btn-primary"
                        id="next-question-btn"
                    >
                        التالي →
                    </button>
                    <button
                        v-else
                        @click="submitQuiz"
                        :disabled="!allAnswered"
                        class="btn-accent"
                        :class="{ 'opacity-50 cursor-not-allowed': !allAnswered }"
                        id="submit-quiz-btn"
                    >
                        إرسال الاختبار
                    </button>
                </div>

                <div v-if="error" class="alert-error mt-4">
                    {{ error }}
                </div>
            </div>

            <!-- ── Result Phase ──────────────────────────────── -->
            <div v-if="phase === 'result' && result">
                <div class="card p-10 text-center flex flex-col items-center justify-center">
                    <div class="p-4 bg-surface-100 dark:bg-surface-800 rounded-full text-primary-500 mb-4">
                        <Icon :name="result.passed ? 'success' : 'error'" class="w-12 h-12" />
                    </div>

                    <h2 class="text-3xl font-black mb-2"
                        :class="result.passed
                            ? 'text-green-700 dark:text-green-400'
                            : 'text-red-700 dark:text-red-400'"
                    >
                        {{ result.passed ? 'نجحت!' : 'لم تنجح هذه المرة' }}
                    </h2>

                    <div class="text-6xl font-black my-6"
                         :class="result.passed
                            ? 'text-green-600 dark:text-green-400'
                            : 'text-red-600 dark:text-red-400'"
                    >
                        {{ result.score }}%
                    </div>

                    <p class="text-surface-500 dark:text-surface-400 text-sm mb-8">
                        درجة النجاح: {{ result.passing_score }}%
                    </p>

                    <div class="flex flex-col sm:flex-row gap-3 justify-center">
                        <button
                            v-if="!result.passed && remainingAttempts > 0"
                            @click="phase = 'intro'"
                            class="btn-primary"
                        >
                            أعد المحاولة
                        </button>
                        <Link :href="route('dashboard')" class="btn-outline">
                            العودة للداشبورد
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
