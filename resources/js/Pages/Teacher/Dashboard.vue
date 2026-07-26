<script setup>
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import { formatQAR, formatMonthly } from '@/lib/money';

defineProps({
    stats:               { type: Object, default: () => ({}) },
    recentSubscriptions: { type: Array,  default: () => [] },
    groups:              { type: Array,  default: () => [] },
});
</script>

<template>
    <Head title="لوحة المعلم" />

    <DashboardLayout>
        <div class="space-y-6">
            <header class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white">
                        أهلاً، {{ $page.props.auth.user?.name?.split(' ')[0] }}
                    </h1>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                        نظرة على مجموعاتك وطلابك
                    </p>
                </div>

                <Link :href="route('teacher.teaching-schedule')" class="btn-primary btn-sm flex items-center gap-2">
                    <Icon name="plus" class="w-4 h-4" />
                    <span>إدارة الجدول والمجموعات</span>
                </Link>
            </header>

            <!-- Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div
                    v-for="stat in [
                        { label: 'مجموعاتك',        value: stats.total_groups,               icon: 'courses',  color: 'primary' },
                        { label: 'طلاب مشتركون',    value: stats.active_students,            icon: 'student',  color: 'accent' },
                        { label: 'حصص خاصة',        value: stats.private_students,           icon: 'teacher',  color: 'primary' },
                        { label: 'أرباحك',          value: formatQAR(stats.total_revenue ?? 0), icon: 'earnings', color: 'accent' },
                    ]"
                    :key="stat.label"
                    class="card p-5"
                >
                    <div class="flex items-center gap-3">
                        <div :class="`p-2.5 rounded-xl bg-${stat.color}-50 dark:bg-${stat.color}-950 text-${stat.color}-600 dark:text-${stat.color}-400`">
                            <Icon :name="stat.icon" class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-xl font-black text-surface-900 dark:text-white truncate">{{ stat.value ?? 0 }}</div>
                            <div class="text-[11px] text-surface-400">{{ stat.label }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid lg:grid-cols-3 gap-5">
                <!-- Groups -->
                <section class="lg:col-span-2 card p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="font-bold text-sm text-surface-900 dark:text-white">مجموعاتي</h2>
                        <Link :href="route('teacher.teaching-schedule')" class="text-primary-600 text-xs hover:underline">
                            إدارة الجدول
                        </Link>
                    </div>

                    <div v-if="groups.length" class="space-y-3">
                        <div
                            v-for="group in groups"
                            :key="group.id"
                            class="flex items-center gap-3 p-3 rounded-xl border border-surface-100 dark:border-surface-800"
                        >
                            <div class="w-10 h-10 rounded-xl bg-primary-50 dark:bg-primary-950 flex items-center justify-center text-primary-600 shrink-0">
                                <Icon name="courses" class="w-5 h-5" />
                            </div>

                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-sm text-surface-900 dark:text-white truncate">
                                    {{ group.name }}
                                </h3>
                                <div class="text-[11px] text-surface-400 flex items-center gap-2 flex-wrap">
                                    <span>{{ group.subject?.name }}</span>
                                    <span v-if="group.grade">· {{ group.grade.name }}</span>
                                    <span>· {{ group.students_count }}/{{ group.capacity }} طالب</span>
                                    <span>· {{ group.materials_count }} مادة</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-xs font-black text-primary-700 dark:text-primary-400 hidden sm:block">
                                    {{ formatMonthly(group.monthly_price) }}
                                </span>
                                <Link :href="route('teacher.materials', { groupId: group.id })" class="btn-outline btn-sm">المواد</Link>
                                <Link :href="route('teacher.worksheets.index', { groupId: group.id })" class="btn-ghost btn-sm">الواجبات</Link>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center py-10">
                        <Icon name="courses" class="w-10 h-10 text-surface-300 mx-auto mb-3" />
                        <p class="text-sm text-surface-400 mb-4">لسه مضفتش أي مجموعة.</p>
                        <Link :href="route('teacher.teaching-schedule')" class="btn-primary btn-sm">أضف مجموعة</Link>
                    </div>
                </section>

                <!-- Recent subscriptions -->
                <section class="card p-5">
                    <h2 class="font-bold text-sm text-surface-900 dark:text-white mb-4">أحدث الاشتراكات</h2>

                    <div v-if="recentSubscriptions.length" class="space-y-3">
                        <div v-for="sub in recentSubscriptions" :key="sub.id" class="flex items-center gap-3">
                            <div class="avatar-sm">
                                <img v-if="sub.student?.avatar" :src="sub.student.avatar" :alt="sub.student.name" class="w-full h-full object-cover" />
                                <span v-else class="text-primary-700 dark:text-primary-300 font-bold text-xs">
                                    {{ sub.student?.name?.charAt(0) }}
                                </span>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold text-surface-800 dark:text-surface-100 truncate">
                                    {{ sub.student?.name }}
                                </div>
                                <div class="text-[10px] text-surface-400 truncate">
                                    {{ sub.assignment?.subject?.name }}
                                    <span v-if="sub.group"> — {{ sub.group.name }}</span>
                                </div>
                            </div>

                            <span
                                class="text-[10px] shrink-0"
                                :class="sub.status === 'active' ? 'badge-green' : 'badge-gray'"
                            >
                                {{ sub.status === 'active' ? 'فعّال' : sub.status }}
                            </span>
                        </div>
                    </div>

                    <p v-else class="text-sm text-surface-400 text-center py-8">لا توجد اشتراكات بعد.</p>
                </section>
            </div>
        </div>
    </DashboardLayout>
</template>
