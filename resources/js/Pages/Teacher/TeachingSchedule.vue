<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

const props = defineProps({ assignments: { type: Array, default: () => [] }, subjects: { type: Array, default: () => [] }, gradeLevels: { type: Array, default: () => [] } });
const days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
const tab = ref('groups');
const lessonDrafts = ref({});
watch(() => props.assignments, (assignments) => {
    assignments.flatMap(item => item.groups || []).forEach(entry => {
        lessonDrafts.value[entry.id] ??= { title: '', description: '' };
    });
}, { immediate: true });
const assignment = useForm({ subject_id: '', grade_level_id: '' });
const group = useForm({
    teaching_assignment_id: '', name: '', capacity: 10, timezone: 'Asia/Qatar',
    day_of_week: 0, start_time: '', end_time: '',
    schedules: [{ day_of_week: 0, start_time: '', end_time: '' }],
});
const privateSlot = useForm({ teaching_assignment_id: '', starts_at: '', ends_at: '', timezone: 'Asia/Qatar' });

function addAssignment() { assignment.post(route('teacher.teaching-schedule.assignments.store'), { onSuccess: () => assignment.reset() }); }
function addSchedule() { if (group.schedules.length < 7) group.schedules.push({ day_of_week: 0, start_time: '', end_time: '' }); }
function removeSchedule(index) { if (group.schedules.length > 1) group.schedules.splice(index, 1); }
function addGroup() {
    const first = group.schedules[0];
    group.day_of_week = first.day_of_week;
    group.start_time = first.start_time;
    group.end_time = first.end_time;
    group.post(route('teacher.teaching-schedule.groups.store'), {
        onSuccess: () => {
            group.reset();
            group.capacity = 10;
            group.timezone = 'Asia/Qatar';
            group.schedules = [{ day_of_week: 0, start_time: '', end_time: '' }];
        },
    });
}
function addPrivate() { privateSlot.post(route('teacher.teaching-schedule.private-slots.store'), { onSuccess: () => privateSlot.reset('starts_at', 'ends_at') }); }
function addLesson(groupId) {
    const draft = lessonDrafts.value[groupId] || {};
    if (!draft.title?.trim()) return;
    router.post(route('teacher.teaching-schedule.groups.lessons.store', groupId), draft, {
        preserveScroll: true,
        onSuccess: () => { lessonDrafts.value[groupId] = { title: '', description: '' }; },
    });
}
function scheduleLesson(id) { router.post(route('teacher.teaching-schedule.group-lessons.schedule', id), {}, { preserveScroll: true, replace: true }); }
function removeGroup(id) { if (confirm('حذف المجموعة؟')) router.delete(route('teacher.teaching-schedule.groups.destroy', id)); }
function removeSlot(id) { if (confirm('إلغاء موعد البرايفيت؟')) router.delete(route('teacher.teaching-schedule.private-slots.destroy', id)); }
function formatDate(value) { return new Date(value).toLocaleString('ar-EG', { dateStyle: 'medium', timeStyle: 'short' }); }
</script>

<template>
    <DashboardLayout>
        <Head title="جدول التدريس والمجموعات" />
        <div class="container-app px-4 py-8 space-y-8">
            <div>
                <h1 class="text-3xl font-black text-surface-900 dark:text-white">جدول التدريس والحجوزات</h1>
                <p class="text-surface-500 mt-2">أنشئ مجموعة بأكثر من يوم، ثم أضف خطة الحصص وجدول الحصة الموجودة عليها الدور.</p>
            </div>

            <section class="card p-6">
                <h2 class="font-bold text-lg mb-4">ربط المادة بالسنة الدراسية</h2>
                <form class="grid md:grid-cols-3 gap-3" @submit.prevent="addAssignment">
                    <select v-model="assignment.subject_id" class="input" required><option value="">اختر المادة</option><option v-for="item in subjects" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                    <select v-model="assignment.grade_level_id" class="input" required><option value="">اختر السنة</option><option v-for="item in gradeLevels" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                    <button class="btn-primary">إضافة الربط</button>
                </form>
            </section>

            <div class="flex gap-2"><button :class="tab === 'groups' ? 'btn-primary' : 'btn-outline'" @click="tab = 'groups'">المجموعات</button><button :class="tab === 'private' ? 'btn-primary' : 'btn-outline'" @click="tab = 'private'">مواعيد البرايفيت</button></div>

            <section class="card p-6">
                <h2 class="font-bold text-lg mb-4">{{ tab === 'groups' ? 'إنشاء مجموعة ومواعيدها الأسبوعية' : 'إضافة موعد برايفيت' }}</h2>
                <form v-if="tab === 'groups'" class="space-y-4" @submit.prevent="addGroup">
                    <div class="grid md:grid-cols-3 gap-3">
                        <select v-model="group.teaching_assignment_id" class="input" required><option value="">اختر المادة والسنة</option><option v-for="item in assignments" :key="item.id" :value="item.id">{{ item.subject.name }} — {{ item.grade_level.name }}</option></select>
                        <input v-model="group.name" class="input" placeholder="اسم المجموعة: المجموعة أ" required>
                        <input v-model.number="group.capacity" type="number" min="1" max="1000" class="input" placeholder="عدد الطلاب" required>
                    </div>
                    <div class="space-y-3">
                        <div v-for="(schedule, index) in group.schedules" :key="index" class="grid md:grid-cols-4 gap-3 items-center rounded-xl border border-surface-200 dark:border-surface-700 p-3">
                            <select v-model.number="schedule.day_of_week" class="input"><option v-for="(day, dayIndex) in days" :key="day" :value="dayIndex">{{ day }}</option></select>
                            <input v-model="schedule.start_time" type="time" class="input" required>
                            <input v-model="schedule.end_time" type="time" class="input" required>
                            <button type="button" class="text-red-500" :disabled="group.schedules.length === 1" @click="removeSchedule(index)">حذف الموعد</button>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-3"><button type="button" class="btn-outline" @click="addSchedule">+ إضافة يوم آخر</button><button class="btn-primary">حفظ المجموعة بكل مواعيدها</button></div>
                </form>
                <form v-else class="grid md:grid-cols-4 gap-3" @submit.prevent="addPrivate">
                    <select v-model="privateSlot.teaching_assignment_id" class="input md:col-span-2" required><option value="">اختر المادة والسنة</option><option v-for="item in assignments" :key="item.id" :value="item.id">{{ item.subject.name }} — {{ item.grade_level.name }}</option></select>
                    <input v-model="privateSlot.starts_at" type="datetime-local" class="input" required><input v-model="privateSlot.ends_at" type="datetime-local" class="input" required><button class="btn-primary">إتاحة الموعد</button>
                </form>
            </section>

            <section v-for="item in assignments" :key="item.id" class="card p-6 space-y-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-bold text-lg">{{ item.subject.name }} — {{ item.grade_level.name }}</h2>
                    <Link :href="route('teacher.curriculum', { assignment: item.id })" class="btn-primary btn-sm">بناء المنهج: الفصول والوحدات والدروس</Link>
                </div>
                <article v-for="entry in item.groups" :key="entry.id" class="rounded-2xl border border-surface-200 dark:border-surface-700 p-5 space-y-4">
                    <div class="flex flex-wrap justify-between gap-3">
                        <div><h3 class="font-black">{{ entry.name }}</h3><p class="text-xs text-primary-600">{{ entry.active_bookings_count }}/{{ entry.capacity }} طالب محجوز</p></div>
                        <button class="text-red-500 text-sm" @click="removeGroup(entry.id)">حذف المجموعة</button>
                    </div>
                    <div class="flex flex-wrap gap-2"><span v-for="schedule in entry.schedules" :key="schedule.id" class="rounded-full bg-primary-500/10 px-3 py-1 text-sm text-primary-700 dark:text-primary-300">{{ days[schedule.day_of_week] }} · {{ schedule.start_time.slice(0, 5) }} إلى {{ schedule.end_time.slice(0, 5) }}</span></div>

                    <div class="rounded-xl bg-surface-50 dark:bg-surface-900/50 p-4">
                        <h4 class="font-bold mb-3">خطة حصص المجموعة</h4>
                        <div v-if="entry.lessons.length" class="space-y-2 mb-4">
                            <div v-for="(lesson, index) in entry.lessons" :key="lesson.id" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-surface-200 dark:border-surface-700 p-3">
                                <div><b>{{ lesson.position }}. {{ lesson.title }}</b><p v-if="lesson.live_session" class="text-xs text-primary-600 mt-1">مجدولة: {{ formatDate(lesson.live_session.scheduled_at) }}</p><p v-else class="text-xs text-surface-500 mt-1">بانتظار الجدولة</p></div>
                                <button v-if="lesson.status === 'pending' && index === entry.lessons.findIndex(row => row.status === 'pending')" type="button" class="btn-primary btn-sm" @click.prevent.stop="scheduleLesson(lesson.id)">جدولة حصة مباشرة</button>
                                <span v-else-if="lesson.status !== 'pending'" class="text-xs rounded-full bg-green-500/10 text-green-600 px-3 py-1">تمت الجدولة</span>
                            </div>
                        </div>
                        <form class="grid md:grid-cols-4 gap-3" @submit.prevent="addLesson(entry.id)">
                            <input v-model="lessonDrafts[entry.id].title" class="input md:col-span-2" placeholder="عنوان الحصة: شرح الوحدة الأولى" required>
                            <input v-model="lessonDrafts[entry.id].description" class="input" placeholder="ملاحظات أو محتوى الحصة">
                            <button class="btn-outline">+ إضافة للخطة</button>
                        </form>
                    </div>
                </article>
                <div v-for="entry in item.private_slots" :key="entry.id" class="border rounded-xl p-4 flex justify-between"><div><b>برايفيت</b><p class="text-sm text-surface-500">{{ formatDate(entry.starts_at) }}</p></div><button class="text-red-500" @click="removeSlot(entry.id)">إلغاء</button></div>
            </section>
        </div>
    </DashboardLayout>
</template>
