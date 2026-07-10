<script setup>
import { Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    stats:             { type: Object, required: true },
    recentEnrollments: { type: Array,  default: () => [] },
    courses:           { type: Array,  default: () => [] },
});

function formatQAR(halala) {
    return new Intl.NumberFormat('ar-QA', { style: 'currency', currency: 'QAR', minimumFractionDigits: 0 })
        .format((halala ?? 0) / 100);
}
</script>

<template>
    <DashboardLayout>
        <Head title="لوحة المدرس" />

        <div class="container-app px-4 py-10">

            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white">
                        أهلاً، {{ $page.props.auth.user?.name?.split(' ')[0] }}
                    </h1>
                    <p class="text-surface-500 dark:text-surface-400 mt-1">لوحة تحكم المدرس</p>
                </div>
                <Link :href="route('teacher.courses.create')" class="btn-primary flex items-center gap-2">
                    <Icon name="plus" class="w-4 h-4" />
                    <span>كورس جديد</span>
                </Link>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
                <div v-for="stat in [
                    { label: 'كورساتك',          value: stats.total_courses,        icon: 'courses', color: 'primary' },
                    { label: 'إجمالي الطلاب',    value: stats.total_students,       icon: 'student', color: 'accent' },
                    { label: 'أكملوا الكورسات',  value: stats.completed_students,   icon: 'success', color: 'green' },
                    { label: 'الإيرادات',         value: formatQAR(stats.total_revenue), icon: 'earnings', color: 'primary' },
                ]" :key="stat.label"
                    class="card p-5 text-center flex flex-col items-center justify-center"
                >
                    <div class="p-3 rounded-2xl mb-3" :class="{
                        'bg-primary-50 text-primary-600 dark:bg-primary-950/50 dark:text-primary-400': stat.color === 'primary',
                        'bg-accent-50 text-accent-600 dark:bg-accent-950/50 dark:text-accent-400': stat.color === 'accent',
                        'bg-green-50 text-green-600 dark:bg-green-950/50 dark:text-green-400': stat.color === 'green',
                    }">
                        <Icon :name="stat.icon" class="w-8 h-8" />
                    </div>
                    <div class="text-2xl font-black text-primary-700 dark:text-primary-400">{{ stat.value }}</div>
                    <div class="text-xs text-surface-500 dark:text-surface-400 mt-1">{{ stat.label }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                <!-- Courses Performance -->
                <div class="card p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="font-bold text-surface-800 dark:text-white">كورساتك</h2>
                        <Link :href="route('teacher.courses')" class="text-primary-600 text-xs hover:underline">
                            إدارة الكورسات
                        </Link>
                    </div>

                    <div v-if="courses.length" class="space-y-3">
                        <div v-for="course in courses.slice(0, 5)" :key="course.id"
                             class="flex items-center gap-3 p-3 rounded-xl hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors">
                            <div class="w-12 h-10 rounded-lg overflow-hidden bg-surface-200 dark:bg-surface-700 flex-shrink-0">
                                <img v-if="course.thumbnail" :src="course.thumbnail" :alt="course.title"
                                     class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-surface-400 bg-surface-100 dark:bg-surface-800">
                                    <Icon name="courses" class="w-5 h-5" />
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-surface-800 dark:text-white text-sm line-clamp-1">
                                    {{ course.title }}
                                </div>
                                <div class="text-xs text-surface-400 flex items-center gap-2">
                                    <span>{{ course.enrollments_count }} طالب</span>
                                    <span v-if="course.avg_rating">⭐ {{ Number(course.avg_rating).toFixed(1) }}</span>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <span :class="course.is_published ? 'badge-green' : 'badge-gray'" class="text-xs">
                                    {{ course.is_published ? 'منشور' : 'مسودة' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center p-10 text-surface-400 flex flex-col items-center justify-center">
                        <div class="p-3 bg-surface-100 dark:bg-surface-800 rounded-full text-primary-500 mb-3">
                            <Icon name="courses" class="w-10 h-10" />
                        </div>
                        <p class="text-sm mb-4">لم تنشئ أي كورس بعد</p>
                        <Link :href="route('teacher.courses.create')" class="btn-primary btn-sm">
                            ابدأ الآن
                        </Link>
                    </div>
                </div>

                <!-- Recent Enrollments -->
                <div class="card p-6">
                    <h2 class="font-bold text-surface-800 dark:text-white mb-5">آخر التسجيلات</h2>

                    <div v-if="recentEnrollments.length" class="space-y-3">
                        <div v-for="enrollment in recentEnrollments" :key="enrollment.id"
                             class="flex items-center gap-3 p-3 rounded-xl hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors">
                            <div class="avatar-md bg-primary-100 dark:bg-primary-900 flex-shrink-0">
                                <span class="text-primary-700 font-bold text-sm">
                                    {{ enrollment.user?.name?.charAt(0) }}
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-surface-800 dark:text-white text-sm">
                                    {{ enrollment.user?.name }}
                                </div>
                                <div class="text-xs text-surface-400 line-clamp-1">{{ enrollment.course?.title }}</div>
                            </div>
                            <div class="text-xs text-surface-400 flex-shrink-0">
                                {{ enrollment.progress_percent }}%
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center p-10 text-surface-400 text-sm">
                        لا يوجد طلاب مسجلون بعد
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
