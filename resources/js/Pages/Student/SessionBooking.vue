<script setup>
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { useConfirm } from '@/composables/useConfirm';
defineProps({ assignments: { type: Array, default: () => [] }, bookings: { type: Array, default: () => [] } });
const days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
const { confirm } = useConfirm();
function book(name, id) { router.post(route(name, id)); }
async function cancel(id) {
    const ok = await confirm({ title: 'إلغاء الحجز', message: 'هل أنت متأكد من إلغاء هذا الحجز؟', confirmLabel: 'إلغاء', variant: 'warning' });
    if (ok) router.delete(route('student.session-booking.cancel', id));
}
function formatDate(value) { return new Date(value).toLocaleString('ar-EG', { dateStyle: 'medium', timeStyle: 'short' }); }
</script>

<template>
    <DashboardLayout>
        <Head title="حجز المدرسين والمواعيد" />
        <div class="container-app px-4 py-8 space-y-8">
            <div><h1 class="text-3xl font-black text-surface-900 dark:text-white">اختيار المدرس والمجموعة</h1><p class="text-surface-500 mt-2">الحجز في المجموعة يشملك في جميع أيامها وحصصها المباشرة المجدولة.</p></div>
            <section class="card p-6">
                <h2 class="font-bold text-lg mb-4">حجوزاتي الحالية</h2>
                <div v-if="bookings.length" class="grid md:grid-cols-2 gap-3">
                    <div v-for="booking in bookings" :key="booking.id" class="border rounded-xl p-4 space-y-2">
                        <div class="flex justify-between"><b>{{ booking.group ? booking.group.assignment.subject.name : 'جلسة برايفيت' }}</b><button class="text-red-500" @click="cancel(booking.id)">إلغاء</button></div>
                        <p class="text-sm text-surface-500">{{ booking.group ? booking.group.assignment.grade_level.name + ' — ' + booking.group.name : formatDate(booking.private_slot.starts_at) }}</p>
                        <div v-if="booking.group" class="flex flex-wrap gap-2"><span v-for="schedule in booking.group.schedules" :key="schedule.id" class="rounded-full bg-primary-500/10 px-3 py-1 text-xs text-primary-700 dark:text-primary-300">{{ days[schedule.day_of_week] }} · {{ schedule.start_time.slice(0, 5) }} إلى {{ schedule.end_time.slice(0, 5) }}</span></div>
                    </div>
                </div>
                <p v-else class="text-surface-500">لا توجد حجوزات حاليًا.</p>
            </section>
            <section v-for="item in assignments" :key="item.id" class="card p-6">
                <h2 class="font-bold text-lg">{{ item.subject.name }} — {{ item.grade_level.name }}</h2><p class="text-sm text-surface-500">المدرس: {{ item.teacher.name }}</p>
                <div class="grid md:grid-cols-2 gap-3 mt-4">
                    <div v-for="group in item.groups" :key="group.id" class="border rounded-xl p-4 space-y-3">
                        <div><b>{{ group.name }}</b><p class="text-xs text-primary-600 mt-1">متاح {{ group.capacity - group.active_bookings_count }} من {{ group.capacity }}</p></div>
                        <div class="flex flex-wrap gap-2"><span v-for="schedule in group.schedules" :key="schedule.id" class="rounded-full bg-surface-100 dark:bg-surface-800 px-3 py-1 text-xs">{{ days[schedule.day_of_week] }} · {{ schedule.start_time.slice(0, 5) }} إلى {{ schedule.end_time.slice(0, 5) }}</span></div>
                        <button class="btn-primary btn-sm" :disabled="group.active_bookings_count >= group.capacity" @click="book('student.session-booking.group', group.id)">{{ group.active_bookings_count >= group.capacity ? 'مكتملة' : 'احجز كل مواعيد المجموعة' }}</button>
                    </div>
                    <div v-for="slot in item.private_slots" :key="slot.id" class="border rounded-xl p-4 flex justify-between"><div><b>جلسة برايفيت</b><p class="text-sm text-surface-500">{{ formatDate(slot.starts_at) }}</p></div><button class="btn-primary btn-sm" @click="book('student.session-booking.private', slot.id)">احجز الموعد</button></div>
                </div>
            </section>
            <p v-if="!assignments.length" class="card p-8 text-center text-surface-500">لا توجد مواعيد متاحة من المدرسين حتى الآن.</p>
        </div>
    </DashboardLayout>
</template>
