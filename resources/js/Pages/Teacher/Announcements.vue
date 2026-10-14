<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

defineProps({
    groups: { type: Array, default: () => [] },
});

const form = useForm({
    teaching_group_id: '',
    title: '',
    body: '',
});

const editingAnnouncementId = ref(null);
const editForm = useForm({
    title: '',
    body: '',
});

function publish() {
    form.post(route('teacher.announcements.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('title', 'body'),
    });
}

function startEdit(announcement) {
    editingAnnouncementId.value = announcement.id;
    editForm.title = announcement.title;
    editForm.body = announcement.body;
    editForm.clearErrors();
}

function cancelEdit() {
    editingAnnouncementId.value = null;
    editForm.reset();
    editForm.clearErrors();
}

function updateAnnouncement() {
    editForm.put(route('teacher.announcements.update', editingAnnouncementId.value), {
        preserveScroll: true,
        onSuccess: cancelEdit,
    });
}

function deleteAnnouncement(id) {
    if (!window.confirm('هل تريد حذف هذا الإعلان؟')) return;

    router.delete(route('teacher.announcements.destroy', id), {
        preserveScroll: true,
    });
}

function formatDate(value) {
    if (!value) return '';

    return new Date(value).toLocaleString('ar-QA', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="إعلانات المجموعات" />

    <DashboardLayout>
        <div class="space-y-8 pb-12">
            <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white">إعلانات المجموعات</h1>
                    <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">
                        انشر تحديثاً للطلاب المشتركين في المجموعة، وسيصل الإشعار لأولياء الأمور المرتبطين توثيقياً.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="route('teacher.announcements.export')" class="btn-outline btn-sm whitespace-nowrap" download>تصدير CSV</a>
                    <Link :href="route('teacher.dashboard')" class="btn-outline btn-sm">العودة إلى لوحة المعلم</Link>
                </div>
            </header>

            <section class="card p-5 sm:p-6">
                <div class="mb-5 flex items-center gap-2">
                    <Icon name="bell" class="h-5 w-5 text-primary-500" />
                    <h2 class="font-black text-surface-900 dark:text-white">نشر إعلان جديد</h2>
                </div>

                <form class="grid gap-4" @submit.prevent="publish">
                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-surface-600 dark:text-surface-300">المجموعة</label>
                        <select v-model="form.teaching_group_id" class="input w-full" required :disabled="!groups.length">
                            <option value="" disabled>اختر مجموعة تدرّسها</option>
                            <option v-for="group in groups" :key="group.id" :value="group.id">
                                {{ group.subject?.name ? `${group.subject.name} — ` : '' }}{{ group.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.teaching_group_id" class="mt-1 text-xs text-red-500">{{ form.errors.teaching_group_id }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-surface-600 dark:text-surface-300">العنوان</label>
                        <input v-model="form.title" class="input w-full" maxlength="180" required placeholder="مثال: موعد المراجعة القادمة" />
                        <p v-if="form.errors.title" class="mt-1 text-xs text-red-500">{{ form.errors.title }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-surface-600 dark:text-surface-300">نص الإعلان</label>
                        <textarea v-model="form.body" class="input min-h-32 w-full resize-y" maxlength="5000" required placeholder="اكتب التفاصيل التي يحتاجها الطلاب..."></textarea>
                        <p v-if="form.errors.body" class="mt-1 text-xs text-red-500">{{ form.errors.body }}</p>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary" :disabled="form.processing || !groups.length">
                            {{ form.processing ? 'جارٍ النشر...' : 'نشر الإعلان وإرسال الإشعار' }}
                        </button>
                    </div>
                </form>

                <p v-if="!groups.length" class="mt-4 rounded-xl bg-surface-50 p-4 text-sm text-surface-500 dark:bg-surface-800/60 dark:text-surface-400">
                    لا توجد مجموعات نشطة مملوكة لك حالياً.
                </p>
            </section>

            <section class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-black text-surface-900 dark:text-white">سجل الإعلانات</h2>
                    <span class="text-xs text-surface-400">آخر 10 إعلانات لكل مجموعة</span>
                </div>

                <div v-if="groups.length" class="grid gap-4 lg:grid-cols-2">
                    <article v-for="group in groups" :key="group.id" class="card overflow-hidden">
                        <div class="border-b border-surface-100 bg-surface-50 p-4 dark:border-surface-800 dark:bg-surface-900/50">
                            <p class="text-xs font-bold text-primary-600 dark:text-primary-400">{{ group.subject?.name }}</p>
                            <h3 class="mt-1 font-black text-surface-900 dark:text-white">{{ group.name }}</h3>
                        </div>
                        <div v-if="group.announcements?.length" class="divide-y divide-surface-100 dark:divide-surface-800">
                            <div v-for="announcement in group.announcements" :key="announcement.id" class="p-4">
                                <form v-if="editingAnnouncementId === announcement.id" class="grid gap-3" @submit.prevent="updateAnnouncement">
                                    <input v-model="editForm.title" class="input w-full" maxlength="180" required />
                                    <p v-if="editForm.errors.title" class="text-xs text-red-500">{{ editForm.errors.title }}</p>
                                    <textarea v-model="editForm.body" class="input min-h-28 w-full resize-y" maxlength="5000" required></textarea>
                                    <p v-if="editForm.errors.body" class="text-xs text-red-500">{{ editForm.errors.body }}</p>
                                    <div class="flex justify-end gap-2">
                                        <button type="button" class="btn-ghost btn-sm" @click="cancelEdit">إلغاء</button>
                                        <button type="submit" class="btn-primary btn-sm" :disabled="editForm.processing">
                                            {{ editForm.processing ? 'جارٍ الحفظ...' : 'حفظ التعديل' }}
                                        </button>
                                    </div>
                                </form>
                                <template v-else>
                                    <div class="flex items-start justify-between gap-3">
                                        <h4 class="font-bold text-surface-900 dark:text-white">{{ announcement.title }}</h4>
                                        <div class="flex shrink-0 items-center gap-2">
                                            <time class="text-[11px] text-surface-400">{{ formatDate(announcement.published_at) }}</time>
                                            <button type="button" class="btn-ghost btn-sm px-2" @click="startEdit(announcement)">تعديل</button>
                                            <button type="button" class="btn-ghost btn-sm px-2 text-red-500" @click="deleteAnnouncement(announcement.id)">حذف</button>
                                        </div>
                                    </div>
                                    <p class="mt-2 whitespace-pre-line text-sm leading-7 text-surface-600 dark:text-surface-300">{{ announcement.body }}</p>
                                </template>
                            </div>
                        </div>
                        <p v-else class="p-6 text-center text-sm text-surface-400">لا توجد إعلانات منشورة لهذه المجموعة.</p>
                    </article>
                </div>
            </section>
        </div>
    </DashboardLayout>
</template>
