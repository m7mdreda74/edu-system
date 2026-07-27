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

watch(() => props.assignments, (assignments) => {
    assignments.flatMap((item) => item.groups || []).forEach((group) => {
        lessonDrafts.value[group.id] ??= { title: '', description: '' };
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
                    الإدارة تحدد المواد والمجموعات والمواعيد والأسعار، وأنت تدير المنهج وخطة الشرح والحصص.
                </p>
            </header>

            <div class="rounded-2xl border border-accent-500/25 bg-accent-500/10 p-4 flex items-start gap-3">
                <Icon name="info" class="w-5 h-5 text-accent-600 shrink-0 mt-0.5" />
                <div>
                    <h2 class="font-bold text-sm text-surface-900 dark:text-white">الصلاحيات الإدارية لدى إدارة المنصة</h2>
                    <p class="text-xs text-surface-500 dark:text-surface-400 mt-1">
                        لإضافة مادة أو مجموعة، تعديل موعد أو سعة، أو إتاحة برايفيت تواصل مع الإدارة.
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

                <article
                    v-for="group in assignment.groups"
                    :key="group.id"
                    class="rounded-2xl border border-surface-200 dark:border-surface-700 p-5 space-y-4"
                >
                    <div class="flex flex-wrap justify-between gap-3">
                        <div>
                            <h3 class="font-black text-surface-900 dark:text-white">{{ group.name }}</h3>
                            <p class="text-xs text-primary-600 mt-1">{{ group.active_bookings_count }} طالب محجوز</p>
                        </div>
                        <div class="flex gap-2">
                            <Link :href="route('teacher.materials', { groupId: group.id })" class="btn-outline btn-sm">المواد</Link>
                            <Link :href="route('teacher.worksheets.index', { groupId: group.id })" class="btn-ghost btn-sm">الواجبات</Link>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="schedule in group.schedules"
                            :key="schedule.id"
                            class="rounded-full bg-primary-500/10 px-3 py-1 text-sm text-primary-700 dark:text-primary-300"
                        >
                            {{ days[schedule.day_of_week] }} · {{ schedule.start_time.slice(0, 5) }} إلى {{ schedule.end_time.slice(0, 5) }}
                        </span>
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
                            <button class="btn-outline">+ إضافة للخطة</button>
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
