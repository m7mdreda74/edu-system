<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    resources: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    categories: { type: Array, default: () => [] },
    assignments: { type: Array, default: () => [] },
    directUploads: { type: Object, default: () => ({}) },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id);
const { confirm } = useConfirm();
const search = ref(props.filters.search ?? '');
const category = ref(props.filters.category ?? '');
const visibility = ref(props.filters.visibility ?? '');
const showForm = ref(false);
const uploading = ref(false);
const attachmentError = ref('');
const selectedAssignment = ref({});

const form = useForm({
    title: '',
    description: '',
    category: '',
    tags: '',
    attachment: null,
});

const allowedTypes = new Set([
    'application/msword',
    'application/pdf',
    'application/vnd.oasis.opendocument.text',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/zip',
    'image/jpeg',
    'image/png',
]);

const resourceRows = computed(() => props.resources?.data ?? []);

function filter() {
    router.get(route('teacher.resource-library'), {
        search: search.value || undefined,
        category: category.value || undefined,
        visibility: visibility.value || undefined,
    }, { preserveState: true, replace: true });
}

function onAttachmentChange(event) {
    const file = event.target.files?.[0] ?? null;
    attachmentError.value = '';
    if (file && (!allowedTypes.has(file.type) || file.size > 25 * 1024 * 1024)) {
        form.attachment = null;
        event.target.value = '';
        attachmentError.value = 'نوع الملف أو حجمه غير مسموح. الحد الأقصى 25 ميجابايت.';
        return;
    }
    form.attachment = file;
}

function resetForm() {
    form.reset();
    attachmentError.value = '';
    showForm.value = false;
}

async function submit() {
    if (!form.attachment) {
        attachmentError.value = 'اختر ملفًا قبل الحفظ.';
        return;
    }

    if (!props.directUploads.enabled) {
        form.post(route('teacher.resource-library.store'), {
            forceFormData: true,
            onSuccess: resetForm,
        });
        return;
    }

    uploading.value = true;
    attachmentError.value = '';
    try {
        const { data: authorization } = await axios.post(props.directUploads.authorize_url, {
            title: form.title,
            description: form.description,
            category: form.category,
            tags: form.tags,
            file_name: form.attachment.name,
            file_size: form.attachment.size,
            mime_type: form.attachment.type,
        });
        const { data: presigned } = await axios.post(props.directUploads.handle_url, {
            pathname: authorization.pathname,
            authorization: authorization.authorization,
        });
        const uploadResponse = await fetch(presigned.upload_url, {
            method: 'PUT',
            body: form.attachment,
        });
        if (!uploadResponse.ok) throw new Error('Blob upload failed.');
        const blob = await uploadResponse.json();
        if (!blob?.url || !blob?.pathname) throw new Error('Blob upload returned an invalid response.');

        await axios.post(route('teacher.resource-library.complete-upload', authorization.resource_id), {
            blob_url: blob.url,
            blob_pathname: blob.pathname,
        });
        resetForm();
        router.reload({ only: ['resources', 'categories', 'assignments'] });
    } catch (error) {
        attachmentError.value = error.response?.data?.message ?? 'تعذر رفع المورد. حاول مرة أخرى.';
    } finally {
        uploading.value = false;
    }
}

function requestShare(resource) {
    router.post(route('teacher.resource-library.request-share', resource.id));
}

function attach(resource) {
    const assignmentId = selectedAssignment.value[resource.id];
    if (!assignmentId) return;
    router.post(route('teacher.resource-library.attach', resource.id), { assignment_id: assignmentId });
}

function detach(resource, assignmentId) {
    router.delete(route('teacher.resource-library.detach', { resource: resource.id, assignmentId }));
}

async function destroy(resource) {
    if (await confirm({
        title: 'حذف المورد',
        message: 'سيتم حذف المورد من المكتبة وإيقاف الوصول إليه.',
        confirmLabel: 'حذف',
        variant: 'danger',
    })) {
        router.delete(route('teacher.resource-library.destroy', resource.id));
    }
}

function visibilityLabel(resource) {
    if (resource.approval_status === 'pending') return 'بانتظار اعتماد الإدارة';
    if (resource.approval_status === 'approved') return 'مشترك معتمد';
    if (resource.approval_status === 'rejected') return 'المشاركة مرفوضة';
    return 'خاص';
}
</script>

<template>
    <Head title="مكتبة الموارد" />
    <DashboardLayout>
        <div class="space-y-6">
            <header class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white">مكتبة الموارد التعليمية</h1>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">احتفظ بملفاتك، أعد استخدامها، وشارك الموارد المفيدة بعد اعتماد الإدارة.</p>
                </div>
                <button type="button" class="btn-primary btn-sm" @click="showForm = !showForm">
                    <Icon name="plus" class="w-4 h-4" />
                    <span class="ms-1">{{ showForm ? 'إغلاق' : 'رفع مورد' }}</span>
                </button>
            </header>

            <p v-if="directUploads.serverless && !directUploads.enabled" class="alert-warning text-sm">
                الرفع المباشر غير مفعّل حاليًا. اربط Vercel Blob قبل رفع الملفات على بيئة serverless.
            </p>

            <form v-if="showForm" class="card p-5 space-y-4" @submit.prevent="submit">
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="input-label" for="resource-title">العنوان</label>
                        <input id="resource-title" v-model="form.title" class="input" required minlength="3" maxlength="255" />
                        <p v-if="form.errors.title" class="text-xs text-red-500 mt-1">{{ form.errors.title }}</p>
                    </div>
                    <div>
                        <label class="input-label" for="resource-category">التصنيف</label>
                        <input id="resource-category" v-model="form.category" class="input" maxlength="80" placeholder="مراجعة، ورقة عمل..." />
                    </div>
                    <div>
                        <label class="input-label" for="resource-tags">الوسوم</label>
                        <input id="resource-tags" v-model="form.tags" class="input" maxlength="1000" placeholder="جبر، تدريب، نهائي" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="input-label" for="resource-description">الوصف</label>
                        <textarea id="resource-description" v-model="form.description" class="input" rows="3" maxlength="2000"></textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="input-label" for="resource-file">الملف</label>
                        <input id="resource-file" type="file" class="input" accept=".pdf,.doc,.docx,.odt,.pptx,.zip,.png,.jpg,.jpeg" @change="onAttachmentChange" />
                        <p class="input-hint">PDF أو Word أو PowerPoint أو ZIP أو صورة، بحد أقصى 25 ميجابايت.</p>
                        <p v-if="attachmentError" class="text-xs text-red-500 mt-1">{{ attachmentError }}</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-ghost btn-sm" @click="resetForm">إلغاء</button>
                    <button type="submit" class="btn-primary btn-sm" :disabled="form.processing || uploading">
                        {{ uploading || form.processing ? 'جارٍ الرفع...' : 'حفظ المورد' }}
                    </button>
                </div>
            </form>

            <div class="card p-4 flex flex-col lg:flex-row gap-3">
                <input v-model="search" class="input flex-1" placeholder="ابحث بالعنوان أو الوصف أو التصنيف" @keyup.enter="filter" />
                <select v-model="category" class="input lg:w-52" @change="filter">
                    <option value="">كل التصنيفات</option>
                    <option v-for="item in categories" :key="item" :value="item">{{ item }}</option>
                </select>
                <select v-model="visibility" class="input lg:w-44" @change="filter">
                    <option value="">كل الموارد</option>
                    <option value="private">مواردي الخاصة</option>
                    <option value="shared">الموارد المشتركة</option>
                </select>
                <button type="button" class="btn-outline btn-sm" @click="filter">بحث</button>
            </div>

            <div v-if="resourceRows.length" class="grid xl:grid-cols-2 gap-4">
                <article v-for="resource in resourceRows" :key="resource.id" class="card p-5 space-y-4">
                    <div class="flex items-start gap-3">
                        <div class="w-11 h-11 rounded-xl bg-primary-50 dark:bg-primary-950 flex items-center justify-center text-primary-600 shrink-0">
                            <Icon name="attachment" class="w-5 h-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 class="font-bold text-surface-900 dark:text-white truncate">{{ resource.title }}</h2>
                            <p class="text-xs text-surface-500 mt-1">{{ resource.category }} · {{ resource.size_label }} · {{ visibilityLabel(resource) }}</p>
                        </div>
                        <a v-if="resource.file_url" :href="resource.file_url" class="btn-outline btn-sm shrink-0">فتح</a>
                    </div>
                    <p v-if="resource.description" class="text-sm text-surface-600 dark:text-surface-300 leading-relaxed">{{ resource.description }}</p>
                    <div v-if="resource.tags?.length" class="flex flex-wrap gap-1.5">
                        <span v-for="tag in resource.tags" :key="tag" class="badge-accent text-[10px]">#{{ tag }}</span>
                    </div>
                    <div v-if="resource.assignments?.length" class="flex flex-wrap gap-2">
                        <span v-for="assignment in resource.assignments" :key="assignment.id" class="badge-green text-[10px]">
                            {{ assignment.subject?.name ?? 'تكليف' }}<span v-if="assignment.grade?.name"> · {{ assignment.grade.name }}</span>
                            <button v-if="resource.owner?.id === currentUserId" type="button" class="ms-1 underline" @click="detach(resource, assignment.id)">×</button>
                        </span>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap pt-2 border-t border-surface-100 dark:border-surface-800">
                        <select v-if="assignments.length" v-model="selectedAssignment[resource.id]" class="input input-sm flex-1 min-w-40">
                            <option value="">إعادة الاستخدام في تكليف...</option>
                            <option v-for="assignment in assignments" :key="assignment.id" :value="assignment.id">
                                {{ assignment.subject?.name ?? 'تكليف' }}<span v-if="assignment.grade?.name"> · {{ assignment.grade.name }}</span>
                            </option>
                        </select>
                        <button v-if="assignments.length" type="button" class="btn-outline btn-sm" @click="attach(resource)">ربط</button>
                        <button v-if="resource.visibility === 'private'" type="button" class="btn-ghost btn-sm" @click="requestShare(resource)">طلب مشاركة</button>
                        <button v-if="resource.owner?.id === currentUserId" type="button" class="btn-ghost btn-sm text-red-500" @click="destroy(resource)">حذف</button>
                    </div>
                </article>
            </div>

            <div v-else class="card p-12 text-center text-surface-500">
                <Icon name="book" class="w-10 h-10 text-surface-300 mx-auto mb-3" />
                <h2 class="font-bold text-surface-700 dark:text-surface-200">لا توجد موارد مطابقة</h2>
                <p class="text-sm mt-1">ارفع أول مورد أو غيّر معايير البحث.</p>
            </div>

            <div v-if="resources?.links?.length > 3" class="flex justify-center gap-1">
                <Link v-for="link in resources.links" :key="link.label" :href="link.url ?? '#'" :class="['btn-ghost btn-sm', link.active ? 'bg-primary-500/10 text-primary-600' : '']" v-html="link.label" />
            </div>
        </div>
    </DashboardLayout>
</template>
