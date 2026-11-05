<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import { formatSchedule } from '@/lib/money';

const props = defineProps({
    sessions:     { type: Array, default: () => [] },
    pastSessions: { type: Array, default: () => [] },
    subjects:     { type: Array, default: () => [] },
    groups:       { type: Array, default: () => [] },
});

let pollTimer = null;

// Subject tab: 'all' | number (subject.id)
const selectedSubjectId = ref('all');
// Group tab: 'all' | 'private' | `group-${group.id}`
const selectedGroupKey = ref('all');
// Time view: 'all' (upcoming + past together) | 'upcoming' | 'past'
const timeFilter = ref('all');

onMounted(() => {
    // Poll every 15 seconds to auto-unlock the entry button once teacher starts the class
    pollTimer = window.setInterval(() => {
        router.reload({
            only: ['sessions', 'pastSessions'],
            preserveState: true,
            preserveScroll: true,
        });
    }, 15000);
});

onBeforeUnmount(() => {
    if (pollTimer) {
        window.clearInterval(pollTimer);
        pollTimer = null;
    }
});

const allCombinedSessions = computed(() => [...props.sessions, ...props.pastSessions]);

// Build subject tabs cleanly (even if a session only had subject name without subject_id)
const subjectTabs = computed(() => {
    const map = new Map();

    props.subjects.forEach((subject) => {
        if (subject?.id) {
            map.set(Number(subject.id), {
                key: Number(subject.id),
                id: Number(subject.id),
                name: subject.name,
            });
        }
    });

    allCombinedSessions.value.forEach((session) => {
        if (session.subject_id && !map.has(Number(session.subject_id))) {
            map.set(Number(session.subject_id), {
                key: Number(session.subject_id),
                id: Number(session.subject_id),
                name: session.subject || 'مادة دراسية',
            });
        }
    });

    return Array.from(map.values());
});

const selectedSubject = computed(() => {
    if (selectedSubjectId.value === 'all') return null;
    return subjectTabs.value.find((s) => s.id === Number(selectedSubjectId.value)) ?? null;
});

function selectSubject(subjectKey) {
    selectedSubjectId.value = subjectKey;
    if (subjectKey === 'all') {
        return;
    }

    if (selectedGroupKey.value.startsWith('group-')) {
        const currentGroupId = Number(selectedGroupKey.value.replace('group-', ''));
        const group = props.groups.find((g) => Number(g.id) === currentGroupId);
        if (!group || Number(group.subject_id) !== Number(subjectKey)) {
            selectedGroupKey.value = 'all';
        }
    } else if (selectedGroupKey.value === 'private') {
        const hasPrivate = allCombinedSessions.value.some(
            (s) => s.type === 'private' && matchesSubject(s, subjectKey),
        );
        if (!hasPrivate) {
            selectedGroupKey.value = 'all';
        }
    }
}

function matchesSubject(session, subjectKey = selectedSubjectId.value) {
    if (subjectKey === 'all') return true;
    if (session.subject_id != null) {
        return Number(session.subject_id) === Number(subjectKey);
    }
    const target = subjectTabs.value.find((s) => s.id === Number(subjectKey));
    return Boolean(target && session.subject === target.name);
}

function matchesGroup(session, groupKey = selectedGroupKey.value) {
    if (groupKey === 'all') return true;
    if (groupKey === 'private') return session.type === 'private';
    if (groupKey.startsWith('group-')) {
        const targetGroupId = Number(groupKey.replace('group-', ''));
        return Number(session.group?.id) === targetGroupId;
    }
    return true;
}

function subjectSessionCount(subjectId) {
    return allCombinedSessions.value.filter((s) => matchesSubject(s, subjectId)).length;
}

const visibleGroups = computed(() => {
    if (selectedSubjectId.value === 'all') {
        return props.groups;
    }
    return props.groups.filter((g) => Number(g.subject_id) === Number(selectedSubjectId.value));
});

const hasPrivateSessionsInSelection = computed(() =>
    allCombinedSessions.value.some((s) => s.type === 'private' && matchesSubject(s)),
);

function groupSessionCount(groupId) {
    return allCombinedSessions.value.filter(
        (s) => matchesSubject(s) && Number(s.group?.id) === Number(groupId),
    ).length;
}

const privateSessionCount = computed(() =>
    allCombinedSessions.value.filter((s) => matchesSubject(s) && s.type === 'private').length,
);

const activeScheduledGroups = computed(() => {
    const base = visibleGroups.value.filter((g) => g.schedules?.length);
    if (selectedGroupKey.value.startsWith('group-')) {
        const targetGroupId = Number(selectedGroupKey.value.replace('group-', ''));
        return base.filter((g) => Number(g.id) === targetGroupId);
    }
    if (selectedGroupKey.value === 'private') {
        return [];
    }
    return base;
});

const filteredUpcomingSessions = computed(() =>
    props.sessions.filter((s) => matchesSubject(s) && matchesGroup(s)),
);

const filteredPastSessions = computed(() =>
    props.pastSessions.filter((s) => matchesSubject(s) && matchesGroup(s)),
);

const liveNowCount = computed(() =>
    filteredUpcomingSessions.value.filter((s) => s.status === 'live').length,
);

function groupSessionsByDate(sessionList) {
    const groups = new Map();

    sessionList.forEach((session) => {
        if (!session.scheduled_at) return;
        const date = new Date(session.scheduled_at);
        const tz = session.timezone || 'Asia/Qatar';
        const dateParts = Object.fromEntries(
            new Intl.DateTimeFormat('en-US', {
                timeZone: tz,
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
                    timeZone: tz,
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
}

const groupedUpcomingSessions = computed(() => groupSessionsByDate(filteredUpcomingSessions.value));
const groupedPastSessions = computed(() => groupSessionsByDate(filteredPastSessions.value));

function formatTime(session) {
    if (!session.scheduled_at) return '';
    return new Date(session.scheduled_at).toLocaleTimeString('ar-QA', {
        timeZone: session.timezone || 'Asia/Qatar',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatDateShort(isoString, timezone) {
    if (!isoString) return '';
    return new Date(isoString).toLocaleDateString('ar-QA', {
        timeZone: timezone || 'Asia/Qatar',
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });
}
</script>

<template>
    <Head title="جدول حصص البث المباشر" />

    <DashboardLayout>
        <div class="space-y-6 pb-12">
            <!-- Header -->
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black text-surface-900 dark:text-white flex items-center gap-2.5">
                        <Icon name="live" class="w-8 h-8 text-red-500" />
                        <span>جدول حصص البث المباشر</span>
                    </h1>
                    <p class="mt-1.5 text-sm text-surface-500 dark:text-surface-400">
                        متابعة مواعيد الحصص المباشرة
                    </p>
                </div>

                <!-- Summary Counters -->
                <div class="flex items-center gap-2 flex-wrap">
                    <span
                        v-if="liveNowCount > 0"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-black bg-red-500/15 text-red-600 dark:text-red-400 border border-red-500/30 animate-pulse"
                    >
                        <span class="w-2 h-2 rounded-full bg-red-500"></span>
                        <span>{{ liveNowCount }} بث مباشر الآن</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-primary-500/10 text-primary-700 dark:text-primary-300 border border-primary-500/20">
                        <span>القادمة: {{ filteredUpcomingSessions.length }}</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 border border-surface-200 dark:border-surface-700">
                        <span>السابقة: {{ filteredPastSessions.length }}</span>
                    </span>
                </div>
            </header>

            <!-- Subjects & Groups Tabs Card -->
            <section
                v-if="subjectTabs.length || groups.length || allCombinedSessions.length"
                class="card p-4 sm:p-5 space-y-4 border border-surface-200 dark:border-surface-800"
            >
                <!-- 1. Subject Tabs -->
                <div class="space-y-2">
                    <div class="text-xs font-black text-surface-500 dark:text-surface-400 flex items-center gap-1.5">
                        <Icon name="book" class="w-3.5 h-3.5 text-primary-500" />
                        <span>المواد الدراسية</span>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <button
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                            :class="selectedSubjectId === 'all'
                                ? 'bg-primary-600 text-white shadow-glow-primary'
                                : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200 dark:hover:bg-surface-700'"
                            @click="selectSubject('all')"
                        >
                            <span>كل المواد</span>
                            <span
                                class="px-1.5 py-0.5 rounded-md text-[10px]"
                                :class="selectedSubjectId === 'all' ? 'bg-white/20 text-white' : 'bg-surface-200 dark:bg-surface-700 text-surface-500 dark:text-surface-300'"
                            >
                                {{ allCombinedSessions.length }}
                            </span>
                        </button>

                        <button
                            v-for="subject in subjectTabs"
                            :key="subject.id"
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                            :class="Number(selectedSubjectId) === subject.id
                                ? 'bg-primary-600 text-white shadow-glow-primary'
                                : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200 dark:hover:bg-surface-700'"
                            @click="selectSubject(subject.id)"
                        >
                            <span>{{ subject.name }}</span>
                            <span
                                class="px-1.5 py-0.5 rounded-md text-[10px]"
                                :class="Number(selectedSubjectId) === subject.id ? 'bg-white/20 text-white' : 'bg-surface-200 dark:bg-surface-700 text-surface-500 dark:text-surface-300'"
                            >
                                {{ subjectSessionCount(subject.id) }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- 2. Group Tabs -->
                <div
                    v-if="visibleGroups.length || hasPrivateSessionsInSelection"
                    class="pt-3 border-t border-surface-100 dark:border-surface-800 space-y-2"
                >
                    <div class="text-xs font-black text-surface-500 dark:text-surface-400 flex items-center gap-1.5">
                        <Icon name="users" class="w-3.5 h-3.5 text-accent-500" />
                        <span>المجموعات</span>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <button
                            type="button"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all border"
                            :class="selectedGroupKey === 'all'
                                ? 'bg-accent-500/15 border-accent-500/50 text-accent-700 dark:text-accent-300'
                                : 'bg-surface-50 dark:bg-surface-900/60 border-surface-200 dark:border-surface-700 text-surface-600 dark:text-surface-300 hover:border-surface-300'"
                            @click="selectedGroupKey = 'all'"
                        >
                            كل المجموعات
                        </button>

                        <button
                            v-for="group in visibleGroups"
                            :key="group.id"
                            type="button"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all border flex items-center gap-1.5"
                            :class="selectedGroupKey === `group-${group.id}`
                                ? 'bg-accent-500/15 border-accent-500/50 text-accent-700 dark:text-accent-300'
                                : 'bg-surface-50 dark:bg-surface-900/60 border-surface-200 dark:border-surface-700 text-surface-600 dark:text-surface-300 hover:border-surface-300'"
                            @click="selectedGroupKey = `group-${group.id}`"
                        >
                            <span>{{ group.name }}</span>
                            <span
                                v-if="selectedSubjectId === 'all' && group.subject_name"
                                class="text-[10px] text-surface-400 font-normal"
                            >
                                ({{ group.subject_name }})
                            </span>
                            <span class="px-1.5 py-0.5 rounded text-[10px] bg-surface-200/70 dark:bg-surface-800 text-surface-500 dark:text-surface-400">
                                {{ groupSessionCount(group.id) }}
                            </span>
                        </button>

                        <button
                            v-if="hasPrivateSessionsInSelection"
                            type="button"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all border flex items-center gap-1.5"
                            :class="selectedGroupKey === 'private'
                                ? 'bg-accent-500/15 border-accent-500/50 text-accent-700 dark:text-accent-300'
                                : 'bg-surface-50 dark:bg-surface-900/60 border-surface-200 dark:border-surface-700 text-surface-600 dark:text-surface-300 hover:border-surface-300'"
                            @click="selectedGroupKey = 'private'"
                        >
                            <span>الحصص الخاصة</span>
                            <span class="px-1.5 py-0.5 rounded text-[10px] bg-surface-200/70 dark:bg-surface-800 text-surface-500 dark:text-surface-400">
                                {{ privateSessionCount }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- 3. Fixed Weekly Group Broadcast Times (when available) -->
                <div
                    v-if="activeScheduledGroups.length"
                    class="pt-3 border-t border-surface-100 dark:border-surface-800"
                >
                    <div class="text-[11px] font-bold text-surface-400 mb-2">
                        المواعيد الأسبوعية الثابتة للبث المباشر:
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div
                            v-for="group in activeScheduledGroups"
                            :key="`sched-${group.id}`"
                            class="p-2.5 rounded-xl bg-surface-50 dark:bg-surface-900/60 border border-surface-200/80 dark:border-surface-800 flex items-center justify-between gap-2 text-xs"
                        >
                            <div class="min-w-0">
                                <div class="font-bold text-surface-900 dark:text-white truncate">
                                    {{ group.name }}
                                </div>
                                <div class="text-[11px] text-surface-500 dark:text-surface-400 truncate">
                                    <span v-if="group.subject_name">{{ group.subject_name }}</span>
                                    <span v-if="group.subject_name && group.teacher_name"> · </span>
                                    <span v-if="group.teacher_name">المعلم: {{ group.teacher_name }}</span>
                                </div>
                            </div>
                            <span class="shrink-0 px-2.5 py-1 rounded-lg bg-primary-500/10 text-primary-700 dark:text-primary-300 font-bold text-[11px]">
                                {{ formatSchedule(group.schedules) }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Time Filter Strip (All / Upcoming / Past) -->
            <div v-if="allCombinedSessions.length" class="flex items-center justify-between gap-3 flex-wrap">
                <div class="inline-flex items-center gap-1 p-1 rounded-xl bg-surface-100 dark:bg-surface-800/80 border border-surface-200 dark:border-surface-700">
                    <button
                        type="button"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all"
                        :class="timeFilter === 'all' ? 'bg-white dark:bg-surface-900 text-surface-900 dark:text-white shadow-sm' : 'text-surface-500 dark:text-surface-400 hover:text-surface-800 dark:hover:text-white'"
                        @click="timeFilter = 'all'"
                    >
                        القادمة والسابقة ({{ filteredUpcomingSessions.length + filteredPastSessions.length }})
                    </button>
                    <button
                        type="button"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all"
                        :class="timeFilter === 'upcoming' ? 'bg-white dark:bg-surface-900 text-surface-900 dark:text-white shadow-sm' : 'text-surface-500 dark:text-surface-400 hover:text-surface-800 dark:hover:text-white'"
                        @click="timeFilter = 'upcoming'"
                    >
                        الحصص القادمة ({{ filteredUpcomingSessions.length }})
                    </button>
                    <button
                        type="button"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all"
                        :class="timeFilter === 'past' ? 'bg-white dark:bg-surface-900 text-surface-900 dark:text-white shadow-sm' : 'text-surface-500 dark:text-surface-400 hover:text-surface-800 dark:hover:text-white'"
                        @click="timeFilter = 'past'"
                    >
                        الحصص السابقة ({{ filteredPastSessions.length }})
                    </button>
                </div>
            </div>

            <!-- 1. Upcoming & Live Sessions Section -->
            <section v-if="timeFilter === 'all' || timeFilter === 'upcoming'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <Icon name="live" class="w-5 h-5 text-red-500" />
                        <span>
                            {{ selectedSubject ? `الحصص المباشرة القادمة — ${selectedSubject.name}` : 'جدول الحصص المباشرة القادمة' }}
                        </span>
                    </h2>
                    <span class="text-xs font-bold text-surface-400">{{ filteredUpcomingSessions.length }} حصة مجدولة</span>
                </div>

                <div v-if="groupedUpcomingSessions.length" class="space-y-4">
                    <div
                        v-for="day in groupedUpcomingSessions"
                        :key="`upcoming-${day.key}`"
                        class="card overflow-hidden border border-surface-200 dark:border-surface-800"
                    >
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
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="flex min-w-20 items-center gap-1.5 text-primary-700 dark:text-primary-300 font-mono text-sm font-black shrink-0">
                                        <Icon name="clock" class="h-4 w-4 text-primary-500" />
                                        <span>{{ formatTime(session) }}</span>
                                    </div>

                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h4 class="text-sm font-black text-surface-900 dark:text-white">{{ session.title }}</h4>
                                            <span :class="session.type === 'private' ? 'badge-accent' : 'badge-primary'" class="text-[10px]">
                                                {{ session.type === 'private' ? 'حصة خاصة' : 'حصة جماعية' }}
                                            </span>
                                            <span v-if="session.status === 'live'" class="badge-red animate-pulse text-[10px]">
                                                مباشرة الآن 🔴
                                            </span>
                                        </div>
                                        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-surface-500 dark:text-surface-400">
                                            <span class="font-bold text-surface-700 dark:text-surface-300">{{ session.subject ?? 'مادة دراسية' }}</span>
                                            <template v-if="session.teacher?.name">
                                                <span>·</span>
                                                <span>المعلم: {{ session.teacher.name }}</span>
                                            </template>
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

                <div
                    v-else-if="allCombinedSessions.length || selectedSubjectId !== 'all'"
                    class="card p-6 text-center border border-surface-200 dark:border-surface-800 text-xs text-surface-500 dark:text-surface-400"
                >
                    لا توجد حصص مباشرة قادمة مجدولة حالياً{{ selectedSubject ? ` في مادة ${selectedSubject.name}` : '' }}.
                </div>
            </section>

            <!-- 2. Past Live Sessions Section -->
            <section v-if="timeFilter === 'all' || timeFilter === 'past'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <Icon name="clock" class="w-5 h-5 text-surface-400" />
                        <span>
                            {{ selectedSubject ? `الحصص المباشرة السابقة — ${selectedSubject.name}` : 'سجل الحصص المباشرة السابقة' }}
                        </span>
                    </h2>
                    <span class="text-xs font-bold text-surface-400">{{ filteredPastSessions.length }} حصة سابقة</span>
                </div>

                <div v-if="groupedPastSessions.length" class="space-y-4">
                    <div
                        v-for="day in groupedPastSessions"
                        :key="`past-${day.key}`"
                        class="card overflow-hidden border border-surface-200 dark:border-surface-800 opacity-95"
                    >
                        <div class="flex items-center gap-2 border-b border-surface-100 bg-surface-50 px-4 py-3 dark:border-surface-800 dark:bg-surface-900/60">
                            <Icon name="calendar" class="h-4 w-4 text-surface-400" />
                            <h3 class="text-sm font-black text-surface-700 dark:text-surface-200">{{ day.label }}</h3>
                        </div>

                        <div class="divide-y divide-surface-100 dark:divide-surface-800">
                            <article
                                v-for="session in day.sessions"
                                :key="session.id"
                                class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center justify-between"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="flex min-w-20 items-center gap-1.5 text-surface-500 dark:text-surface-400 font-mono text-sm font-bold shrink-0">
                                        <Icon name="clock" class="h-4 w-4 text-surface-400" />
                                        <span>{{ formatTime(session) }}</span>
                                    </div>

                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h4 class="text-sm font-bold text-surface-900 dark:text-white">{{ session.title }}</h4>
                                            <span :class="session.type === 'private' ? 'badge-accent' : 'badge-gray'" class="text-[10px]">
                                                {{ session.type === 'private' ? 'حصة خاصة' : 'حصة جماعية' }}
                                            </span>
                                            <span
                                                v-if="session.status === 'ended'"
                                                class="px-2 py-0.5 rounded-md text-[10px] font-bold"
                                                :class="session.attended
                                                    ? 'bg-green-500/10 text-green-600 dark:text-green-400'
                                                    : 'bg-surface-100 dark:bg-surface-800 text-surface-500 dark:text-surface-400'"
                                            >
                                                {{ session.attended ? '✓ حضرت البث' : 'انتهت الحصة' }}
                                            </span>
                                            <span
                                                v-else-if="session.status === 'cancelled'"
                                                class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400"
                                            >
                                                مُلغاة / لم تنعقد
                                            </span>
                                        </div>

                                        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-surface-500 dark:text-surface-400">
                                            <span class="font-bold text-surface-700 dark:text-surface-300">{{ session.subject ?? 'مادة دراسية' }}</span>
                                            <template v-if="session.teacher?.name">
                                                <span>·</span>
                                                <span>المعلم: {{ session.teacher.name }}</span>
                                            </template>
                                            <template v-if="session.group?.name">
                                                <span>·</span>
                                                <span>{{ session.group.name }}</span>
                                            </template>
                                            <template v-if="session.makeup_scheduled_at">
                                                <span>·</span>
                                                <span class="text-primary-600 dark:text-primary-400 font-bold">
                                                    حصة تعويضية: {{ formatDateShort(session.makeup_scheduled_at, session.timezone) }}
                                                </span>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <div class="shrink-0 flex items-center gap-2">
                                    <Link
                                        v-if="session.has_recording && session.group?.id"
                                        :href="route('student.learn', { groupId: session.group.id })"
                                        class="btn-outline btn-sm flex items-center gap-1.5 font-bold"
                                    >
                                        <span>مشاهدة التسجيل</span>
                                        <span>←</span>
                                    </Link>
                                    <span
                                        v-else
                                        class="text-xs font-bold px-3 py-1.5 rounded-lg bg-surface-100 dark:bg-surface-800 text-surface-500 dark:text-surface-400"
                                    >
                                        {{ session.status === 'cancelled' ? 'لم تنعقد' : 'انتهى البث' }}
                                    </span>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>

                <div
                    v-else-if="allCombinedSessions.length || selectedSubjectId !== 'all'"
                    class="card p-6 text-center border border-surface-200 dark:border-surface-800 text-xs text-surface-500 dark:text-surface-400"
                >
                    لا توجد حصص مباشرة سابقة مسجلة{{ selectedSubject ? ` في مادة ${selectedSubject.name}` : '' }}.
                </div>
            </section>

            <!-- Global Empty State (No subscriptions/groups/sessions at all) -->
            <div
                v-if="!allCombinedSessions.length && !subjectTabs.length && !groups.length"
                class="card flex flex-col items-center justify-center p-16 text-center space-y-3"
            >
                <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-surface-100 dark:bg-surface-800">
                    <Icon name="live" class="h-8 w-8 text-surface-400" />
                </div>
                <h3 class="text-base font-black text-surface-800 dark:text-surface-100">لا توجد حصص بث مباشر مسجلة حالياً</h3>
                <p class="text-xs text-surface-500 dark:text-surface-400 max-w-sm">
                    بمجرد اشتراكك في المجموعات وجدولة معلميك لحصص البث المباشر، ستظهر مواعيد الحصص القادمة والسابقة هنا مصنّفة حسب المادة والمجموعة.
                </p>
            </div>
        </div>
    </DashboardLayout>
</template>
