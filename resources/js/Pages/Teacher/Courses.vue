<script setup>
import { Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    courses: { type: Array, default: () => [] },
});

function formatQAR(halala) {
    if (!halala) return 'مجاني';
    return new Intl.NumberFormat('ar-QA', { style: 'currency', currency: 'QAR', minimumFractionDigits: 0 })
        .format(halala / 100);
}

function deleteCourse(courseId) {
    if (!confirm('هل أنت متأكد من حذف هذا الكورس؟')) return;
    router.delete(route('teacher.courses.destroy', { id: courseId }), { preserveScroll: true });
}
</script>

<template>
    <DashboardLayout>
        <Head title="كورساتي" />

        <div class="container-app px-4 py-10">

            <!-- Header -->
            <div class="flex items-center justify-between gap-4 mb-8">
                <h1 class="text-3xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                    <Icon name="courses" class="w-8 h-8 text-primary-500" />
                    <span>كورساتي</span>
                </h1>
                <Link :href="route('teacher.courses.create')" class="btn-primary flex items-center gap-2">
                    <Icon name="plus" class="w-4 h-4" />
                    <span>كورس جديد</span>
                </Link>
            </div>

            <!-- Empty state -->
            <div v-if="!courses.length" class="card p-16 text-center">
                <div class="text-center text-surface-400 py-12 flex flex-col items-center justify-center gap-2">
                    <Icon name="courses" class="w-12 h-12 text-surface-400" />
                    <p>لم تقم بإنشاء أي كورس بعد</p>
                </div>
                <p class="text-surface-400 mb-6 text-sm">ابدأ بإنشاء كورسك الأول وشارك معرفتك</p>
                <Link :href="route('teacher.courses.create')" class="btn-primary btn-lg">
                    <span>أنشئ كورسك الأول</span>
                </Link>
            </div>

            <!-- Courses Grid -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                <div v-for="course in courses" :key="course.id"
                     class="card overflow-hidden hover:shadow-xl transition-shadow duration-300 group">

                    <!-- Thumbnail -->
                    <div class="relative h-40 bg-surface-100 dark:bg-surface-800 overflow-hidden">
                        <img v-if="course.thumbnail"
                             :src="course.thumbnail" :alt="course.title"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                        <div v-else class="w-full h-full flex items-center justify-center text-5xl bg-surface-100 dark:bg-surface-800 text-surface-400">
                            <Icon name="courses" class="w-16 h-16" />
                        </div>

                        <!-- Status badge -->
                        <div class="absolute top-3 start-3">
                            <span :class="course.is_published ? 'badge-green' : 'badge-gray'" class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full" :class="course.is_published ? 'bg-green-500' : 'bg-surface-400 dark:bg-surface-500'"></span>
                                <span>{{ course.is_published ? 'منشور' : 'مسودة' }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="p-5">
                        <h3 class="font-bold text-surface-900 dark:text-white line-clamp-2 leading-snug mb-2">
                            {{ course.title }}
                        </h3>

                        <div class="flex flex-wrap gap-4 text-xs text-surface-400 dark:text-surface-500 mt-2">
                            <span class="flex items-center gap-1">
                                <Icon name="users" class="w-4 h-4" />
                                <span>{{ course.enrollments_count }} طالب</span>
                            </span>
                            <span class="flex items-center gap-1">
                                <Icon name="courses" class="w-4 h-4" />
                                <span>{{ course.total_lessons }} درس</span>
                            </span>
                            <span class="text-primary-700 dark:text-primary-400 font-semibold">
                                {{ formatQAR(course.discount_price ?? course.price) }}
                            </span>
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-wrap gap-2 mt-4">
                            <Link
                                :href="route('teacher.courses.edit', { id: course.id })"
                                class="btn-outline btn-sm flex-1 flex items-center justify-center gap-1"
                            >
                                <Icon name="edit" class="w-4 h-4" />
                                <span>تعديل</span>
                            </Link>
                            <Link
                                :href="route('teacher.lessons', { id: course.id })"
                                class="btn-outline btn-sm flex-1 flex items-center justify-center gap-1"
                            >
                                <Icon name="courses" class="w-4 h-4" />
                                <span>الدروس</span>
                            </Link>
                            <Link
                                :href="route('teacher.worksheets.index', { id: course.id })"
                                class="btn-outline btn-sm flex-1 flex items-center justify-center gap-1"
                            >
                                <span>أوراق العمل</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
