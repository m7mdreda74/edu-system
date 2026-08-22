<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    group:     { type: Object, required: true },
    materials: { type: Array, default: () => [] },
});

const showForm = ref(false);
const { confirm } = useConfirm();

const form = useForm({
    title: '',
    video_url: '',
    duration_seconds: null,
    order: null,
    is_free_preview: false,
    description: '',
    attachment: null,
});

function onAttachmentChange(event) {
    const file = event.target.files?.[0] ?? null;
    const allowedTypes = new Set([
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip',
    ]);

    if (file && (!allowedTypes.has(file.type) || file.size > 20 * 1024 * 1024)) {
        form.attachment = null;
        event.target.value = '';
        form.setError('attachment', 'نوع الملف أو حجمه غير مسموح. الحد الأقصى 20 ميجابايت.');
        return;
    }

    form.attachment = file;
    form.clearErrors('attachment');
}

function submit() {
    form.post(route('teacher.materials.store', { groupId: props.group.id }), {
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            showForm.value = false;
        },
    });
}

async function destroy(id) {
    const ok = await confirm({
        title: 'حذف المادة',
        message: 'سيتم حذف هذه المادة نهائياً.',
        confirmLabel: 'حذف',
        variant: 'danger',
    });
    if (ok) router.delete(route('teacher.materials.destroy', { id }));
}

function formatDuration(seconds) {
    if (!seconds) return '—';
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    return hours > 0 ? `${hours}س ${minutes % 60}د` : `${minutes} دقيقة`;
}
</script>

<template>
    <Head :title="`مواد ${group.name}`" />

    <DashboardLayout>
        <div class="space-y-6">
            <header class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <nav class="text-xs text-surface-400 mb-1">
                        <Link :href="route('teacher.teaching-schedule')" class="hover:text-primary-500">جدول التدريس</Link>
                        <span class="mx-1">/</span>
                        <span>{{ group.subject?.name }}</span>
                    </nav>
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white">مواد {{ group.name }}</h1>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                        الفيديوهات والملفات المتاحة للطلاب المشتركين في هذه المجموعة
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Link v-if="group.assignment_id" :href="route('teacher.curriculum', { assignment: group.assignment_id })" class="btn-outline btn-sm">
                        <Icon name="book" class="w-4 h-4" />
                        <span class="ms-1">بناء المنهج</span>
                    </Link>
                    <button type="button" class="btn-primary btn-sm" @click="showForm = !showForm">
                        <Icon name="plus" class="w-4 h-4" />
                        <span class="ms-1">{{ showForm ? 'إغلاق' : 'إضافة مادة' }}</span>
                    </button>
                </div>
            </header>

            <p v-if="group.assignment_id" class="alert-info text-sm">
                هذه الشاشة ترفع كل مادة إلى الوحدة الأولى فقط. لتقسيم المنهج إلى فصول ووحدات ودروس واختبارات استخدم
                <Link :href="route('teacher.curriculum', { assignment: group.assignment_id })" class="font-bold hover:underline">بناء المنهج</Link>.
            </p>

            <!-- Add form -->
            <form v-if="showForm" class="card p-5 space-y-4" @submit.prevent="submit">
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label for="title" class="input-label">العنوان</label>
                        <input id="title" v-model="form.title" type="text" minlength="3" maxlength="255" class="input" required />
                        <p v-if="form.errors.title" class="text-xs text-red-500 mt-1">{{ form.errors.title }}</p>
                    </div>

                    <div>
                        <label for="video_url" class="input-label">رابط الفيديو</label>
                        <input id="video_url" v-model="form.video_url" type="url" maxlength="2048" class="input" dir="ltr" placeholder="https://..." />
                        <p v-if="form.errors.video_url" class="text-xs text-red-500 mt-1">{{ form.errors.video_url }}</p>
                    </div>

                    <div>
                        <label for="duration" class="input-label">المدة (بالثواني)</label>
                        <input id="duration" v-model.number="form.duration_seconds" type="number" min="0" max="86400" class="input" />
                        <p class="input-hint">تُستخدم لحساب تقدّم الطالب تلقائياً.</p>
                    </div>

                    <div>
                        <label for="order" class="input-label">الترتيب</label>
                        <input id="order" v-model.number="form.order" type="number" min="1" max="999" class="input" placeholder="تلقائي" />
                    </div>

                    <div>
                        <label for="attachment" class="input-label">ملف مرفق</label>
                        <input id="attachment" type="file" accept=".pdf,.docx,.pptx,.zip,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/zip" class="input" @change="onAttachmentChange" />
                    </div>

                    <div class="sm:col-span-2">
                        <label for="description" class="input-label">الوصف</label>
                        <textarea id="description" v-model="form.description" rows="3" maxlength="2000" class="input"></textarea>
                    </div>

                    <label class="sm:col-span-2 flex items-center gap-2 text-sm text-surface-600 dark:text-surface-300">
                        <input v-model="form.is_free_preview" type="checkbox" class="rounded" />
                        <span>معاينة مجانية (يشاهدها أي طالب بدون اشتراك)</span>
                    </label>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-ghost btn-sm" @click="showForm = false">إلغاء</button>
                    <button type="submit" class="btn-primary btn-sm" :disabled="form.processing">
                        {{ form.processing ? 'جارٍ الحفظ...' : 'حفظ' }}
                    </button>
                </div>
            </form>

            <!-- Material list -->
            <div v-if="materials.length" class="card divide-y divide-surface-100 dark:divide-surface-800">
                <div v-for="material in materials" :key="material.id" class="flex items-center gap-4 p-4">
                    <div class="w-9 h-9 rounded-lg bg-primary-50 dark:bg-primary-950 flex items-center justify-center text-primary-600 font-black text-xs shrink-0">
                        {{ material.order }}
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-sm text-surface-900 dark:text-white truncate">{{ material.title }}</h3>
                            <span v-if="material.is_live_recording" class="badge-primary text-[10px] shrink-0">تسجيل حصة محمي</span>
                            <span v-if="material.is_free_preview" class="badge-green text-[10px] shrink-0">معاينة مجانية</span>
                        </div>
                        <div class="text-[11px] text-surface-400 flex items-center gap-2 mt-0.5 flex-wrap">
                            <span>{{ formatDuration(material.duration_seconds) }}</span>
                            <span v-if="material.video_url">· فيديو</span>
                            <span v-if="material.attachment_path">· مرفق</span>
                        </div>
                    </div>

                    <button v-if="!material.is_live_recording" type="button" class="btn-ghost btn-sm text-red-500 shrink-0" aria-label="حذف المادة" @click="destroy(material.id)">
                        <Icon name="trash" class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <div v-else class="card p-12 text-center">
                <Icon name="video" class="w-10 h-10 text-surface-300 mx-auto mb-3" />
                <h3 class="font-bold text-surface-700 dark:text-surface-200 mb-1">لا توجد مواد بعد</h3>
                <p class="text-sm text-surface-400">أضف أول فيديو أو ملف لطلاب هذه المجموعة.</p>
            </div>
        </div>
    </DashboardLayout>
</template>
