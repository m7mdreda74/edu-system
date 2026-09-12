<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import StatCard from '@/Components/StatCard.vue';

const props = defineProps({
    groups: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
});

const searchQuery = ref('');
const selectedGroupForStudents = ref(null);
const studentSearchQuery = ref('');

const days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

const filteredGroups = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.groups;
    }
    const q = searchQuery.value.toLowerCase();
    return props.groups.filter(g =>
        g.name?.toLowerCase().includes(q) ||
        g.subject?.name?.toLowerCase().includes(q) ||
        g.grade?.name?.toLowerCase().includes(q)
    );
});

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
    <DashboardLayout>
        <Head title="مجموعاتي التعليمية" />

        <div class="container-app px-4 py-8 space-y-7">
            <header class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white flex items-center gap-3">
                        <Icon name="courses" class="w-8 h-8 text-primary-500" />
                        <span>مجموعاتي التعليمية</span>
                    </h1>
                    <p class="text-surface-500 dark:text-surface-400 mt-1">
                        إدارة مجموعاتك الدراسية، ومتابعة الطلاب المسجلين بكل مجموعة ومواعيدها الأسبوعية.
                    </p>
                </div>

                <Link :href="route('teacher.teaching-schedule')" class="btn-primary flex items-center gap-2">
                    <Icon name="calendar" class="w-4 h-4" />
                    <span>الخطة الأكاديمية والجدول</span>
                </Link>
            </header>

            <!-- Stats Grid -->
            <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <StatCard label="إجمالي المجموعات" :value="stats.total_groups || groups.length" icon="courses" tone="primary" />
                <StatCard label="المجموعات النشطة" :value="stats.active_groups || groups.length" icon="success" tone="green" />
                <StatCard label="الطلاب المسجلون" :value="stats.total_students || 0" icon="student" tone="accent" />
                <StatCard label="السعة الإجمالية" :value="stats.total_capacity || 0" icon="users" tone="primary" />
            </section>

            <!-- Search Bar -->
            <div class="card p-4 flex flex-wrap items-center justify-between gap-3">
                <div class="relative flex-1 min-w-[240px]">
                    <Icon name="search" class="w-5 h-5 text-surface-400 absolute start-3 top-1/2 -translate-y-1/2" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        class="input ps-10 text-sm"
                        placeholder="ابحث باسم المجموعة أو المادة أو الصف..."
                    />
                </div>
                <span class="text-xs text-surface-500 font-bold">
                    عرض {{ filteredGroups.length }} من {{ groups.length }} مجموعة
                </span>
            </div>

            <!-- Groups Grid -->
            <section v-if="filteredGroups.length" class="grid md:grid-cols-2 gap-6">
                <article
                    v-for="group in filteredGroups"
                    :key="group.id"
                    class="card p-6 flex flex-col justify-between space-y-5 hover:border-primary-500/40 transition-colors"
                >
                    <div class="space-y-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-xl font-black text-surface-900 dark:text-white">{{ group.name }}</h2>
                                <div class="flex flex-wrap items-center gap-2 mt-1 text-xs text-surface-500">
                                    <span class="font-bold text-primary-600 dark:text-primary-400">{{ group.subject?.name }}</span>
                                    <span>·</span>
                                    <span>{{ group.grade?.name }}</span>
                                    <span v-if="group.term">· {{ group.term }}</span>
                                </div>
                            </div>
                            <span :class="group.is_active ? 'badge-green' : 'badge-gray'" class="text-xs">
                                {{ group.is_active ? 'نشطة' : 'متوقفة' }}
                            </span>
                        </div>

                        <!-- Capacity Bar -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-surface-600 dark:text-surface-300 font-bold flex items-center gap-1.5">
                                    <Icon name="users" class="w-3.5 h-3.5 text-primary-500" />
                                    <span>المقاعد المحجوزة</span>
                                </span>
                                <span class="font-bold font-mono text-surface-900 dark:text-white">
                                    {{ group.students_count }} / {{ group.capacity }}
                                </span>
                            </div>
                            <div class="w-full h-2 bg-surface-100 dark:bg-surface-700 rounded-full overflow-hidden">
                                <div
                                    class="h-full bg-primary-500 rounded-full transition-all duration-300"
                                    :style="{ width: `${Math.min(100, Math.round((group.students_count / (group.capacity || 1)) * 100))}%` }"
                                ></div>
                            </div>
                        </div>

                        <!-- Weekly Schedules -->
                        <div class="space-y-2">
                            <span class="text-xs font-bold text-surface-500">المواعيد الأسبوعية:</span>
                            <div v-if="group.schedules?.length" class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="s in group.schedules"
                                    :key="s.id"
                                    class="inline-flex items-center gap-1 rounded-lg bg-surface-100 dark:bg-surface-800 px-2.5 py-1 text-xs font-medium text-surface-700 dark:text-surface-300"
                                >
                                    <Icon name="clock" class="w-3 h-3 text-primary-500" />
                                    <span>{{ days[s.day_of_week] }} · {{ s.start_time?.slice(0, 5) }}</span>
                                </span>
                            </div>
                            <p v-else class="text-xs text-surface-400">لم يتم تحديد مواعيد لهذه المجموعة بعد.</p>
                        </div>

                        <!-- Enrolled Students Strip -->
                        <div class="rounded-xl bg-surface-50 dark:bg-surface-800/60 p-3 border border-surface-200 dark:border-surface-700 space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-bold text-surface-700 dark:text-surface-300 flex items-center gap-1.5">
                                    <Icon name="student" class="w-3.5 h-3.5 text-accent-500" />
                                    <span>طلاب المجموعة ({{ group.students?.length ?? 0 }})</span>
                                </span>
                                <button
                                    type="button"
                                    class="text-xs font-bold text-primary-600 hover:underline"
                                    @click="openStudentsModal(group)"
                                >
                                    عرض كل الطلاب
                                </button>
                            </div>

                            <div v-if="group.students?.length" class="flex flex-wrap items-center gap-1.5">
                                <div
                                    v-for="student in group.students.slice(0, 5)"
                                    :key="student.id"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-white dark:bg-surface-900 ps-1 pe-2.5 py-0.5 text-[11px] border border-surface-200 dark:border-surface-700 shadow-sm"
                                >
                                    <div class="w-5 h-5 rounded-full bg-primary-500/10 text-primary-600 flex items-center justify-center font-bold text-[9px] overflow-hidden shrink-0">
                                        <img v-if="student.avatar" :src="student.avatar" :alt="student.name" class="w-full h-full object-cover" />
                                        <span v-else>{{ student.name?.charAt(0) }}</span>
                                    </div>
                                    <span class="truncate max-w-[100px]">{{ student.name }}</span>
                                </div>
                                <span v-if="group.students.length > 5" class="text-[11px] font-bold text-surface-400">
                                    +{{ group.students.length - 5 }} آخرين
                                </span>
                            </div>
                            <p v-else class="text-xs text-surface-400">لا يوجد طلاب مسجلون حتى الآن.</p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="pt-3 border-t border-surface-100 dark:border-surface-700/60 flex flex-wrap items-center justify-between gap-2">
                        <button
                            type="button"
                            class="btn-primary btn-sm flex items-center gap-1.5"
                            @click="openStudentsModal(group)"
                        >
                            <Icon name="users" class="w-4 h-4" />
                            <span>قائمة الطلاب ({{ group.students?.length ?? 0 }})</span>
                        </button>

                        <div class="flex items-center gap-1.5">
                            <Link v-if="group.assignment_id" :href="route('teacher.curriculum', { assignment: group.assignment_id })" class="btn-outline btn-sm">المنهج</Link>
                            <Link :href="route('teacher.materials', { groupId: group.id })" class="btn-outline btn-sm">المواد</Link>
                            <Link :href="route('teacher.worksheets.index', { groupId: group.id })" class="btn-ghost btn-sm">الواجبات</Link>
                        </div>
                    </div>
                </article>
            </section>

            <div v-else class="card p-12 text-center text-surface-400">
                <Icon name="courses" class="w-12 h-12 mx-auto mb-3 opacity-50" />
                <p class="text-base font-bold text-surface-700 dark:text-surface-300">لا توجد مجموعات تطابق البحث.</p>
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
    </DashboardLayout>
</template>
