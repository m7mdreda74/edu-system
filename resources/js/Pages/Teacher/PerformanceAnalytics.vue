<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    range: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    assignments: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
    students: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    trend: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    attention: { type: Array, default: () => [] },
    quiz_history: { type: Array, default: () => [] },
});

const selectedAssignment = ref(props.filters.assignment || '');
const selectedGroup = ref(props.filters.group || '');
const selectedStudent = ref(props.filters.student || '');
const selectedDays = ref(props.filters.days || 30);

const visibleGroups = computed(() => selectedAssignment.value
    ? props.groups.filter((group) => String(group.assignment_id) === String(selectedAssignment.value))
    : props.groups);

const maxTrend = computed(() => ({
    lessons: Math.max(1, ...props.trend.map((bucket) => Number(bucket.lessons_completed || 0))),
    assignments: Math.max(1, ...props.trend.map((bucket) => Number(bucket.assignments_submitted || 0))),
    quizzes: Math.max(1, ...props.trend.map((bucket) => Number(bucket.quiz_attempts || 0))),
}));

function applyFilters() {
    router.get(route('teacher.analytics'), {
        assignment: selectedAssignment.value || undefined,
        group: selectedGroup.value || undefined,
        student: selectedStudent.value || undefined,
        days: selectedDays.value || 30,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function changeAssignment() {
    if (!visibleGroups.value.some((group) => String(group.id) === String(selectedGroup.value))) {
        selectedGroup.value = '';
    }
    applyFilters();
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('ar-QA', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function formatScore(value) {
    return value === null || value === undefined ? '—' : `${value}%`;
}

function barWidth(value, maximum) {
    return `${Math.min(100, Math.round((Number(value || 0) / maximum) * 100))}%`;
}

function attentionLabel(reason) {
    return {
        missing_assignments: `واجبات بلا تسليم (${reason.value})`,
        low_attendance: `حضور منخفض (${reason.value}%)`,
        declining_results: `انخفاض في النتائج (${reason.value}%)`,
    }[reason.code] || 'تحتاج متابعة';
}

function attentionClass(row) {
    return row.requires_attention
        ? 'border-amber-200 bg-amber-50/70 dark:border-amber-900/60 dark:bg-amber-950/20'
        : 'border-surface-200 bg-white dark:border-surface-800 dark:bg-surface-900/40';
}
</script>

<template>
    <Head title="تحليل أداء الطلاب" />

    <DashboardLayout>
        <div class="space-y-7 pb-12">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white">تحليل أداء الطلاب</h1>
                    <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">
                        مؤشرات قابلة للتفسير من الحضور والتقدم والواجبات والاختبارات الفعلية.
                    </p>
                </div>
                <div class="text-xs text-surface-400">
                    {{ formatDate(range.start) }} — {{ formatDate(range.end) }}
                </div>
            </header>

            <section class="card p-4 sm:p-5">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <label class="space-y-1 text-xs font-bold text-surface-600 dark:text-surface-300">
                        <span>المادة والصف</span>
                        <select v-model="selectedAssignment" class="input text-sm" @change="changeAssignment">
                            <option value="">كل المواد والصفوف</option>
                            <option v-for="assignment in assignments" :key="assignment.id" :value="assignment.id">
                                {{ assignment.subject?.name }} — {{ assignment.grade?.name }}
                            </option>
                        </select>
                    </label>
                    <label class="space-y-1 text-xs font-bold text-surface-600 dark:text-surface-300">
                        <span>المجموعة</span>
                        <select v-model="selectedGroup" class="input text-sm" @change="applyFilters">
                            <option value="">كل المجموعات</option>
                            <option v-for="group in visibleGroups" :key="group.id" :value="group.id">
                                {{ group.name }}<span v-if="group.subject"> — {{ group.subject }}</span>
                            </option>
                        </select>
                    </label>
                    <label class="space-y-1 text-xs font-bold text-surface-600 dark:text-surface-300">
                        <span>الطالب</span>
                        <select v-model="selectedStudent" class="input text-sm" @change="applyFilters">
                            <option value="">كل الطلاب</option>
                            <option v-for="student in students" :key="student.id" :value="student.id">
                                {{ student.name }}
                            </option>
                        </select>
                    </label>
                    <label class="space-y-1 text-xs font-bold text-surface-600 dark:text-surface-300">
                        <span>الفترة</span>
                        <select v-model="selectedDays" class="input text-sm" @change="applyFilters">
                            <option :value="7">آخر 7 أيام</option>
                            <option :value="30">آخر 30 يومًا</option>
                            <option :value="90">آخر 90 يومًا</option>
                        </select>
                    </label>
                    <div class="flex items-end">
                        <button type="button" class="btn-outline w-full" @click="applyFilters">
                            <Icon name="chart" class="me-2 inline-block h-4 w-4" />
                            تحديث التحليل
                        </button>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                <div class="card p-4">
                    <span class="block text-xl font-black text-surface-900 dark:text-white">{{ summary.students ?? 0 }}</span>
                    <span class="text-[11px] text-surface-500">الطلاب</span>
                </div>
                <div class="card p-4 border-amber-200 dark:border-amber-900/60">
                    <span class="block text-xl font-black text-amber-600">{{ summary.students_requiring_attention ?? 0 }}</span>
                    <span class="text-[11px] text-surface-500">تحتاج متابعة</span>
                </div>
                <div class="card p-4">
                    <span class="block text-xl font-black text-green-600">{{ summary.lessons_completed ?? 0 }}</span>
                    <span class="text-[11px] text-surface-500">دروس مكتملة</span>
                </div>
                <div class="card p-4">
                    <span class="block text-xl font-black text-primary-600">{{ summary.study_minutes ?? 0 }}</span>
                    <span class="text-[11px] text-surface-500">دقائق دراسة</span>
                </div>
                <div class="card p-4">
                    <span class="block text-xl font-black text-accent-600">{{ summary.assignments_submitted ?? 0 }}</span>
                    <span class="text-[11px] text-surface-500">واجبات مسلّمة</span>
                </div>
                <div class="card p-4">
                    <span class="block text-xl font-black text-red-600">{{ summary.missing_assignments ?? 0 }}</span>
                    <span class="text-[11px] text-surface-500">واجبات ناقصة</span>
                </div>
                <div class="card p-4">
                    <span class="block text-xl font-black text-surface-900 dark:text-white">{{ formatScore(summary.average_quiz_score) }}</span>
                    <span class="text-[11px] text-surface-500">متوسط الاختبارات</span>
                </div>
                <div class="card p-4">
                    <span class="block text-xl font-black text-sky-600">{{ formatScore(summary.attendance_rate) }}</span>
                    <span class="text-[11px] text-surface-500">نسبة الحضور</span>
                </div>
            </section>

            <section class="card overflow-hidden">
                <div class="flex items-center gap-2 border-b border-surface-100 p-5 dark:border-surface-800">
                    <Icon name="chart" class="h-5 w-5 text-primary-500" />
                    <div>
                        <h2 class="font-black text-surface-900 dark:text-white">اتجاه النشاط</h2>
                        <p class="mt-1 text-xs text-surface-400">تجميع زمني يوضح التغير دون تصنيف الطالب حكمًا نهائيًا.</p>
                    </div>
                </div>
                <div v-if="trend.length" class="overflow-x-auto p-5">
                    <div class="min-w-[640px] space-y-4">
                        <div class="grid grid-cols-[7rem_1fr_5rem] items-center gap-3 text-xs font-bold text-surface-500">
                            <span>الفترة</span>
                            <span>النشاط</span>
                            <span class="text-end">الاختبار</span>
                        </div>
                        <div v-for="bucket in trend" :key="bucket.start" class="grid grid-cols-[7rem_1fr_5rem] items-center gap-3">
                            <div class="text-xs font-bold text-surface-600 dark:text-surface-300">
                                {{ formatDate(bucket.start) }}
                                <span class="block text-[10px] font-normal text-surface-400">{{ formatDate(bucket.end) }}</span>
                            </div>
                            <div class="space-y-1.5">
                                <div class="flex items-center gap-2 text-[11px]">
                                    <span class="w-20 text-surface-500">دروس {{ bucket.lessons_completed }}</span>
                                    <div class="h-2 flex-1 rounded-full bg-surface-100 dark:bg-surface-800"><div class="h-2 rounded-full bg-green-500" :style="{ width: barWidth(bucket.lessons_completed, maxTrend.lessons) }"></div></div>
                                </div>
                                <div class="flex items-center gap-2 text-[11px]">
                                    <span class="w-20 text-surface-500">واجبات {{ bucket.assignments_submitted }}</span>
                                    <div class="h-2 flex-1 rounded-full bg-surface-100 dark:bg-surface-800"><div class="h-2 rounded-full bg-accent-500" :style="{ width: barWidth(bucket.assignments_submitted, maxTrend.assignments) }"></div></div>
                                </div>
                                <div class="flex items-center gap-2 text-[11px]">
                                    <span class="w-20 text-surface-500">اختبارات {{ bucket.quiz_attempts }}</span>
                                    <div class="h-2 flex-1 rounded-full bg-surface-100 dark:bg-surface-800"><div class="h-2 rounded-full bg-primary-500" :style="{ width: barWidth(bucket.quiz_attempts, maxTrend.quizzes) }"></div></div>
                                </div>
                            </div>
                            <div class="text-end text-xs font-black text-surface-700 dark:text-surface-200">
                                {{ formatScore(bucket.average_quiz_score) }}
                                <span class="mt-1 block text-[10px] font-normal text-surface-400">حضور {{ formatScore(bucket.attendance_rate) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="p-10 text-center text-sm text-surface-400">لا توجد سجلات في هذه الفترة.</div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(20rem,0.6fr)]">
                <div class="card overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-surface-100 p-5 dark:border-surface-800">
                        <Icon name="student" class="h-5 w-5 text-primary-500" />
                        <h2 class="font-black text-surface-900 dark:text-white">ملخص الطلاب</h2>
                    </div>
                    <div v-if="rows.length" class="overflow-x-auto">
                        <table class="min-w-[900px] w-full text-sm">
                            <thead class="bg-surface-50 text-xs text-surface-500 dark:bg-surface-900/60 dark:text-surface-400">
                                <tr>
                                    <th class="px-5 py-3 text-start font-bold">الطالب</th>
                                    <th class="px-5 py-3 text-start font-bold">التقدم</th>
                                    <th class="px-5 py-3 text-start font-bold">الواجبات</th>
                                    <th class="px-5 py-3 text-start font-bold">الاختبارات</th>
                                    <th class="px-5 py-3 text-start font-bold">الحضور</th>
                                    <th class="px-5 py-3 text-end font-bold">الحالة</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                                <tr v-for="row in rows" :key="row.id" :class="attentionClass(row)" class="align-top">
                                    <td class="px-5 py-4">
                                        <div class="font-bold text-surface-900 dark:text-white">{{ row.name }}</div>
                                        <div class="mt-0.5 text-xs text-surface-400">{{ row.email }}</div>
                                    </td>
                                    <td class="px-5 py-4 text-xs text-surface-600 dark:text-surface-300">
                                        <div>{{ row.lessons_completed }} درس مكتمل</div>
                                        <div class="mt-1 text-surface-400">{{ row.study_minutes }} دقيقة</div>
                                    </td>
                                    <td class="px-5 py-4 text-xs">
                                        <span class="font-black text-green-600">{{ row.assignments_submitted }} مسلّم</span>
                                        <span class="mt-1 block text-red-500">{{ row.missing_assignments }} ناقص</span>
                                    </td>
                                    <td class="px-5 py-4 text-xs">
                                        <span class="font-bold text-surface-700 dark:text-surface-200">{{ row.quiz_attempts }} محاولة</span>
                                        <span class="mt-1 block text-surface-400">{{ formatScore(row.average_quiz_score) }}</span>
                                    </td>
                                    <td class="px-5 py-4 text-xs font-bold text-sky-600">{{ formatScore(row.attendance_rate) }}</td>
                                    <td class="px-5 py-4 text-end">
                                        <span v-if="row.requires_attention" class="badge-accent text-[10px]">تحتاج متابعة</span>
                                        <span v-else class="badge-green text-[10px]">مستقر</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="p-10 text-center text-sm text-surface-400">لا يوجد طلاب مؤهلون لهذا النطاق.</div>
                </div>

                <div class="card overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-surface-100 p-5 dark:border-surface-800">
                        <Icon name="clock" class="h-5 w-5 text-amber-500" />
                        <h2 class="font-black text-surface-900 dark:text-white">تحتاج متابعة</h2>
                    </div>
                    <div v-if="attention.length" class="space-y-3 p-5">
                        <article v-for="row in attention" :key="row.id" class="rounded-xl border border-amber-200 bg-amber-50/70 p-4 dark:border-amber-900/60 dark:bg-amber-950/20">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-black text-surface-900 dark:text-white">{{ row.name }}</h3>
                                    <p class="mt-1 text-xs text-surface-500">{{ row.email }}</p>
                                </div>
                                <span class="text-xs font-black text-amber-600">{{ formatScore(row.average_quiz_score) }}</span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                <span v-for="reason in row.attention_reasons" :key="reason.code" class="rounded-full bg-white px-2.5 py-1 text-[11px] font-bold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                                    {{ attentionLabel(reason) }}
                                </span>
                            </div>
                        </article>
                    </div>
                    <div v-else class="p-10 text-center text-sm text-surface-400">لا توجد إشارات متابعة في هذه الفترة.</div>
                </div>
            </section>

            <section v-if="quiz_history.length" class="card overflow-hidden">
                <div class="flex items-center gap-2 border-b border-surface-100 p-5 dark:border-surface-800">
                    <Icon name="chart" class="h-5 w-5 text-primary-500" />
                    <h2 class="font-black text-surface-900 dark:text-white">تاريخ الاختبارات</h2>
                </div>
                <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3">
                    <article v-for="attempt in quiz_history" :key="attempt.id" class="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-bold text-surface-900 dark:text-white">{{ attempt.student?.name }}</h3>
                                <p class="mt-1 text-xs text-surface-500">{{ attempt.title }}</p>
                            </div>
                            <span class="font-black text-primary-600">{{ formatScore(attempt.score) }}</span>
                        </div>
                        <p class="mt-3 text-[11px] text-surface-400">{{ formatDate(attempt.submitted_at) }}</p>
                    </article>
                </div>
            </section>
        </div>
    </DashboardLayout>
</template>
