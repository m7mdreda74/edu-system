<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    assignments: { type: Array, default: () => [] },
});

const days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
const lessonDrafts = ref({});
const scheduleDrafts = ref({});
const freeSlotDrafts = ref({});
const privateSlotDrafts = ref({});

watch(() => props.assignments, (assignments) => {
    assignments.flatMap((item) => item.groups || []).forEach((group) => {
        lessonDrafts.value[group.id] ??= { title: '', description: '' };
        scheduleDrafts.value[group.id] ??= { day_of_week: 0, start_time: '', end_time: '' };
    });
    assignments.forEach((assignment) => {
        freeSlotDrafts.value[assignment.id] ??= { starts_at: '', ends_at: '' };
        privateSlotDrafts.value[assignment.id] ??= { starts_at: '', ends_at: '' };
    });
}, { immediate: true });

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

function formatDate(value) {
    return new Date(value).toLocaleString('ar-EG', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
</script>

<template>
    <DashboardLayout>
        <Head title="الخطة الأكاديمية وجدول الحصص" />

        <div class="container-app px-4 py-8 space-y-7">
            <header>
                <h1 class="text-3xl font-black text-surface-900 dark:text-white">الخطة الأكاديمية وجدول الحصص</h1>
                <p class="text-surface-500 mt-2">
                    الإدارة تحدد الإسنادات والأسعار والسعة، وأنت تنشر مواعيد مجموعاتك ومواعيد البرايفيت من جدولك.
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
                    <Link :href="route('teacher.curriculum', { assignment: assignment.id })" class="btn-primary btn-sm">
                        بناء المنهج والوحدات والدروس
                    </Link>
                </div>

                <div class="rounded-2xl border border-accent-500/25 bg-accent-500/5 p-5 space-y-4">
                    <div>
                        <h3 class="font-black text-surface-900 dark:text-white">الحصة التجريبية المجانية</h3>
                        <p class="text-xs text-surface-500 mt-1">
                            انشر مواعيد متاحة ليحجز الطالب حصة واحدة معك مسبقًا وبدون أي رسوم.
                        </p>
                    </div>

                    <form class="grid md:grid-cols-3 gap-3" @submit.prevent="publishFreeSlot(assignment.id)">
                        <input v-model="freeSlotDrafts[assignment.id].starts_at" type="datetime-local" class="input" required />
                        <input v-model="freeSlotDrafts[assignment.id].ends_at" type="datetime-local" class="input" required />
                        <button type="submit" class="btn-accent">نشر الموعد المجاني</button>
                    </form>

                    <div v-if="assignment.private_slots?.length" class="grid md:grid-cols-2 gap-3">
                        <div
                            v-for="slot in assignment.private_slots.filter(item => item.is_free_intro)"
                            :key="slot.id"
                            class="rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-900 p-3 flex items-center justify-between gap-3"
                        >
                            <div>
                                <p class="text-sm font-bold">{{ formatDate(slot.starts_at) }}</p>
                                <p class="text-xs mt-1" :class="slot.status === 'booked' ? 'text-green-600' : 'text-surface-500'">
                                    {{ slot.status === 'booked' ? `محجوزة بواسطة ${slot.booking?.student?.name ?? 'طالب'}` : 'متاحة للحجز' }}
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

                <div v-if="assignment.accepts_private" class="rounded-2xl border border-primary-500/25 bg-primary-500/5 p-5 space-y-4">
                    <div>
                        <h3 class="font-black text-surface-900 dark:text-white">مواعيد البرايفيت</h3>
                        <p class="text-xs text-surface-500 mt-1">كل موعد متاح لطالب واحد فقط، ويُغلق تلقائيًا بمجرد حجزه.</p>
                    </div>

                    <form class="grid md:grid-cols-3 gap-3" @submit.prevent="publishPrivateSlot(assignment.id)">
                        <input v-model="privateSlotDrafts[assignment.id].starts_at" type="datetime-local" class="input" required />
                        <input v-model="privateSlotDrafts[assignment.id].ends_at" type="datetime-local" class="input" required />
                        <button type="submit" class="btn-primary">نشر موعد برايفيت</button>
                    </form>

                    <div v-if="assignment.private_slots?.some(item => !item.is_free_intro)" class="grid md:grid-cols-2 gap-3">
                        <div
                            v-for="slot in assignment.private_slots.filter(item => !item.is_free_intro)"
                            :key="slot.id"
                            class="rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-900 p-3 flex items-center justify-between gap-3"
                        >
                            <div>
                                <p class="text-sm font-bold">{{ formatDate(slot.starts_at) }}</p>
                                <p class="text-xs mt-1" :class="slot.status === 'booked' ? 'text-green-600' : 'text-surface-500'">
                                    {{ slot.status === 'booked' ? `محجوز بواسطة ${slot.booking?.student?.name ?? 'طالب'}` : 'متاح لطالب واحد' }}
                                </p>
                            </div>
                            <button v-if="slot.status !== 'booked'" type="button" class="btn-ghost btn-sm text-red-500" @click="cancelPrivateSlot(slot.id)">إلغاء</button>
                        </div>
                    </div>
                    <p v-else class="text-xs text-surface-400">لم تنشر مواعيد برايفيت بعد.</p>
                </div>

                <article
                    v-for="group in assignment.groups"
                    :key="group.id"
                    class="rounded-2xl border border-surface-200 dark:border-surface-700 p-5 space-y-4"
                >
                    <div class="flex flex-wrap justify-between gap-3">
                        <div>
                            <h3 class="font-black text-surface-900 dark:text-white">{{ group.name }}</h3>
                            <p class="text-xs text-primary-600 mt-1">{{ group.active_bookings_count }} من {{ group.capacity }} طالب محجوز</p>
                        </div>
                        <div class="flex gap-2">
                            <Link :href="route('teacher.materials', { groupId: group.id })" class="btn-outline btn-sm">المواد</Link>
                            <Link :href="route('teacher.worksheets.index', { groupId: group.id })" class="btn-ghost btn-sm">الواجبات</Link>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-surface-200 dark:border-surface-700 p-4">
                        <span class="text-sm font-bold">سعة المجموعة: {{ group.capacity }} طالب</span>
                        <span class="text-xs text-surface-400">المحجوز {{ group.active_bookings_count }} · المتبقي {{ Math.max(0, group.capacity - group.active_bookings_count) }} مقعد</span>
                        <span class="text-xs text-surface-400">تعديل السعة من لوحة الإدارة فقط</span>
                    </div>

                    <div class="rounded-xl border border-primary-500/20 bg-primary-500/5 p-4 space-y-3">
                        <div class="flex flex-wrap gap-2">
                        <span
                            v-for="schedule in group.schedules"
                            :key="schedule.id"
                            class="inline-flex items-center gap-2 rounded-full bg-primary-500/10 px-3 py-1 text-sm text-primary-700 dark:text-primary-300"
                        >
                            {{ days[schedule.day_of_week] }} · {{ schedule.start_time.slice(0, 5) }} إلى {{ schedule.end_time.slice(0, 5) }}
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
                        <h4 class="font-bold mb-3">خطة حصص المجموعة</h4>

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
                            <input v-model="lessonDrafts[group.id].title" class="input md:col-span-2" placeholder="عنوان الحصة" required />
                            <input v-model="lessonDrafts[group.id].description" class="input" placeholder="ملاحظات أكاديمية" />
                                <button type="submit" class="btn-outline">+ إضافة للخطة</button>
                        </form>
                    </div>
                </article>

                <div v-if="!assignment.groups.length" class="rounded-xl bg-surface-50 dark:bg-surface-900 p-6 text-center text-sm text-surface-500">
                    لم تنشئ الإدارة مجموعة لهذا الإسناد بعد.
                </div>
            </section>

            <div v-if="!assignments.length" class="card p-12 text-center">
                <Icon name="book" class="w-12 h-12 text-surface-300 mx-auto mb-3" />
                <h2 class="font-bold text-surface-800 dark:text-white">لا توجد مواد مسندة إليك بعد</h2>
                <p class="text-sm text-surface-500 mt-2">تواصل مع الإدارة لإسناد المادة والصف الدراسي.</p>
            </div>
        </div>
    </DashboardLayout>
</template>
