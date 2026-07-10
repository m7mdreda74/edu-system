<script setup>
import { useForm, router, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    sessions: { type: Array, required: true },
    courses:  { type: Array, required: true },
});

const form = useForm({
    course_id:    '',
    title:        '',
    description:  '',
    scheduled_at: '',
    room_id:      '', // Zoom link
});

const isModalOpen = ref(false);

function submit() {
    form.post(route('teacher.live-sessions.store'), {
        onSuccess: () => {
            form.reset();
            isModalOpen.value = false;
        }
    });
}

function deleteSession(id) {
    if (confirm('هل أنت متأكد من حذف هذه الحصة؟')) {
        router.delete(route('teacher.live-sessions.destroy', id));
    }
}

function updateStatus(id, newStatus) {
    let recording_url = null;
    if (newStatus === 'ended') {
        recording_url = prompt('هل لديك رابط تسجيل الحصة المباشرة لتوفيره للطلاب؟ (اختياري)');
    }
    
    router.patch(route('teacher.live-sessions.status', id), {
        status: newStatus,
        recording_url: recording_url
    });
}

const statusColors = {
    scheduled: 'badge-gray',
    live:      'badge-accent',
    ended:     'badge-primary'
};
const statusLabels = {
    scheduled: 'مجدولة',
    live:      'مباشر الآن',
    ended:     'منتهية'
};
</script>

<template>
    <DashboardLayout>
        <Head title="الحصص المباشرة" />

        <div class="container-app px-4 py-8">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white">الحصص المباشرة 🔴</h1>
                    <p class="text-surface-500 mt-1">جدولة وبدء حصص البث المباشر (Zoom, Meet) لطلابك</p>
                </div>
                <button @click="isModalOpen = true" class="btn-primary">
                    + جدولة حصة جديدة
                </button>
            </div>

            <!-- List of Sessions -->
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-surface-50 dark:bg-surface-800 border-b border-surface-200 dark:border-surface-700">
                            <tr>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">عنوان الحصة / الكورس</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">الموعد</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">الحالة</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">الرابط/التسجيل</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-700">
                            <tr v-for="session in sessions" :key="session.id" class="hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors">
                                <td class="p-4">
                                    <div class="font-bold text-surface-900 dark:text-white text-base">{{ session.title }}</div>
                                    <div class="text-xs text-surface-500 mt-1">{{ session.course.title }}</div>
                                </td>
                                <td class="p-4 text-surface-600 dark:text-surface-300 font-mono text-xs">
                                    {{ new Date(session.scheduled_at).toLocaleString('ar-EG', { dateStyle: 'medium', timeStyle: 'short' }) }}
                                </td>
                                <td class="p-4">
                                    <span :class="statusColors[session.status]" class="text-xs">
                                        {{ statusLabels[session.status] }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    <div v-if="session.status !== 'ended'">
                                        <a :href="route('live-sessions.room', session.id)" target="_blank" class="text-primary-500 hover:underline text-xs block truncate max-w-[150px]">دخول القاعة المباشرة</a>
                                    </div>
                                    <div v-if="session.recording_url">
                                        <a :href="session.recording_url" target="_blank" class="text-accent-500 hover:underline text-xs block truncate max-w-[150px]">رابط التسجيل</a>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <button v-if="session.status === 'scheduled'" @click="updateStatus(session.id, 'live')" class="btn-sm bg-accent-50 text-accent-600 hover:bg-accent-100 dark:bg-accent-900/30 dark:hover:bg-accent-900/50">بدء الحصة</button>
                                        <button v-if="session.status === 'live'" @click="updateStatus(session.id, 'ended')" class="btn-sm bg-surface-200 text-surface-700 hover:bg-surface-300 dark:bg-surface-700 dark:text-surface-300">إنهاء</button>
                                        <button @click="deleteSession(session.id)" class="btn-sm btn-ghost text-red-500 hover:bg-red-50" title="حذف">🗑️</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="sessions.length === 0">
                                <td colspan="5" class="p-8 text-center text-surface-400">
                                    لا توجد حصص مجدولة حالياً. قم بجدولة أول حصة لك.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="isModalOpen = false"></div>
            <div class="relative bg-white dark:bg-surface-900 rounded-2xl shadow-xl w-full max-w-lg overflow-hidden animate-fade-up">
                <form @submit.prevent="submit">
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-surface-900 dark:text-white mb-6">جدولة حصة مباشرة</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="input-label">اختر الكورس</label>
                                <select v-model="form.course_id" class="input" required>
                                    <option value="" disabled>-- الكورس --</option>
                                    <option v-for="course in courses" :key="course.id" :value="course.id">
                                        {{ course.title }}
                                    </option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="input-label">عنوان الحصة</label>
                                <input v-model="form.title" type="text" class="input" placeholder="مثال: مراجعة الوحدة الأولى" required>
                            </div>

                            <div>
                                <label class="input-label">موعد وتاريخ الحصة</label>
                                <input v-model="form.scheduled_at" type="datetime-local" class="input" required>
                            </div>

                            <div>
                                <label class="input-label">رابط البث الخارجي (اختياري) - <span class="text-surface-400 font-normal">استخدمه فقط إن كنت تريد بثاً خارجياً كـ Zoom</span></label>
                                <input v-model="form.room_id" type="url" dir="ltr" class="input" placeholder="https://zoom.us/j/...">
                            </div>

                            <div>
                                <label class="input-label">وصف إضافي (اختياري)</label>
                                <textarea v-model="form.description" class="input resize-y" rows="2" placeholder="معلومات للطلاب قبل بدء الحصة..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="p-4 bg-surface-50 dark:bg-surface-950 flex justify-end gap-3 border-t border-surface-200 dark:border-surface-800">
                        <button type="button" @click="isModalOpen = false" class="btn-ghost">إلغاء</button>
                        <button type="submit" :disabled="form.processing" class="btn-primary">
                            {{ form.processing ? 'حفظ...' : 'جدولة الحصة' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </DashboardLayout>
</template>
