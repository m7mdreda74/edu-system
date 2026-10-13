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

const visibleAssignments = computed(() => activeFilter.value === 'all'
    ? props.assignments
    : props.assignments.filter((assignment) => assignment.status === activeFilter.value));

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
    <Head title="واجباتي" />

    <DashboardLayout>
        <div class="space-y-7">
            <header>
                <h1 class="text-2xl font-black text-surface-900 dark:text-white">مركز واجباتي</h1>
                <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">كل الواجبات المرتبطة باشتراكاتك الفعّالة وحالتها الحالية.</p>
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
                            </div>
                            <p class="mt-1 text-xs text-surface-500 dark:text-surface-400">
                                {{ assignment.subject?.name }} — {{ assignment.teacher?.name }}<span v-if="assignment.group?.name"> · {{ assignment.group.name }}</span>
                            </p>
                            <p class="mt-2 flex items-center gap-1.5 text-xs" :class="assignment.is_late ? 'font-bold text-red-500' : 'text-surface-500'">
                                <Icon name="calendar" class="h-3.5 w-3.5" />
                                التسليم: {{ formatDate(assignment.due_date) }}
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <a v-if="assignment.worksheet_file" :href="assignment.worksheet_file" class="btn-outline btn-sm" target="_blank" rel="noopener">
                                ورقة الواجب
                            </a>
                            <Link
                                v-if="assignment.group_id"
                                :href="route('student.learn', { groupId: assignment.group_id })"
                                class="btn-primary btn-sm"
                            >
                                فتح الدرس
                            </Link>
                            <Link v-else :href="route('student.my-classes')" class="btn-primary btn-sm">فتح الحصص</Link>
                        </div>
                    </div>

                    <div v-if="assignment.submission" class="mt-4 rounded-xl bg-surface-50 p-3 text-xs dark:bg-surface-900/60">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-surface-500">
                            <span>تم التسليم: {{ new Date(assignment.submission.submitted_at).toLocaleDateString('ar-QA') }}</span>
                            <span v-if="assignment.submission.score !== null" class="font-bold text-green-600">
                                الدرجة: {{ assignment.submission.score }} / {{ assignment.max_score ?? '—' }}
                            </span>
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
                <h2 class="font-bold text-surface-800 dark:text-surface-100">لا توجد واجبات في هذا التصنيف</h2>
                <p class="mt-1 text-sm text-surface-400">ستظهر الواجبات المنشورة هنا بعد وجود اشتراك فعّال.</p>
            </div>
        </div>
    </DashboardLayout>
</template>
