<script setup>
import { ref } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    course:  { type: Object, required: true },
    lessons: { type: Array,  default: () => [] },
});

const form = useForm({
    title:            '',
    video_url:        '',
    duration_seconds: '',
    is_free_preview:  false,
    description:      '',
});

const showForm = ref(false);

function formatDuration(seconds) {
    if (!seconds) return '0:00';
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
}

function addLesson() {
    form.post(route('teacher.lessons.store', { id: props.course.id }), {
        onSuccess: () => {
            form.reset();
            showForm.value = false;
        },
    });
}

function deleteLesson(lessonId) {
    if (!confirm('هل تريد حذف هذا الدرس؟')) return;
    router.delete(route('teacher.lessons.destroy', { id: lessonId }), { preserveScroll: true });
}
</script>

<template>
    <DashboardLayout>
        <Head :title="`دروس — ${course.title}`" />

        <div class="container-app px-4 py-10 max-w-3xl">

            <!-- Header -->
            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('teacher.courses')" class="btn-ghost p-2 rounded-lg">
                    <Icon name="arrowRight" class="w-5 h-5 rtl-flip" />
                </Link>
                <div>
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white">إدارة الدروس</h1>
                    <p class="text-sm text-surface-400">{{ course.title }}</p>
                </div>
            </div>

            <!-- Lessons List -->
            <div class="space-y-3 mb-6">
                <div v-if="!lessons.length" class="card p-10 text-center text-surface-400 flex flex-col items-center justify-center">
                    <Icon name="live" class="w-12 h-12 text-surface-400 mb-3" />
                    <p>لم تُضف أي درس بعد</p>
                </div>

                <div v-for="(lesson, idx) in lessons" :key="lesson.id"
                     class="card p-4 flex items-center gap-4 group">

                    <!-- Order number -->
                    <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center
                                text-primary-700 dark:text-primary-300 font-bold text-sm flex-shrink-0">
                        {{ idx + 1 }}
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-surface-800 dark:text-white text-sm line-clamp-1">
                            {{ lesson.title }}
                        </div>
                        <div class="flex items-center gap-3 mt-1 text-xs text-surface-400">
                            <span class="flex items-center gap-1">
                                <Icon name="clock" class="w-3.5 h-3.5" />
                                {{ formatDuration(lesson.duration_seconds) }}
                            </span>
                            <span v-if="lesson.is_free_preview" class="text-green-600 dark:text-green-400 flex items-center gap-1">
                                <Icon name="unlock" class="w-3.5 h-3.5" />
                                معاينة مجانية
                            </span>
                        </div>
                    </div>

                    <button
                        @click="deleteLesson(lesson.id)"
                        class="opacity-0 group-hover:opacity-100 transition-all duration-200
                               text-red-500 hover:text-red-700 dark:text-red-400 p-2 rounded-lg
                               hover:bg-red-50 dark:hover:bg-red-950/50"
                        :id="`delete-lesson-${lesson.id}`"
                    >
                        <Icon name="close" class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- Add Lesson Form -->
            <div v-if="showForm" class="card p-6 mb-4">
                <h3 class="font-bold text-surface-800 dark:text-white mb-4">إضافة درس جديد</h3>
                <form @submit.prevent="addLesson" class="space-y-4">

                    <div>
                        <label class="input-label" for="lesson-title">عنوان الدرس *</label>
                        <input id="lesson-title" v-model="form.title" type="text" class="input"
                               :class="{ 'border-red-500': form.errors.title }"
                               placeholder="مثال: مقدمة في التفاضل والتكامل" required />
                        <p v-if="form.errors.title" class="text-red-500 text-xs mt-1">{{ form.errors.title }}</p>
                    </div>

                    <div>
                        <label class="input-label" for="video-url">رابط الفيديو *</label>
                        <input id="video-url" v-model="form.video_url" type="url" class="input"
                               :class="{ 'border-red-500': form.errors.video_url }"
                               placeholder="https://www.youtube.com/watch?v=..." required />
                        <p v-if="form.errors.video_url" class="text-red-500 text-xs mt-1">{{ form.errors.video_url }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="input-label" for="duration">المدة (بالثواني) *</label>
                            <input id="duration" v-model="form.duration_seconds" type="number" min="1"
                                   class="input" placeholder="3600 = ساعة واحدة" required />
                        </div>
                        <div class="flex items-end gap-2 pb-1">
                            <input id="free-preview" v-model="form.is_free_preview"
                                   type="checkbox" class="w-4 h-4 text-primary-600 rounded" />
                            <label for="free-preview" class="text-sm cursor-pointer text-surface-600 dark:text-surface-400">
                                معاينة مجانية
                            </label>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" :disabled="form.processing" class="btn-primary flex items-center gap-2"
                                :class="{ 'opacity-60': form.processing }" id="add-lesson-btn">
                            <span v-if="form.processing" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            <Icon v-else name="plus" class="w-4 h-4" />
                            <span>{{ form.processing ? 'جاري الإضافة...' : 'إضافة الدرس' }}</span>
                        </button>
                        <button type="button" @click="showForm = false" class="btn-ghost">إلغاء</button>
                    </div>
                </form>
            </div>

            <button
                v-if="!showForm"
                @click="showForm = true"
                class="btn-outline w-full btn-lg flex items-center justify-center gap-2 transition-all duration-200 hover:scale-101"
                id="show-add-lesson-form"
            >
                <Icon name="plus" class="w-5 h-5" />
                <span>إضافة درس جديد</span>
            </button>
        </div>
    </DashboardLayout>
</template>
