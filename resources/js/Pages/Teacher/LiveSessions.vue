<script setup>
import { useForm, router, Head } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { ref, computed } from 'vue';
import { useClientPagination } from '@/composables/useClientPagination';
import { formatTime12 } from '@/lib/money';
import axios from 'axios';

const props = defineProps({
    sessions: { type: Array, required: true },
    assignments: { type: Array, required: true },
    attendanceReport: { type: Array, default: () => [] },
});

const {
    data: paginatedSessions,
    pagination: sessionsPagination,
    setPage: setSessionsPage,
} = useClientPagination(computed(() => props.sessions));
const {
    data: paginatedAttendance,
    pagination: attendancePagination,
    setPage: setAttendancePage,
} = useClientPagination(computed(() => props.attendanceReport));

const form = useForm({
    title:        '',
    description:  '',
    is_free:      false,
    source_type:  'group',
    teaching_group_id: '',
    private_session_slot_id: '',
    scheduled_date: '',
});

const modalStep = ref(1);
const isModalOpen = ref(false);
const actionModal = ref(null);
const startingSessionId = ref(null);

const statusForm = useForm({ status: 'ended', recording_url: '', curriculum_unit_id: '' });
const editForm = useForm({ title: '', description: '' });
const attendanceForm = useForm({ student_ids: [] });
const apologyForm = useForm({ reason: '' });
const makeupForm = useForm({ scheduled_at: '' });

function getUnitsForSession(session) {
    const assignmentId = session?.teaching_group?.teaching_assignment_id
        || session?.private_session_slot?.teaching_assignment_id;
    if (!assignmentId) return [];
    const assignment = props.assignments.find(a => String(a.id) === String(assignmentId));
    return assignment?.units || [];
}

// A session hangs off a teaching assignment now — pick the subject first.
const selectedAssignmentId = ref('');
const matchingAssignments = computed(() => props.assignments.filter(
    assignment => String(assignment.id) === String(selectedAssignmentId.value),
));
const groupOptions = computed(() => matchingAssignments.value.flatMap(assignment => assignment.groups.map(group => ({ ...group, subject: assignment.subject?.name, grade: assignment.grade_level?.name }))));
const privateOptions = computed(() => matchingAssignments.value.flatMap(assignment => assignment.private_slots.map(slot => ({ ...slot, subject: assignment.subject?.name, grade: assignment.grade_level?.name }))));

const filteredPrivateOptions = computed(() => {
    return privateOptions.value.filter(slot => {
        if (form.is_free) {
            return Boolean(slot.is_free_intro);
        }
        return !slot.is_free_intro;
    });
});

const days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

function localDateTimeValue(date = new Date()) {
    const pad = (value) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function localDateValue(date = new Date()) {
    const pad = (value) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

const minMakeupDateTime = localDateTimeValue();
const minScheduledDate = localDateValue(new Date(Date.now() + 24 * 60 * 60 * 1000));

function openCreateModal() {
    form.reset();
    form.is_free = false;
    form.source_type = 'group';
    modalStep.value = 1;
    selectedAssignmentId.value = props.assignments[0]?.id ? String(props.assignments[0].id) : '';
    resetScheduleSelection();
    isModalOpen.value = true;
}

function isSessionReadyToStart(session) {
    if (session.status === 'live') return true;
    if (session.status !== 'scheduled' || isOverdueSession(session)) return false;
    const scheduledTime = new Date(session.scheduled_at).getTime();
    // Teacher can start session 15 minutes before scheduled start time or any time after
    return (scheduledTime - Date.now()) <= (15 * 60 * 1000);
}

async function startAndEnterSession(session) {
    startingSessionId.value = session.id;
    try {
        await axios.post(route('teacher.live-sessions.start', session.id));
        window.location.href = route('live-sessions.room', session.id);
    } catch (error) {
        console.error('Error starting live session', error);
        window.location.href = route('live-sessions.room', session.id);
    } finally {
        startingSessionId.value = null;
    }
}

function submit() {
    form.post(route('teacher.live-sessions.store'), {
        onSuccess: () => {
            form.reset();
            modalStep.value = 1;
            isModalOpen.value = false;
        }
    });
}

function resetScheduleSelection() {
    form.teaching_group_id = '';
    form.private_session_slot_id = '';
    form.scheduled_date = '';
}

function openEndModal(session) {
    statusForm.reset();
    statusForm.clearErrors();
    statusForm.status = 'ended';
    statusForm.recording_url = '';
    statusForm.curriculum_unit_id = '';
    actionModal.value = {
        type: 'end',
        session,
        sessionId: session.id,
    };
}

function openRecordingModal(session) {
    statusForm.reset();
    statusForm.clearErrors();
    statusForm.status = 'ended';
    statusForm.recording_url = session.recording_url || '';
    statusForm.curriculum_unit_id = '';
    actionModal.value = {
        type: 'recording',
        session,
        sessionId: session.id,
    };
}

function openEdit(session) {
    editForm.reset();
    editForm.clearErrors();
    editForm.title = session.title;
    editForm.description = session.description || '';
    actionModal.value = {
        type: 'edit',
        session,
    };
}

function submitEdit() {
    editForm.patch(route('teacher.live-sessions.update', actionModal.value.session.id), {
        preserveScroll: true,
        onSuccess: () => {
            actionModal.value = null;
            editForm.reset();
        },
    });
}

function updateStatus(id, newStatus) {
    if (newStatus === 'ended') {
        const session = props.sessions.find(s => s.id === id);
        if (session) {
            openEndModal(session);
            return;
        }
    }
    router.patch(route('teacher.live-sessions.status', id), {
        status: newStatus,
    });
}

function submitEndSession() {
    const sessionId = actionModal.value.sessionId || actionModal.value.session?.id;
    statusForm.patch(route('teacher.live-sessions.status', sessionId), {
        preserveScroll: true,
        onSuccess: () => { actionModal.value = null; statusForm.reset(); },
    });
}

function openAttendance(session) {
    attendanceForm.clearErrors();
    attendanceForm.student_ids = (session.attendance_students ?? [])
        .filter(student => student.present)
        .map(student => student.id);
    actionModal.value = { type: 'attendance', session };
}

function submitAttendance() {
    attendanceForm.post(route('teacher.live-sessions.attendance', actionModal.value.session.id), {
        preserveScroll: true,
        onSuccess: () => { actionModal.value = null; },
    });
}

function openApology(session) {
    apologyForm.reset();
    actionModal.value = { type: 'apology', session };
}

function submitApology() {
    apologyForm.post(route('teacher.live-sessions.apologize', actionModal.value.session.id), {
        preserveScroll: true,
        onSuccess: () => {
            actionModal.value = null;
            apologyForm.reset();
        },
    });
}

function openMakeup(session) {
    makeupForm.reset();
    actionModal.value = { type: 'makeup', session };
}

function submitMakeup() {
    makeupForm.post(route('teacher.session-apologies.makeup', actionModal.value.session.apology.id), {
        preserveScroll: true,
        onSuccess: () => {
            actionModal.value = null;
            makeupForm.reset();
        },
    });
}

const statusColors = {
    scheduled: 'badge-gray',
    live:      'badge-accent',
    ended:     'badge-primary',
    cancelled: 'badge-red',
};
const statusLabels = {
    scheduled: 'مجدولة',
    live:      'مباشر الآن',
    ended:     'منتهية',
    cancelled: 'معتذر عنها',
};

function isOverdueSession(session) {
    if (session.status !== 'scheduled' || !session.scheduled_at) return false;
    return new Date(session.scheduled_at).getTime() < Date.now() - (2 * 60 * 60 * 1000);
}

function getSessionStatusLabel(session) {
    if (session.status === 'cancelled') {
        if (session.apology?.reason?.includes('غياب بدون عذر')) {
            return 'ملغية (غياب)';
        }
        return 'معتذر عنها';
    }
    if (isOverdueSession(session)) {
        return 'ملغية (فات موعدها)';
    }
    return statusLabels[session.status] || session.status;
}

function getSessionStatusColor(session) {
    if (session.status === 'cancelled' || isOverdueSession(session)) {
        return 'badge-red';
    }
    return statusColors[session.status] || 'badge-gray';
}

function formatDate(value) {
    return value
        ? new Date(value).toLocaleString('ar-EG', { dateStyle: 'medium', timeStyle: 'short' })
        : '—';
}
</script>

<template>
    <DashboardLayout>
        <Head title="الحصص المباشرة" />

        <div class="dashboard-data-page">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <Icon name="live" class="w-8 h-8 text-primary-500" />
                        <span>الحصص المباشرة</span>
                    </h1>
                    <p class="text-surface-500 mt-1">جدولة وبدء حصص مباشرة آمنة عبر Jitsi داخل المنصة</p>
                </div>
                <button type="button" @click="openCreateModal" class="btn-primary flex items-center gap-2">
                    <span>+ جدولة حصة جديدة</span>
                </button>
            </div>

            <!-- List of Sessions -->
            <div class="card data-table-card">
                <div class="data-table-scroll no-scrollbar">
                    <table class="data-table">
                        <thead class="bg-surface-50 dark:bg-surface-800 border-b border-surface-200 dark:border-surface-700">
                            <tr>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">عنوان الحصة / المجموعة</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">الموعد</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">الحالة</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">الحضور</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">Jitsi/التسجيل</th>
                                <th class="data-table-actions text-start p-4 font-semibold text-surface-600 dark:text-surface-300">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-700">
                            <tr v-for="session in paginatedSessions" :key="session.id" class="hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors">
                                <td class="p-4">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-bold text-surface-900 dark:text-white text-base">{{ session.title }}</span>
                                        <span :class="session.is_free ? 'badge-green' : 'badge-primary'" class="text-[10px] font-bold">
                                            {{ session.is_free ? 'حصة مجانية' : 'حصة مدفوعة' }}
                                        </span>
                                        <span :class="session.teaching_group ? 'badge-accent' : 'badge-gray'" class="text-[10px] font-bold">
                                            {{ session.teaching_group ? 'مجموعة' : 'برايفت' }}
                                        </span>
                                    </div>
                                    <div v-if="session.teaching_group" class="text-xs text-primary-600 dark:text-primary-400 mt-1">
                                        مجموعة: {{ session.teaching_group.name }}
                                    </div>
                                    <div v-else-if="session.private_session_slot" class="text-xs text-accent-600 dark:text-accent-400 mt-1">
                                        {{ session.private_session_slot.is_free_intro ? 'جلسة تجريبية مجانية' : 'جلسة برايفيت محجوزة' }}
                                    </div>
                                    <div v-if="session.apology" class="mt-2 rounded-lg bg-red-500/10 p-2 text-[11px] text-red-600">
                                        سبب الاعتذار: {{ session.apology.reason }}
                                    </div>
                                </td>
                                <td class="p-4 text-surface-600 dark:text-surface-300 font-mono text-xs">
                                    {{ new Date(session.scheduled_at).toLocaleString('ar-EG', { dateStyle: 'medium', timeStyle: 'short' }) }}
                                </td>
                                <td class="p-4">
                                    <span :class="getSessionStatusColor(session)" class="text-xs">
                                        {{ getSessionStatusLabel(session) }}
                                    </span>
                                    <p v-if="session.apology?.status === 'makeup_scheduled'" class="mt-2 text-[11px] text-green-600">
                                        تم التعويض: {{ new Date(session.apology.makeup_scheduled_at).toLocaleString('ar-EG', { dateStyle: 'medium', timeStyle: 'short' }) }}
                                    </p>
                                    <p v-else-if="session.apology?.status === 'deducted'" class="mt-2 text-[11px] text-red-600">سجلت الإدارة خصمًا</p>
                                    <p v-else-if="session.apology" class="mt-2 text-[11px] text-accent-600">بانتظار التعويض أو قرار الإدارة</p>
                                </td>
                                <td class="p-4">
                                    <div class="text-sm font-bold text-surface-800 dark:text-surface-100">
                                        {{ session.attendees_count ?? 0 }} حاضر
                                    </div>
                                    <div class="text-[11px] text-surface-400 mt-1">
                                        {{ session.attendance_minutes ?? 0 }} دقيقة حضور
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div v-if="['scheduled', 'live'].includes(session.status) && !isOverdueSession(session)">
                                        <a :href="route('live-sessions.room', session.id)" target="_blank" rel="noopener noreferrer" class="text-primary-500 hover:underline text-xs block truncate max-w-[180px]">
                                            دخول غرفة Jitsi
                                        </a>
                                    </div>
                                    <div v-else-if="session.status === 'cancelled' || isOverdueSession(session)" class="text-xs text-red-400">
                                        الحصة ملغية (غير متاحة)
                                    </div>
                                    <div v-if="session.recording_url" class="text-accent-500 text-xs mt-1 flex items-center gap-1.5 flex-wrap">
                                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-accent-500"></span>
                                        <span>تم نشر التسجيل</span>
                                        <a :href="session.recording_url" target="_blank" rel="noopener noreferrer" class="font-semibold underline hover:text-accent-400 text-[11px]">معاينة التسجيل ↗</a>
                                    </div>
                                    <div v-else-if="session.status === 'ended'" class="text-xs text-surface-400 mt-1">
                                        لا يوجد تسجيل منشور
                                    </div>
                                </td>
                                <td class="data-table-actions p-3">
                                    <div class="flex max-w-[24rem] flex-wrap items-center gap-2">
                                        <!-- Ready to start / At session time -->
                                        <template v-if="session.status === 'scheduled' && !isOverdueSession(session)">
                                            <button
                                                v-if="isSessionReadyToStart(session)"
                                                type="button"
                                                @click="startAndEnterSession(session)"
                                                :disabled="startingSessionId === session.id"
                                                class="btn-sm bg-emerald-600 hover:bg-emerald-700 text-white font-bold flex items-center gap-1.5 shadow-sm shadow-emerald-500/20"
                                                title="بدء الحصة الآن ونقل الطلاب والغرفة إلى الحالة المباشرة"
                                            >
                                                <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>
                                                <span>{{ startingSessionId === session.id ? 'جاري التحويل...' : 'بدء الحصة ودخول الغرفة 🔴' }}</span>
                                            </button>
                                            <button
                                                v-else
                                                type="button"
                                                disabled
                                                class="btn-sm bg-surface-100 dark:bg-surface-800 text-surface-400 dark:text-surface-500 cursor-not-allowed flex items-center gap-1.5 border border-surface-200 dark:border-surface-700"
                                                :title="'يُفتح زر بدء الحصة في موعدها (' + formatDate(session.scheduled_at) + ')'"
                                            >
                                                <Icon name="clock" class="w-3.5 h-3.5 text-surface-400" />
                                                <span>يُفتح في موعد الحصة</span>
                                            </button>
                                        </template>

                                        <!-- Live now -->
                                        <a
                                            v-if="session.status === 'live'"
                                            :href="route('live-sessions.room', session.id)"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="btn-sm bg-red-600 hover:bg-red-700 text-white font-bold flex items-center gap-1.5 animate-pulse"
                                        >
                                            <span class="w-2 h-2 rounded-full bg-white"></span>
                                            <span>دخول الغرفة (مباشر الآن 🔴)</span>
                                        </a>

                                        <button type="button" v-if="session.status === 'scheduled' && !isOverdueSession(session)" @click="openEdit(session)" class="btn-sm btn-outline">تعديل</button>
                                        <button type="button" v-if="session.status === 'scheduled' && !isOverdueSession(session)" @click="openApology(session)" class="btn-sm btn-ghost text-red-500">تقديم اعتذار</button>
                                        <button type="button" v-if="session.status === 'live'" @click="openEndModal(session)" class="btn-sm bg-surface-200 text-surface-700 hover:bg-surface-300 dark:bg-surface-700 dark:text-surface-300">إنهاء</button>
                                        <button type="button" v-if="session.status === 'ended'" @click="openRecordingModal(session)" class="btn-sm" :class="session.recording_url ? 'btn-outline text-accent-500 border-accent-500/40' : 'btn-primary'">
                                            {{ session.recording_url ? 'تعديل التسجيل' : 'إضافة تسجيل' }}
                                        </button>
                                        <button type="button" v-if="['live', 'ended'].includes(session.status)" @click="openAttendance(session)" class="btn-sm btn-outline">تسجيل الحضور</button>
                                        <button type="button" v-if="session.apology?.status === 'pending'" @click="openMakeup(session)" class="btn-sm btn-primary">حدد حصة تعويضية</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="sessions.length === 0">
                                <td colspan="6" class="p-8 text-center text-surface-400">
                                    لا توجد حصص مجدولة حالياً. قم بجدولة أول حصة لك.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <DataTablePagination :paginator="sessionsPagination" item-label="حصة" @page-change="setSessionsPage" />
            </div>

            <div class="card data-table-card">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-surface-200 p-4 dark:border-surface-700">
                    <div>
                        <h2 class="text-lg font-black text-surface-900 dark:text-white">تقرير حضور الطلاب</h2>
                        <p class="mt-1 text-xs text-surface-500">الطلاب المشتركون معك — يتم احتساب الدخول والخروج تلقائيًا من غرفة الحصة.</p>
                    </div>
                    <span class="text-xs text-surface-400">{{ attendanceReport.length }} سجل</span>
                </div>
                <div class="data-table-scroll no-scrollbar">
                    <table class="data-table">
                        <thead class="bg-surface-50 dark:bg-surface-800">
                            <tr>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">الطالب</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">الحصة / المادة</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">موعد الحصة</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">دخل</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">خرج</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">المدة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-700">
                            <tr v-for="row in paginatedAttendance" :key="row.id">
                                <td class="p-4">
                                    <div class="font-bold text-surface-900 dark:text-white">{{ row.student?.name || '—' }}</div>
                                    <div class="text-xs text-surface-400">{{ row.student?.email || '' }}</div>
                                </td>
                                <td class="p-4">
                                    <div class="font-semibold">{{ row.session || '—' }}</div>
                                    <div class="text-xs text-surface-400">{{ row.subject || '—' }}</div>
                                </td>
                                <td class="p-4 text-xs">{{ formatDate(row.scheduled_at) }}</td>
                                <td class="p-4 text-xs">{{ formatDate(row.joined_at) }}</td>
                                <td class="p-4 text-xs">{{ formatDate(row.left_at) }}</td>
                                <td class="p-4">{{ row.minutes || 0 }} دقيقة</td>
                            </tr>
                            <tr v-if="!attendanceReport.length">
                                <td colspan="6" class="p-8 text-center text-surface-400">لا توجد سجلات حضور حتى الآن.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <DataTablePagination :paginator="attendancePagination" item-label="سجل حضور" @page-change="setAttendancePage" />
            </div>
        </div>

        <!-- Session action modal -->
        <div v-if="actionModal" class="modal-overlay z-[60]" dir="rtl" role="dialog" aria-modal="true" aria-label="إجراء على الحصة">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="actionModal = null"></div>
            <div class="relative modal-panel-compact w-full max-w-md rounded-2xl border border-surface-200 bg-white shadow-2xl dark:border-surface-700 dark:bg-surface-900 animate-fade-up">
                <form v-if="actionModal.type === 'end'" @submit.prevent="submitEndSession">
                    <div class="p-6">
                        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-accent-500/10 text-accent-600">
                            <Icon name="live" class="h-6 w-6" />
                        </div>
                        <h3 class="text-xl font-black text-surface-900 dark:text-white">إنهاء الحصة المباشرة</h3>
                        <p class="mt-2 text-sm leading-6 text-surface-500">عند تأكيد الإنهاء سيتم إغلاق الغرفة وتثبيت حضور الطلاب. إذا كان لديك رابط تسجيل (YouTube أو Jitsi)، يمكنك إدخاله الآن لنشره كدرس للطلاب تلقائياً، أو إضافته لاحقاً.</p>
                        
                        <div class="mt-5 space-y-4">
                            <div>
                                <label class="input-label">رابط التسجيل (اختياري - YouTube أو رابط Jitsi معتمد)</label>
                                <input v-model="statusForm.recording_url" type="url" class="input" placeholder="https://www.youtube.com/watch?v=..." />
                                <p v-if="statusForm.errors.recording_url" class="error-msg">{{ statusForm.errors.recording_url }}</p>
                            </div>

                            <div v-if="getUnitsForSession(actionModal.session).length">
                                <label class="input-label">الوحدة التعليمية للنشر (اختياري)</label>
                                <select v-model="statusForm.curriculum_unit_id" class="input">
                                    <option value="">-- الوحدة الأولى (افتراضي) --</option>
                                    <option v-for="unit in getUnitsForSession(actionModal.session)" :key="unit.id" :value="unit.id">
                                        {{ unit.title }}
                                    </option>
                                </select>
                                <p v-if="statusForm.errors.curriculum_unit_id" class="error-msg">{{ statusForm.errors.curriculum_unit_id }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-surface-200 bg-surface-50 p-4 dark:border-surface-800 dark:bg-surface-950">
                        <button type="button" class="btn-ghost" :disabled="statusForm.processing" @click="actionModal = null">إلغاء</button>
                        <button type="submit" class="btn-primary" :disabled="statusForm.processing">{{ statusForm.processing ? 'جاري الحفظ...' : 'تأكيد إنهاء الحصة' }}</button>
                    </div>
                </form>

                <form v-else-if="actionModal.type === 'recording'" @submit.prevent="submitEndSession">
                    <div class="p-6">
                        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-500/10 text-primary-600">
                            <Icon name="video" class="h-6 w-6" />
                        </div>
                        <h3 class="text-xl font-black text-surface-900 dark:text-white">
                            {{ actionModal.session.recording_url ? 'تعديل تسجيل الحصة' : 'إضافة تسجيل الحصة' }}
                        </h3>
                        <p class="mt-2 text-sm leading-6 text-surface-500">
                            انشر تسجيل الحصة كدرس محمي للطلاب المشتركين في المجموعة ليتمكنوا من مشاهدته في أي وقت.
                        </p>

                        <!-- Explainer Banner -->
                        <div class="mt-4 rounded-xl border border-accent-500/20 bg-accent-500/5 p-4 text-xs leading-relaxed text-surface-700 dark:text-surface-300">
                            <div class="font-bold text-accent-600 dark:text-accent-400 mb-1.5 flex items-center gap-1.5">
                                <span>💡</span>
                                <span>خطوات نشر التسجيل للطلاب:</span>
                            </div>
                            <ol class="list-decimal list-inside space-y-1 text-surface-600 dark:text-surface-400">
                                <li>إذا كنت سجلت الحصة، ستجد ملف الفيديو في مجلد التنزيلات (Downloads) على جهازك.</li>
                                <li>ارفع الفيديو على قناتك في <strong>YouTube</strong> بحالة <strong>غير مدرج (Unlisted)</strong> لضمان الخصوصية.</li>
                                <li>انسخ رابط الفيديو والصقه في الحقل أدناه ليظهر للطلاب حصرياً بدون إعلانات.</li>
                            </ol>
                        </div>

                        <div class="mt-5 space-y-4">
                            <div>
                                <label class="input-label">رابط الفيديو (YouTube)</label>
                                <input v-model="statusForm.recording_url" type="url" class="input" required placeholder="https://www.youtube.com/watch?v=..." />
                                <p v-if="statusForm.errors.recording_url" class="error-msg">{{ statusForm.errors.recording_url }}</p>
                            </div>

                            <div v-if="getUnitsForSession(actionModal.session).length">
                                <label class="input-label">الوحدة التعليمية للنشر في المنهج</label>
                                <select v-model="statusForm.curriculum_unit_id" class="input">
                                    <option value="">-- الوحدة الأولى (افتراضي) --</option>
                                    <option v-for="unit in getUnitsForSession(actionModal.session)" :key="unit.id" :value="unit.id">
                                        {{ unit.title }}
                                    </option>
                                </select>
                                <p v-if="statusForm.errors.curriculum_unit_id" class="error-msg">{{ statusForm.errors.curriculum_unit_id }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-surface-200 bg-surface-50 p-4 dark:border-surface-800 dark:bg-surface-950">
                        <button type="button" class="btn-ghost" :disabled="statusForm.processing" @click="actionModal = null">إلغاء</button>
                        <button type="submit" class="btn-primary" :disabled="statusForm.processing">{{ statusForm.processing ? 'جاري النشر...' : 'حفظ ونشر التسجيل' }}</button>
                    </div>
                </form>

                <form v-else-if="actionModal.type === 'edit'" @submit.prevent="submitEdit">
                    <div class="p-6">
                        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-500/10 text-primary-600">
                            <Icon name="edit" class="h-6 w-6" />
                        </div>
                        <h3 class="text-xl font-black text-surface-900 dark:text-white">تعديل بيانات الحصة المجدولة</h3>
                        <p class="mt-2 text-sm leading-6 text-surface-500">يمكنك تعديل عنوان الحصة ووصفها قبل موعد بدئها.</p>

                        <div class="mt-5 space-y-4">
                            <div>
                                <label class="input-label">عنوان الحصة</label>
                                <input v-model="editForm.title" type="text" minlength="3" maxlength="255" class="input" required />
                                <p v-if="editForm.errors.title" class="error-msg">{{ editForm.errors.title }}</p>
                            </div>

                            <div>
                                <label class="input-label">وصف الحصة (اختياري)</label>
                                <textarea v-model="editForm.description" rows="3" maxlength="2000" class="input resize-y"></textarea>
                                <p v-if="editForm.errors.description" class="error-msg">{{ editForm.errors.description }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-surface-200 bg-surface-50 p-4 dark:border-surface-800 dark:bg-surface-950">
                        <button type="button" class="btn-ghost" :disabled="editForm.processing" @click="actionModal = null">إلغاء</button>
                        <button type="submit" class="btn-primary" :disabled="editForm.processing">{{ editForm.processing ? 'جاري الحفظ...' : 'حفظ التعديلات' }}</button>
                    </div>
                </form>
                <form v-else-if="actionModal.type === 'attendance'" @submit.prevent="submitAttendance">
                    <div class="p-6">
                        <h3 class="text-xl font-black text-surface-900 dark:text-white">كشف حضور الحصة</h3>
                        <p class="mt-2 text-sm leading-6 text-surface-500">علّم على الطلاب الحاضرين ثم احفظ الكشف.</p>
                        <div class="mt-5 max-h-72 overflow-y-auto divide-y divide-surface-100 dark:divide-surface-800">
                            <label v-for="student in actionModal.session.attendance_students" :key="student.id" class="flex items-center gap-3 py-3 text-sm cursor-pointer">
                                <input v-model="attendanceForm.student_ids" type="checkbox" :value="student.id" class="rounded" />
                                <span class="font-semibold text-surface-800 dark:text-surface-100">{{ student.name }}</span>
                                <span class="text-xs text-surface-400" dir="ltr">{{ student.email }}</span>
                            </label>
                            <p v-if="!actionModal.session.attendance_students?.length" class="py-6 text-center text-sm text-surface-400">لا يوجد طلاب محجوزون في هذه الحصة.</p>
                            <p v-if="attendanceForm.errors.student_ids" class="error-msg">{{ attendanceForm.errors.student_ids }}</p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-surface-200 bg-surface-50 p-4 dark:border-surface-800 dark:bg-surface-950">
                        <button type="button" class="btn-ghost" @click="actionModal = null">إلغاء</button>
                        <button type="submit" class="btn-primary" :disabled="attendanceForm.processing">حفظ الحضور</button>
                    </div>
                </form>
                <form v-else-if="actionModal.type === 'apology'" @submit.prevent="submitApology">
                    <div class="p-6">
                        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-red-500/10 text-red-500"><Icon name="calendar" class="h-6 w-6" /></div>
                        <h3 class="text-xl font-black text-surface-900 dark:text-white">تقديم اعتذار عن الحصة</h3>
                        <p class="mt-2 text-sm leading-6 text-surface-500">
                            ستُلغى الحصة الأصلية ويصل الاعتذار للإدارة. بعد الإرسال يمكنك تحديد موعد تعويضي قبل تسجيل الخصم.
                        </p>
                        <div class="mt-5">
                            <label class="input-label">سبب الاعتذار</label>
                            <textarea v-model="apologyForm.reason" rows="4" minlength="10" maxlength="2000" class="input" required placeholder="اكتب سبب الاعتذار بوضوح"></textarea>
                            <p v-if="apologyForm.errors.reason" class="error-msg">{{ apologyForm.errors.reason }}</p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-surface-200 bg-surface-50 p-4 dark:border-surface-800 dark:bg-surface-950">
                        <button type="button" class="btn-ghost" @click="actionModal = null">إلغاء</button>
                        <button type="submit" class="rounded-xl bg-red-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-red-600" :disabled="apologyForm.processing">إرسال الاعتذار</button>
                    </div>
                </form>
                <form v-else-if="actionModal.type === 'makeup'" @submit.prevent="submitMakeup">
                    <div class="p-6">
                        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-green-500/10 text-green-600"><Icon name="success" class="h-6 w-6" /></div>
                        <h3 class="text-xl font-black text-surface-900 dark:text-white">تحديد الحصة التعويضية</h3>
                        <p class="mt-2 text-sm leading-6 text-surface-500">اختر موعدًا مستقبليًا مناسبًا. بعد الحفظ يُغلق الاعتذار كتعويض بدون خصم.</p>
                        <div class="mt-5">
                            <label class="input-label">موعد الحصة التعويضية</label>
                            <input v-model="makeupForm.scheduled_at" type="datetime-local" :min="minMakeupDateTime" class="input" required />
                            <p v-if="makeupForm.errors.scheduled_at" class="error-msg">{{ makeupForm.errors.scheduled_at }}</p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-surface-200 bg-surface-50 p-4 dark:border-surface-800 dark:bg-surface-950">
                        <button type="button" class="btn-ghost" @click="actionModal = null">إلغاء</button>
                        <button type="submit" class="btn-primary" :disabled="makeupForm.processing">حفظ الحصة التعويضية</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Create Modal with Step Wizard & Cards -->
        <div v-if="isModalOpen" class="modal-overlay z-50" role="dialog" aria-modal="true" aria-label="جدولة حصة مباشرة">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="isModalOpen = false"></div>
            <div class="relative modal-panel-compact bg-white dark:bg-surface-900 rounded-3xl shadow-2xl w-full max-w-2xl border border-surface-200 dark:border-surface-800 animate-fade-up overflow-hidden">
                <!-- Modal Top Header -->
                <div class="p-6 border-b border-surface-100 dark:border-surface-800 flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                            <span class="p-2 rounded-xl bg-primary-500/10 text-primary-600 dark:text-primary-400">
                                <Icon name="calendar" class="w-5 h-5" />
                            </span>
                            <span>جدولة حصة مباشرة جديدة</span>
                        </h3>
                        <p class="text-xs text-surface-500 mt-1">اتبع خطوات الكروت لتحديد نوع الحصة، الحضور، وتثبيت الموعد المعتمد</p>
                    </div>
                    <button type="button" @click="isModalOpen = false" class="p-2 rounded-xl text-surface-400 hover:text-surface-600 hover:bg-surface-100 dark:hover:bg-surface-800 transition">
                        ✕
                    </button>
                </div>

                <!-- Stepper Progress Bar -->
                <div class="px-6 pt-5 pb-2 bg-surface-50/50 dark:bg-surface-950/30 border-b border-surface-100 dark:border-surface-800">
                    <div class="grid grid-cols-3 gap-2">
                        <!-- Step 1 Tab -->
                        <button
                            type="button"
                            @click="modalStep > 1 && (modalStep = 1)"
                            class="flex items-center gap-2.5 p-2 rounded-xl text-start transition"
                            :class="modalStep === 1 ? 'bg-white dark:bg-surface-800 shadow-sm border border-surface-200 dark:border-surface-700' : (modalStep > 1 ? 'hover:bg-surface-100 dark:hover:bg-surface-800 cursor-pointer' : 'opacity-60 cursor-not-allowed')"
                        >
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
                                  :class="modalStep > 1 ? 'bg-emerald-500 text-white' : (modalStep === 1 ? 'bg-primary-600 text-white' : 'bg-surface-200 dark:bg-surface-700 text-surface-600')">
                                {{ modalStep > 1 ? '✓' : '1' }}
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-surface-800 dark:text-surface-100 truncate">تسعير الحصة</p>
                                <p class="text-[10px] text-surface-400 truncate">{{ form.is_free ? 'مجانية' : 'مدفوعة' }}</p>
                            </div>
                        </button>

                        <!-- Step 2 Tab -->
                        <button
                            type="button"
                            @click="modalStep > 2 && (modalStep = 2)"
                            class="flex items-center gap-2.5 p-2 rounded-xl text-start transition"
                            :class="modalStep === 2 ? 'bg-white dark:bg-surface-800 shadow-sm border border-surface-200 dark:border-surface-700' : (modalStep > 2 ? 'hover:bg-surface-100 dark:hover:bg-surface-800 cursor-pointer' : 'opacity-60 cursor-not-allowed')"
                        >
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
                                  :class="modalStep > 2 ? 'bg-emerald-500 text-white' : (modalStep === 2 ? 'bg-primary-600 text-white' : 'bg-surface-200 dark:bg-surface-700 text-surface-600')">
                                {{ modalStep > 2 ? '✓' : '2' }}
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-surface-800 dark:text-surface-100 truncate">نوع الحضور</p>
                                <p class="text-[10px] text-surface-400 truncate">{{ form.source_type === 'group' ? 'مجموعة' : 'برايفت' }}</p>
                            </div>
                        </button>

                        <!-- Step 3 Tab -->
                        <div
                            class="flex items-center gap-2.5 p-2 rounded-xl text-start"
                            :class="modalStep === 3 ? 'bg-white dark:bg-surface-800 shadow-sm border border-surface-200 dark:border-surface-700' : 'opacity-60'"
                        >
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
                                  :class="modalStep === 3 ? 'bg-primary-600 text-white' : 'bg-surface-200 dark:bg-surface-700 text-surface-600'">
                                3
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-surface-800 dark:text-surface-100 truncate">الموعد والتفاصيل</p>
                                <p class="text-[10px] text-surface-400 truncate">حسب الجدول</p>
                            </div>
                        </div>
                    </div>
                </div>

                <form @submit.prevent="submit">
                    <!-- STEP 1: FREE VS PAID CARDS -->
                    <div v-if="modalStep === 1" class="p-6 space-y-4">
                        <div class="mb-4">
                            <h4 class="text-base font-bold text-surface-900 dark:text-white">الخطوة 1: حدد تسعير الحصة (مجانية أم مدفوعة)</h4>
                            <p class="text-xs text-surface-500 mt-1">اختر ما إذا كانت الحصة مجانية وتجريبية أو مدفوعة للمشتركين</p>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-4">
                            <!-- Card Free -->
                            <div
                                @click="form.is_free = true; resetScheduleSelection()"
                                class="cursor-pointer relative p-5 rounded-2xl border-2 transition-all duration-200 flex flex-col justify-between"
                                :class="form.is_free === true ? 'border-emerald-500 bg-emerald-50/60 dark:bg-emerald-950/20 ring-2 ring-emerald-500/20 shadow-md' : 'border-surface-200 dark:border-surface-700 hover:border-emerald-300 dark:hover:border-emerald-800 bg-white dark:bg-surface-900'"
                            >
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-2xl">
                                        🎁
                                    </div>
                                    <span class="badge-green text-[10px] font-bold">مجانية للطلاب</span>
                                </div>
                                <div>
                                    <h5 class="text-base font-black text-surface-900 dark:text-white">حصة مجانية (Free / Intro)</h5>
                                    <p class="text-xs text-surface-500 dark:text-surface-400 mt-1 leading-5">
                                        جلسة تجريبية مفتوحة للتعريف بالمنهج، حصة مراجعة مجانية، أو جلسة تمهيدية مجانية لجذب طلاب جدد.
                                    </p>
                                </div>
                                <div class="mt-4 pt-3 border-t border-surface-100 dark:border-surface-800 flex items-center justify-between text-xs font-bold text-emerald-600 dark:text-emerald-400">
                                    <span>{{ form.is_free === true ? '✓ تم الاختيار' : 'اضغط للاختيار' }}</span>
                                    <span class="text-lg">←</span>
                                </div>
                            </div>

                            <!-- Card Paid -->
                            <div
                                @click="form.is_free = false; resetScheduleSelection()"
                                class="cursor-pointer relative p-5 rounded-2xl border-2 transition-all duration-200 flex flex-col justify-between"
                                :class="form.is_free === false ? 'border-primary-500 bg-primary-50/60 dark:bg-primary-950/20 ring-2 ring-primary-500/20 shadow-md' : 'border-surface-200 dark:border-surface-700 hover:border-primary-300 dark:hover:border-primary-800 bg-white dark:bg-surface-900'"
                            >
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <div class="w-12 h-12 rounded-2xl bg-primary-500/10 text-primary-600 dark:text-primary-400 flex items-center justify-center text-2xl">
                                        💎
                                    </div>
                                    <span class="badge-primary text-[10px] font-bold">للمشتركين فقط</span>
                                </div>
                                <div>
                                    <h5 class="text-base font-black text-surface-900 dark:text-white">حصة مدفوعة (Paid Class)</h5>
                                    <p class="text-xs text-surface-500 dark:text-surface-400 mt-1 leading-5">
                                        حصة منهجية مدفوعة مخصصة للطلاب المشتركين في المجموعة أو جلسة برايفيت مدفوعة تم تأكيد حجزها.
                                    </p>
                                </div>
                                <div class="mt-4 pt-3 border-t border-surface-100 dark:border-surface-800 flex items-center justify-between text-xs font-bold text-primary-600 dark:text-primary-400">
                                    <span>{{ form.is_free === false ? '✓ تم الاختيار' : 'اضغط للاختيار' }}</span>
                                    <span class="text-lg">←</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2: GROUP VS PRIVATE CARDS -->
                    <div v-else-if="modalStep === 2" class="p-6 space-y-4">
                        <div class="mb-4">
                            <h4 class="text-base font-bold text-surface-900 dark:text-white">الخطوة 2: حدد نوع الحضور (مجموعة ولا برايفيت)</h4>
                            <p class="text-xs text-surface-500 mt-1">اختر نمط انعقاد الحصة: جماعي لمجموعة أو فردي لطالب واحد</p>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-4">
                            <!-- Card Group -->
                            <div
                                @click="form.source_type = 'group'; resetScheduleSelection()"
                                class="cursor-pointer relative p-5 rounded-2xl border-2 transition-all duration-200 flex flex-col justify-between"
                                :class="form.source_type === 'group' ? 'border-primary-500 bg-primary-50/60 dark:bg-primary-950/20 ring-2 ring-primary-500/20 shadow-md' : 'border-surface-200 dark:border-surface-700 hover:border-primary-300 dark:hover:border-primary-800 bg-white dark:bg-surface-900'"
                            >
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <div class="w-12 h-12 rounded-2xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center text-2xl">
                                        👥
                                    </div>
                                    <span class="badge-primary text-[10px] font-bold">مجموعة دراسية</span>
                                </div>
                                <div>
                                    <h5 class="text-base font-black text-surface-900 dark:text-white">حصة مجموعة (Group)</h5>
                                    <p class="text-xs text-surface-500 dark:text-surface-400 mt-1 leading-5">
                                        حصة تفاعلية جماعية لطلاب المجموعة المسجلين وفق الجدول الأسبوعي للمجموعة.
                                    </p>
                                </div>
                                <div class="mt-4 pt-3 border-t border-surface-100 dark:border-surface-800 flex items-center justify-between text-xs font-bold text-primary-600 dark:text-primary-400">
                                    <span>{{ form.source_type === 'group' ? '✓ تم الاختيار' : 'اضغط للاختيار' }}</span>
                                    <span class="text-lg">←</span>
                                </div>
                            </div>

                            <!-- Card Private -->
                            <div
                                @click="form.source_type = 'private'; resetScheduleSelection()"
                                class="cursor-pointer relative p-5 rounded-2xl border-2 transition-all duration-200 flex flex-col justify-between"
                                :class="form.source_type === 'private' ? 'border-accent-500 bg-accent-50/60 dark:bg-accent-950/20 ring-2 ring-accent-500/20 shadow-md' : 'border-surface-200 dark:border-surface-700 hover:border-accent-300 dark:hover:border-accent-800 bg-white dark:bg-surface-900'"
                            >
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-2xl">
                                        👤
                                    </div>
                                    <span class="badge-accent text-[10px] font-bold">جلسة برايفيت 1:1</span>
                                </div>
                                <div>
                                    <h5 class="text-base font-black text-surface-900 dark:text-white">حصة خاصة (Private)</h5>
                                    <p class="text-xs text-surface-500 dark:text-surface-400 mt-1 leading-5">
                                        جلسة فردية مباشرة لطالب واحد يتم اختيارها من بين المواعيد المحجوزة في جدولك.
                                    </p>
                                </div>
                                <div class="mt-4 pt-3 border-t border-surface-100 dark:border-surface-800 flex items-center justify-between text-xs font-bold text-accent-600 dark:text-accent-400">
                                    <span>{{ form.source_type === 'private' ? '✓ تم الاختيار' : 'اضغط للاختيار' }}</span>
                                    <span class="text-lg">←</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 3: SCHEDULE TIMING & DETAILS -->
                    <div v-else-if="modalStep === 3" class="p-6 space-y-4 max-h-[68vh] overflow-y-auto">
                        <div class="mb-2 flex items-center justify-between">
                            <div>
                                <h4 class="text-base font-bold text-surface-900 dark:text-white">الخطوة 3: تحديد الموعد من المواعيد المسجلة</h4>
                                <p class="text-xs text-surface-500 mt-1">اختر الموعد المعتمد وبيانات الحصة</p>
                            </div>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <button type="button" @click="modalStep = 1" class="text-[11px] font-bold px-2 py-1 rounded-lg bg-surface-100 dark:bg-surface-800 text-surface-600 hover:bg-surface-200">
                                    {{ form.is_free ? '🎁 مجانية' : '💎 مدفوعة' }} ✎
                                </button>
                                <button type="button" @click="modalStep = 2" class="text-[11px] font-bold px-2 py-1 rounded-lg bg-surface-100 dark:bg-surface-800 text-surface-600 hover:bg-surface-200">
                                    {{ form.source_type === 'group' ? '👥 مجموعة' : '👤 برايفيت' }} ✎
                                </button>
                            </div>
                        </div>

                        <!-- Form fields -->
                        <div class="space-y-4 pt-2">
                            <div>
                                <label class="input-label">المادة والصف الدراسي</label>
                                <select v-model="selectedAssignmentId" class="input" required @change="resetScheduleSelection">
                                    <option value="" disabled>-- اختر المادة والصف --</option>
                                    <option v-for="assignment in assignments" :key="assignment.id" :value="assignment.id">
                                        {{ assignment.subject?.name }} — {{ assignment.grade_level?.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- IF GROUP -->
                            <div v-if="form.source_type === 'group'" class="space-y-4">
                                <div>
                                    <label class="input-label">المجموعة الدراسية المسجلة</label>
                                    <select v-model="form.teaching_group_id" class="input" required>
                                        <option value="" disabled>-- اختر المجموعة من جدولك --</option>
                                        <option v-for="group in groupOptions" :key="group.id" :value="group.id">
                                            {{ group.name }} — يوم {{ days[group.day_of_week] }} ({{ formatTime12(group.start_time) }} - {{ formatTime12(group.end_time) }})
                                        </option>
                                    </select>
                                    <p v-if="!groupOptions.length" class="text-xs text-red-500 mt-1">
                                        لا توجد مجموعات مسجلة لهذه المادة. أضف مجموعة من جدول التدريس أولاً.
                                    </p>
                                </div>

                                <div>
                                    <label class="input-label">تاريخ الحصة (يجب أن يوافق يوم المجموعة)</label>
                                    <input v-model="form.scheduled_date" type="date" :min="minScheduledDate" class="input" required />
                                    <p class="text-[11px] text-surface-500 mt-1">
                                        اختر التاريخ المناسب، وسيتم اعتماد وقت البداية والنهاية تلقائياً من مواعيد المجموعة المسجلة.
                                    </p>
                                </div>
                            </div>

                            <!-- IF PRIVATE -->
                            <div v-else class="space-y-4">
                                <div>
                                    <label class="input-label">الموعد المحجوز من جدول البرايفيت المسجل</label>
                                    <select v-model="form.private_session_slot_id" class="input" required>
                                        <option value="" disabled>-- اختر موعد الحصة الخاصة المحجوز --</option>
                                        <option v-for="slot in filteredPrivateOptions" :key="slot.id" :value="slot.id">
                                            {{ new Date(slot.starts_at).toLocaleString('ar-EG', { dateStyle: 'medium', timeStyle: 'short' }) }}
                                            — الطالب: {{ slot.booking?.student?.name || 'طالب محجوز' }}
                                        </option>
                                    </select>
                                    <p v-if="!filteredPrivateOptions.length" class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                                        لا توجد مواعيد برايفيت محجوزة مطابقة لهذا الاختيار ({{ form.is_free ? 'حصة تجريبية مجانية' : 'جلسة برايفيت مدفوعة' }}). تأكد من حجز الطالب للموعد أولاً.
                                    </p>
                                </div>
                            </div>

                            <div>
                                <label class="input-label">عنوان الحصة</label>
                                <input v-model="form.title" type="text" minlength="3" maxlength="255" class="input" placeholder="مثال: مراجعة الباب الأول وحل تدريبات عامة" required />
                            </div>

                            <div>
                                <label class="input-label">وصف الحصة وتعليمات الطلاب (اختياري)</label>
                                <textarea v-model="form.description" class="input resize-y" rows="2" maxlength="2000" placeholder="تعليمات للطلاب قبل موعد الحصة..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- FOOTER ACTIONS -->
                    <div class="p-4 bg-surface-50 dark:bg-surface-950 flex justify-between items-center border-t border-surface-200 dark:border-surface-800">
                        <div>
                            <button v-if="modalStep > 1" type="button" @click="modalStep--" class="btn-ghost text-xs">
                                → السابق
                            </button>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="isModalOpen = false" class="btn-ghost">
                                إلغاء
                            </button>
                            <button
                                v-if="modalStep < 3"
                                type="button"
                                @click="modalStep++"
                                class="btn-primary"
                            >
                                التالي ←
                            </button>
                            <button
                                v-else
                                type="submit"
                                :disabled="form.processing"
                                class="btn-primary"
                            >
                                {{ form.processing ? 'جاري الجدولة...' : 'تأكيد وجدولة الحصة ✅' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </DashboardLayout>
</template>

