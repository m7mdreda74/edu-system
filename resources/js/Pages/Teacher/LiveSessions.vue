<script setup>
import { useForm, router, Head } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { ref, computed } from 'vue';
import { useClientPagination } from '@/composables/useClientPagination';

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
    source_type: 'group',
    teaching_group_id: '',
    private_session_slot_id: '',
    scheduled_date: '',
});

const isModalOpen = ref(false);
const actionModal = ref(null);
const statusForm = useForm({ status: 'ended' });
const attendanceForm = useForm({ student_ids: [] });
const apologyForm = useForm({ reason: '' });
const makeupForm = useForm({ scheduled_at: '' });

// A session hangs off a teaching assignment now — pick the subject first.
const selectedAssignmentId = ref('');
const matchingAssignments = computed(() => props.assignments.filter(
    assignment => String(assignment.id) === String(selectedAssignmentId.value),
));
const groupOptions = computed(() => matchingAssignments.value.flatMap(assignment => assignment.groups.map(group => ({ ...group, subject: assignment.subject?.name, grade: assignment.grade_level?.name }))));
const privateOptions = computed(() => matchingAssignments.value.flatMap(assignment => assignment.private_slots.map(slot => ({ ...slot, subject: assignment.subject?.name, grade: assignment.grade_level?.name }))));

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

function submit() {
    form.post(route('teacher.live-sessions.store'), {
        onSuccess: () => {
            form.reset();
            isModalOpen.value = false;
        }
    });
}

function resetScheduleSelection() {
    form.teaching_group_id = '';
    form.private_session_slot_id = '';
    form.scheduled_date = '';
}

function updateStatus(id, newStatus) {
    if (newStatus === 'ended') {
        statusForm.reset();
        statusForm.status = 'ended';
        actionModal.value = {
            type: 'end',
            sessionId: id,
        };
        return;
    }
    router.patch(route('teacher.live-sessions.status', id), {
        status: newStatus,
    });
}

function submitEndSession() {
    statusForm.patch(route('teacher.live-sessions.status', actionModal.value.sessionId), {
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
                <button type="button" @click="isModalOpen = true" class="btn-primary">
                    + جدولة حصة جديدة
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
                                    <div class="font-bold text-surface-900 dark:text-white text-base">{{ session.title }}</div>
                                    <div class="text-xs text-surface-500 mt-1">{{ session.teaching_group?.name || 'حصة خاصة' }}</div>
                                    <div v-if="session.teaching_group" class="text-[11px] text-primary-500 mt-1">مجموعة: {{ session.teaching_group.name }}</div>
                                    <div v-else-if="session.private_session_slot" class="text-[11px] text-accent-500 mt-1">
                                        {{ session.private_session_slot.is_free_intro ? 'حصة تجريبية مجانية محجوزة' : 'جلسة برايفيت محجوزة' }}
                                    </div>
                                    <div v-if="session.apology" class="mt-2 rounded-lg bg-red-500/10 p-2 text-[11px] text-red-600">
                                        سبب الاعتذار: {{ session.apology.reason }}
                                    </div>
                                </td>
                                <td class="p-4 text-surface-600 dark:text-surface-300 font-mono text-xs">
                                    {{ new Date(session.scheduled_at).toLocaleString('ar-EG', { dateStyle: 'medium', timeStyle: 'short' }) }}
                                </td>
                                <td class="p-4">
                                    <span :class="statusColors[session.status]" class="text-xs">
                                        {{ statusLabels[session.status] }}
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
                                    <div v-if="['scheduled', 'live'].includes(session.status)">
                                        <a :href="route('live-sessions.room', session.id)" target="_blank" rel="noopener noreferrer" class="text-primary-500 hover:underline text-xs block truncate max-w-[180px]">
                                            دخول غرفة Jitsi
                                        </a>
                                    </div>
                                    <div v-if="session.recording_url" class="text-accent-500 text-xs">
                                        تم نشر التسجيل داخل المنصة
                                    </div>
                                </td>
                                <td class="data-table-actions p-3">
                                    <div class="flex max-w-[22rem] flex-wrap items-center gap-2">
                                        <a v-if="session.status === 'scheduled'" :href="route('live-sessions.room', session.id)" target="_blank" rel="noopener noreferrer" class="btn-sm bg-accent-50 text-accent-600 hover:bg-accent-100 dark:bg-accent-900/30 dark:hover:bg-accent-900/50">دخول وبدء الحصة</a>
                                        <button type="button" v-if="session.status === 'scheduled'" @click="openApology(session)" class="btn-sm btn-ghost text-red-500">تقديم اعتذار</button>
                                        <button type="button" v-if="session.status === 'live'" @click="updateStatus(session.id, 'ended')" class="btn-sm bg-surface-200 text-surface-700 hover:bg-surface-300 dark:bg-surface-700 dark:text-surface-300">إنهاء</button>
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
                        <p class="mt-2 text-sm leading-6 text-surface-500">يتم تسجيل الحصة على خادم Jitsi تلقائيًا، وبعد الإنهاء يُحفظ التسجيل ويظهر للطلاب داخل المنصة. استخدم غرفة Jitsi لإيقاف التسجيل وإنهاء الحصة.</p>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-surface-200 bg-surface-50 p-4 dark:border-surface-800 dark:bg-surface-950">
                        <button type="button" class="btn-ghost" :disabled="statusForm.processing" @click="actionModal = null">إلغاء</button>
                        <button type="submit" class="btn-primary" :disabled="statusForm.processing">{{ statusForm.processing ? 'جاري الحفظ...' : 'تأكيد إنهاء الحصة' }}</button>
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

        <!-- Create Modal -->
        <div v-if="isModalOpen" class="modal-overlay z-50" role="dialog" aria-modal="true" aria-label="إنشاء حصة مباشرة">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="isModalOpen = false"></div>
            <div class="relative modal-panel-compact bg-white dark:bg-surface-900 rounded-2xl shadow-xl w-full max-w-lg animate-fade-up">
                <form @submit.prevent="submit">
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-surface-900 dark:text-white mb-6">جدولة حصة مباشرة</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="input-label">اختر المادة والصف</label>
                                <select v-model="selectedAssignmentId" class="input" required @change="resetScheduleSelection">
                                    <option value="" disabled>-- المادة --</option>
                                    <option v-for="assignment in assignments" :key="assignment.id" :value="assignment.id">
                                        {{ assignment.subject?.name }} — {{ assignment.grade_level?.name }}
                                    </option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="input-label">عنوان الحصة</label>
                                <input v-model="form.title" type="text" minlength="3" maxlength="255" class="input" placeholder="مثال: مراجعة الوحدة الأولى" required>
                            </div>

                            <div>
                                <label class="input-label">نوع الحجز والموعد</label>
                                <select v-model="form.source_type" class="input" @change="resetScheduleSelection" required>
                                    <option value="group">حصة مجموعة</option>
                                    <option value="private">حصة برايفيت</option>
                                </select>
                            </div>

                            <div>
                                <label class="input-label">{{ form.source_type === 'group' ? 'اختر المجموعة' : 'اختر موعد البرايفيت المحجوز' }}</label>
                                <select v-if="form.source_type === 'group'" v-model="form.teaching_group_id" class="input" required>
                                    <option value="" disabled>-- المجموعة --</option>
                                    <option v-for="group in groupOptions" :key="group.id" :value="group.id">
                                        {{ group.name }} — {{ days[group.day_of_week] }} — {{ group.start_time }} إلى {{ group.end_time }}
                                    </option>
                                </select>
                                <select v-else v-model="form.private_session_slot_id" class="input" required>
                                    <option value="" disabled>-- الموعد المحجوز --</option>
                                    <option v-for="slot in privateOptions" :key="slot.id" :value="slot.id">
                                        {{ new Date(slot.starts_at).toLocaleString('ar-EG', { dateStyle: 'medium', timeStyle: 'short' }) }}
                                    </option>
                                </select>
                                <p v-if="!matchingAssignments.length" class="text-xs text-red-500 mt-1">لا يوجد جدول لهذه المادة. أنشئ ربط المادة والصف من جدول التدريس أولاً.</p>
                            </div>

                            <div v-if="form.source_type === 'group'">
                                <label class="input-label">تاريخ تنفيذ حصة المجموعة</label>
                                <input v-model="form.scheduled_date" type="date" :min="minScheduledDate" class="input" required>
                                <p class="text-[11px] text-surface-400 mt-1">اختار نفس يوم المجموعة، والساعة هتتحدد تلقائيًا من الجدول.</p>
                            </div>

                            <div>
                                <label class="input-label">وصف إضافي (اختياري)</label>
                                <textarea v-model="form.description" class="input resize-y" rows="2" maxlength="2000" placeholder="معلومات للطلاب قبل بدء الحصة..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="p-4 bg-surface-50 dark:bg-surface-950 flex justify-end gap-3 border-t border-surface-200 dark:border-surface-800">
                        <button type="button" @click="isModalOpen = false" class="btn-ghost">إلغاء</button>
                        <button type="submit" :disabled="form.processing" class="btn-primary">
                            {{ form.processing ? 'حفظ...' : 'جدولة الحصة' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </DashboardLayout>
</template>

