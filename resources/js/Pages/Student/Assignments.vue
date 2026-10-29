<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    assignments: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
});

const activeFilter = ref('all');
const filters = [
    { key: 'all', label: 'الكل' },
    { key: 'pending', label: 'مطلوب' },
    { key: 'in_progress', label: 'قيد التنفيذ' },
    { key: 'submitted', label: 'تم التسليم' },
    { key: 'late', label: 'متأخر' },
    { key: 'graded', label: 'تم التصحيح' },
];

const visibleAssignments = computed(() => {
    if (activeFilter.value === 'all') {
        return props.assignments;
    }
    if (activeFilter.value === 'late') {
        return props.assignments.filter((assignment) =>
            assignment.status === 'late' || assignment.submitted_late || assignment.submission?.is_late
        );
    }
    return props.assignments.filter((assignment) => assignment.status === activeFilter.value);
});

const statusLabels = {
    pending: 'مطلوب',
    in_progress: 'قيد التنفيذ',
    submitted: 'تم التسليم',
    late: 'متأخر',
    graded: 'تم التصحيح',
};

const statusClasses = {
    pending: 'badge-gray',
    in_progress: 'badge-primary',
    submitted: 'badge-accent',
    late: 'badge-red',
    graded: 'badge-green',
};

function formatDate(value) {
    if (!value) return 'بدون موعد نهائي';
    return new Date(`${value}T00:00:00`).toLocaleDateString('ar-QA', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });
}
</script>

<template>
    <Head title="التقييمات" />

    <DashboardLayout>
        <div class="space-y-7">
            <header>
                <h1 class="text-2xl font-black text-surface-900 dark:text-white">التقييمات</h1>
                <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">كل التقييمات المرتبطة باشتراكاتك الفعّالة وحالتها الحالية.</p>
            </header>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <button
                    v-for="filter in filters"
                    :key="filter.key"
                    type="button"
                    class="card p-3 text-start transition-colors"
                    :class="activeFilter === filter.key ? 'border-primary-500 bg-primary-500/5' : 'border-transparent'"
                    @click="activeFilter = filter.key"
                >
                    <span class="block text-lg font-black text-surface-900 dark:text-white">
                        {{ filter.key === 'all' ? summary.total ?? 0 : summary[filter.key] ?? 0 }}
                    </span>
                    <span class="text-[11px] text-surface-500">{{ filter.label }}</span>
                </button>
            </div>

            <section v-if="visibleAssignments.length" class="space-y-3">
                <article v-for="assignment in visibleAssignments" :key="assignment.id" class="card p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-bold text-surface-900 dark:text-white">{{ assignment.title }}</h2>
                                <span :class="statusClasses[assignment.status]" class="text-[10px]">{{ statusLabels[assignment.status] }}</span>
                                <span
                                    v-if="assignment.submitted_late || assignment.submission?.is_late"
                                    class="badge-red text-[10px] flex items-center gap-1 font-bold"
                                    title="تم التسليم بعد انتهاء الموعد المحدد"
                                >
                                    <span>⚠️</span>
                                    <span>تسليم متأخر</span>
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-surface-500 dark:text-surface-400">
                                {{ assignment.subject?.name }} — {{ assignment.teacher?.name }}<span v-if="assignment.group?.name"> · {{ assignment.group.name }}</span>
                            </p>
                            <p class="mt-2 flex items-center gap-1.5 text-xs" :class="assignment.is_late ? 'font-bold text-red-500' : 'text-surface-500'">
                                <Icon name="calendar" class="h-3.5 w-3.5" />
                                <span>التسليم: {{ formatDate(assignment.due_date) }}</span>
                                <span v-if="!assignment.submission && assignment.is_late" class="ms-1 font-bold text-red-600 dark:text-red-400">
                                    (متأخر - يرجى التسليم فوراً)
                                </span>
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <a v-if="assignment.worksheet_file" :href="assignment.worksheet_file" class="btn-outline btn-sm" target="_blank" rel="noopener">
                                ورقة الأسئلة
                            </a>
                            <Link
                                :href="route('student.assessments')"
                                class="btn-primary btn-sm font-bold"
                            >
                                تسليم التقييم
                            </Link>
                        </div>
                    </div>

                    <div v-if="assignment.submission" class="mt-4 rounded-xl bg-surface-50 p-3 text-xs dark:bg-surface-900/60">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-surface-500">
                            <div class="flex items-center gap-2">
                                <span>تم التسليم: {{ new Date(assignment.submission.submitted_at).toLocaleDateString('ar-QA') }}</span>
                                <span
                                    v-if="assignment.submission.is_late || assignment.submitted_late"
                                    class="rounded bg-red-500/10 px-1.5 py-0.5 text-[10px] font-bold text-red-500 border border-red-500/20"
                                >
                                    تأخر في التسليم
                                </span>
                            </div>
                            <span v-if="assignment.submission.score !== null" class="font-bold text-green-600">
                                الدرجة: {{ assignment.submission.score }} / {{ assignment.max_score ?? '—' }}
                            </span>
                        </div>

                        <!-- Late submission explicit note -->
                        <div
                            v-if="assignment.submission.is_late || assignment.submitted_late || assignment.late_notice"
                            class="mt-2 flex items-center gap-1.5 rounded-lg bg-red-500/10 px-2.5 py-1.5 text-[11px] font-medium text-red-600 dark:text-red-400 border border-red-500/20"
                        >
                            <Icon name="alert-triangle" class="h-3.5 w-3.5 flex-shrink-0" />
                            <span>{{ assignment.late_notice || 'تنبيه: تم تسليم هذا التقييم بعد انتهاء الموعد المحدد (تأخر في التسليم).' }}</span>
                        </div>

                        <p v-if="assignment.submission.teacher_feedback" class="mt-2 text-surface-700 dark:text-surface-200">
                            {{ assignment.submission.teacher_feedback }}
                        </p>
                        <a v-if="assignment.submission.file" :href="assignment.submission.file" class="mt-2 inline-block font-bold text-primary-600 underline" target="_blank" rel="noopener">
                            عرض التسليم
                        </a>
                    </div>
                </article>
            </section>

            <div v-else class="card p-10 text-center">
                <Icon name="book" class="mx-auto mb-3 h-10 w-10 text-surface-300" />
                <h2 class="font-bold text-surface-800 dark:text-surface-100">لا توجد تقييمات في هذا التصنيف</h2>
                <p class="mt-1 text-sm text-surface-400">ستظهر التقييمات المنشورة هنا بعد وجود اشتراك فعّال.</p>
            </div>
        </div>
    </DashboardLayout>
</template>
