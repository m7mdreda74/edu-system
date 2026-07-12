<script setup>
import { computed, onMounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    inProgress: { type: Array, default: () => [] },
    completed:  { type: Array, default: () => [] },
    upcomingSessions: { type: Array, default: () => [] },
    stats:      { type: Object, default: () => ({}) },
});

function formatDuration(seconds) {
    if (!seconds) return null;
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return h > 0 ? `${h}س ${m}د` : `${m} د`;
}

const allEnrollments = computed(() => [...props.inProgress, ...props.completed]);

onMounted(() => {
    if (window.location.hash === '#completed-courses') {
        setTimeout(() => {
            const el = document.getElementById('completed-courses');
            if (el) {
                el.scrollIntoView({ behavior: 'smooth' });
            }
        }, 300);
    }
});
</script>

<template>
    <DashboardLayout>
        <Head title="لوحة التحكم" />

        <div class="container-app px-4 py-10">

            <!-- ── Welcome + Stats ─────────────────────────────── -->
            <div class="mb-10">
                <h1 class="text-3xl font-black text-surface-900 dark:text-white mb-1">
                    أهلاً، {{ $page.props.auth.user?.name?.split(' ')[0] }}
                </h1>
                <p class="text-surface-500 dark:text-surface-400">تابع تقدمك في كورساتك</p>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
                <div v-for="stat in [
                    { label: 'إجمالي الكورسات', value: stats.totalEnrolled, icon: 'courses', color: 'primary' },
                    { label: 'قيد التعلم',       value: stats.inProgress,    icon: 'courses', color: 'accent' },
                    { label: 'مكتملة',            value: stats.completed,     icon: 'success', color: 'green' },
                    { label: 'متوسط التقدم',      value: stats.avgProgress + '%', icon: 'progress', color: 'primary' },
                ]" :key="stat.label"
                    class="card p-5 text-center flex flex-col items-center justify-center transition-all duration-300 transform hover:scale-102 hover:shadow-card-hover"
                >
                    <div class="p-3 rounded-2xl mb-3" :class="{
                        'bg-primary-50 text-primary-600 dark:bg-primary-950/50 dark:text-primary-400': stat.color === 'primary',
                        'bg-accent-50 text-accent-600 dark:bg-accent-950/50 dark:text-accent-400': stat.color === 'accent',
                        'bg-green-50 text-green-600 dark:bg-green-950/50 dark:text-green-400': stat.color === 'green',
                    }">
                        <Icon :name="stat.icon" class="w-8 h-8" />
                    </div>
                    <div class="text-2xl font-black text-surface-900 dark:text-white">{{ stat.value }}</div>
                    <div class="text-xs text-surface-500 dark:text-surface-400 mt-1">{{ stat.label }}</div>
                </div>
            </div>

            <!-- ── Upcoming Live Sessions ──────────────────────── -->
            <section v-if="upcomingSessions && upcomingSessions.length" class="mb-10">
                <h2 class="text-xl font-bold text-surface-900 dark:text-white mb-5 flex items-center gap-2">
                    <Icon name="live" class="w-6 h-6 text-red-500 animate-pulse" />
                    <span>الحصص المباشرة القادمة</span>
                    <span class="badge-accent">{{ upcomingSessions.length }}</span>
                </h2>

                <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-5">
                    <div v-for="session in upcomingSessions" :key="session.id"
                         class="card p-5 border-l-4 transition-all duration-300 transform hover:-translate-y-1 hover:shadow-card-hover" :class="session.status === 'live' ? 'border-accent-500 bg-accent-50/30 dark:bg-accent-950/20' : 'border-primary-500'">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <h3 class="font-bold text-surface-900 dark:text-white text-lg leading-tight">{{ session.title }}</h3>
                                <div class="text-xs text-surface-500 mt-1 flex items-center gap-1.5">
                                    <Icon name="courses" class="w-4 h-4 text-surface-400" />
                                    <span>{{ session.course?.title }}</span>
                                </div>
                            </div>
                            <span v-if="session.status === 'live'" class="badge-accent animate-pulse">مباشر الآن!</span>
                            <span v-else class="badge-primary text-xs flex items-center gap-1">
                                <Icon name="clock" class="w-3.5 h-3.5" />
                                {{ new Date(session.scheduled_at).toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' }) }}
                            </span>
                        </div>
                        
                        <p v-if="session.description" class="text-sm text-surface-600 dark:text-surface-300 line-clamp-2 mb-4">
                            {{ session.description }}
                        </p>
                        
                        <div class="flex items-center justify-between mt-auto pt-4 border-t border-surface-100 dark:border-surface-800">
                            <div class="text-xs text-surface-500 flex items-center gap-1">
                                <Icon name="teacher" class="w-4 h-4 text-surface-400" />
                                <span>{{ session.teacher?.name }}</span>
                            </div>
                            
                            <a v-if="session.status === 'live'" :href="route('live-sessions.room', session.id)" target="_blank" class="btn-accent btn-sm transition-all duration-200 hover:scale-105">
                                دخول الحصة
                            </a>
                            <button v-else-if="session.status === 'scheduled'" disabled class="btn-sm bg-surface-100 text-surface-400 dark:bg-surface-800 cursor-not-allowed">
                                يبدأ قريباً...
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── In Progress Courses ─────────────────────────── -->
            <section v-if="inProgress.length" class="mb-10">
                <h2 class="text-xl font-bold text-surface-900 dark:text-white mb-5 flex items-center gap-2">
                    <Icon name="courses" class="w-6 h-6 text-primary-500" />
                    <span>قيد التعلم</span>
                    <span class="badge-primary">{{ inProgress.length }}</span>
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                    <div v-for="enrollment in inProgress" :key="enrollment.id"
                         class="card-hover p-5 flex flex-col gap-4">

                        <!-- Course thumbnail + info -->
                        <div class="flex gap-3">
                            <div class="w-16 h-16 rounded-xl overflow-hidden bg-surface-200 dark:bg-surface-700 flex-shrink-0">
                                <img v-if="enrollment.course?.thumbnail"
                                     :src="enrollment.course.thumbnail"
                                     :alt="enrollment.course.title"
                                     class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-surface-400 bg-surface-100 dark:bg-surface-800">
                                    <Icon name="courses" class="w-6 h-6" />
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div v-if="enrollment.course?.subject" class="badge-gray text-xs mb-1">
                                    {{ enrollment.course.subject.name }}
                                </div>
                                <h3 class="font-bold text-surface-800 dark:text-white text-sm leading-snug line-clamp-2">
                                    {{ enrollment.course?.title }}
                                </h3>
                                <p v-if="enrollment.course?.teacher" class="text-xs text-surface-500 dark:text-surface-400 mt-1">
                                    {{ enrollment.course.teacher.name }}
                                </p>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div>
                            <div class="flex justify-between text-xs text-surface-500 dark:text-surface-400 mb-1.5">
                                <span>التقدم</span>
                                <span class="font-bold text-primary-700 dark:text-primary-400">
                                    {{ enrollment.progress_percent }}%
                                </span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-bar-fill"
                                     :style="{ width: enrollment.progress_percent + '%' }">
                                </div>
                            </div>
                            <div class="flex justify-between text-xs text-surface-400 dark:text-surface-500 mt-1">
                                <span>
                                    {{ enrollment.course?.total_lessons || 0 }} درس
                                </span>
                                <span v-if="enrollment.course?.total_duration" class="flex items-center gap-1">
                                    <Icon name="clock" class="w-3.5 h-3.5" />
                                    {{ formatDuration(enrollment.course.total_duration) }}
                                </span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-2 mt-auto">
                            <Link
                                :href="route('student.learn', { slug: enrollment.course?.slug })"
                                class="btn-primary flex-1 text-center transition-all duration-200"
                                id="continue-course-btn"
                            >
                                متابعة التعلم
                            </Link>
                            <form v-if="enrollment.course?.teacher_id" @submit.prevent="router.post(route('chat.start'), { course_id: enrollment.course.id, teacher_id: enrollment.course.teacher_id })" class="shrink-0">
                                <button type="submit" class="btn-outline px-3.5 hover:bg-surface-100 dark:hover:bg-surface-700 py-2.5 h-full rounded-xl" title="راسل المدرس">
                                    <Icon name="chat" class="w-5 h-5 text-primary-500" />
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── Completed Courses ────────────────────────────── -->
            <section v-if="completed.length" id="completed-courses">
                <h2 class="text-xl font-bold text-surface-900 dark:text-white mb-5 flex items-center gap-2">
                    <Icon name="success" class="w-6 h-6 text-green-500" />
                    <span>الكورسات المكتملة</span>
                    <span class="badge-green">{{ completed.length }}</span>
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                    <div v-for="enrollment in completed" :key="enrollment.id"
                         class="card p-5 flex flex-col gap-4 border-2 border-green-100 dark:border-green-900">

                        <div class="flex gap-3">
                            <div class="w-16 h-16 rounded-xl overflow-hidden bg-surface-200 dark:bg-surface-700 flex-shrink-0">
                                <img v-if="enrollment.course?.thumbnail"
                                     :src="enrollment.course.thumbnail"
                                     class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-surface-400 bg-surface-100 dark:bg-surface-800">
                                    <Icon name="courses" class="w-6 h-6" />
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-surface-800 dark:text-white text-sm line-clamp-2">
                                    {{ enrollment.course?.title }}
                                </h3>
                                <div class="badge-green text-xs mt-1">مكتمل 100%</div>
                            </div>
                        </div>

                        <!-- Certificate Number -->
                        <div class="bg-accent-50 dark:bg-accent-950/30 rounded-xl p-3 text-center">
                            <div class="text-xs text-surface-500 dark:text-surface-400 mb-1">رقم الشهادة</div>
                            <div class="font-mono font-bold text-sm text-accent-700 dark:text-accent-400">
                                {{ enrollment.cert_number }}
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <Link
                                :href="route('student.certificate', { enrollmentId: enrollment.id })"
                                class="btn-outline flex-1 text-center"
                            >
                                عرض الشهادة
                            </Link>
                            <form v-if="enrollment.course?.teacher_id" @submit.prevent="router.post(route('chat.start'), { course_id: enrollment.course.id, teacher_id: enrollment.course.teacher_id })" class="shrink-0">
                                <button type="submit" class="btn-outline px-3.5 hover:bg-surface-100 dark:hover:bg-surface-700 py-2.5 h-full rounded-xl" title="راسل المدرس">
                                    <Icon name="chat" class="w-5 h-5 text-primary-500" />
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── Empty State ─────────────────────────────────── -->
            <div v-if="!inProgress.length && !completed.length"
                 class="card p-20 text-center flex flex-col items-center justify-center">
                <div class="p-4 bg-surface-100 dark:bg-surface-800 rounded-full text-primary-500 mb-4">
                    <Icon name="courses" class="w-12 h-12" />
                </div>
                <h3 class="text-xl font-bold text-surface-700 dark:text-white mb-2">
                    لم تبدأ أي كورس بعد
                </h3>
                <p class="text-surface-500 dark:text-surface-400 mb-6">
                    اكتشف كورساتنا وابدأ رحلتك نحو التفوق
                </p>
                <Link :href="route('courses.index')" class="btn-primary btn-lg">
                    تصفح الكورسات
                </Link>
            </div>
        </div>
    </DashboardLayout>
</template>
