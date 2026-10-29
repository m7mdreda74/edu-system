<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    sessions:    { type: Array, default: () => [] },
    homework:    { type: Array, default: () => [] },
    assessments: { type: Array, default: () => [] },
});

let pollTimer = null;
const activeFilter = ref('all'); // 'all' | 'sessions' | 'homework' | 'assessments'

onMounted(() => {
    // Poll every 15 seconds to auto-unlock the entry button once teacher starts the class
    pollTimer = window.setInterval(() => {
        router.reload({ only: ['sessions'], preserveScroll: true });
    }, 15000);
});

onBeforeUnmount(() => {
    if (pollTimer) {
        window.clearInterval(pollTimer);
        pollTimer = null;
    }
});

// Group live sessions by date
const groupedSessions = computed(() => {
    const groups = new Map();

    props.sessions.forEach((session) => {
        const date = new Date(session.scheduled_at);
        const dateParts = Object.fromEntries(
            new Intl.DateTimeFormat('en-US', {
                timeZone: session.timezone,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
            }).formatToParts(date).map(({ type, value }) => [type, value]),
        );
        const key = `${dateParts.year}-${dateParts.month}-${dateParts.day}`;

        if (!groups.has(key)) {
            groups.set(key, {
                key,
                label: date.toLocaleDateString('ar-QA', {
                    timeZone: session.timezone,
                    weekday: 'long',
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric',
                }),
                sessions: [],
            });
        }

        groups.get(key).sessions.push(session);
    });

    return Array.from(groups.values());
});

function formatTime(session) {
    return new Date(session.scheduled_at).toLocaleTimeString('ar-QA', {
        timeZone: session.timezone,
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatDueDate(dueDate) {
    if (!dueDate) return 'بدون موعد نهائي';
    return new Date(`${dueDate}T00:00:00`).toLocaleDateString('ar-QA', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });
}
</script>

<template>
    <Head title="التقويم الدراسي وجدول الحصص" />

    <DashboardLayout>
        <div class="space-y-6 pb-12">
            <!-- Header -->
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white flex items-center gap-2.5">
                        <Icon name="calendar" class="w-8 h-8 text-primary-500" />
                        <span>التقويم الدراسي والجدول الأسبوعي</span>
                    </h1>
                    <p class="mt-1.5 text-sm text-surface-500 dark:text-surface-400">
                        متابعة مواعيد الحصص المباشرة، المواعيد النهائية لتسليم التقييمات، وأوقات الاختبارات المقررة.
                    </p>
                </div>

                <!-- Fast Filter Pills -->
                <div class="flex items-center gap-2 flex-wrap">
                    <button
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all"
                        :class="activeFilter === 'all' ? 'bg-primary-600 text-white shadow-glow-primary' : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200'"
                        @click="activeFilter = 'all'"
                    >
                        الكل ({{ sessions.length + homework.length + assessments.length }})
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all"
                        :class="activeFilter === 'sessions' ? 'bg-primary-600 text-white shadow-glow-primary' : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200'"
                        @click="activeFilter = 'sessions'"
                    >
                        الحصص المباشرة ({{ sessions.length }})
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all"
                        :class="activeFilter === 'homework' ? 'bg-primary-600 text-white shadow-glow-primary' : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200'"
                        @click="activeFilter = 'homework'"
                    >
                        التقييمات الدورية ({{ homework.length }})
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all"
                        :class="activeFilter === 'assessments' ? 'bg-primary-600 text-white shadow-glow-primary' : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200'"
                        @click="activeFilter = 'assessments'"
                    >
                        الاختبارات ({{ assessments.length }})
                    </button>
                </div>
            </header>

            <!-- 1. Live Sessions Section -->
            <section v-if="(activeFilter === 'all' || activeFilter === 'sessions') && groupedSessions.length" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <Icon name="live" class="w-5 h-5 text-red-500" />
                        <span>جدول الحصص المباشرة القادمة</span>
                    </h2>
                    <span class="text-xs font-bold text-surface-400">{{ sessions.length }} حصة مجدولة</span>
                </div>

                <div class="space-y-4">
                    <div v-for="day in groupedSessions" :key="day.key" class="card overflow-hidden border border-surface-200 dark:border-surface-800">
                        <div class="flex items-center gap-2 border-b border-surface-100 bg-surface-50 px-4 py-3 dark:border-surface-800 dark:bg-surface-900/60">
                            <Icon name="calendar" class="h-4 w-4 text-primary-600 dark:text-primary-400" />
                            <h3 class="text-sm font-black text-surface-800 dark:text-surface-100">{{ day.label }}</h3>
                        </div>

                        <div class="divide-y divide-surface-100 dark:divide-surface-800">
                            <article
                                v-for="session in day.sessions"
                                :key="session.id"
                                class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center justify-between"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="flex min-w-20 items-center gap-1.5 text-primary-700 dark:text-primary-300 font-mono text-sm font-black">
                                        <Icon name="clock" class="h-4 w-4 text-primary-500" />
                                        <span>{{ formatTime(session) }}</span>
                                    </div>

                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h4 class="text-sm font-black text-surface-900 dark:text-white">{{ session.title }}</h4>
                                            <span :class="session.type === 'private' ? 'badge-accent' : 'badge-primary'" class="text-[10px]">
                                                {{ session.type === 'private' ? 'حصة خاصة' : 'حصة جماعية' }}
                                            </span>
                                            <span v-if="session.status === 'live'" class="badge-red animate-pulse text-[10px]">مباشرة الآن 🔴</span>
                                        </div>
                                        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-surface-500 dark:text-surface-400">
                                            <span class="font-bold text-surface-700 dark:text-surface-300">{{ session.subject ?? 'مادة دراسية' }}</span>
                                            <span>·</span>
                                            <span>المعلم: {{ session.teacher?.name }}</span>
                                            <template v-if="session.group?.name">
                                                <span>·</span>
                                                <span>{{ session.group.name }}</span>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <div class="shrink-0">
                                    <Link
                                        v-if="session.status === 'live'"
                                        :href="route('live-sessions.room', session.id)"
                                        class="btn-primary btn-sm flex items-center gap-1.5 shadow-md shadow-primary-500/20 font-bold"
                                    >
                                        <span class="w-2 h-2 rounded-full bg-red-400 animate-ping"></span>
                                        <span>دخول الحصة الآن 🔴</span>
                                    </Link>
                                    <button
                                        v-else
                                        type="button"
                                        disabled
                                        class="btn-sm bg-surface-100 dark:bg-surface-800 text-surface-400 dark:text-surface-500 cursor-not-allowed flex items-center gap-1.5 border border-surface-200 dark:border-surface-700"
                                        title="يتفعل زر الدخول فور بدء المعلم للحصة"
                                    >
                                        <Icon name="clock" class="w-3.5 h-3.5 text-surface-400" />
                                        <span>بانتظار بدء المعلم</span>
                                    </button>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 2. Homework Due Dates Section -->
            <section v-if="(activeFilter === 'all' || activeFilter === 'homework') && homework.length" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <Icon name="edit" class="w-5 h-5 text-accent-500" />
                        <span>مواعيد تسليم التقييمات والتطبيقات</span>
                    </h2>
                    <span class="text-xs font-bold text-surface-400">{{ homework.length }} تقييم</span>
                </div>

                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="hw in homework"
                        :key="hw.id"
                        class="card p-5 flex flex-col justify-between space-y-4 border-r-4 border-r-accent-500 hover:shadow-md transition-all"
                    >
                        <div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-accent-500/10 text-accent-700 dark:text-accent-300">
                                    {{ hw.subject }}
                                </span>
                                <span
                                    class="text-[11px] font-bold px-2 py-0.5 rounded"
                                    :class="hw.status === 'graded' ? 'bg-green-500/10 text-green-600' : (hw.is_late ? 'bg-red-500/10 text-red-600' : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-400')"
                                >
                                    {{ hw.status === 'graded' ? 'تم التصحيح' : (hw.is_late ? 'متأخر' : 'مطلوب التسليم') }}
                                </span>
                            </div>

                            <h3 class="font-black text-sm text-surface-900 dark:text-white mt-2 leading-snug">
                                {{ hw.title }}
                            </h3>
                            <p class="text-xs text-surface-500 mt-1">المعلم: {{ hw.teacher }}</p>
                        </div>

                        <div class="flex items-center justify-between pt-3 border-t border-surface-100 dark:border-surface-800 text-xs">
                            <div class="flex items-center gap-1.5 text-surface-500">
                                <Icon name="clock" class="w-3.5 h-3.5 text-accent-500" />
                                <span>آخر موعد: <strong>{{ formatDueDate(hw.due_date) }}</strong></span>
                            </div>

                            <Link
                                :href="route('student.assessments')"
                                class="btn-accent btn-xs px-3 py-1 font-bold"
                            >
                                تسليم التقييم ←
                            </Link>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 3. Quizzes & Assessments Section -->
            <section v-if="(activeFilter === 'all' || activeFilter === 'assessments') && assessments.length" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <Icon name="chart" class="w-5 h-5 text-primary-500" />
                        <span>الاختبارات والتقييمات المقررة</span>
                    </h2>
                    <span class="text-xs font-bold text-surface-400">{{ assessments.length }} اختبار</span>
                </div>

                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="qz in assessments"
                        :key="qz.id"
                        class="card p-5 flex flex-col justify-between space-y-4 border-r-4 border-r-primary-500 hover:shadow-md transition-all"
                    >
                        <div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-primary-500/10 text-primary-700 dark:text-primary-300">
                                    {{ qz.subject }}
                                </span>
                                <span class="badge-primary text-[10px]">
                                    {{ qz.mode === 'electronic' ? 'إلكتروني' : 'ورقي / مقالي' }}
                                </span>
                            </div>

                            <h3 class="font-black text-sm text-surface-900 dark:text-white mt-2 leading-snug">
                                {{ qz.title }}
                            </h3>
                            <p class="text-xs text-surface-500 mt-1">المعلم: {{ qz.teacher }}</p>
                        </div>

                        <div class="flex items-center justify-between pt-3 border-t border-surface-100 dark:border-surface-800 text-xs">
                            <span class="text-surface-500">
                                <strong class="text-primary-600 font-bold">{{ qz.total_points }}</strong> درجة
                                <span v-if="qz.time_limit_minutes"> · {{ qz.time_limit_minutes }} دقيقة</span>
                            </span>

                            <Link
                                v-if="qz.mode === 'electronic' && qz.can_start"
                                :href="route('student.quiz.take', qz.id)"
                                class="btn-primary btn-xs px-3 py-1 font-bold"
                            >
                                خوض الاختبار ←
                            </Link>
                            <Link
                                v-else
                                :href="route('student.assessments')"
                                class="btn-outline btn-xs px-3 py-1 font-bold"
                            >
                                عرض التفاصيل
                            </Link>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Empty State -->
            <div
                v-if="!sessions.length && !homework.length && !assessments.length"
                class="card flex flex-col items-center justify-center p-16 text-center space-y-3"
            >
                <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-surface-100 dark:bg-surface-800">
                    <Icon name="calendar" class="h-8 w-8 text-surface-400" />
                </div>
                <h3 class="text-base font-black text-surface-800 dark:text-surface-100">لا توجد مواعيد مسجلة حالياً</h3>
                <p class="text-xs text-surface-500 dark:text-surface-400 max-w-sm">
                    فور أن يقوم معلموك بجدولة حصص جديدة أو إضافة تقييمات واختبارات بمواعيد تسليم، ستظهر في تقويمك فوراً.
                </p>
            </div>
        </div>
    </DashboardLayout>
</template>

