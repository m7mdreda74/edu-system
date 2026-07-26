<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    gradeLevel: { type: Object, required: true },
    subjects:   { type: Array, required: true },
    assignments: { type: Array, required: true },
    stats:       { type: Object, default: () => ({}) },
    students:   { type: Array, required: true },
    teachers:   { type: Array, required: true },
});

const activeTab = ref('subjects'); // subjects, groups, teachers, students

// Groups live under assignments; flatten once for the table.
const groups = computed(() => props.assignments.flatMap(assignment =>
    (assignment.groups ?? []).map(group => ({
        ...group,
        subject: assignment.subject?.name,
        teacher: assignment.teacher?.name,
    })),
));
</script>

<template>
    <DashboardLayout>
        <Head :title="`تفاصيل المرحلة: ${gradeLevel.name}`" />

        <div class="container-app px-4 py-8">
            <!-- Back & Header -->
            <div class="mb-8">
                <Link :href="route('admin.grade-levels')" class="flex items-center gap-2 text-sm text-surface-500 hover:text-primary-500 transition-colors mb-4">
                    <Icon name="arrow-right" class="w-4 h-4 rtl:rotate-180" />
                    <span>العودة للمراحل الدراسية</span>
                </Link>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-3xl font-black text-surface-900 dark:text-white">{{ gradeLevel.name }}</h1>
                            <span :class="gradeLevel.is_active ? 'badge-green' : 'badge-gray'">
                                {{ gradeLevel.is_active ? 'نشط' : 'غير نشط' }}
                            </span>
                        </div>
                        <p class="text-surface-500 mt-1">الرمز التعريفي: {{ gradeLevel.key }} | {{ gradeLevel.name_en || 'بدون اسم إنجليزي' }}</p>
                    </div>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="card p-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 text-indigo-500 flex items-center justify-center flex-shrink-0">
                        <Icon name="courses" class="w-6 h-6" />
                    </div>
                    <div>
                        <span class="block text-2xl font-black text-surface-900 dark:text-white">{{ subjects.length }}</span>
                        <span class="text-xs text-surface-400">المواد الدراسية</span>
                    </div>
                </div>
                <div class="card p-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-500 flex items-center justify-center flex-shrink-0">
                        <Icon name="courses" class="w-6 h-6" />
                    </div>
                    <div>
                        <span class="block text-2xl font-black text-surface-900 dark:text-white">{{ groups.length }}</span>
                        <span class="text-xs text-surface-400">مجموعات التدريس</span>
                    </div>
                </div>
                <div class="card p-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center flex-shrink-0">
                        <Icon name="student" class="w-6 h-6" />
                    </div>
                    <div>
                        <span class="block text-2xl font-black text-surface-900 dark:text-white">{{ students.length }}</span>
                        <span class="text-xs text-surface-400">الطلاب</span>
                    </div>
                </div>
                <div class="card p-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-rose-500/10 text-rose-500 flex items-center justify-center flex-shrink-0">
                        <Icon name="teacher" class="w-6 h-6" />
                    </div>
                    <div>
                        <span class="block text-2xl font-black text-surface-900 dark:text-white">{{ teachers.length }}</span>
                        <span class="text-xs text-surface-400">المعلمون</span>
                    </div>
                </div>
            </div>

            <!-- Tab Buttons -->
            <div class="flex border-b border-surface-200 dark:border-surface-800 mb-6 overflow-x-auto whitespace-nowrap">
                <button @click="activeTab = 'subjects'" 
                        :class="[
                            'px-6 py-3 font-semibold text-sm border-b-2 transition-all duration-200',
                            activeTab === 'subjects' 
                                ? 'border-primary-500 text-primary-600 dark:text-primary-400 font-bold'
                                : 'border-transparent text-surface-500 hover:text-surface-800 dark:hover:text-white'
                        ]"
                >
                    المواد الدراسية ({{ subjects.length }})
                </button>
                <button @click="activeTab = 'groups'" 
                        :class="[
                            'px-6 py-3 font-semibold text-sm border-b-2 transition-all duration-200',
                            activeTab === 'groups' 
                                ? 'border-primary-500 text-primary-600 dark:text-primary-400 font-bold'
                                : 'border-transparent text-surface-500 hover:text-surface-800 dark:hover:text-white'
                        ]"
                >
                    المجموعات ({{ groups.length }})
                </button>
                <button @click="activeTab = 'teachers'" 
                        :class="[
                            'px-6 py-3 font-semibold text-sm border-b-2 transition-all duration-200',
                            activeTab === 'teachers' 
                                ? 'border-primary-500 text-primary-600 dark:text-primary-400 font-bold'
                                : 'border-transparent text-surface-500 hover:text-surface-800 dark:hover:text-white'
                        ]"
                >
                    المعلمون ({{ teachers.length }})
                </button>
                <button @click="activeTab = 'students'" 
                        :class="[
                            'px-6 py-3 font-semibold text-sm border-b-2 transition-all duration-200',
                            activeTab === 'students' 
                                ? 'border-primary-500 text-primary-600 dark:text-primary-400 font-bold'
                                : 'border-transparent text-surface-500 hover:text-surface-800 dark:hover:text-white'
                        ]"
                >
                    الطلاب ({{ students.length }})
                </button>
            </div>

            <!-- Tab Content -->
            <div class="card p-6">
                <!-- 1. Subjects Tab -->
                <div v-if="activeTab === 'subjects'">
                    <h3 class="text-lg font-bold text-surface-900 dark:text-white mb-4">المواد الدراسية المسجلة للمرحلة</h3>
                    <div class="overflow-x-auto" v-if="subjects.length > 0">
                        <table class="table-app w-full text-start">
                            <thead>
                                <tr>
                                    <th class="text-start">اسم المادة</th>
                                    <th class="text-start">الاسم بالإنجليزية</th>
                                    <th class="text-start">عدد المعلمين</th>
                                    <th class="text-start">الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="subj in subjects" :key="subj.id" class="hover:bg-surface-50/50">
                                    <td class="font-bold text-surface-900 dark:text-white">
                                        <div class="flex items-center gap-2">
                                            <Icon :name="subj.icon || 'book'" class="w-4 h-4 text-primary-500" />
                                            <span>{{ subj.name }}</span>
                                        </div>
                                    </td>
                                    <td>{{ subj.name_en || '-' }}</td>
                                    <td>{{ assignments.filter(a => a.subject?.id === subj.id).length }} معلم</td>
                                    <td>
                                        <span :class="subj.is_active ? 'badge-green' : 'badge-gray'">
                                            {{ subj.is_active ? 'نشط' : 'غير نشط' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-center py-8 text-surface-500">
                        لا توجد مواد دراسية مرتبطة بهذه المرحلة حالياً.
                    </div>
                </div>

                <!-- 2. Groups Tab -->
                <div v-if="activeTab === 'groups'">
                    <h3 class="text-lg font-bold text-surface-900 dark:text-white mb-4">مجموعات التدريس في هذه المرحلة</h3>
                    <div class="overflow-x-auto" v-if="groups.length > 0">
                        <table class="table-app w-full text-start">
                            <thead>
                                <tr>
                                    <th class="text-start">اسم المجموعة</th>
                                    <th class="text-start">المادة</th>
                                    <th class="text-start">المعلم</th>
                                    <th class="text-start">الاشتراك الشهري</th>
                                    <th class="text-start">الطلاب</th>
                                    <th class="text-start">الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="group in groups" :key="group.id" class="hover:bg-surface-50/50">
                                    <td class="font-bold text-surface-900 dark:text-white">
                                        {{ group.name }}
                                    </td>
                                    <td>{{ group.subject || '-' }}</td>
                                    <td>{{ group.teacher || '-' }}</td>
                                    <td>{{ group.monthly_price / 100 }} ر.ق</td>
                                    <td>{{ group.students_count }} / {{ group.capacity }}</td>
                                    <td>
                                        <span :class="group.is_active ? 'badge-green' : 'badge-gray'">
                                            {{ group.is_active ? 'مفعّلة' : 'متوقفة' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-center py-8 text-surface-500">
                        لا توجد مجموعات تدريس في هذه المرحلة بعد.
                    </div>
                </div>

                <!-- 3. Teachers Tab -->
                <div v-if="activeTab === 'teachers'">
                    <h3 class="text-lg font-bold text-surface-900 dark:text-white mb-4">أعضاء هيئة التدريس النشطين في المرحلة</h3>
                    <div class="overflow-x-auto" v-if="teachers.length > 0">
                        <table class="table-app w-full text-start">
                            <thead>
                                <tr>
                                    <th class="text-start">اسم المعلم</th>
                                    <th class="text-start">البريد الإلكتروني</th>
                                    <th class="text-start">السيرة الذاتية</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="teacher in teachers" :key="teacher.id" class="hover:bg-surface-50/50">
                                    <td class="font-bold text-surface-900 dark:text-white">
                                        {{ teacher.name }}
                                    </td>
                                    <td>{{ teacher.email }}</td>
                                    <td class="max-w-md truncate text-surface-500">{{ teacher.bio || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-center py-8 text-surface-500">
                        لا يوجد معلمون مسندون لهذه المرحلة حالياً.
                    </div>
                </div>

                <!-- 4. Students Tab -->
                <div v-if="activeTab === 'students'">
                    <h3 class="text-lg font-bold text-surface-900 dark:text-white mb-4">الطلاب المسجلين في هذه المرحلة</h3>
                    <div class="overflow-x-auto" v-if="students.length > 0">
                        <table class="table-app w-full text-start">
                            <thead>
                                <tr>
                                    <th class="text-start">اسم الطالب</th>
                                    <th class="text-start">البريد الإلكتروني</th>
                                    <th class="text-start">الهاتف</th>
                                    <th class="text-start">حالة الحساب</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="student in students" :key="student.id" class="hover:bg-surface-50/50">
                                    <td class="font-bold text-surface-900 dark:text-white">
                                        {{ student.name }}
                                    </td>
                                    <td>{{ student.email }}</td>
                                    <td>{{ student.phone || '-' }}</td>
                                    <td>
                                        <span :class="student.is_active ? 'badge-green' : 'badge-red'">
                                            {{ student.is_active ? 'نشط' : 'معطل' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-center py-8 text-surface-500">
                        لا يوجد طلاب مقيدين في هذه المرحلة حالياً.
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
