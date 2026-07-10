<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CourseCard from '@/Components/CourseCard.vue';

const props = defineProps({
    teacher:           { type: Object, required: true },
    courses:           { type: Array,  default: () => [] },
    totalStudents:     { type: Number, default: 0 },
    totalCourses:      { type: Number, default: 0 },
    averageRating:     { type: Number, default: 0 },
});

function formatRating(r) {
    return Number(r).toFixed(1);
}
</script>

<template>
    <AppLayout>
        <Head :title="`${teacher.name} — المدرس`" />

        <!-- Hero Banner -->
        <div class="hero-gradient py-16 px-4">
            <div class="container-app">
                <div class="flex flex-col sm:flex-row items-center gap-8">

                    <!-- Avatar -->
                    <div class="w-32 h-32 rounded-2xl overflow-hidden border-4 border-white/30 shadow-xl flex-shrink-0 bg-white/10">
                        <img v-if="teacher.avatar" :src="teacher.avatar" :alt="teacher.name"
                             class="w-full h-full object-cover" />
                        <div v-else class="w-full h-full flex items-center justify-center text-5xl">👨‍🏫</div>
                    </div>

                    <!-- Info -->
                    <div class="text-center sm:text-start text-white">
                        <h1 class="text-3xl font-black mb-2">{{ teacher.name }}</h1>
                        <p v-if="teacher.bio" class="text-white/75 max-w-xl leading-relaxed mb-4">
                            {{ teacher.bio }}
                        </p>
                        <div class="flex flex-wrap gap-6 justify-center sm:justify-start">
                            <div v-for="stat in [
                                { value: totalCourses,            label: 'كورس' },
                                { value: totalStudents,           label: 'طالب' },
                                { value: formatRating(averageRating) + '★', label: 'تقييم' },
                            ]" :key="stat.label" class="text-center">
                                <div class="text-2xl font-black">{{ stat.value }}</div>
                                <div class="text-white/60 text-xs">{{ stat.label }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Courses -->
        <div class="container-app px-4 py-12">
            <h2 class="text-2xl font-black text-surface-900 dark:text-white mb-6">
                كورسات المدرس ({{ courses.length }})
            </h2>

            <div v-if="!courses.length" class="card p-16 text-center text-surface-400">
                <div class="text-5xl mb-4">📭</div>
                <p>لا توجد كورسات منشورة حتى الآن.</p>
            </div>

            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <CourseCard v-for="course in courses" :key="course.id" :course="course" />
            </div>
        </div>
    </AppLayout>
</template>
