<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import StatCard from '@/Components/StatCard.vue';

const props = defineProps({
    students: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.q || '');
const selectedGroup = ref(props.filters.group_id || '');

function applyFilter() {
    router.get(route('teacher.students.index'), {
        q: search.value || undefined,
        group_id: selectedGroup.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilter() {
    search.value = '';
    selectedGroup.value = '';
    applyFilter();
}

function formatDate(val) {
    if (!val) return '—';
    return new Date(val).toLocaleDateString('ar-EG', { dateStyle: 'medium' });
}
</script>

<template>
    <DashboardLayout>
        <Head title="قائمة طلابي" />

        <div class="container-app px-4 py-8 space-y-7">
            <header class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white flex items-center gap-3">
                        <Icon name="student" class="w-8 h-8 text-primary-500" />
                        <span>قائمة طلابي</span>
                    </h1>
                    <p class="text-surface-500 dark:text-surface-400 mt-1">
                        دليل شامل لجميع الطلاب المشتركين معك في مجموعاتك التعليمية والجلسات الخاصة.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Link :href="route('teacher.groups.index')" class="btn-outline flex items-center gap-2">
                        <Icon name="courses" class="w-4 h-4" />
                        <span>عرض المجموعات</span>
                    </Link>
                    <Link :href="route('chat.index')" class="btn-primary flex items-center gap-2">
                        <Icon name="chat" class="w-4 h-4" />
                        <span>مركز الرسائل</span>
                    </Link>
                </div>
            </header>

            <!-- Stats Grid -->
            <section class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                <StatCard label="إجمالي الطلاب" :value="stats.total_students || students.length" icon="student" tone="primary" />
                <StatCard label="طلاب المجموعات" :value="stats.group_students || 0" icon="courses" tone="accent" />
                <StatCard label="طلاب الجلسات الخاصة" :value="stats.private_students || 0" icon="users" tone="green" />
            </section>

            <!-- Filter Controls -->
            <div class="card p-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3 flex-1">
                    <div class="relative min-w-[220px] flex-1">
                        <Icon name="search" class="w-5 h-5 text-surface-400 absolute start-3 top-1/2 -translate-y-1/2" />
                        <input
                            v-model="search"
                            type="text"
                            class="input ps-10 text-sm"
                            placeholder="ابحث باسم الطالب أو البريد..."
                            @keyup.enter="applyFilter"
                        />
                    </div>

                    <select v-model="selectedGroup" class="input text-sm w-auto min-w-[180px]" @change="applyFilter">
                        <option value="">جميع المجموعات</option>
                        <option v-for="g in groups" :key="g.id" :value="g.id">
                            {{ g.name }} ({{ g.subject }})
                        </option>
                    </select>

                    <button type="button" class="btn-primary btn-sm" @click="applyFilter">
                        تطبيق
                    </button>
                    <button v-if="search || selectedGroup" type="button" class="btn-ghost btn-sm" @click="resetFilter">
                        إلغاء الفلتر
                    </button>
                </div>

                <span class="text-xs text-surface-500 font-bold">
                    إجمالي النتائج: {{ students.length }} طالب
                </span>
            </div>

            <!-- Students List / Table -->
            <div class="card data-table-card">
                <div class="data-table-scroll no-scrollbar">
                    <table class="data-table">
                        <thead class="bg-surface-50 dark:bg-surface-800 border-b border-surface-200 dark:border-surface-700">
                            <tr>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">الطالب</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">المجموعات المشترك بها</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">المواد</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">النوع</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">حضور الحصص</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">تاريخ الانضمام</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-700">
                            <tr
                                v-for="student in students"
                                :key="student.id"
                                class="hover:bg-surface-50 dark:hover:bg-surface-800/60 transition-colors"
                            >
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-primary-500/10 text-primary-600 flex items-center justify-center font-bold text-sm overflow-hidden shrink-0">
                                            <img v-if="student.avatar" :src="student.avatar" :alt="student.name" class="w-full h-full object-cover" />
                                            <span v-else>{{ student.name?.charAt(0) }}</span>
                                        </div>
                                        <div>
                                            <p class="font-bold text-surface-900 dark:text-white text-sm">{{ student.name }}</p>
                                            <p class="text-xs text-surface-400 font-mono">{{ student.email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div v-if="student.groups?.length" class="flex flex-wrap gap-1">
                                        <span
                                            v-for="gName in student.groups"
                                            :key="gName"
                                            class="inline-flex items-center rounded-lg bg-primary-500/10 px-2 py-0.5 text-xs font-medium text-primary-700 dark:text-primary-300"
                                        >
                                            {{ gName }}
                                        </span>
                                    </div>
                                    <span v-else class="text-xs text-surface-400">—</span>
                                </td>
                                <td class="p-4">
                                    <div v-if="student.subjects?.length" class="flex flex-wrap gap-1">
                                        <span
                                            v-for="subj in student.subjects"
                                            :key="subj"
                                            class="inline-flex items-center rounded-lg bg-surface-100 dark:bg-surface-800 px-2 py-0.5 text-xs text-surface-700 dark:text-surface-300"
                                        >
                                            {{ subj }}
                                        </span>
                                    </div>
                                    <span v-else class="text-xs text-surface-400">—</span>
                                </td>
                                <td class="p-4">
                                    <div class="flex flex-wrap gap-1">
                                        <span v-if="student.has_group" class="badge-primary text-[10px]">مجموعة</span>
                                        <span v-if="student.has_private" class="badge-accent text-[10px]">برايفيت</span>
                                    </div>
                                </td>
                                <td class="p-4 font-mono text-xs">
                                    <span class="font-bold text-surface-800 dark:text-surface-200">
                                        {{ student.attended_sessions }}
                                    </span>
                                    <span class="text-surface-400"> حصة</span>
                                </td>
                                <td class="p-4 font-mono text-xs text-surface-500">
                                    {{ formatDate(student.joined_at) }}
                                </td>
                                <td class="p-4">
                                    <Link :href="route('chat.index')" class="btn-outline btn-sm flex items-center gap-1">
                                        <Icon name="chat" class="w-3.5 h-3.5 text-primary-500" />
                                        <span>محادثة</span>
                                    </Link>
                                </td>
                            </tr>

                            <tr v-if="students.length === 0">
                                <td colspan="7" class="p-12 text-center text-surface-400">
                                    <Icon name="student" class="w-12 h-12 mx-auto mb-3 opacity-40" />
                                    <p class="font-bold text-surface-700 dark:text-surface-300">لا يوجد طلاب مطابقون للشروط حالياً.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
