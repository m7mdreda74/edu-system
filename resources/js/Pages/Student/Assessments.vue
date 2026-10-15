<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
});

const filter = ref('all');

const filters = [
    { key: 'all', label: 'الكل' },
    { key: 'available', label: 'متاحة' },
    { key: 'in_progress', label: 'قيد التنفيذ' },
    { key: 'completed', label: 'مكتملة' },
    { key: 'upcoming', label: 'قادمة' },
];

const filteredItems = computed(() => filter.value === 'all'
    ? props.items
    : props.items.filter((item) => item.status === filter.value));

const statusLabels = {
    available: 'متاح الآن',
    in_progress: 'قيد التنفيذ',
    completed: 'مكتمل',
    upcoming: 'يفتح قريباً',
    closed: 'مغلق',
    not_ready: 'غير جاهز',
};

const statusClasses = {
    available: 'badge-green',
    in_progress: 'badge-primary',
    completed: 'badge-accent',
    upcoming: 'badge-gray',
    closed: 'badge-gray',
    not_ready: 'badge-red',
};

function dateLabel(value) {
    if (!value) return 'متاح دون موعد محدد';

    return new Date(value).toLocaleString('ar-QA', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="مركز الاختبارات" />

    <DashboardLayout>
        <div class="space-y-8 pb-12">
            <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white">مركز الاختبارات</h1>
                    <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">
                        اختباراتك المنشورة ضمن الاشتراكات النشطة ومحاولاتك السابقة.
                    </p>
                </div>
                <Link :href="route('dashboard')" class="btn-outline btn-sm">العودة إلى اللوحة</Link>
            </header>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                <div v-for="stat in [
                    { label: 'كل الاختبارات', value: summary.total, icon: 'courses' },
                    { label: 'متاحة الآن', value: summary.available, icon: 'success' },
                    { label: 'قيد التنفيذ', value: summary.in_progress, icon: 'clock' },
                    { label: 'اختبارات مكتملة', value: summary.completed, icon: 'chart' },
                    { label: 'متوسط الدرجات', value: (summary.average_score ?? 0) + '%', icon: 'certificate' },
                ]" :key="stat.label" class="card p-4">
                    <Icon :name="stat.icon" class="h-5 w-5 text-primary-500" />
                    <div class="mt-3 text-2xl font-black text-surface-900 dark:text-white">{{ stat.value ?? 0 }}</div>
                    <div class="mt-1 text-[11px] text-surface-400">{{ stat.label }}</div>
                </div>
            </div>

            <div class="flex gap-2 overflow-x-auto pb-1">
                <button
                    v-for="item in filters"
                    :key="item.key"
                    type="button"
                    class="shrink-0 rounded-xl px-4 py-2 text-xs font-bold transition-colors"
                    :class="filter === item.key ? 'bg-primary-600 text-white' : 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-300'"
                    @click="filter = item.key"
                >
                    {{ item.label }}
                </button>
            </div>

            <section v-if="filteredItems.length" class="grid gap-4 lg:grid-cols-2">
                <article v-for="item in filteredItems" :key="item.id" class="card p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-primary-600 dark:text-primary-400">
                                {{ item.subject?.name }} · {{ item.unit?.title }}
                            </p>
                            <h2 class="mt-1 truncate text-lg font-black text-surface-900 dark:text-white">{{ item.title }}</h2>
                            <p class="mt-1 text-xs text-surface-400">{{ item.teacher?.name }}</p>
                        </div>
                        <span :class="statusClasses[item.status] ?? 'badge-gray'" class="shrink-0 text-[10px]">
                            {{ statusLabels[item.status] ?? item.status }}
                        </span>
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-3 text-xs">
                        <div class="rounded-xl bg-surface-50 p-3 dark:bg-surface-800/60">
                            <span class="block text-surface-400">الأسئلة</span>
                            <strong class="mt-1 block text-surface-800 dark:text-surface-100">{{ item.questions_count }}</strong>
                        </div>
                        <div class="rounded-xl bg-surface-50 p-3 dark:bg-surface-800/60">
                            <span class="block text-surface-400">المحاولات المتبقية</span>
                            <strong class="mt-1 block text-surface-800 dark:text-surface-100">{{ item.remaining_attempts }}</strong>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs">
                        <div class="text-surface-500 dark:text-surface-400">
                            <span v-if="item.best_attempt">أفضل نتيجة: <strong class="text-surface-800 dark:text-surface-100">{{ item.best_attempt.score }}%</strong></span>
                            <span v-else-if="item.available_from">يفتح: {{ dateLabel(item.available_from) }}</span>
                            <span v-else>متاح الآن</span>
                        </div>
                        <p v-if="item.best_attempt?.teacher_feedback" class="w-full text-xs text-surface-500 dark:text-surface-400">
                            ملاحظات المدرس: {{ item.best_attempt.teacher_feedback }}
                        </p>
                        <Link :href="route('student.quiz', { quizId: item.id })" class="btn-primary btn-sm">
                            {{ item.can_start ? 'ابدأ الاختبار' : 'عرض التفاصيل' }}
                        </Link>
                    </div>
                </article>
            </section>

            <div v-else class="card p-10 text-center">
                <Icon name="chart" class="mx-auto h-10 w-10 text-surface-300" />
                <h2 class="mt-3 font-black text-surface-800 dark:text-surface-100">لا توجد اختبارات في هذا التصنيف</h2>
                <p class="mt-1 text-sm text-surface-400">ستظهر هنا الاختبارات المنشورة ضمن اشتراكاتك النشطة.</p>
            </div>
        </div>
    </DashboardLayout>
</template>
