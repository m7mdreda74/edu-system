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
const quizQuestions  = ref([...props.questions]);
const result         = ref(null);
const error          = ref('');
const timeLeft       = ref(null);
let   timerInterval  = null;
const flaggedQuestionIds = ref(new Set());
const showSubmitModal    = ref(false);

const currentQuestion = computed(() => quizQuestions.value[currentIdx.value]);

const isAnswered = (questionId) =>
    (answers.value[questionId]?.length ?? 0) > 0;

function toggleFlag(questionId) {
    if (!questionId) return;
    if (flaggedQuestionIds.value.has(questionId)) {
        flaggedQuestionIds.value.delete(questionId);
    } else {
        flaggedQuestionIds.value.add(questionId);
    }
}

function isFlagged(questionId) {
    return flaggedQuestionIds.value.has(questionId);
}

const answeredCount = computed(() =>
    quizQuestions.value.filter(q => isAnswered(q.id)).length
);

const unansweredCount = computed(() =>
    quizQuestions.value.length - answeredCount.value
);

const flaggedCount = computed(() =>
    flaggedQuestionIds.value.size
);

const allAnswered = computed(() =>
    quizQuestions.value.every(q => isAnswered(q.id))
);

const progressPercent = computed(() =>
    quizQuestions.value.length
        ? Math.round(((currentIdx.value + 1) / quizQuestions.value.length) * 100)
        : 0
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
        quizQuestions.value = res.data.questions ?? [];
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
    if (currentIdx.value < quizQuestions.value.length - 1) {
        currentIdx.value++;
    }
}

function goPrev() {
    if (currentIdx.value > 0) {
        currentIdx.value--;
    }
}

function jumpToQuestion(idx) {
    if (idx >= 0 && idx < quizQuestions.value.length) {
        currentIdx.value = idx;
    }
}

function jumpToFirstUnanswered() {
    showSubmitModal.value = false;
    const firstIdx = quizQuestions.value.findIndex(q => !isAnswered(q.id));
    if (firstIdx !== -1) {
        currentIdx.value = firstIdx;
    }
}

const isSubmitting = ref(false);

async function submitQuiz() {
    showSubmitModal.value = false;
    clearInterval(timerInterval);
    restoreCheatingEvents();
    error.value = '';
    isSubmitting.value = true;

    try {
        const res = await axios.post(route('student.quiz.submit', { quizId: props.quiz.id }), {
            attempt_id: attemptId.value,
            answers:    answers.value,
        });

        result.value = res.data;
        phase.value  = 'result';
    } catch (e) {
        error.value = e.response?.data?.error ?? 'فشل إرسال الاختبار، حاول مجدداً';
    } finally {
        isSubmitting.value = false;
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
                                {{ quiz.total_points }}
                            </div>
                            <div class="text-xs text-surface-500 dark:text-surface-400 mt-1">إجمالي النقاط</div>
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
                                    {{ att.earned_points }}/{{ att.total_points }} نقطة · {{ att.score }}%
                                </span>
                                <span v-if="att.passed" class="badge-green text-xs">نجاح</span>
                                <span v-else class="badge-red text-xs">رسوب</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="error" class="alert-error mb-4">
                        {{ error }}
                    </div>

                    <button type="button"
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

                <!-- Top Header: Progress Counter & Timer & Flag & Quick Finish -->
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div class="flex items-center gap-2">
                        <span class="badge-primary text-xs font-bold px-2.5 py-1">
                            سؤال {{ currentIdx + 1 }} من {{ quizQuestions.length }}
                        </span>
                        <button type="button"
                            @click="toggleFlag(currentQuestion?.id)"
                            class="px-3 py-1 text-xs rounded-lg font-medium flex items-center gap-1.5 transition-all"
                            :class="isFlagged(currentQuestion?.id)
                                ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-300 border border-amber-300 dark:border-amber-700'
                                : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300 hover:bg-surface-200 border border-transparent'"
                            id="flag-question-btn"
                        >
                            <span>{{ isFlagged(currentQuestion?.id) ? '🚩 معلم للمراجعة' : '🏳️ تمييز للمراجعة' }}</span>
                        </button>
                    </div>

                    <div class="flex items-center gap-3">
                        <!-- Timer -->
                        <div v-if="timeLeftFormatted"
                             class="font-mono font-bold px-3 py-1.5 rounded-lg text-sm flex items-center gap-1.5"
                             :class="isTimeRunningOut
                                ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-400 animate-pulse'
                                : 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300'"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            {{ timeLeftFormatted }}
                        </div>

                        <!-- Direct Finish/Submit trigger -->
                        <button type="button"
                            @click="showSubmitModal = true"
                            class="btn-outline btn-sm text-xs font-bold text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-950/50"
                        >
                            إنهاء الاختبار 📋
                        </button>
                    </div>
                </div>

                <!-- Progress bar -->
                <div class="progress-bar mb-4">
                    <div class="progress-bar-fill" :style="{ width: progressPercent + '%' }"></div>
                </div>

                <!-- Question Palette (شبكة أرقام أسئلة الاختبار) -->
                <div class="card p-3.5 mb-5 bg-surface-50 dark:bg-surface-800/40 border border-surface-200 dark:border-surface-700">
                    <div class="flex flex-wrap items-center justify-between text-xs text-surface-500 dark:text-surface-400 mb-2 gap-2">
                        <span class="font-medium">لوحة التنقل بين الأسئلة:</span>
                        <div class="flex items-center gap-3 font-medium">
                            <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span> مجاب ({{ answeredCount }})
                            </span>
                            <span class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400">
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span> مراجعة ({{ flaggedCount }})
                            </span>
                            <span class="inline-flex items-center gap-1 text-surface-500">
                                <span class="w-2 h-2 rounded-full bg-surface-300 dark:bg-surface-600"></span> متبقي ({{ unansweredCount }})
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button"
                            v-for="(q, idx) in quizQuestions"
                            :key="q.id"
                            @click="jumpToQuestion(idx)"
                            class="w-9 h-9 rounded-xl text-xs font-bold transition-all relative flex items-center justify-center border"
                            :class="[
                                idx === currentIdx ? 'ring-2 ring-primary-500 ring-offset-2 dark:ring-offset-surface-900 scale-105' : '',
                                isFlagged(q.id)
                                    ? 'bg-amber-500 text-white border-amber-600 shadow-sm'
                                    : (isAnswered(q.id)
                                        ? 'bg-emerald-600 text-white border-emerald-700 shadow-sm'
                                        : 'bg-white dark:bg-surface-800 text-surface-700 dark:text-surface-300 border-surface-200 dark:border-surface-700 hover:border-primary-400')
                            ]"
                            :title="`سؤال ${idx + 1} (${isAnswered(q.id) ? 'مجاب' : 'غير مجاب'}${isFlagged(q.id) ? ' - معلَم للمراجعة' : ''})`"
                        >
                            {{ idx + 1 }}
                            <span v-if="isFlagged(q.id) && isAnswered(q.id)" class="absolute -top-1 -right-1 w-2 h-2 rounded-full bg-amber-400 ring-1 ring-white"></span>
                        </button>
                    </div>
                </div>

                <!-- Current Question Card -->
                <div class="card p-6 mb-5">
                    <div class="text-xs text-primary-600 dark:text-primary-400 font-semibold mb-2">
                        {{ currentQuestion?.type === 'multiple' ? 'اختر أكثر من إجابة' : (currentQuestion?.type === 'true_false' ? 'حدد صحة العبارة (صواب أم خطأ)' : 'اختر الإجابة الصحيحة') }}
                        · {{ currentQuestion?.points }} نقطة
                    </div>
                    <h2 class="text-lg font-bold text-surface-900 dark:text-white leading-relaxed mb-6">
                        {{ currentQuestion?.text }}
                    </h2>

                    <!-- Options -->
                    <div class="space-y-3">
                        <button type="button"
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

                <!-- Navigation Controls -->
                <div class="flex items-center justify-between gap-3">
                    <button type="button"
                        @click="goPrev"
                        :disabled="currentIdx === 0"
                        class="btn-ghost"
                        :class="{ 'opacity-40 cursor-not-allowed': currentIdx === 0 }"
                    >
                        ← السابق
                    </button>

                    <button type="button"
                        v-if="currentIdx < quizQuestions.length - 1"
                        @click="goNext"
                        class="btn-primary"
                        id="next-question-btn"
                    >
                        التالي →
                    </button>
                    <button type="button"
                        v-else
                        @click="showSubmitModal = true"
                        class="btn-accent"
                        id="submit-quiz-btn"
                    >
                        مراجعة وتسليم الاختبار ✓
                    </button>
                </div>

                <div v-if="error" class="alert-error mt-4">
                    {{ error }}
                </div>
            </div>

            <!-- ── Pre-Submission Confirmation Modal ───────────────── -->
            <div v-if="showSubmitModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
                <div class="card p-6 max-w-lg w-full bg-white dark:bg-surface-900 shadow-2xl border border-surface-200 dark:border-surface-700">
                    <div class="flex items-center justify-between mb-4 border-b border-surface-100 dark:border-surface-800 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">📋</span>
                            <h3 class="text-lg font-bold text-surface-900 dark:text-white">تأكيد تسليم الاختبار</h3>
                        </div>
                        <button type="button" @click="showSubmitModal = false" class="text-surface-400 hover:text-surface-600 text-xl font-bold">✕</button>
                    </div>

                    <p class="text-sm text-surface-600 dark:text-surface-300 mb-4">
                        يرجى مراجعة ملخص إجاباتك قبل التسليم النهائي. بمجرد التسليم، لن تتمكن من تعديل الإجابات.
                    </p>

                    <!-- Breakdown Cards -->
                    <div class="grid grid-cols-3 gap-3 mb-4">
                        <div class="p-3 rounded-xl bg-surface-50 dark:bg-surface-800 text-center border border-surface-200 dark:border-surface-700">
                            <div class="text-xs text-surface-500 mb-1">الأسئلة الإجمالية</div>
                            <div class="text-xl font-bold text-surface-900 dark:text-white">{{ quizQuestions.length }}</div>
                        </div>
                        <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-center border border-emerald-200 dark:border-emerald-800">
                            <div class="text-xs text-emerald-700 dark:text-emerald-400 mb-1">تمت إجابتها</div>
                            <div class="text-xl font-bold text-emerald-700 dark:text-emerald-400">{{ answeredCount }}</div>
                        </div>
                        <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-center border border-amber-200 dark:border-amber-800">
                            <div class="text-xs text-amber-700 dark:text-amber-400 mb-1">غير مجابة</div>
                            <div class="text-xl font-bold text-amber-700 dark:text-amber-400">{{ unansweredCount }}</div>
                        </div>
                    </div>

                    <!-- Warning for unanswered questions -->
                    <div v-if="unansweredCount > 0" class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300 text-xs mb-4 flex items-start gap-2">
                        <span class="text-base">⚠️</span>
                        <div>
                            <strong>انتباه:</strong> لديك {{ unansweredCount }} سؤال لم تقم بالإجابة عليه بعد. الأسئلة المتروكة ستحتسب درجاتها صفراً.
                        </div>
                    </div>

                    <div v-if="flaggedCount > 0" class="p-2.5 rounded-xl bg-surface-100 dark:bg-surface-800 text-xs text-surface-600 dark:text-surface-300 mb-4 flex items-center gap-2">
                        <span>🚩</span>
                        <span>لديك {{ flaggedCount }} سؤال معلَم للمراجعة.</span>
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-col sm:flex-row gap-2.5 justify-end">
                        <button type="button"
                            v-if="unansweredCount > 0"
                            @click="jumpToFirstUnanswered"
                            class="btn-outline text-xs py-2 px-3 order-2 sm:order-1"
                        >
                            الذهاب للأسئلة المتبقية 🔍
                        </button>
                        <button type="button"
                            @click="showSubmitModal = false"
                            class="btn-ghost text-xs py-2 px-3 order-3 sm:order-2"
                        >
                            الرجوع للاختبار
                        </button>
                        <button type="button"
                            @click="submitQuiz"
                            :disabled="isSubmitting"
                            class="btn-accent text-xs py-2.5 px-4 font-bold order-1 sm:order-3"
                        >
                            <span v-if="isSubmitting">جاري الإرسال...</span>
                            <span v-else>تأكيد التسليم الآن ✓</span>
                        </button>
                    </div>
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
                        جمعت {{ result.earned_points }} من {{ result.total_points }} نقطة
                        · درجة النجاح: {{ result.passing_score }}%
                    </p>

                    <div class="flex flex-col sm:flex-row gap-3 justify-center">
                        <button type="button"
                            v-if="remainingAttempts > 0"
                            @click="phase = 'intro'"
                            class="btn-primary"
                        >
                            إعادة المحاولة (متبقي {{ remainingAttempts }})
                        </button>
                        <Link :href="route('student.assessments')" class="btn-primary shadow-glow-primary">
                            العودة للواجبات والتقييمات
                        </Link>
                        <Link :href="route('dashboard')" class="btn-outline">
                            اللوحة الرئيسية
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
