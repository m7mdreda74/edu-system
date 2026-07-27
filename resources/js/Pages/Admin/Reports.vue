<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import StatCard from '@/Components/StatCard.vue';
import { formatQAR } from '@/lib/money';

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    teachers: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
    sessions: { type: Object, default: () => ({ data: [], links: [] }) },
    summary: { type: Object, default: () => ({}) },
});

function applyFilters(event) {
    const data = Object.fromEntries(new FormData(event.target).entries());
    router.get(route('admin.reports'), data, { preserveState: true, replace: true });
}

function formatDate(value) {
    return value
        ? new Date(value).toLocaleString('ar-EG', { dateStyle: 'medium', timeStyle: 'short' })
        : '—';
}

function statusLabel(status) {
    return {
        scheduled: 'مجدولة',
        live: 'مباشرة الآن',
        ended: 'منتهية',
        cancelled: 'ملغاة',
    }[status] || status;
}

function statusClass(status) {
    return {
        scheduled: 'badge-accent',
        live: 'badge-green',
        ended: 'badge-blue',
        cancelled: 'badge-red',
    }[status] || 'badge-gray';
}
</script>

<template>
    <DashboardLayout>
        <Head title="تقارير المنصة" />

        <div class="space-y-6" dir="rtl">
            <header class="flex flex-wrap items-start justify-between gap-4 print-hidden">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-black text-surface-900 dark:text-white">
                        <Icon name="chart" class="h-7 w-7 text-primary-500" />
                        تقارير المنصة
                    </h1>
                    <p class="mt-1 text-sm text-surface-500">متابعة المدرسين والمجموعات والحصص والحجوزات في جداول واضحة.</p>
                </div>
                <button type="button" class="btn-primary" @click="window.print()">
                    <Icon name="download" class="h-4 w-4" />
                    طباعة / حفظ PDF
                </button>
            </header>

            <form class="card grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-5 print-hidden" @submit.prevent="applyFilters">
                <div>
                    <label class="input-label">من تاريخ</label>
                    <input name="start_date" type="date" class="input" :value="filters.start_date || ''" />
                </div>
                <div>
                    <label class="input-label">إلى تاريخ</label>
                    <input name="end_date" type="date" class="input" :value="filters.end_date || ''" />
                </div>
                <div>
                    <label class="input-label">المدرس</label>
                    <select name="teacher_id" class="input">
                        <option value="">كل المدرسين</option>
                        <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id" :selected="String(filters.teacher_id || '') === String(teacher.id)">
                            {{ teacher.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="input-label">حالة الحصة</label>
                    <select name="status" class="input">
                        <option value="">كل الحالات</option>
                        <option v-for="status in ['scheduled', 'live', 'ended', 'cancelled']" :key="status" :value="status" :selected="filters.status === status">
                            {{ statusLabel(status) }}
                        </option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button class="btn-primary flex-1">تطبيق الفلاتر</button>
                    <Link :href="route('admin.reports')" class="btn-outline">مسح</Link>
                </div>
            </form>

            <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
                <StatCard label="المدرسون" :value="summary.teachers || 0" icon="users" tone="primary" />
                <StatCard label="المجموعات" :value="summary.groups || 0" icon="courses" tone="accent" />
                <StatCard label="الطلاب المحجوزون" :value="summary.students || 0" icon="student" tone="green" />
                <StatCard label="كل الحصص" :value="summary.sessions || 0" icon="live" tone="blue" />
                <StatCard label="مباشرة الآن" :value="summary.live_sessions || 0" icon="live" tone="red" />
                <StatCard label="حصص منتهية" :value="summary.ended_sessions || 0" icon="success" tone="green" />
            </div>

            <section class="report-section">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="section-title">ملخص المدرسين</h2>
                    <span class="text-xs text-surface-400">{{ teachers.length }} مدرس</span>
                </div>
                <div class="table-wrap">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>المدرس</th>
                                <th>المادة</th>
                                <th>التكليفات</th>
                                <th>المجموعات</th>
                                <th>الطلاب</th>
                                <th>الحصص</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="teacher in teachers" :key="teacher.id">
                                <td class="font-bold">{{ teacher.name }}</td>
                                <td>{{ teacher.subject?.name || '—' }}</td>
                                <td>{{ teacher.teaching_assignments_count || 0 }}</td>
                                <td>{{ teacher.groups_count || 0 }}</td>
                                <td>{{ teacher.students_count || 0 }}</td>
                                <td>{{ teacher.sessions_count || 0 }}</td>
                            </tr>
                            <tr v-if="!teachers.length"><td colspan="6" class="empty-cell">لا توجد بيانات.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="report-section">
                <h2 class="section-title mb-3">الحصص المباشرة</h2>
                <div class="table-wrap">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>الحصة</th>
                                <th>المدرس</th>
                                <th>المادة / المجموعة</th>
                                <th>الموعد</th>
                                <th>الطلاب</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="session in sessions.data" :key="session.id">
                                <td class="font-bold">{{ session.title }}</td>
                                <td>{{ session.teacher?.name || '—' }}</td>
                                <td>
                                    {{ session.teaching_group?.assignment?.subject?.name || 'حصة برايفيت' }}
                                    <span class="block text-xs text-surface-400">{{ session.teaching_group?.name || 'موعد خاص' }}</span>
                                </td>
                                <td>{{ formatDate(session.scheduled_at) }}</td>
                                <td>{{ session.attendees_count || 0 }}</td>
                                <td><span :class="statusClass(session.status)">{{ statusLabel(session.status) }}</span></td>
                            </tr>
                            <tr v-if="!sessions.data?.length"><td colspan="6" class="empty-cell">لا توجد حصص مطابقة للفلاتر.</td></tr>
                        </tbody>
                    </table>
                </div>
                <nav v-if="sessions.links?.length > 3" class="mt-4 flex flex-wrap justify-center gap-2 print-hidden">
                    <Link v-for="link in sessions.links" :key="link.label" :href="link.url || '#'" preserve-scroll class="rounded-xl px-3 py-2 text-sm" :class="[link.active ? 'bg-primary-500 text-white' : 'bg-surface-100 dark:bg-surface-800', !link.url ? 'pointer-events-none opacity-40' : '']" v-html="link.label" />
                </nav>
            </section>

            <section class="report-section">
                <h2 class="section-title mb-3">المجموعات والحجوزات</h2>
                <div class="table-wrap">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>المجموعة</th>
                                <th>المدرس</th>
                                <th>المادة</th>
                                <th>الفصل الدراسي</th>
                                <th>الحجوزات</th>
                                <th>السعة</th>
                                <th>الحصص</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="group in groups" :key="group.id">
                                <td class="font-bold">{{ group.name }}</td>
                                <td>{{ group.assignment?.teacher?.name || '—' }}</td>
                                <td>{{ group.assignment?.subject?.name || '—' }}</td>
                                <td>{{ group.term?.name || '—' }}</td>
                                <td>{{ group.active_bookings_count || 0 }}</td>
                                <td>{{ group.capacity }}</td>
                                <td>{{ group.live_sessions_count || 0 }}</td>
                            </tr>
                            <tr v-if="!groups.length"><td colspan="7" class="empty-cell">لا توجد مجموعات مطابقة للفلاتر.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </DashboardLayout>
</template>

<style>
.report-section { @apply rounded-2xl border border-surface-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900; }
.section-title { @apply text-lg font-black text-surface-900 dark:text-white; }
.table-wrap { @apply overflow-x-auto; }
.report-table { @apply min-w-full text-right text-sm; }
.report-table th { @apply border-b border-surface-200 px-4 py-3 text-xs font-black text-surface-500 dark:border-surface-800; }
.report-table td { @apply border-b border-surface-100 px-4 py-3 text-surface-700 dark:border-surface-800 dark:text-surface-300; }
.report-table tbody tr:last-child td { @apply border-b-0; }
.empty-cell { @apply py-8 text-center text-surface-400; }
@media print {
    .print-hidden, aside, header, nav { display: none !important; }
    body, main { background: white !important; color: #111827 !important; }
    .report-section { break-inside: avoid; box-shadow: none !important; border-color: #d1d5db !important; margin-bottom: 18px; }
    .report-table td, .report-table th { color: #111827 !important; border-color: #d1d5db !important; }
}
</style>
