<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    sessions: { type: Array, default: () => [] },
});

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
</script>

<template>
    <Head title="جدول حصصي" />

    <DashboardLayout>
        <div class="space-y-6">
            <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white">جدول حصصي</h1>
                    <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">
                        كل مواعيد حصصك الجماعية والخاصة القادمة في مكان واحد.
                    </p>
                </div>
                <div class="badge-primary self-start sm:self-auto">
                    {{ sessions.length }} حصة قادمة
                </div>
            </header>

            <div v-if="groupedSessions.length" class="space-y-5">
                <section v-for="day in groupedSessions" :key="day.key" class="card overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-surface-100 bg-surface-50 px-4 py-3 dark:border-surface-800 dark:bg-surface-900/60">
                        <Icon name="calendar" class="h-4 w-4 text-primary-600 dark:text-primary-400" />
                        <h2 class="text-sm font-black text-surface-800 dark:text-surface-100">{{ day.label }}</h2>
                    </div>

                    <div class="divide-y divide-surface-100 dark:divide-surface-800">
                        <article
                            v-for="session in day.sessions"
                            :key="session.id"
                            class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center"
                        >
                            <div class="flex min-w-24 items-center gap-2 text-primary-700 dark:text-primary-300">
                                <Icon name="clock" class="h-4 w-4" />
                                <span class="text-sm font-black">{{ formatTime(session) }}</span>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-black text-surface-900 dark:text-white">{{ session.title }}</h3>
                                    <span :class="session.type === 'private' ? 'badge-accent' : 'badge-primary'" class="text-[10px]">
                                        {{ session.type === 'private' ? 'حصة خاصة' : 'حصة جماعية' }}
                                    </span>
                                    <span v-if="session.status === 'live'" class="badge-red animate-pulse text-[10px]">مباشرة الآن</span>
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-surface-500 dark:text-surface-400">
                                    <span>{{ session.subject ?? 'المادة غير محددة' }}</span>
                                    <span>·</span>
                                    <span>{{ session.teacher?.name }}</span>
                                    <template v-if="session.group?.name">
                                        <span>·</span>
                                        <span>{{ session.group.name }}</span>
                                    </template>
                                </div>
                                <p v-if="session.description" class="mt-2 text-xs leading-6 text-surface-500 dark:text-surface-400">
                                    {{ session.description }}
                                </p>
                            </div>

                            <Link
                                v-if="session.status === 'live'"
                                :href="route('live-sessions.room', session.id)"
                                class="btn-primary btn-sm shrink-0"
                            >
                                ادخل الحصة
                            </Link>
                            <span v-else class="badge-gray self-start text-[10px] sm:self-auto">مجدولة</span>
                        </article>
                    </div>
                </section>
            </div>

            <div v-else class="card flex flex-col items-center justify-center p-12 text-center">
                <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-surface-100 dark:bg-surface-800">
                    <Icon name="calendar" class="h-7 w-7 text-surface-400" />
                </div>
                <h2 class="text-sm font-black text-surface-800 dark:text-surface-100">لا توجد حصص قادمة حاليًا</h2>
                <p class="mt-1 text-xs text-surface-500 dark:text-surface-400">ستظهر هنا الحصص فور أن يحدد المعلم موعدها.</p>
            </div>
        </div>
    </DashboardLayout>
</template>
