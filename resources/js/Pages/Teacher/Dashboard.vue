<script setup>
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

defineProps({
    stats:  { type: Object, default: () => ({}) },
    groups: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="لوحة المعلم" />

    <DashboardLayout>
        <div class="space-y-6">
            <header class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white">
                        أهلاً، {{ $page.props.auth.user?.name }}
                    </h1>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                        مساحتك الأكاديمية لإدارة المنهج والشرح والواجبات والاختبارات
                    </p>
                </div>

                <Link :href="route('teacher.teaching-schedule')" class="btn-primary btn-sm flex items-center gap-2">
                    <Icon name="book" class="w-4 h-4" />
                    <span>الخطة الأكاديمية</span>
                </Link>
            </header>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div
                    v-for="stat in [
                        { label: 'المناهج المسندة', value: stats.assignments,     icon: 'book',     color: 'primary' },
                        { label: 'المجموعات',       value: stats.total_groups,    icon: 'courses',  color: 'accent' },
                        { label: 'طلابك',           value: stats.active_students, icon: 'student',  color: 'primary' },
                        { label: 'الدروس المنشورة', value: stats.lessons,         icon: 'video',    color: 'accent' },
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

            <div class="rounded-2xl border border-primary-500/20 bg-primary-500/5 p-4 flex items-start gap-3">
                <Icon name="info" class="w-5 h-5 text-primary-600 shrink-0" />
                <p class="text-xs text-surface-600 dark:text-surface-300 leading-relaxed">
                    الإدارة مسؤولة عن الإسناد والمجموعات والسعة والأسعار والاشتراكات والتسويات المالية، وأنت مسؤول عن المواعيد والعمل الأكاديمي.
                    دورك هنا أكاديمي: المنهج، الدروس، الفيديوهات، الملازم، الواجبات، الاختبارات والحصص المباشرة.
                </p>
            </div>

            <section class="card p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-bold text-sm text-surface-900 dark:text-white">المجموعات المسندة إليك</h2>
                    <Link :href="route('teacher.teaching-schedule')" class="text-primary-600 text-xs hover:underline">
                        عرض الخطة
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
                            <h3 class="font-bold text-sm text-surface-900 dark:text-white truncate">{{ group.name }}</h3>
                            <div class="text-[11px] text-surface-400 flex items-center gap-2 flex-wrap">
                                <span>{{ group.subject?.name }}</span>
                                <span v-if="group.grade">· {{ group.grade.name }}</span>
                                <span>· {{ group.students_count }} طالب</span>
                                <span>· {{ group.materials_count }} درس</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <Link v-if="group.assignment_id" :href="route('teacher.curriculum', { assignment: group.assignment_id })" class="btn-primary btn-sm">المنهج</Link>
                            <Link :href="route('teacher.materials', { groupId: group.id })" class="btn-outline btn-sm">المواد</Link>
                            <Link :href="route('teacher.worksheets.index', { groupId: group.id })" class="btn-ghost btn-sm">الواجبات</Link>
                        </div>
                    </div>
                </div>

                <div v-else class="text-center py-10">
                    <Icon name="courses" class="w-10 h-10 text-surface-300 mx-auto mb-3" />
                    <p class="text-sm text-surface-400">لم تسند الإدارة إليك مجموعات بعد.</p>
                </div>
            </section>
        </div>
    </DashboardLayout>
</template>
