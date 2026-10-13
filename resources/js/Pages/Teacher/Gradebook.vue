<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    assignments: { type: Array, default: () => [] },
    rows: { type: Object, default: () => ({ data: [], links: [] }) },
    summary: { type: Object, default: () => ({}) },
    selectedAssignment: { type: [Number, String], default: null },
});

const rowItems = computed(() => props.rows?.data ?? []);
const paginationLinks = computed(() => props.rows?.links ?? []);

function selectAssignment(event) {
    const assignment = event.target.value;
    router.get(route('teacher.gradebook'), assignment ? { assignment } : {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('ar-QA', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function scoreLabel(row) {
    if (row.earned === null || row.maximum === null) return 'بانتظار التصحيح';
    return `${row.earned} / ${row.maximum}`;
}

const statusLabels = {
    graded: 'تم التصحيح',
    needs_grading: 'يحتاج تصحيح',
};

const statusClasses = {
    graded: 'badge-green',
    needs_grading: 'badge-accent',
};
</script>

<template>
    <Head title="سجل الدرجات" />

    <DashboardLayout>
        <div class="space-y-7">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white">سجل الدرجات</h1>
                    <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">
                        نظرة موحّدة على الواجبات المسلّمة والاختبارات التي أجراها طلابك.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a
                        :href="route('teacher.gradebook.export', selectedAssignment ? { assignment: selectedAssignment } : {})"
                        class="btn-outline btn-sm flex items-center gap-1.5 whitespace-nowrap"
                        download
                    >
                        <Icon name="download" class="h-4 w-4" />
                        تصدير CSV
                    </a>
                    <label for="gradebook-assignment" class="sr-only">تصفية المادة</label>
                    <select
                        id="gradebook-assignment"
                        class="input text-sm"
                        :value="selectedAssignment || ''"
                        @change="selectAssignment"
                    >
                        <option value="">كل المواد والصفوف</option>
                        <option v-for="assignment in assignments" :key="assignment.id" :value="assignment.id">
                            {{ assignment.subject?.name }} — {{ assignment.grade?.name }}
                        </option>
                    </select>
                </div>
            </header>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                <div class="card p-4">
                    <span class="block text-xl font-black text-surface-900 dark:text-white">{{ summary.assessments ?? 0 }}</span>
                    <span class="text-[11px] text-surface-500">التقييمات</span>
                </div>
                <div class="card p-4">
                    <span class="block text-xl font-black text-surface-900 dark:text-white">{{ summary.students ?? 0 }}</span>
                    <span class="text-[11px] text-surface-500">الطلاب</span>
                </div>
                <div class="card p-4">
                    <span class="block text-xl font-black text-green-600">{{ summary.graded ?? 0 }}</span>
                    <span class="text-[11px] text-surface-500">تم التصحيح</span>
                </div>
                <div class="card p-4">
                    <span class="block text-xl font-black text-amber-600">{{ summary.needs_grading ?? 0 }}</span>
                    <span class="text-[11px] text-surface-500">تحتاج تصحيح</span>
                </div>
                <div class="card p-4 sm:col-span-2 lg:col-span-3">
                    <span class="block text-xl font-black text-primary-600">
                        {{ summary.overall_percentage === null || summary.overall_percentage === undefined ? '—' : `${summary.overall_percentage}%` }}
                    </span>
                    <span class="text-[11px] text-surface-500">النسبة من السجلات ذات الدرجة فقط</span>
                </div>
            </div>

            <section v-if="rowItems.length" class="card overflow-hidden">
                <div class="flex items-center gap-2 border-b border-surface-100 p-5 dark:border-surface-800">
                    <Icon name="chart" class="h-5 w-5 text-primary-500" />
                    <h2 class="font-black text-surface-900 dark:text-white">تفاصيل التقييمات</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-50 text-xs text-surface-500 dark:bg-surface-900/60 dark:text-surface-400">
                            <tr>
                                <th class="px-5 py-3 text-start font-bold">الطالب</th>
                                <th class="px-5 py-3 text-start font-bold">التقييم</th>
                                <th class="px-5 py-3 text-start font-bold">المادة</th>
                                <th class="px-5 py-3 text-start font-bold">الدرجة</th>
                                <th class="px-5 py-3 text-start font-bold">الحالة</th>
                                <th class="px-5 py-3 text-start font-bold">التاريخ</th>
                                <th class="px-5 py-3 text-end font-bold">إجراء</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                            <tr v-for="row in rowItems" :key="row.id" class="align-top">
                                <td class="px-5 py-4">
                                    <div class="font-bold text-surface-900 dark:text-white">{{ row.student?.name || 'طالب غير معروف' }}</div>
                                    <div class="mt-0.5 text-xs text-surface-400">{{ row.student?.email }}</div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="font-bold text-surface-900 dark:text-white">{{ row.title }}</div>
                                    <span class="mt-1 inline-block text-[11px] text-surface-400">{{ row.kind_label }}</span>
                                </td>
                                <td class="px-5 py-4 text-surface-600 dark:text-surface-300">{{ row.subject?.name || '—' }}</td>
                                <td class="px-5 py-4">
                                    <div class="font-black text-surface-900 dark:text-white">{{ scoreLabel(row) }}</div>
                                    <div v-if="row.percentage !== null" class="mt-0.5 text-xs text-surface-500">{{ row.percentage }}%</div>
                                </td>
                                <td class="px-5 py-4">
                                    <span :class="statusClasses[row.status]" class="text-[10px]">{{ statusLabels[row.status] }}</span>
                                    <p v-if="row.feedback" class="mt-2 max-w-xs text-xs text-surface-500">{{ row.feedback }}</p>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-xs text-surface-500">{{ formatDate(row.assessed_at) }}</td>
                                <td class="px-5 py-4 text-end">
                                    <Link
                                        v-if="row.assignment_id"
                                        :href="route('teacher.curriculum', { assignment: row.assignment_id })"
                                        class="btn-ghost btn-sm"
                                    >
                                        فتح المنهج
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav
                    v-if="paginationLinks.length > 3"
                    class="flex flex-wrap items-center justify-center gap-1 border-t border-surface-100 p-4 dark:border-surface-800"
                    aria-label="تنقل سجل الدرجات"
                >
                    <template v-for="link in paginationLinks" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            :class="['btn-ghost btn-sm', link.active ? 'bg-primary-500/10 text-primary-600' : '']"
                            preserve-state
                            preserve-scroll
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="btn-sm cursor-not-allowed text-surface-300 dark:text-surface-600"
                            v-html="link.label"
                        />
                    </template>
                </nav>
            </section>

            <div v-else class="card p-10 text-center">
                <Icon name="chart" class="mx-auto mb-3 h-10 w-10 text-surface-300" />
                <h2 class="font-bold text-surface-800 dark:text-surface-100">لا توجد سجلات تقييم بعد</h2>
                <p class="mt-1 text-sm text-surface-400">
                    ستظهر هنا الواجبات المسلّمة والاختبارات المكتملة فقط؛ الواجب غير المسلّم لا يتحول تلقائيًا إلى صفر.
                </p>
            </div>
        </div>
    </DashboardLayout>
</template>
