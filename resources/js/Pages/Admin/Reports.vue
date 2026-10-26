<script setup>
import { computed, nextTick, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import StatCard from '@/Components/StatCard.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { useClientPagination } from '@/composables/useClientPagination';

const props = defineProps({
    filters:    { type: Object,  default: () => ({}) },
    printMode:  { type: Boolean, default: false },
    teachers:   { type: Array,   default: () => [] },
    students:   { type: Array,   default: () => [] },
    groups:     { type: Array,   default: () => [] },
    sessions:   { type: Object,  default: () => ({ data: [], links: [] }) },
    attendance: { type: Object,  default: () => ({ data: [], links: [] }) },
    summary:    { type: Object,  default: () => ({}) },
});

// Category Tabs
const activeCategory = ref(props.filters.report_type || 'all');

const categories = [
    { key: 'all',        label: 'التقرير الشامل',            icon: 'chart',   badge: null },
    { key: 'teachers',   label: 'ملخص المدرسين',             icon: 'users',   badge: props.summary.teachers ?? props.teachers.length },
    { key: 'attendance', label: 'تقرير حضور الطلاب',         icon: 'student', badge: props.attendance?.meta?.total ?? props.attendance?.data?.length },
    { key: 'sessions',   label: 'تقرير الحصص المباشرة',      icon: 'live',    badge: props.summary.sessions ?? props.sessions?.meta?.total },
    { key: 'groups',     label: 'تقرير المجموعات والدورات',  icon: 'courses', badge: props.summary.groups ?? props.groups.length },
];

const {
    data: paginatedTeachers,
    pagination: teachersPagination,
    setPage: setTeachersPage,
} = useClientPagination(computed(() => props.teachers));

const {
    data: paginatedGroups,
    pagination: groupsPagination,
    setPage: setGroupsPage,
} = useClientPagination(computed(() => props.groups));

function setCategory(catKey) {
    activeCategory.value = catKey;
    const current = { ...props.filters, report_type: catKey };
    router.get(route('admin.reports'), current, { preserveState: true, replace: true });
}

function applyFilters(event) {
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData.entries());
    data.report_type = activeCategory.value;
    router.get(route('admin.reports'), data, { preserveState: true, replace: true });
}

function formatDate(value) {
    return value
        ? new Date(value).toLocaleString('ar-EG', { dateStyle: 'medium', timeStyle: 'short' })
        : '—';
}

function formatTime(value) {
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

function sessionTypeLabel(session) {
    if (session.private_session_slot_id || session.privateSessionSlot) return 'مرافقة خاصة';
    if (session.is_free) return 'مجانية / تجريبية';
    return 'مجموعة دراسية';
}

function sessionTypeBadge(session) {
    if (session.private_session_slot_id || session.privateSessionSlot) return 'bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300 border border-purple-200 dark:border-purple-800';
    if (session.is_free) return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800';
    return 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800';
}

function printReport() {
    router.get(route('admin.reports'), {
        report_type:  activeCategory.value,
        start_date:   props.filters.start_date || '',
        end_date:     props.filters.end_date || '',
        teacher_id:   props.filters.teacher_id || '',
        student_id:   props.filters.student_id || '',
        session_type: props.filters.session_type || 'all',
        group_id:     props.filters.group_id || '',
        status:       props.filters.status || '',
        print:        1,
    }, {
        preserveState: true,
        replace:       true,
        onSuccess:     () => nextTick(() => window.print()),
    });
}

const printFilterSummary = computed(() => {
    const teacher = props.teachers.find((item) => String(item.id) === String(props.filters.teacher_id || ''));
    const student = props.students.find((item) => String(item.id) === String(props.filters.student_id || ''));
    const group   = props.groups.find((item) => String(item.id) === String(props.filters.group_id || ''));
    const sessionTypeNames = {
        all:     'كل الأنواع',
        private: 'مرافقة خاصة',
        free:    'مجانية / تجريبية',
        group:   'مجموعات دراسية',
    };

    const values = [];
    if (activeCategory.value && activeCategory.value !== 'all') {
        const catMap = {
            teachers:   'ملخص المدرسين',
            attendance: 'تقرير حضور الطلاب',
            sessions:   'تقرير الحصص المباشرة',
            groups:     'تقرير المجموعات والدورات',
        };
        values.push(`الفئة: ${catMap[activeCategory.value] || activeCategory.value}`);
    }
    if (teacher) values.push(`المدرس: ${teacher.name}`);
    if (student) values.push(`الطالب: ${student.name}`);
    if (group)   values.push(`المجموعة: ${group.name}`);
    if (props.filters.session_type && props.filters.session_type !== 'all') {
        values.push(`نوع الحصة: ${sessionTypeNames[props.filters.session_type] || props.filters.session_type}`);
    }
    if (props.filters.status) values.push(`الحالة: ${statusLabel(props.filters.status)}`);
    if (props.filters.start_date) values.push(`من ${props.filters.start_date}`);
    if (props.filters.end_date)   values.push(`إلى ${props.filters.end_date}`);

    return values.length ? values.join(' — ') : 'كافة البيانات دون فلاتر';
});

const activeCategoryTitle = computed(() => {
    switch (activeCategory.value) {
        case 'teachers':   return 'تقرير ملخص المدرسين والتكليفات';
        case 'attendance': return 'تقرير سجل حضور وغياب الطلاب الفعلي';
        case 'sessions':   return 'تقرير الحصص المباشرة (مرافقة - مجانية - مجموعات)';
        case 'groups':     return 'تقرير المجموعات الدراسية والدورات';
        default:           return 'التقرير الشامل لبوابة المجد التعليمية';
    }
});
</script>

<template>
    <DashboardLayout>
        <Head title="تقارير المنصة" />

        <div class="dashboard-data-page" dir="rtl">

            <!-- Print Header -->
            <div v-if="printMode" class="print-only report-print-header">
                <h1>{{ activeCategoryTitle }}</h1>
                <p>بوابة المجد التعليمية — نظام إدارة التعلم والتقارير</p>
                <p>الفلاتر المطبقة: {{ printFilterSummary }}</p>
                <p>تاريخ ووقت الطباعة: {{ formatDate(new Date().toISOString()) }}</p>
            </div>

            <!-- Page Header -->
            <header class="flex flex-wrap items-start justify-between gap-4 print-hidden mb-6">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-black text-surface-900 dark:text-white">
                        <Icon name="chart" class="h-7 w-7 text-primary-500" />
                        <span>تقارير المنصة والاستخراج المخصص</span>
                    </h1>
                    <p class="mt-1 text-sm text-surface-500">
                        استخراج وطباعة تقارير دقيقة ومفصلة حسب كل فئة (مدرسين، حضور طلاب، حصص مباشرة، ومجموعات).
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" class="btn-primary flex items-center gap-2" @click="printReport()">
                        <Icon name="download" class="h-4 w-4" />
                        <span>طباعة / تصدير PDF للفئة المحددة</span>
                    </button>
                </div>
            </header>

            <!-- ── Category Tabs ─────────────────────────────────── -->
            <div class="print-hidden mb-6 border-b border-surface-200 dark:border-surface-800">
                <nav class="flex flex-wrap gap-2 -mb-px" aria-label="فئات التقارير">
                    <button
                        v-for="cat in categories"
                        :key="cat.key"
                        type="button"
                        @click="setCategory(cat.key)"
                        class="px-4 py-3 text-sm font-bold border-b-2 transition-all flex items-center gap-2"
                        :class="activeCategory === cat.key
                            ? 'border-primary-600 text-primary-700 dark:border-primary-400 dark:text-primary-300 bg-primary-50/50 dark:bg-primary-950/20 rounded-t-xl'
                            : 'border-transparent text-surface-600 hover:text-surface-900 dark:text-surface-400 dark:hover:text-surface-200 hover:border-surface-300'"
                    >
                        <Icon :name="cat.icon" class="w-4 h-4" />
                        <span>{{ cat.label }}</span>
                        <span
                            v-if="cat.badge !== null"
                            class="px-1.5 py-0.5 rounded-full text-xs"
                            :class="activeCategory === cat.key
                                ? 'bg-primary-600 text-white'
                                : 'bg-surface-200 dark:bg-surface-800 text-surface-600 dark:text-surface-300'"
                        >
                            {{ cat.badge }}
                        </span>
                    </button>
                </nav>
            </div>

            <!-- ── Dynamic Filter Toolbar ────────────────────────── -->
            <form class="card p-5 mb-6 print-hidden" @submit.prevent="applyFilters">
                <div class="text-xs font-bold text-surface-500 dark:text-surface-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                    <span>🔍</span>
                    <span>فلاتر البحث والاستخراج: {{ activeCategoryTitle }}</span>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">

                    <!-- Filter: Teacher (All, Teachers, Attendance, Sessions, Groups) -->
                    <div>
                        <label class="input-label">المدرس</label>
                        <select name="teacher_id" class="input">
                            <option value="">كل المدرسين</option>
                            <option
                                v-for="teacher in teachers"
                                :key="teacher.id"
                                :value="teacher.id"
                                :selected="String(filters.teacher_id || '') === String(teacher.id)"
                            >
                                {{ teacher.name }} ({{ teacher.subject?.name || 'عام' }})
                            </option>
                        </select>
                    </div>

                    <!-- Filter: Student (Attendance & All) -->
                    <div v-if="activeCategory === 'attendance' || activeCategory === 'all'">
                        <label class="input-label">الطالب (اختيار الطالب)</label>
                        <select name="student_id" class="input">
                            <option value="">كل الطلاب</option>
                            <option
                                v-for="std in students"
                                :key="std.id"
                                :value="std.id"
                                :selected="String(filters.student_id || '') === String(std.id)"
                            >
                                {{ std.name }} ({{ std.email }})
                            </option>
                        </select>
                    </div>

                    <!-- Filter: Session Type (Sessions, Attendance, All) -->
                    <div v-if="activeCategory === 'sessions' || activeCategory === 'attendance' || activeCategory === 'all'">
                        <label class="input-label">نوع الحصة المباشرة</label>
                        <select name="session_type" class="input">
                            <option value="all" :selected="!filters.session_type || filters.session_type === 'all'">كل الأنواع</option>
                            <option value="private" :selected="filters.session_type === 'private'">مرافقة خاصة (فردية)</option>
                            <option value="free" :selected="filters.session_type === 'free'">مجانية / تجريبية</option>
                            <option value="group" :selected="filters.session_type === 'group'">مجموعات دراسية</option>
                        </select>
                    </div>

                    <!-- Filter: Group / Course (Groups, Sessions, All) -->
                    <div v-if="activeCategory === 'groups' || activeCategory === 'sessions' || activeCategory === 'all'">
                        <label class="input-label">المجموعة / الدورة</label>
                        <select name="group_id" class="input">
                            <option value="">كل المجموعات</option>
                            <option
                                v-for="grp in groups"
                                :key="grp.id"
                                :value="grp.id"
                                :selected="String(filters.group_id || '') === String(grp.id)"
                            >
                                {{ grp.name }} ({{ grp.assignment?.teacher?.name }})
                            </option>
                        </select>
                    </div>

                    <!-- Filter: Session Status (Sessions, Attendance, All) -->
                    <div v-if="activeCategory === 'sessions' || activeCategory === 'attendance' || activeCategory === 'all'">
                        <label class="input-label">حالة الحصة</label>
                        <select name="status" class="input">
                            <option value="">كل الحالات</option>
                            <option
                                v-for="status in ['scheduled', 'live', 'ended', 'cancelled']"
                                :key="status"
                                :value="status"
                                :selected="filters.status === status"
                            >
                                {{ statusLabel(status) }}
                            </option>
                        </select>
                    </div>

                    <!-- Filter: Date From -->
                    <div>
                        <label class="input-label">من تاريخ</label>
                        <input name="start_date" type="date" class="input" :value="filters.start_date || ''" />
                    </div>

                    <!-- Filter: Date To -->
                    <div>
                        <label class="input-label">إلى تاريخ</label>
                        <input name="end_date" type="date" class="input" :min="filters.start_date || undefined" :value="filters.end_date || ''" />
                    </div>

                    <!-- Submit & Reset Actions -->
                    <div class="flex items-end gap-2">
                        <button type="submit" class="btn-primary flex-1">
                            تطبيق الفلترة 🔍
                        </button>
                        <Link :href="route('admin.reports', { report_type: activeCategory })" class="btn-outline">
                            إعادة ضبط
                        </Link>
                    </div>
                </div>
            </form>

            <!-- ── Summary Statistics Cards ──────────────────────── -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6 mb-6">
                <StatCard label="المدرسون" :value="summary.teachers || 0" icon="users" tone="primary" />
                <StatCard label="المجموعات" :value="summary.groups || 0" icon="courses" tone="accent" />
                <StatCard label="الطلاب المحجوزون" :value="summary.students || 0" icon="student" tone="green" />
                <StatCard label="كل الحصص" :value="summary.sessions || 0" icon="live" tone="blue" />
                <StatCard label="مباشرة الآن" :value="summary.live_sessions || 0" icon="live" tone="red" />
                <StatCard label="حصص منتهية" :value="summary.ended_sessions || 0" icon="success" tone="green" />
            </div>

            <!-- ────────────────────────────────────────────────── -->
            <!-- 1. Teachers Summary Section                        -->
            <!-- ────────────────────────────────────────────────── -->
            <section
                v-if="activeCategory === 'all' || activeCategory === 'teachers'"
                class="report-section mb-6"
            >
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-surface-100 dark:border-surface-800 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">👨‍🏫</span>
                        <h2 class="section-title">ملخص المدرسين</h2>
                    </div>
                    <span class="badge-primary text-xs">{{ teachers.length }} مدرس مسجل</span>
                </div>

                <div class="table-wrap">
                    <table class="report-table data-table">
                        <thead>
                            <tr>
                                <th>المدرس</th>
                                <th>المادة</th>
                                <th>الفيديو التعريفي</th>
                                <th>التكليفات الأكاديمية</th>
                                <th>المجموعات الدراسية</th>
                                <th>الطلاب الفعليون</th>
                                <th>الحصص المنعقدة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="teacher in (printMode ? teachers : paginatedTeachers)" :key="teacher.id">
                                <td class="font-bold text-surface-900 dark:text-white">{{ teacher.name }}</td>
                                <td>{{ teacher.subject?.name || '—' }}</td>
                                <td>
                                    <span v-if="teacher.intro_video_url" class="badge-green text-[10px]">
                                        🎬 متوفر
                                    </span>
                                    <span v-else class="text-surface-400 text-[10px]">
                                        بدون فيديو
                                    </span>
                                </td>
                                <td>{{ teacher.teaching_assignments_count || 0 }}</td>
                                <td>{{ teacher.groups_count || 0 }}</td>
                                <td>{{ teacher.students_count || 0 }} طالب</td>
                                <td>{{ teacher.sessions_count || 0 }} حصة</td>
                            </tr>
                            <tr v-if="!teachers.length">
                                <td colspan="7" class="empty-cell">لا توجد بيانات مطابقة لفلاتر المدرسين.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <DataTablePagination
                    v-if="activeCategory === 'teachers' || activeCategory === 'all'"
                    class="print-hidden"
                    :paginator="teachersPagination"
                    item-label="معلم"
                    @page-change="setTeachersPage"
                />
            </section>

            <!-- ────────────────────────────────────────────────── -->
            <!-- 2. Student Attendance Report Section               -->
            <!-- ────────────────────────────────────────────────── -->
            <section
                v-if="activeCategory === 'all' || activeCategory === 'attendance'"
                class="report-section mb-6"
            >
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-surface-100 dark:border-surface-800 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🎓</span>
                        <div>
                            <h2 class="section-title">تقرير حضور الطلاب</h2>
                            <p class="text-xs text-surface-400 mt-0.5">تسجيل دقيق لوقت الدخول والخروج الفعلي والمدة لكل حصة</p>
                        </div>
                    </div>
                    <span class="badge-green text-xs">{{ attendance.meta?.total ?? attendance.data?.length ?? 0 }} سجل حضور</span>
                </div>

                <div class="table-wrap">
                    <table class="report-table data-table">
                        <thead>
                            <tr>
                                <th>الطالب</th>
                                <th>المدرس</th>
                                <th>الحصة / المادة</th>
                                <th>نوع الحصة</th>
                                <th>موعد الحصة</th>
                                <th>وقت الدخول</th>
                                <th>وقت الخروج</th>
                                <th>مدة الحضور</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in attendance.data" :key="row.id">
                                <td>
                                    <div class="font-bold text-surface-900 dark:text-white">{{ row.student?.name || '—' }}</div>
                                    <div class="text-xs text-surface-400 font-mono">{{ row.student?.email || '' }}</div>
                                </td>
                                <td>{{ row.teacher?.name || '—' }}</td>
                                <td>
                                    <div class="font-semibold text-surface-800 dark:text-surface-200">{{ row.session || '—' }}</div>
                                    <div class="text-xs text-surface-400">{{ row.subject || '—' }}</div>
                                </td>
                                <td>
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold" :class="row.session?.includes('برايفيت') ? 'badge-accent' : 'badge-blue'">
                                        {{ row.session?.includes('برايفيت') ? 'مرافقة خاصة' : 'مجموعة' }}
                                    </span>
                                </td>
                                <td>{{ formatDate(row.scheduled_at) }}</td>
                                <td class="font-mono text-xs text-emerald-600 dark:text-emerald-400 font-semibold">{{ formatTime(row.joined_at) }}</td>
                                <td class="font-mono text-xs text-surface-500">{{ formatTime(row.left_at) }}</td>
                                <td class="font-bold text-surface-900 dark:text-white">{{ row.minutes || 0 }} دقيقة</td>
                            </tr>
                            <tr v-if="!attendance.data?.length">
                                <td colspan="8" class="empty-cell">لا توجد سجلات حضور مطابقة للفلاتر المحددة.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <DataTablePagination class="print-hidden" :paginator="attendance" item-label="سجل حضور" />
            </section>

            <!-- ────────────────────────────────────────────────── -->
            <!-- 3. Live Sessions Report (مرافقة - مجانية - مجموعات)  -->
            <!-- ────────────────────────────────────────────────── -->
            <section
                v-if="activeCategory === 'all' || activeCategory === 'sessions'"
                class="report-section mb-6"
            >
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-surface-100 dark:border-surface-800 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🎥</span>
                        <div>
                            <h2 class="section-title">تقرير الحصص المباشرة</h2>
                            <p class="text-xs text-surface-400 mt-0.5">تصنيف الحصص حسب النوع: مرافقة خاصة (فردية) — مجانية وتجريبية — مجموعات دراسية</p>
                        </div>
                    </div>
                    <span class="badge-blue text-xs">{{ sessions.meta?.total ?? sessions.data?.length ?? 0 }} حصة مباشرة</span>
                </div>

                <div class="table-wrap">
                    <table class="report-table data-table">
                        <thead>
                            <tr>
                                <th>عنوان الحصة</th>
                                <th>المدرس</th>
                                <th>نوع الحصة المباشرة</th>
                                <th>المادة / المجموعة</th>
                                <th>الموعد المحدد</th>
                                <th>عدد الحاضرين</th>
                                <th>حالة الحصة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="session in sessions.data" :key="session.id">
                                <td class="font-bold text-surface-900 dark:text-white">{{ session.title }}</td>
                                <td>{{ session.teacher?.name || '—' }}</td>
                                <td>
                                    <span class="px-2 py-0.5 rounded text-xs font-bold inline-block" :class="sessionTypeBadge(session)">
                                        {{ sessionTypeLabel(session) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="font-medium text-surface-800 dark:text-surface-200">
                                        {{ session.teaching_group?.assignment?.subject?.name || 'حصة خاصة / مرافقة' }}
                                    </div>
                                    <div class="text-xs text-surface-400">
                                        {{ session.teaching_group?.name || 'موعد تدريس فردي' }}
                                    </div>
                                </td>
                                <td>{{ formatDate(session.scheduled_at) }}</td>
                                <td class="font-bold text-center">{{ session.attendees_count || 0 }}</td>
                                <td>
                                    <span :class="statusClass(session.status)">{{ statusLabel(session.status) }}</span>
                                </td>
                            </tr>
                            <tr v-if="!sessions.data?.length">
                                <td colspan="7" class="empty-cell">لا توجد حصص مباشرة مطابقة للفلاتر المحددة.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <DataTablePagination class="print-hidden" :paginator="sessions" item-label="حصة" />
            </section>

            <!-- ────────────────────────────────────────────────── -->
            <!-- 4. Groups & Courses Report                          -->
            <!-- ────────────────────────────────────────────────── -->
            <section
                v-if="activeCategory === 'all' || activeCategory === 'groups'"
                class="report-section mb-6"
            >
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-surface-100 dark:border-surface-800 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">📚</span>
                        <div>
                            <h2 class="section-title">تقرير المجموعات والدورات</h2>
                            <p class="text-xs text-surface-400 mt-0.5">متابعة سعة المجموعات ونسب إشغال الطلاب والحصص المنعقدة لكل مجموعة</p>
                        </div>
                    </div>
                    <span class="badge-accent text-xs">{{ groups.length }} مجموعة دراسية</span>
                </div>

                <div class="table-wrap">
                    <table class="report-table data-table">
                        <thead>
                            <tr>
                                <th>المجموعة</th>
                                <th>المدرس</th>
                                <th>المادة والمرحلة</th>
                                <th>الفصل الأكاديمي</th>
                                <th>الحجوزات الفعالة</th>
                                <th>السعة القصوى</th>
                                <th>نسبة الإشغال</th>
                                <th>الحصص المنعقدة</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="group in (printMode ? groups : paginatedGroups)" :key="group.id">
                                <td class="font-bold text-surface-900 dark:text-white">{{ group.name }}</td>
                                <td>{{ group.assignment?.teacher?.name || '—' }}</td>
                                <td>
                                    <div>{{ group.assignment?.subject?.name || '—' }}</div>
                                    <div class="text-xs text-surface-400">{{ group.assignment?.grade_level?.name || '' }}</div>
                                </td>
                                <td>{{ group.term?.name || '—' }}</td>
                                <td class="font-bold text-primary-700 dark:text-primary-300">{{ group.active_bookings_count || 0 }} طالب</td>
                                <td>{{ group.capacity }}</td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <div class="w-16 bg-surface-200 dark:bg-surface-700 rounded-full h-2 overflow-hidden">
                                            <div
                                                class="bg-primary-600 h-full rounded-full"
                                                :style="{ width: `${Math.min(100, Math.round(((group.active_bookings_count || 0) / (group.capacity || 1)) * 100))}%` }"
                                            ></div>
                                        </div>
                                        <span class="text-xs font-semibold">
                                            {{ Math.round(((group.active_bookings_count || 0) / (group.capacity || 1)) * 100) }}%
                                        </span>
                                    </div>
                                </td>
                                <td>{{ group.live_sessions_count || 0 }}</td>
                                <td>
                                    <span :class="group.is_active ? 'badge-green' : 'badge-gray'">
                                        {{ group.is_active ? 'نشطة' : 'متوقفة' }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!groups.length">
                                <td colspan="9" class="empty-cell">لا توجد مجموعات مطابقة للفلاتر.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <DataTablePagination
                    v-if="activeCategory === 'groups' || activeCategory === 'all'"
                    class="print-hidden"
                    :paginator="groupsPagination"
                    item-label="مجموعة"
                    @page-change="setGroupsPage"
                />
            </section>
        </div>
    </DashboardLayout>
</template>

<style>
.report-section { @apply rounded-2xl border border-surface-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900; }
.section-title { @apply text-lg font-black text-surface-900 dark:text-white; }
.table-wrap {
    @apply overflow-x-auto;
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.table-wrap::-webkit-scrollbar { display: none; }
.report-table { @apply min-w-full text-right text-sm; }
.report-table th { @apply border-b border-surface-200 px-3 py-2.5 text-xs font-black text-surface-500 dark:border-surface-800; }
.report-table td { @apply border-b border-surface-100 px-3 py-2.5 text-surface-700 dark:border-surface-800 dark:text-surface-300; }
.report-table tbody tr:last-child td { @apply border-b-0; }
.empty-cell { @apply py-8 text-center text-surface-400; }
.print-only { display: none; }
@media (min-width: 1280px) {
    .table-wrap { overflow-x: clip; }
}
@media print {
    .print-hidden, aside, header, nav { display: none !important; }
    .print-only { display: block !important; }
    html, body, #app, .h-screen, main { height: auto !important; min-height: 0 !important; overflow: visible !important; }
    body, main { background: white !important; color: #111827 !important; }
    .dashboard-data-page { width: 100% !important; max-width: none !important; padding: 0 !important; }
    .report-print-header { margin-bottom: 20px; border-bottom: 2px solid #111827; padding-bottom: 12px; }
    .report-print-header h1 { font-size: 22px; font-weight: 900; margin: 0 0 6px; }
    .report-print-header p { margin: 3px 0; font-size: 11px; }
    .table-wrap { overflow: visible !important; }
    .report-section { break-inside: auto; page-break-inside: auto; box-shadow: none !important; border-color: #d1d5db !important; margin-bottom: 18px; }
    .report-section h2 { break-after: avoid; page-break-after: avoid; }
    .report-table thead { display: table-header-group; }
    .report-table tr { break-inside: avoid; page-break-inside: avoid; }
    .report-table td, .report-table th { color: #111827 !important; border-color: #d1d5db !important; }
}
</style>
