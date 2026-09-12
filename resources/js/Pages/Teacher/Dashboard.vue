<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

defineProps({
    stats:  { type: Object, default: () => ({}) },
    groups: { type: Array, default: () => [] },
});

const selectedGroupForStudents = ref(null);
const studentSearchQuery = ref('');

function openStudentsModal(group) {
    selectedGroupForStudents.value = group;
    studentSearchQuery.value = '';
}

function closeStudentsModal() {
    selectedGroupForStudents.value = null;
    studentSearchQuery.value = '';
}
</script>

<template>
    <Head title="لوحة المعلم" />

    <DashboardLayout>
        <div class="space-y-6">
            <header class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white">
                        أهلاً، {{ $page.props.auth.user?.name }}
                    </h1>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                        مساحتك الأكاديمية لإدارة المنهج والشرح والواجبات والاختبارات
                    </p>
                </div>

                <Link :href="route('teacher.teaching-schedule')" class="btn-primary btn-sm flex items-center gap-2">
                    <Icon name="book" class="w-4 h-4" />
                    <span>الخطة الأكاديمية</span>
                </Link>
            </header>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div
                    v-for="stat in [
                        { label: 'المناهج المسندة', value: stats.assignments,     icon: 'book',     color: 'primary' },
                        { label: 'المجموعات',       value: stats.total_groups,    icon: 'courses',  color: 'accent' },
                        { label: 'طلابك',           value: stats.active_students, icon: 'student',  color: 'primary' },
                        { label: 'الدروس المنشورة', value: stats.lessons,         icon: 'video',    color: 'accent' },
                    ]"
                    :key="stat.label"
                    class="card p-5"
                >
                    <div class="flex items-center gap-3">
                        <div :class="`p-2.5 rounded-xl bg-${stat.color}-50 dark:bg-${stat.color}-950 text-${stat.color}-600 dark:text-${stat.color}-400`">
                            <Icon :name="stat.icon" class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-xl font-black text-surface-900 dark:text-white truncate">{{ stat.value ?? 0 }}</div>
                            <div class="text-[11px] text-surface-400">{{ stat.label }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-primary-500/20 bg-primary-500/5 p-4 flex items-start gap-3">
                <Icon name="info" class="w-5 h-5 text-primary-600 shrink-0" />
                <p class="text-xs text-surface-600 dark:text-surface-300 leading-relaxed">
                    الإدارة مسؤولة عن الإسناد والمجموعات والسعة والأسعار والاشتراكات والتسويات المالية، وأنت مسؤول عن المواعيد والعمل الأكاديمي.
                    دورك هنا أكاديمي: المنهج، الدروس، الفيديوهات، الملازم، الواجبات، الاختبارات والحصص المباشرة.
                </p>
            </div>

            <section class="card p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-bold text-sm text-surface-900 dark:text-white">المجموعات المسندة إليك</h2>
                    <Link :href="route('teacher.teaching-schedule')" class="text-primary-600 text-xs hover:underline">
                        عرض الخطة
                    </Link>
                </div>

                <div v-if="groups.length" class="space-y-3">
                    <div
                        v-for="group in groups"
                        :key="group.id"
                        class="flex items-center gap-3 p-3 rounded-xl border border-surface-100 dark:border-surface-800"
                    >
                        <div class="w-10 h-10 rounded-xl bg-primary-50 dark:bg-primary-950 flex items-center justify-center text-primary-600 shrink-0">
                            <Icon name="courses" class="w-5 h-5" />
                        </div>

                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-sm text-surface-900 dark:text-white truncate">{{ group.name }}</h3>
                            <div class="text-[11px] text-surface-400 flex items-center gap-2 flex-wrap">
                                <span>{{ group.subject?.name }}</span>
                                <span v-if="group.grade">· {{ group.grade.name }}</span>
                                <span>· {{ group.students_count }} طالب</span>
                                <span>· {{ group.materials_count }} درس</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <button
                                type="button"
                                class="btn-outline btn-sm flex items-center gap-1.5"
                                @click="openStudentsModal(group)"
                            >
                                <Icon name="users" class="w-3.5 h-3.5 text-primary-500" />
                                <span>الطلاب ({{ group.students_count }})</span>
                            </button>
                            <Link v-if="group.assignment_id" :href="route('teacher.curriculum', { assignment: group.assignment_id })" class="btn-primary btn-sm">المنهج</Link>
                            <Link :href="route('teacher.materials', { groupId: group.id })" class="btn-outline btn-sm">المواد</Link>
                            <Link :href="route('teacher.worksheets.index', { groupId: group.id })" class="btn-ghost btn-sm">الواجبات</Link>
                        </div>
                    </div>
                </div>

                <div v-else class="text-center py-10">
                    <Icon name="courses" class="w-10 h-10 text-surface-300 mx-auto mb-3" />
                    <p class="text-sm text-surface-400">لم تسند الإدارة إليك مجموعات بعد.</p>
                </div>
            </section>
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
                            {{ selectedGroupForStudents.students?.length ?? 0 }} طالب مشترك في هذه المجموعة
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
    </DashboardLayout>
</template>
