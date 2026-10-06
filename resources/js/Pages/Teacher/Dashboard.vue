<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import StatCard from '@/Components/StatCard.vue';
import ScheduleModal from '@/Components/Teacher/ScheduleModal.vue';
import PrivateSlotModal from '@/Components/Teacher/PrivateSlotModal.vue';
import QuickContentModal from '@/Components/Teacher/QuickContentModal.vue';
import { formatTime12 } from '@/lib/money';

const props = defineProps({
    stats:             { type: Object, default: () => ({}) },
    assignments:       { type: Array,  default: () => [] },
    groups:            { type: Array,  default: () => [] },
    upcoming_sessions: { type: Array,  default: () => [] },
    terms:             { type: Array,  default: () => [] },
});

// Modals state
const activeScheduleGroup = ref(null);
const showScheduleModal = ref(false);

const activePrivateAssignment = ref(null);
const showPrivateModal = ref(false);

const activeContentAssignment = ref(null);
const showContentModal = ref(false);

const selectedGroupForStudents = ref(null);
const studentSearchQuery = ref('');

function openSchedule(group) {
    activeScheduleGroup.value = group;
    showScheduleModal.value = true;
}

function openPrivate(assignment) {
    activePrivateAssignment.value = assignment;
    showPrivateModal.value = true;
}

function openQuickContent(assignment) {
    activeContentAssignment.value = assignment;
    showContentModal.value = true;
}

function openStudents(group) {
    selectedGroupForStudents.value = group;
    studentSearchQuery.value = '';
}

function closeStudents() {
    selectedGroupForStudents.value = null;
    studentSearchQuery.value = '';
}

function autoScheduleGroup(groupId) {
    router.post(route('teacher.teaching-schedule.groups.auto-schedule', groupId), {}, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="لوحة المعلم" />

    <DashboardLayout>
        <div class="space-y-8 pb-12">
            <!-- ── Top Header ──────────────────────────────────────── -->
            <header class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white tracking-tight">
                        مرحباً، أستاذ {{ $page.props.auth.user?.name }} 👋
                    </h1>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1.5 leading-relaxed">
                        مساحتك لإدارة المواد الدراسية، مواعيد المجموعات والبرايفت، وجدولة الحصص ونشر المحتوى بكل سلاسة.
                    </p>
                </div>

                <div class="flex items-center gap-2.5">
                    <Link :href="route('teacher.live-sessions')" class="btn-primary btn-sm flex items-center gap-2 shadow-glow-primary">
                        <Icon name="live" class="w-4 h-4" />
                        <span>الحصص المباشرة</span>
                    </Link>
                    <Link :href="route('teacher.teaching-schedule')" class="btn-outline btn-sm flex items-center gap-2">
                        <Icon name="calendar" class="w-4 h-4" />
                        <span>جدول المواعيد الأسبوعي</span>
                    </Link>
                </div>
            </header>

            <!-- ── Stat Cards ──────────────────────────────────────── -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div
                    v-for="stat in [
                        { label: 'المواد والصفوف المسندة', value: stats.assignments,     icon: 'courses', color: 'primary' },
                        { label: 'مجموعات التدريس',        value: stats.total_groups,    icon: 'users',   color: 'accent' },
                        { label: 'الطلاب المشتركون',       value: stats.active_students, icon: 'student', color: 'primary' },
                        { label: 'الدروس والمواد المنشورة', value: stats.lessons,         icon: 'book',    color: 'accent' },
                    ]"
                    :key="stat.label"
                    class="card p-5 hover:shadow-lg transition-shadow duration-300"
                >
                    <div class="flex items-center gap-3.5">
                        <div :class="`p-3 rounded-2xl bg-${stat.color}-50 dark:bg-${stat.color}-950 text-${stat.color}-600 dark:text-${stat.color}-400`">
                            <Icon :name="stat.icon" class="w-6 h-6" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-2xl font-black text-surface-900 dark:text-white truncate">{{ stat.value ?? 0 }}</div>
                            <div class="text-xs text-surface-400 mt-0.5">{{ stat.label }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Upcoming Sessions Bar ────────────────────────────── -->
            <section v-if="upcoming_sessions.length" class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-extrabold text-sm text-surface-800 dark:text-surface-200 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-red-500 animate-ping"></span>
                        <span>أقرب الحصص المباشرة المجدولة:</span>
                    </h2>
                    <Link :href="route('teacher.live-sessions')" class="text-xs font-bold text-primary-600 hover:underline">
                        عرض جميع الحصص ←
                    </Link>
                </div>

                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                    <div
                        v-for="session in upcoming_sessions"
                        :key="session.id"
                        class="card p-4 flex items-center justify-between gap-3 border-l-4 border-l-primary-500 hover:border-primary-600 transition-all"
                    >
                        <div class="min-w-0">
                            <div class="text-xs font-bold text-surface-400">
                                {{ session.subject_name }} · {{ session.group_name }}
                            </div>
                            <h3 class="font-black text-sm text-surface-900 dark:text-white truncate mt-0.5">
                                {{ session.title }}
                            </h3>
                            <div class="text-xs font-semibold text-primary-600 dark:text-primary-400 mt-1 flex items-center gap-1.5">
                                <Icon name="clock" class="w-3.5 h-3.5" />
                                <span>{{ session.scheduled_at_formatted }}</span>
                            </div>
                        </div>

                        <a
                            :href="session.room_url"
                            class="btn-primary btn-sm shrink-0 flex items-center gap-1.5 shadow-sm"
                        >
                            <Icon name="live" class="w-3.5 h-3.5" />
                            <span>دخول</span>
                        </a>
                    </div>
                </div>
            </section>

            <!-- ── Main Academic Subjects & Groups Section ──────────── -->
            <section class="space-y-6">
                <div class="flex items-center justify-between border-b border-surface-200 dark:border-surface-800 pb-3">
                    <div>
                        <h2 class="text-xl font-black text-surface-900 dark:text-white">
                            موادك والمراحل الدراسية المسندة إليك
                        </h2>
                        <p class="text-xs text-surface-400 mt-0.5">
                            لكل مادة وصف دراسي: إدارة المجموعات، المواعيد الأسبوعية، الحصص الفردية والمحتوى
                        </p>
                    </div>
                </div>

                <div v-if="assignments.length" class="space-y-6">
                    <article
                        v-for="assignment in assignments"
                        :key="assignment.id"
                        class="card p-6 sm:p-7 space-y-6 border border-surface-200 dark:border-surface-800 hover:border-surface-300 dark:hover:border-surface-700 transition-colors shadow-sm"
                    >
                        <!-- Assignment Header (Stage, Subject, Grade) -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-surface-100 dark:border-surface-800/80 pb-5">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-primary-500 to-primary-700 text-white flex items-center justify-center font-black text-xl shadow-glow-primary shrink-0">
                                    {{ assignment.subject.name.charAt(0) }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-primary-500/10 text-primary-700 dark:text-primary-300 border border-primary-500/20">
                                            {{ assignment.stage.name }}
                                        </span>
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-surface-100 dark:bg-surface-800 text-surface-700 dark:text-surface-300">
                                            {{ assignment.grade.name }}
                                        </span>
                                    </div>
                                    <h3 class="text-2xl font-black text-surface-900 dark:text-white mt-1">
                                        {{ assignment.subject.name }}
                                    </h3>
                                </div>
                            </div>

                            <!-- Fast Action Toolbar -->
                            <div class="flex items-center gap-2 flex-wrap">
                                <button
                                    type="button"
                                    class="btn-primary btn-sm flex items-center gap-1.5 shadow-sm"
                                    @click="openQuickContent(assignment)"
                                    title="إضافة درس ومرفقاته فوراً"
                                >
                                    <Icon name="sparkles" class="w-3.5 h-3.5" />
                                    <span>إضافة محتوى سريع</span>
                                </button>

                                <button
                                    type="button"
                                    class="btn-outline btn-sm flex items-center gap-1.5"
                                    @click="openPrivate(assignment)"
                                    title="إضافة موعد برايفت أو تجريبي"
                                >
                                    <Icon name="clock" class="w-3.5 h-3.5 text-accent-500" />
                                    <span>موعد برايفيت</span>
                                </button>

                                <Link
                                    :href="route('teacher.curriculum', { assignment: assignment.id })"
                                    class="btn-ghost btn-sm flex items-center gap-1.5"
                                    title="عرض وتعديل المنهج بالكامل"
                                >
                                    <Icon name="book" class="w-3.5 h-3.5 text-surface-500" />
                                    <span>المنهج الكامل ({{ assignment.units_count }} وحدة)</span>
                                </Link>
                            </div>
                        </div>

                        <!-- Groups Section -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-black text-surface-800 dark:text-surface-200 flex items-center gap-2">
                                    <Icon name="courses" class="w-4 h-4 text-primary-500" />
                                    <span>المجموعات المسندة ({{ assignment.groups.length }} مجموعة)</span>
                                </h4>
                            </div>

                            <div v-if="assignment.groups.length" class="grid sm:grid-cols-2 lg:grid-cols-2 gap-4">
                                <div
                                    v-for="group in assignment.groups"
                                    :key="group.id"
                                    class="rounded-2xl p-5 border border-surface-200 dark:border-surface-800 bg-surface-50/60 dark:bg-surface-900/40 hover:bg-surface-50 dark:hover:bg-surface-900 transition-all space-y-4"
                                >
                                    <!-- Group Info -->
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h5 class="font-extrabold text-base text-surface-900 dark:text-white">
                                                {{ group.name }}
                                            </h5>
                                            <p class="text-xs text-surface-400 mt-0.5">
                                                سعة المجموعة: {{ group.capacity }} مقعد · المشتركون: {{ group.students_count }} طالب
                                            </p>
                                        </div>

                                        <button
                                            type="button"
                                            class="text-xs font-bold text-primary-600 hover:underline flex items-center gap-1 shrink-0"
                                            @click="openStudents(group)"
                                        >
                                            <Icon name="users" class="w-3.5 h-3.5" />
                                            <span>الطلاب ({{ group.students_count }})</span>
                                        </button>
                                    </div>

                                    <!-- Schedules Badge (12-hour system) -->
                                    <div class="p-3.5 rounded-xl bg-white dark:bg-surface-950/70 border border-surface-200 dark:border-surface-800/80 space-y-2">
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="font-bold text-surface-600 dark:text-surface-400">مواعيد الحصص:</span>
                                            <button
                                                type="button"
                                                class="text-xs font-bold text-primary-600 hover:underline flex items-center gap-1"
                                                @click="openSchedule(group)"
                                            >
                                                <span>{{ group.schedules.length ? 'تعديل المواعيد ✎' : '+ تحديد المواعيد' }}</span>
                                            </button>
                                        </div>

                                        <div v-if="group.schedules.length" class="flex flex-wrap gap-1.5">
                                            <span
                                                v-for="s in group.schedules"
                                                :key="s.id"
                                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-primary-500/10 text-primary-800 dark:text-primary-200 text-xs font-bold border border-primary-500/20"
                                            >
                                                <Icon name="clock" class="w-3 h-3 text-primary-600" />
                                                <span>{{ s.day_name }} · {{ s.start_time_12 }} إلى {{ s.end_time_12 }}</span>
                                            </span>
                                        </div>
                                        <p v-else class="text-xs text-amber-600 dark:text-amber-400 font-semibold">
                                            ⚠️ لم يتم ضبط مواعيد أسبوعية للمجموعة بعد.
                                        </p>
                                    </div>

                                    <!-- Group Lessons & Auto-Schedule status -->
                                    <div class="flex items-center justify-between gap-3 text-xs pt-1 border-t border-surface-100 dark:border-surface-800">
                                        <div class="text-surface-500 font-medium">
                                            {{ group.lessons_count }} حصة في الخطة
                                            <span v-if="group.pending_lessons_count > 0" class="text-amber-600 font-bold">
                                                ({{ group.pending_lessons_count }} بحاجة لجدولة)
                                            </span>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <button
                                                v-if="group.pending_lessons_count > 0 && group.schedules.length"
                                                type="button"
                                                class="btn-accent btn-sm text-[11px] font-black"
                                                @click="autoScheduleGroup(group.id)"
                                                title="توزيع الحصص المتبقية تلقائياً على المواعيد"
                                            >
                                                ⚡ جدولة الحصص تلقائياً
                                            </button>
                                            <Link
                                                :href="route('teacher.teaching-schedule')"
                                                class="btn-ghost btn-sm text-[11px]"
                                            >
                                                الخطة
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="text-center py-6 border border-dashed border-surface-200 dark:border-surface-800 rounded-2xl">
                                <Icon name="courses" class="w-8 h-8 text-surface-300 mx-auto mb-2" />
                                <p class="text-xs text-surface-400">لم تنشئ الإدارة مجموعات لهذا الإسناد بعد.</p>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-else class="card p-12 text-center space-y-3">
                    <Icon name="courses" class="w-12 h-12 text-surface-300 mx-auto" />
                    <h3 class="text-lg font-bold text-surface-800 dark:text-white">لا توجد مواد مسندة إليك حالياً</h3>
                    <p class="text-xs text-surface-400 max-w-md mx-auto">
                        تتولى إدارة المنصة إسناد المواد والصفوف الدراسية إليك. بمجرد الإسناد ستظهر هنا فوراً.
                    </p>
                </div>
            </section>
        </div>

        <!-- ── Integrated Modals ────────────────────────────────────── -->
        <!-- 1. Schedule Modal -->
        <ScheduleModal
            v-model="showScheduleModal"
            :group="activeScheduleGroup"
            @saved="router.reload()"
        />

        <!-- 2. Private Slot Modal -->
        <PrivateSlotModal
            v-model="showPrivateModal"
            :assignment="activePrivateAssignment"
            @saved="router.reload()"
        />

        <!-- 3. Quick Content Modal -->
        <QuickContentModal
            v-model="showContentModal"
            :assignment="activeContentAssignment"
            :terms="terms"
            @saved="router.reload()"
        />

        <!-- 4. Students Modal -->
        <div
            v-if="selectedGroupForStudents"
            class="modal-overlay z-[80] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            @click.self="closeStudents"
        >
            <div class="card w-full max-w-2xl max-h-[85vh] flex flex-col p-6 space-y-4 shadow-2xl">
                <div class="flex items-start justify-between gap-3 border-b border-surface-200 dark:border-surface-700 pb-4">
                    <div>
                        <h2 class="text-xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                            <Icon name="users" class="w-6 h-6 text-primary-500" />
                            <span>طلاب مجموعة: {{ selectedGroupForStudents.name }}</span>
                        </h2>
                        <p class="text-xs text-surface-500 mt-1">
                            {{ selectedGroupForStudents.students?.length ?? 0 }} طالب مشترك · سعة المجموعة {{ selectedGroupForStudents.capacity }} مقعد
                        </p>
                    </div>
                    <button type="button" class="text-surface-400 hover:text-surface-600 text-lg font-bold" @click="closeStudents">✕</button>
                </div>

                <div v-if="selectedGroupForStudents.students?.length" class="space-y-2">
                    <input
                        v-model="studentSearchQuery"
                        type="text"
                        class="input text-xs"
                        placeholder="ابحث بالاسم أو البريد الإلكتروني..."
                    />
                </div>

                <div class="overflow-y-auto no-scrollbar flex-1 space-y-2.5 py-1">
                    <div
                        v-for="(student, sIdx) in (selectedGroupForStudents.students || []).filter(s => !studentSearchQuery || s.name.toLowerCase().includes(studentSearchQuery.toLowerCase()) || s.email.toLowerCase().includes(studentSearchQuery.toLowerCase()))"
                        :key="student.id"
                        class="flex items-center justify-between gap-3 p-3.5 rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-900/60 hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                    >
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="text-xs font-mono text-surface-400 w-5">{{ sIdx + 1 }}.</span>
                            <div class="w-10 h-10 rounded-full bg-primary-500/10 text-primary-600 flex items-center justify-center font-bold text-sm overflow-hidden shrink-0">
                                <img v-if="student.avatar" :src="student.avatar" :alt="student.name" class="w-full h-full object-cover" />
                                <span v-else>{{ student.name?.charAt(0) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="font-bold text-sm text-surface-900 dark:text-white truncate">{{ student.name }}</p>
                                <p class="text-xs text-surface-500 dark:text-surface-400 font-mono truncate">{{ student.email }}</p>
                            </div>
                        </div>
                        <span class="badge-green text-xs shrink-0">مشترك</span>
                    </div>

                    <div v-if="!selectedGroupForStudents.students?.length" class="text-center py-8 text-xs text-surface-400">
                        لا يوجد طلاب مشتركون في هذه المجموعة حتى الآن.
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
