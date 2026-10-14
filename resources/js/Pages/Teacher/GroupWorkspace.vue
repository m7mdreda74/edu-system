<script setup>
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import StatCard from '@/Components/StatCard.vue';
import { formatTime12 } from '@/lib/money';

const props = defineProps({
    group: { type: Object, required: true },
    studentsCount: { type: Number, default: 0 },
});

const tabs = [
    { label: 'نظرة عامة', icon: 'dashboard', href: () => route('teacher.groups.workspace', { groupId: props.group.id }) },
    { label: 'الطلاب', icon: 'student', href: () => '#students' },
    { label: 'الجدول', icon: 'calendar', href: () => route('teacher.teaching-schedule') },
    { label: 'المنهج والدروس', icon: 'book', href: () => route('teacher.curriculum', { assignment: props.group.assignment_id }) },
    { label: 'المواد', icon: 'file', href: () => route('teacher.materials', { groupId: props.group.id }) },
    { label: 'الواجبات', icon: 'assignment', href: () => route('teacher.worksheets.index', { groupId: props.group.id }) },
    { label: 'الحصص المباشرة', icon: 'live', href: () => route('teacher.live-sessions') },
    { label: 'التقييم والتحليل', icon: 'chart', href: () => route('teacher.analytics', { assignment: props.group.assignment_id, group: props.group.id }) },
    { label: 'الإعلانات', icon: 'chat', href: () => route('teacher.announcements') },
];
</script>

<template>
    <DashboardLayout>
        <Head :title="`مساحة ${group.name}`" />

        <div class="container-app space-y-6" dir="rtl">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <Link :href="route('teacher.groups.index')" class="mb-3 inline-flex items-center gap-1 text-sm font-bold text-primary-600 hover:underline">
                        <Icon name="arrow-right" class="h-4 w-4" />
                        العودة إلى مجموعاتي
                    </Link>
                    <h1 class="flex items-center gap-3 text-3xl font-black text-surface-900 dark:text-white">
                        <Icon name="courses" class="h-8 w-8 text-primary-500" />
                        {{ group.name }}
                    </h1>
                    <p class="mt-1 text-sm text-surface-500">{{ group.subject?.name }} · {{ group.grade?.name }} · مساحة تشغيل موحّدة للمجموعة.</p>
                </div>
                <span :class="group.is_active ? 'badge-green' : 'badge-gray'">{{ group.is_active ? 'نشطة' : 'متوقفة' }}</span>
            </header>

            <nav class="card flex gap-2 overflow-x-auto p-3" aria-label="أقسام مساحة المجموعة">
                <a v-for="tab in tabs" :key="tab.label" :href="tab.href()" class="btn-ghost btn-sm flex shrink-0 items-center gap-1.5 whitespace-nowrap">
                    <Icon :name="tab.icon" class="h-4 w-4" />
                    {{ tab.label }}
                </a>
            </nav>

            <section class="grid gap-4 sm:grid-cols-3">
                <StatCard label="طلاب المجموعة" :value="studentsCount" icon="student" tone="primary" />
                <StatCard label="سعة المجموعة" :value="group.capacity" icon="users" tone="accent" />
                <StatCard label="المقاعد المتاحة" :value="Math.max(0, group.capacity - studentsCount)" icon="success" tone="green" />
            </section>

            <section class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
                <div class="card p-6">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-black text-surface-900 dark:text-white">إجراءات سريعة</h2>
                            <p class="mt-1 text-xs text-surface-500">كل إجراء يفتح المسار الحالي المملوك للمعلم.</p>
                        </div>
                        <Link :href="route('teacher.groups.roster.page', { groupId: group.id })" class="btn-outline btn-sm">قائمة الطلاب</Link>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <Link :href="route('teacher.curriculum', { assignment: group.assignment_id })" class="card-hover rounded-2xl border border-surface-200 p-4 dark:border-surface-700">
                            <Icon name="book" class="mb-2 h-5 w-5 text-primary-500" />
                            <span class="block font-bold text-surface-900 dark:text-white">إنشاء درس أو منهج</span>
                            <span class="text-xs text-surface-500">إدارة الوحدات والدروس والنتائج التعليمية</span>
                        </Link>
                        <Link :href="route('teacher.materials', { groupId: group.id })" class="card-hover rounded-2xl border border-surface-200 p-4 dark:border-surface-700">
                            <Icon name="file" class="mb-2 h-5 w-5 text-primary-500" />
                            <span class="block font-bold text-surface-900 dark:text-white">رفع مادة تعليمية</span>
                            <span class="text-xs text-surface-500">الفيديوهات والملفات المرتبطة بالمجموعة</span>
                        </Link>
                        <Link :href="route('teacher.worksheets.index', { groupId: group.id })" class="card-hover rounded-2xl border border-surface-200 p-4 dark:border-surface-700">
                            <Icon name="assignment" class="mb-2 h-5 w-5 text-primary-500" />
                            <span class="block font-bold text-surface-900 dark:text-white">الواجبات والتسليمات</span>
                            <span class="text-xs text-surface-500">إنشاء واجب ومراجعة التسليمات</span>
                        </Link>
                        <Link :href="route('teacher.live-sessions')" class="card-hover rounded-2xl border border-surface-200 p-4 dark:border-surface-700">
                            <Icon name="live" class="mb-2 h-5 w-5 text-primary-500" />
                            <span class="block font-bold text-surface-900 dark:text-white">جدولة حصة مباشرة</span>
                            <span class="text-xs text-surface-500">الحصص المباشرة والحضور والتسجيلات</span>
                        </Link>
                    </div>
                </div>

                <div id="schedule" class="card p-6">
                    <h2 class="text-xl font-black text-surface-900 dark:text-white">الجدول الأسبوعي</h2>
                    <div v-if="group.schedules?.length" class="mt-5 space-y-3">
                        <div v-for="schedule in group.schedules" :key="`${schedule.day_of_week}-${schedule.start_time}`" class="flex items-center justify-between rounded-xl bg-surface-50 p-3 dark:bg-surface-800">
                            <span class="text-sm font-bold text-surface-700 dark:text-surface-200">اليوم {{ schedule.day_of_week + 1 }}</span>
                            <span class="text-sm text-surface-500">{{ formatTime12(schedule.start_time) }} — {{ formatTime12(schedule.end_time) }}</span>
                        </div>
                    </div>
                    <p v-else class="mt-5 text-sm text-surface-500">لم يتم تحديد موعد أسبوعي لهذه المجموعة بعد.</p>
                    <Link :href="route('teacher.teaching-schedule')" class="btn-outline btn-sm mt-5">فتح الخطة والجدول</Link>
                </div>
            </section>

            <section id="students" class="card flex flex-wrap items-center justify-between gap-4 p-6">
                <div>
                    <h2 class="text-xl font-black text-surface-900 dark:text-white">طلاب المجموعة</h2>
                    <p class="mt-1 text-sm text-surface-500">{{ studentsCount }} طالبًا. البحث والتصفح يتمان عبر قائمة مصفحة عند الطلب.</p>
                </div>
                <Link :href="route('teacher.groups.roster.page', { groupId: group.id })" class="btn-primary btn-sm">فتح قائمة الطلاب</Link>
            </section>

            <section class="card border-accent-200 bg-accent-50/40 p-5 dark:border-accent-900/40 dark:bg-accent-950/20">
                <div class="flex items-start gap-3">
                    <Icon name="info" class="mt-0.5 h-5 w-5 shrink-0 text-accent-600" />
                    <p class="text-sm text-surface-700 dark:text-surface-200">يتم إبقاء gradebook والحصص المباشرة والتحليلات في صفحاتها الحالية مع تمرير نطاق المجموعة أو التكليف عند دعمه، حتى لا تتكرر قواعد الصلاحيات والدرجات في مساحة جديدة.</p>
                </div>
            </section>
        </div>
    </DashboardLayout>
</template>
