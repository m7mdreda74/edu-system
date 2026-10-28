<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import ScheduleModal from '@/Components/Teacher/ScheduleModal.vue';
import { formatTime12 } from '@/lib/money';

const props = defineProps({
    assignments: { type: Array, default: () => [] },
});

const days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
const lessonDrafts = ref({});
const scheduleDrafts = ref({});
const freeSlotDrafts = ref({});
const privateSlotDrafts = ref({});
const selectedGroupForStudents = ref(null);
const studentSearchQuery = ref('');
const showScheduleModal = ref(false);
const activeGroupForSchedule = ref(null);

function openScheduleModal(group) {
    activeGroupForSchedule.value = group;
    showScheduleModal.value = true;
}

function autoScheduleAll(groupId) {
    router.post(route('teacher.teaching-schedule.groups.auto-schedule', groupId), {}, {
        preserveScroll: true,
    });
}

function openStudentsModal(group) {
    selectedGroupForStudents.value = group;
    studentSearchQuery.value = '';
}

function closeStudentsModal() {
    selectedGroupForStudents.value = null;
    studentSearchQuery.value = '';
}

function localDateTimeValue(date = new Date()) {
    const pad = (value) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

const minSlotDateTime = localDateTimeValue();

const privateGroupDrafts = ref({});

watch(() => props.assignments, (assignments) => {
    assignments.flatMap((item) => item.groups || []).forEach((group) => {
        lessonDrafts.value[group.id] ??= { title: '', description: '' };
        scheduleDrafts.value[group.id] ??= { day_of_week: 0, start_time: '', end_time: '' };
    });
    assignments.forEach((assignment) => {
        freeSlotDrafts.value[assignment.id] ??= { starts_at: '', ends_at: '' };
        privateSlotDrafts.value[assignment.id] ??= { starts_at: '', ends_at: '' };
        privateGroupDrafts.value[assignment.id] ??= { days: [6, 2], start_time: '18:00', duration_minutes: 60 };
    });
}, { immediate: true });

function togglePrivateDay(assignmentId, dayVal) {
    if (!privateGroupDrafts.value[assignmentId]) {
        privateGroupDrafts.value[assignmentId] = { days: [6, 2], start_time: '18:00', duration_minutes: 60 };
    }
    const daysArr = privateGroupDrafts.value[assignmentId].days;
    const idx = daysArr.indexOf(dayVal);
    if (idx > -1) {
        if (daysArr.length > 2) {
            daysArr.splice(idx, 1);
        }
    } else {
        daysArr.push(dayVal);
    }
}

function publishPrivateGroup(assignmentId) {
    const draft = privateGroupDrafts.value[assignmentId];
    if (!draft || draft.days.length < 2 || !draft.start_time) {
        alert('يرجى اختيار يومين للحصة الأسبوعية ووقت البدء.');
        return;
    }

    router.post(
        route('teacher.teaching-schedule.private-groups.store', assignmentId),
        {
            teaching_assignment_id: assignmentId,
            days: draft.days,
            start_time: draft.start_time,
            duration_minutes: draft.duration_minutes || 60,
            timezone: 'Asia/Qatar',
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                privateGroupDrafts.value[assignmentId] = { days: [6, 2], start_time: '18:00', duration_minutes: 60 };
            },
        }
    );
}

function removePrivateGroup(groupId) {
    if (!confirm('هل أنت متأكد من حذف موعد البرايفيت هذا؟')) return;
    router.delete(route('teacher.teaching-schedule.private-groups.destroy', groupId), {
        preserveScroll: true,
    });
}

function formatGroupSchedules(group) {
    if (!group?.schedules?.length) return 'الموعد بالتنسيق';
    return group.schedules.map(s => `${days[s.day_of_week]} (${formatTime12(s.start_time)} إلى ${formatTime12(s.end_time)})`).join(' و ');
}

function addLesson(groupId) {
    const draft = lessonDrafts.value[groupId] || {};
    if (!draft.title?.trim()) return;

    router.post(route('teacher.teaching-schedule.groups.lessons.store', groupId), draft, {
        preserveScroll: true,
        onSuccess: () => {
            lessonDrafts.value[groupId] = { title: '', description: '' };
        },
    });
}

function scheduleLesson(id) {
    router.post(route('teacher.teaching-schedule.group-lessons.schedule', id), {}, {
        preserveScroll: true,
        replace: true,
    });
}

function addGroupSchedule(groupId) {
    router.post(
        route('teacher.teaching-schedule.groups.schedules.store', groupId),
        scheduleDrafts.value[groupId],
        {
            preserveScroll: true,
            onSuccess: () => {
                scheduleDrafts.value[groupId] = { day_of_week: 0, start_time: '', end_time: '' };
            },
        },
    );
}

function removeGroupSchedule(scheduleId) {
    router.delete(route('teacher.teaching-schedule.group-schedules.destroy', scheduleId), {
        preserveScroll: true,
    });
}

function publishFreeSlot(assignmentId) {
    const draft = freeSlotDrafts.value[assignmentId];

    router.post(route('teacher.free-intro-sessions.store'), {
        teaching_assignment_id: assignmentId,
        starts_at: draft.starts_at,
        ends_at: draft.ends_at,
        timezone: 'Asia/Qatar',
    }, {
        preserveScroll: true,
        onSuccess: () => {
            freeSlotDrafts.value[assignmentId] = { starts_at: '', ends_at: '' };
        },
    });
}

function cancelFreeSlot(slotId) {
    router.delete(route('teacher.free-intro-sessions.destroy', slotId), { preserveScroll: true });
}

function publishPrivateSlot(assignmentId) {
    const draft = privateSlotDrafts.value[assignmentId];

    router.post(route('teacher.private-slots.store'), {
        teaching_assignment_id: assignmentId,
        starts_at: draft.starts_at,
        ends_at: draft.ends_at,
        timezone: 'Asia/Qatar',
    }, {
        preserveScroll: true,
        onSuccess: () => {
            privateSlotDrafts.value[assignmentId] = { starts_at: '', ends_at: '' };
        },
    });
}

function cancelPrivateSlot(slotId) {
    router.delete(route('teacher.private-slots.destroy', slotId), { preserveScroll: true });
}

function formatDate(value, timeZone = 'Asia/Qatar') {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString('ar-QA', {
            timeZone: timeZone || 'Asia/Qatar',
            dateStyle: 'medium',
            timeStyle: 'short',
        });
    } catch {
        return new Date(value).toLocaleString('ar-EG', {
            dateStyle: 'medium',
            timeStyle: 'short',
        });
    }
}
</script>

<template>
    <DashboardLayout>
        <Head title="تحديد المواعيد وجدول الحصص" />

        <div class="container-app px-4 py-8 space-y-7">
            <header>
                <h1 class="text-3xl font-black text-surface-900 dark:text-white">تحديد المواعيد وجدول الحصص</h1>
                <p class="text-surface-500 mt-2">
                    حدد مواعيد مجموعاتك الأسبوعية، مواعيد الحصص الخاصة (البرايفت)، والحصص المجانية. لبناء الخطة الأكاديمية والمناهج، انتقل إلى تبويب الخطة الأكاديمية.
                </p>
            </header>

            <div class="rounded-2xl border border-accent-500/25 bg-accent-500/10 p-4 flex items-start gap-3">
                <Icon name="info" class="w-5 h-5 text-accent-600 shrink-0 mt-0.5" />
                <div>
                    <h2 class="font-bold text-sm text-surface-900 dark:text-white">فصل واضح للصلاحيات</h2>
                    <p class="text-xs text-surface-500 dark:text-surface-400 mt-1">
                        تواصل مع الإدارة للإسناد والتسعير والسعة. نشر المواعيد والمنهج والمحتوى والاختبارات والحصص المباشرة مسؤوليتك.
                    </p>
                </div>
            </div>

            <section v-for="assignment in assignments" :key="assignment.id" class="card p-6 space-y-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-bold text-lg text-surface-900 dark:text-white">
                            {{ assignment.subject.name }} — {{ assignment.grade_level.name }}
                        </h2>
                        <p class="text-xs text-surface-400 mt-1">{{ assignment.groups.length }} مجموعة مسندة إليك</p>
                    </div>
                    <Link :href="route('teacher.curriculum', { assignment: assignment.id })" class="btn-primary btn-sm flex items-center gap-1.5 shadow-sm">
                        <Icon name="book" class="w-4 h-4" />
                        <span>الخطة الأكاديمية وبناء المنهج</span>
                    </Link>
                </div>

                <div class="rounded-2xl border border-accent-500/25 bg-accent-500/5 p-5 space-y-4">
                    <div>
                        <h3 class="font-black text-surface-900 dark:text-white">الحصة التجريبية المجانية (توقيت الدوحة)</h3>
                        <p class="text-xs text-surface-500 mt-1">
                            انشر مواعيد متاحة ليحجز الطالب حصة واحدة معك مسبقًا وبدون أي رسوم (تُحسب الأوقات بتوقيت الدوحة GMT+3).
                        </p>
                    </div>

                    <form class="grid md:grid-cols-3 gap-3" @submit.prevent="publishFreeSlot(assignment.id)">
                        <input v-model="freeSlotDrafts[assignment.id].starts_at" type="datetime-local" :min="minSlotDateTime" class="input" required />
                        <input v-model="freeSlotDrafts[assignment.id].ends_at" type="datetime-local" :min="minSlotDateTime" class="input" required />
                        <button type="submit" class="btn-accent">نشر الموعد المجاني</button>
                    </form>

                    <div v-if="assignment.private_slots?.length" class="grid md:grid-cols-2 gap-3">
                        <div
                            v-for="slot in assignment.private_slots.filter(item => item.is_free_intro)"
                            :key="slot.id"
                            class="rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-900 p-3 flex items-center justify-between gap-3"
                        >
                            <div>
                                <p class="text-sm font-bold">{{ formatDate(slot.starts_at, slot.timezone) }}</p>
                                <p class="text-xs mt-1" :class="slot.status === 'booked' ? 'text-green-600' : 'text-surface-500'">
                                    {{ slot.status === 'booked' ? `محجوزة بواسطة ${slot.booking?.student?.name ?? 'طالب'}` : 'متاحة للحجز (بتوقيت الدوحة)' }}
                                </p>
                            </div>
                            <button
                                v-if="slot.status !== 'booked'"
                                type="button"
                                class="btn-ghost btn-sm text-red-500"
                                @click="cancelFreeSlot(slot.id)"
                            >
                                إلغاء
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Private Section (2 days like group) -->
                <div v-if="assignment.accepts_private" class="rounded-2xl border border-primary-500/25 bg-primary-500/5 p-5 space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="font-black text-surface-900 dark:text-white flex items-center gap-2">
                                <span>مواعيد البرايفيت الأسبوعية (يومين زي المجموعة)</span>
                                <span class="badge-primary text-[10px]">اشتراك شهري · طالب واحد فقط</span>
                            </h3>
                            <p class="text-xs text-surface-500 mt-1">
                                حدد يومين في الأسبوع والتوقيت لحصة البرايفيت. كل موعد متاح لطالب واحد فقط ويُحجز باشتراك شهري.
                            </p>
                        </div>
                    </div>

                    <form class="space-y-3 p-4 rounded-xl bg-white dark:bg-surface-900 border border-primary-500/20" @submit.prevent="publishPrivateGroup(assignment.id)">
                        <!-- Days of week (Select 2 days) -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-bold text-surface-800 dark:text-surface-200">
                                    أيام الحصة (اختر يومين مثل المجموعة):
                                </label>
                                <span class="text-[11px] font-bold text-primary-600">
                                    المحدد: {{ (privateGroupDrafts[assignment.id]?.days || []).map(d => days[d]).join(' و ') || 'اختر يومين' }}
                                </span>
                            </div>
                            <div class="grid grid-cols-4 sm:grid-cols-7 gap-1.5">
                                <button
                                    v-for="(dayName, dIdx) in days"
                                    :key="dIdx"
                                    type="button"
                                    @click="togglePrivateDay(assignment.id, dIdx)"
                                    :class="[
                                        'py-2 px-1 rounded-xl text-xs font-bold transition-all border text-center',
                                        privateGroupDrafts[assignment.id]?.days?.includes(dIdx)
                                            ? 'bg-primary-600 text-white border-primary-600 shadow-sm'
                                            : 'bg-surface-100 dark:bg-surface-800 text-surface-700 dark:text-surface-300 border-transparent hover:border-surface-300'
                                    ]"
                                >
                                    {{ dayName }}
                                </button>
                            </div>
                        </div>

                        <!-- Time & Duration -->
                        <div class="grid sm:grid-cols-3 gap-3 pt-1">
                            <div>
                                <label class="block text-xs font-bold text-surface-800 dark:text-surface-200 mb-1">وقت البدء (توقيت الدوحة):</label>
                                <input v-model="privateGroupDrafts[assignment.id].start_time" type="time" class="input text-sm font-bold" required />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-surface-800 dark:text-surface-200 mb-1">المدة:</label>
                                <select v-model.number="privateGroupDrafts[assignment.id].duration_minutes" class="input text-sm font-bold">
                                    <option :value="30">30 دقيقة</option>
                                    <option :value="45">45 دقيقة</option>
                                    <option :value="60">ساعة (60 دقيقة)</option>
                                    <option :value="90">ساعة ونصف (90 دقيقة)</option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="btn-primary w-full h-[42px] flex items-center justify-center gap-1.5 font-bold" :disabled="(privateGroupDrafts[assignment.id]?.days?.length || 0) < 2">
                                    <Icon name="check" class="w-4 h-4" />
                                    <span>نشر موعد برايفيت (يومان أسبوعياً)</span>
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Published Private Appointments (Groups with capacity 1) -->
                    <div v-if="assignment.groups?.some(g => g.is_private || g.capacity === 1)" class="grid md:grid-cols-2 gap-3">
                        <div
                            v-for="pGroup in assignment.groups.filter(g => g.is_private || g.capacity === 1)"
                            :key="pGroup.id"
                            class="rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-900 p-4 flex flex-col justify-between gap-3 shadow-sm"
                        >
                            <div>
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <h4 class="font-bold text-sm text-surface-900 dark:text-white flex items-center gap-1.5">
                                            <span>👤</span>
                                            <span>{{ pGroup.name }}</span>
                                        </h4>
                                        <p class="text-xs text-primary-600 font-bold mt-1">
                                            {{ formatGroupSchedules(pGroup) }}
                                        </p>
                                    </div>
                                    <span v-if="pGroup.students?.length" class="badge-green text-[10px]">
                                        محجوز (طالب مشترك)
                                    </span>
                                    <span v-else class="badge-primary text-[10px]">
                                        متاح للحجز (طالب واحد)
                                    </span>
                                </div>

                                <div v-if="pGroup.students?.length" class="mt-3 pt-2.5 border-t border-surface-100 dark:border-surface-800 flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-primary-500/10 text-primary-600 flex items-center justify-center font-bold text-xs overflow-hidden shrink-0">
                                        <img v-if="pGroup.students[0].avatar" :src="pGroup.students[0].avatar" :alt="pGroup.students[0].name" class="w-full h-full object-cover" />
                                        <span v-else>{{ pGroup.students[0].name?.charAt(0) }}</span>
                                    </div>
                                    <div class="text-xs truncate">
                                        <span class="text-surface-500">الطالب: </span>
                                        <strong class="text-surface-900 dark:text-white">{{ pGroup.students[0].name }}</strong>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-surface-100 dark:border-surface-800 flex items-center justify-between gap-2">
                                <button
                                    type="button"
                                    class="text-xs text-primary-600 font-bold hover:underline flex items-center gap-1"
                                    @click="openScheduleModal(pGroup)"
                                >
                                    <Icon name="calendar" class="w-3.5 h-3.5" />
                                    <span>تعديل المواعيد</span>
                                </button>
                                <button
                                    v-if="!pGroup.students?.length"
                                    type="button"
                                    class="btn-ghost btn-xs text-red-500"
                                    @click="removePrivateGroup(pGroup.id)"
                                >
                                    إلغاء الموعد
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-xs text-surface-400">لم تنشر مواعيد برايفيت أسبوعية بعد.</p>
                </div>

                <article
                    v-for="group in (assignment.groups || []).filter(g => !g.is_private && g.capacity > 1)"
                    :key="group.id"
                    class="rounded-2xl border border-surface-200 dark:border-surface-700 p-5 space-y-4"
                >
                    <div class="flex flex-wrap justify-between gap-3">
                        <div>
                            <h3 class="font-black text-surface-900 dark:text-white">{{ group.name }}</h3>
                            <p class="text-xs text-primary-600 mt-1">{{ group.active_bookings_count }} من {{ group.capacity }} طالب محجوز</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="btn-primary btn-sm flex items-center gap-1.5"
                                @click="openScheduleModal(group)"
                            >
                                <Icon name="calendar" class="w-4 h-4" />
                                <span>المواعيد والجدولة الذكية</span>
                            </button>
                            <button
                                type="button"
                                class="btn-outline btn-sm flex items-center gap-1.5"
                                @click="openStudentsModal(group)"
                            >
                                <Icon name="users" class="w-4 h-4 text-primary-500" />
                                <span>الطلاب ({{ group.students?.length ?? group.active_bookings_count }})</span>
                            </button>
                            <Link :href="route('teacher.materials', { groupId: group.id })" class="btn-outline btn-sm">المواد</Link>
                            <Link :href="route('teacher.worksheets.index', { groupId: group.id })" class="btn-ghost btn-sm">الواجبات</Link>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-surface-200 dark:border-surface-700 p-4">
                        <span class="text-sm font-bold">سعة المجموعة: {{ group.capacity }} طالب</span>
                        <span class="text-xs text-surface-400">المحجوز {{ group.active_bookings_count }} · المتبقي {{ Math.max(0, group.capacity - group.active_bookings_count) }} مقعد</span>
                        <span class="text-xs text-surface-400">تعديل السعة من لوحة الإدارة فقط</span>
                    </div>

                    <!-- Enrolled Students Strip -->
                    <div class="rounded-xl bg-surface-50 dark:bg-surface-800/60 p-3.5 border border-surface-200 dark:border-surface-700 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-bold text-surface-700 dark:text-surface-300 flex items-center gap-1.5">
                                <Icon name="users" class="w-3.5 h-3.5 text-primary-500" />
                                <span>الطلاب المشتركون في هذه المجموعة ({{ group.students?.length ?? group.active_bookings_count }})</span>
                            </span>
                            <button
                                v-if="group.students?.length"
                                type="button"
                                class="text-[11px] font-semibold text-primary-600 hover:underline"
                                @click="openStudentsModal(group)"
                            >
                                عرض التفاصيل
                            </button>
                        </div>

                        <div v-if="group.students?.length" class="flex flex-wrap items-center gap-2">
                            <div
                                v-for="student in group.students"
                                :key="student.id"
                                class="inline-flex items-center gap-2 rounded-xl bg-white dark:bg-surface-900 px-3 py-1.5 text-xs border border-surface-200 dark:border-surface-700 shadow-sm"
                            >
                                <div class="w-6 h-6 rounded-full bg-primary-500/10 text-primary-600 flex items-center justify-center font-bold text-[10px] overflow-hidden shrink-0">
                                    <img v-if="student.avatar" :src="student.avatar" :alt="student.name" class="w-full h-full object-cover" />
                                    <span v-else>{{ student.name?.charAt(0) }}</span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-bold text-surface-900 dark:text-surface-100 leading-tight">{{ student.name }}</span>
                                    <span class="text-[10px] text-surface-400 font-mono">{{ student.email }}</span>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-xs text-surface-400">لا يوجد طلاب مسجلون في هذه المجموعة حالياً.</p>
                    </div>

                    <div class="rounded-xl border border-primary-500/20 bg-primary-500/5 p-4 space-y-3">
                        <div class="flex flex-wrap gap-2">
                        <span
                            v-for="schedule in group.schedules"
                            :key="schedule.id"
                            class="inline-flex items-center gap-2 rounded-full bg-primary-500/10 px-3 py-1 text-sm text-primary-700 dark:text-primary-300"
                        >
                            {{ days[schedule.day_of_week] }} · {{ formatTime12(schedule.start_time) }} إلى {{ formatTime12(schedule.end_time) }}
                            <button
                                type="button"
                                class="font-black text-red-500"
                                aria-label="حذف الموعد"
                                @click="removeGroupSchedule(schedule.id)"
                            >
                                ×
                            </button>
                        </span>
                        <p v-if="!group.schedules.length" class="text-xs text-surface-500">حدد أول موعد للمجموعة لتظهر للطلاب وتتم جدولة الحصص عليه.</p>
                        </div>

                        <form class="grid md:grid-cols-4 gap-3" @submit.prevent="addGroupSchedule(group.id)">
                            <select v-model.number="scheduleDrafts[group.id].day_of_week" class="input" required>
                                <option v-for="(day, index) in days" :key="day" :value="index">{{ day }}</option>
                            </select>
                            <input v-model="scheduleDrafts[group.id].start_time" type="time" class="input" required />
                            <input v-model="scheduleDrafts[group.id].end_time" type="time" class="input" required />
                        <button type="submit" class="btn-outline">إضافة موعد للمجموعة</button>
                        </form>
                    </div>

                    <div class="rounded-xl bg-surface-50 dark:bg-surface-900/50 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <div>
                                <h4 class="font-bold flex items-center gap-2">
                                    <span>جدولة الحصص المباشرة للمجموعة</span>
                                    <span class="badge-primary text-[10px]">Live Sessions</span>
                                </h4>
                                <p class="text-xs text-surface-500 mt-0.5">
                                    تُجدول تلقائياً في مواعيد المجموعة التالية. لبناء الوحدات الكاملة والملازم، انتقل إلى
                                    <Link :href="route('teacher.curriculum', { assignment: assignment.id })" class="text-primary-600 dark:text-primary-400 underline font-bold">الخطة الأكاديمية</Link>.
                                </p>
                            </div>
                            <button
                                v-if="group.lessons?.some((l) => l.status === 'pending')"
                                type="button"
                                class="btn-primary btn-sm text-xs flex items-center gap-1.5"
                                @click="autoScheduleAll(group.id)"
                            >
                                <Icon name="calendar" class="w-3.5 h-3.5" />
                                <span>⚡ جدولة كافة الحصص المعلقة تلقائياً</span>
                            </button>
                        </div>

                        <div v-if="group.lessons.length" class="space-y-2 mb-4">
                            <div
                                v-for="(lesson, index) in group.lessons"
                                :key="lesson.id"
                                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-surface-200 dark:border-surface-700 p-3"
                            >
                                <div>
                                    <b>{{ lesson.position }}. {{ lesson.title }}</b>
                                    <p v-if="lesson.live_session" class="text-xs text-primary-600 mt-1">
                                        مجدولة: {{ formatDate(lesson.live_session.scheduled_at) }}
                                    </p>
                                    <p v-else class="text-xs text-surface-500 mt-1">بانتظار الجدولة</p>
                                </div>

                                <button
                                    v-if="lesson.status === 'pending' && index === group.lessons.findIndex((row) => row.status === 'pending')"
                                    type="button"
                                    class="btn-primary btn-sm"
                                    @click="scheduleLesson(lesson.id)"
                                >
                                    جدولة حصة مباشرة
                                </button>
                                <span v-else-if="lesson.status !== 'pending'" class="badge-green text-xs">تمت الجدولة</span>
                            </div>
                        </div>

                        <form class="grid md:grid-cols-4 gap-3" @submit.prevent="addLesson(group.id)">
                            <input v-model="lessonDrafts[group.id].title" class="input md:col-span-2" minlength="3" maxlength="255" placeholder="عنوان الحصة المباشرة" required />
                            <input v-model="lessonDrafts[group.id].description" class="input" maxlength="2000" placeholder="ملاحظات الحصة" />
                            <button type="submit" class="btn-outline">+ إضافة للجدول</button>
                        </form>
                    </div>
                </article>

                <div v-if="!assignment.groups?.some(g => !g.is_private && g.capacity > 1)" class="rounded-xl bg-surface-50 dark:bg-surface-900 p-6 text-center text-sm text-surface-500">
                    لم تنشئ الإدارة مجموعة دراسية لهذا الإسناد بعد.
                </div>
            </section>

            <div v-if="!assignments.length" class="card p-12 text-center">
                <Icon name="book" class="w-12 h-12 text-surface-300 mx-auto mb-3" />
                <h2 class="font-bold text-surface-800 dark:text-white">لا توجد مواد مسندة إليك بعد</h2>
                <p class="text-sm text-surface-500 mt-2">تواصل مع الإدارة لإسناد المادة والصف الدراسي.</p>
            </div>
        </div>

        <!-- Students Modal -->
        <div
            v-if="selectedGroupForStudents"
            class="modal-overlay z-[70] bg-black/60 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            :aria-label="`طلاب ${selectedGroupForStudents.name}`"
            @click.self="closeStudentsModal"
        >
            <div class="card w-full max-w-2xl max-h-[85vh] flex flex-col p-6 space-y-4 shadow-2xl">
                <div class="flex items-start justify-between gap-3 border-b border-surface-200 dark:border-surface-700 pb-4">
                    <div>
                        <h2 class="text-xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                            <Icon name="users" class="w-6 h-6 text-primary-500" />
                            <span>طلاب مجموعة: {{ selectedGroupForStudents.name }}</span>
                        </h2>
                        <p class="text-xs text-surface-500 mt-1">
                            {{ selectedGroupForStudents.students?.length ?? 0 }} طالب مسجل · سعة المجموعة {{ selectedGroupForStudents.capacity }} مقعد
                        </p>
                    </div>
                    <button type="button" class="text-surface-400 hover:text-surface-600 text-lg font-bold" @click="closeStudentsModal">✕</button>
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

                        <div class="flex items-center gap-2 shrink-0">
                            <span class="badge-green text-xs">مشترك</span>
                            <Link :href="route('chat.index')" class="btn-ghost btn-sm text-primary-600">
                                رسالة
                            </Link>
                        </div>
                    </div>

                    <div v-if="!selectedGroupForStudents.students?.length" class="py-12 text-center text-surface-400 text-sm">
                        لا يوجد طلاب مسجلون في هذه المجموعة بعد.
                    </div>
                    <div v-else-if="studentSearchQuery && !(selectedGroupForStudents.students || []).some(s => s.name.toLowerCase().includes(studentSearchQuery.toLowerCase()) || s.email.toLowerCase().includes(studentSearchQuery.toLowerCase()))" class="py-8 text-center text-surface-400 text-xs">
                        لا يوجد نتائج تطابق بحثك.
                    </div>
                </div>

                <div class="pt-3 border-t border-surface-200 dark:border-surface-700 flex justify-end">
                    <button type="button" class="btn-outline btn-sm" @click="closeStudentsModal">إغلاق</button>
                </div>
            </div>
        </div>

        <!-- Schedule Modal -->
        <ScheduleModal
            v-model="showScheduleModal"
            :group="activeGroupForSchedule"
            @saved="activeGroupForSchedule = null"
        />
    </DashboardLayout>
</template>
