<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import { formatQAR } from '@/lib/money';

const props = defineProps({
    activeSubscriptions: { type: Array, default: () => [] },
    expiringSoon:        { type: Array, default: () => [] },
    upcomingSessions:    { type: Array, default: () => [] },
    pendingPayments:     { type: Array, default: () => [] },
    pendingParentLinks:  { type: Array, default: () => [] },
    continueLearning:    { type: Object, default: null },
    stats:               { type: Object, default: () => ({}) },
});

function respondToParentLink(linkId, action) {
    router.post(route(`student.parent-links.${action}`, { linkId }), {}, {
        preserveScroll: true,
    });
}

function formatDateTime(value) {
    if (!value) return '';
    return new Date(value).toLocaleString('ar-QA', {
        weekday: 'long',
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="لوحة الطالب" />

    <DashboardLayout>
        <div class="space-y-8">
            <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white">لوحة الطالب</h1>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">نظرة سريعة على دراستك</p>
                </div>
                <Link :href="route('student.assignments')" class="btn-outline btn-sm self-start sm:self-auto">
                    مركز واجباتي
                </Link>
            </header>

            <section v-if="props.pendingParentLinks.length" class="card p-5 border-amber-300 dark:border-amber-900 bg-amber-500/5">
                <div class="flex items-center gap-2 font-bold text-surface-900 dark:text-white">
                    <Icon name="users" class="w-5 h-5 text-amber-500" />
                    طلبات ربط حسابات أولياء الأمور
                </div>
                <p class="mt-1 text-xs text-surface-500 dark:text-surface-400">
                    لا يحصل ولي الأمر على بياناتك أو صلاحيات الحجز والدفع قبل موافقتك.
                </p>
                <div class="mt-4 space-y-3">
                    <div v-for="link in props.pendingParentLinks" :key="link.id" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border border-surface-200 dark:border-surface-700 p-3">
                        <div>
                            <p class="text-sm font-bold text-surface-900 dark:text-white">{{ link.parent?.name }}</p>
                            <p class="text-xs text-surface-500">يريد ربط حسابه بحسابك كـ {{ link.relationship === 'father' ? 'أب' : (link.relationship === 'mother' ? 'أم' : 'ولي أمر') }}</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="btn-primary btn-sm" @click="respondToParentLink(link.id, 'approve')">موافقة</button>
                            <button type="button" class="btn-outline btn-sm text-red-500 border-red-200" @click="respondToParentLink(link.id, 'reject')">رفض</button>
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="continueLearning" class="card p-5 border-primary-200 dark:border-primary-900/60 bg-primary-500/5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-primary-600 dark:text-primary-400">متابعة التعلم</p>
                        <h2 class="mt-1 text-lg font-black text-surface-900 dark:text-white truncate">{{ continueLearning.lesson_title }}</h2>
                        <p class="mt-1 text-xs text-surface-500 dark:text-surface-400">
                            {{ continueLearning.subject?.name }} — {{ continueLearning.teacher?.name }} · {{ continueLearning.group_name }}
                        </p>
                    </div>
                    <Link :href="route('student.learn', { groupId: continueLearning.group_id })" class="btn-primary btn-sm shrink-0">
                        {{ continueLearning.watched_seconds > 0 ? 'استكمل الدرس' : 'ابدأ الدرس' }}
                    </Link>
                </div>
            </section>

            <!-- Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div
                    v-for="stat in [
                        { label: 'اشتراكات فعّالة', value: stats.activeSubscriptions, icon: 'courses',  color: 'primary' },
                        { label: 'معلمون',          value: stats.teachers,            icon: 'teacher',  color: 'accent' },
                        { label: 'مواد',            value: stats.subjects,            icon: 'globe',    color: 'primary' },
                        { label: 'حصص قادمة',       value: stats.upcomingSessions,    icon: 'live',     color: 'accent' },
                    ]"
                    :key="stat.label"
                    class="card p-5"
                >
                    <div class="flex items-center gap-3">
                        <div :class="`p-2.5 rounded-xl bg-${stat.color}-50 dark:bg-${stat.color}-950 text-${stat.color}-600 dark:text-${stat.color}-400`">
                            <Icon :name="stat.icon" class="w-5 h-5" />
                        </div>
                        <div>
                            <div class="text-2xl font-black text-surface-900 dark:text-white">{{ stat.value ?? 0 }}</div>
                            <div class="text-[11px] text-surface-400">{{ stat.label }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Renewal reminders -->
            <section v-if="expiringSoon.length" class="alert-warn flex-col items-stretch">
                <div class="flex items-center gap-2 font-bold">
                    <Icon name="clock" class="w-4 h-4" />
                    اشتراكات قاربت على الانتهاء
                </div>
                <ul class="mt-2 space-y-1.5 text-xs">
                    <li v-for="sub in expiringSoon" :key="sub.id" class="flex items-center justify-between gap-3">
                        <span>{{ sub.label }} — متبقٍ {{ sub.days_remaining }} يوم</span>
                        <Link :href="route('student.my-classes')" class="font-bold underline shrink-0">جدّد الآن</Link>
                    </li>
                </ul>
            </section>

            <!-- Payments awaiting admin verification -->
            <section v-if="pendingPayments.length">
                <h2 class="text-sm font-black text-surface-800 dark:text-surface-100 mb-3">مدفوعات قيد المراجعة</h2>

                <div class="card divide-y divide-surface-100 dark:divide-surface-800">
                    <div v-for="payment in pendingPayments" :key="payment.id" class="flex items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-surface-800 dark:text-surface-100 truncate">
                                {{ payment.subscription?.assignment?.subject?.name ?? 'اشتراك شهري' }}
                            </div>
                            <div class="text-[11px] text-surface-400">
                                تم رفع الإيصال — بانتظار تأكيد الإدارة
                            </div>
                        </div>
                        <span class="badge-accent text-[10px] shrink-0">{{ formatQAR(payment.amount) }}</span>
                    </div>
                </div>
            </section>

            <!-- Upcoming live sessions -->
            <section>
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-sm font-black text-surface-800 dark:text-surface-100 flex items-center gap-2">
                        <Icon name="live" class="w-4 h-4 text-red-500" />
                        الحصص المباشرة القادمة
                    </h2>
                    <Link :href="route('student.schedule')" class="text-xs font-bold text-primary-600 dark:text-primary-400">
                        عرض الجدول كاملًا
                    </Link>
                </div>

                <div v-if="upcomingSessions.length" class="card divide-y divide-surface-100 dark:divide-surface-800">
                    <div v-for="session in upcomingSessions" :key="session.id" class="flex items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-surface-800 dark:text-surface-100 truncate">
                                {{ session.title }}
                            </div>
                            <div class="text-[11px] text-surface-400 flex items-center gap-2 flex-wrap">
                                <span>{{ session.subject ?? 'حصة خاصة' }}</span>
                                <span>·</span>
                                <span>{{ formatDateTime(session.scheduled_at) }}</span>
                            </div>
                        </div>

                        <Link
                            v-if="session.status === 'live'"
                            :href="route('live-sessions.room', session.id)"
                            class="btn-primary btn-sm shrink-0 animate-pulse"
                        >
                            ادخل الآن
                        </Link>
                        <span v-else class="badge-gray text-[10px] shrink-0">مجدولة</span>
                    </div>
                </div>

                <p v-else class="card p-8 text-center text-sm text-surface-400">
                    لا توجد حصص مباشرة مجدولة حالياً.
                </p>
            </section>

            <!-- Active subscriptions shortcut -->
            <section v-if="activeSubscriptions.length">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-black text-surface-800 dark:text-surface-100">حصصي</h2>
                    <Link :href="route('student.my-classes')" class="text-xs font-bold text-primary-600 dark:text-primary-400">عرض الكل</Link>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="sub in activeSubscriptions"
                        :key="sub.id"
                        :href="sub.group ? route('student.learn', { groupId: sub.group.id }) : route('student.my-classes')"
                        class="card-hover p-4 flex items-center gap-3"
                    >
                        <div class="avatar-md">
                            <img v-if="sub.teacher?.avatar" :src="sub.teacher.avatar" :alt="sub.teacher.name" class="w-full h-full object-cover" />
                            <span v-else class="text-primary-700 dark:text-primary-300 font-bold">{{ sub.teacher?.name?.charAt(0) }}</span>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-surface-800 dark:text-surface-100 truncate">
                                {{ sub.subject?.name }}
                            </div>
                            <div class="text-[11px] text-surface-400 truncate">
                                {{ sub.teacher?.name }} · متبقٍ {{ sub.days_remaining }} يوم
                            </div>
                        </div>
                    </Link>
                </div>
            </section>
        </div>
    </DashboardLayout>
</template>
