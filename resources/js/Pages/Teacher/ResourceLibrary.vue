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
    counts: { type: Object, default: () => ({ total: 0, pending: 0, approved: 0, rejected: 0 }) },
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
const statusFilter = ref(props.filters.status ?? '');
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

function filter(statusOverride = null) {
    if (statusOverride !== null) {
        statusFilter.value = statusOverride;
    }
    router.get(route('teacher.resource-library'), {
        search: search.value || undefined,
        category: category.value || undefined,
        visibility: visibility.value || undefined,
        status: statusFilter.value || undefined,
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
        router.reload({ only: ['resources', 'categories', 'assignments', 'counts'] });
    } catch (error) {
        attachmentError.value = error.response?.data?.message ?? 'تعذر رفع المورد. حاول مرة أخرى.';
    } finally {
        uploading.value = false;
    }
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
        message: 'سيتم حذف المورد من مكتبتك نهائياً.',
        confirmLabel: 'حذف',
        variant: 'danger',
    })) {
        router.delete(route('teacher.resource-library.destroy', resource.id));
    }
}

const statusBadgeClasses = {
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-300 dark:border-amber-700',
    approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700',
    rejected: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 border border-rose-300 dark:border-rose-700',
    none: 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300',
};

const statusLabels = {
    pending: '⏳ بانتظار مراجعة وموافقة الإدارة',
    approved: '✅ معتمد من الإدارة ومتاح للطلاب',
    rejected: '❌ مرفوض من الإدارة',
    none: 'خاص',
};
</script>

<template>
    <Head title="مكتبة الموارد التعليمية" />
    <DashboardLayout>
        <div class="space-y-6 max-w-7xl mx-auto">
            <!-- Header -->
            <header class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <span class="p-2 rounded-xl bg-primary-100 dark:bg-primary-950 text-primary-600 dark:text-primary-400">
                            <Icon name="book" class="w-6 h-6" />
                        </span>
                        مكتبة الموارد التعليمية
                    </h1>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                        ارفع ملازمك ومذكراتك التعليمية. تخضع الملفات لمراجعة الإدارة واعتمادها مع تقديم مقترحات وملاحظات قبل إتاحتها للطلاب.
                    </p>
                </div>
                <button
                    type="button"
                    class="btn-primary btn-sm flex items-center gap-1.5 shadow-sm"
                    @click="showForm = !showForm"
                >
                    <Icon :name="showForm ? 'close' : 'plus'" class="w-4 h-4" />
                    <span>{{ showForm ? 'إغلاق النموذج' : 'رفع مورد جديد' }}</span>
                </button>
            </header>

            <!-- Stats Overview Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <button
                    type="button"
                    class="card p-4 text-start transition-all hover:border-primary-300 dark:hover:border-primary-700"
                    :class="statusFilter === '' ? 'ring-2 ring-primary-500' : ''"
                    @click="filter('')"
                >
                    <div class="text-xs text-surface-500 font-medium">إجمالي الموارد</div>
                    <div class="text-2xl font-black text-surface-900 dark:text-white mt-1">{{ counts.total ?? resourceRows.length }}</div>
                </button>

                <button
                    type="button"
                    class="card p-4 text-start transition-all hover:border-amber-400"
                    :class="statusFilter === 'pending' ? 'ring-2 ring-amber-500 bg-amber-50/50 dark:bg-amber-950/20' : ''"
                    @click="filter('pending')"
                >
                    <div class="text-xs text-amber-700 dark:text-amber-400 font-bold flex items-center gap-1">
                        <span>⏳ بانتظار المراجعة</span>
                    </div>
                    <div class="text-2xl font-black text-amber-800 dark:text-amber-300 mt-1">{{ counts.pending ?? 0 }}</div>
                </button>

                <button
                    type="button"
                    class="card p-4 text-start transition-all hover:border-emerald-400"
                    :class="statusFilter === 'approved' ? 'ring-2 ring-emerald-500 bg-emerald-50/50 dark:bg-emerald-950/20' : ''"
                    @click="filter('approved')"
                >
                    <div class="text-xs text-emerald-700 dark:text-emerald-400 font-bold flex items-center gap-1">
                        <span>✅ معتمدة ومتاحة</span>
                    </div>
                    <div class="text-2xl font-black text-emerald-800 dark:text-emerald-300 mt-1">{{ counts.approved ?? 0 }}</div>
                </button>

                <button
                    type="button"
                    class="card p-4 text-start transition-all hover:border-rose-400"
                    :class="statusFilter === 'rejected' ? 'ring-2 ring-rose-500 bg-rose-50/50 dark:bg-rose-950/20' : ''"
                    @click="filter('rejected')"
                >
                    <div class="text-xs text-rose-700 dark:text-rose-400 font-bold flex items-center gap-1">
                        <span>❌ مرفوضة تحتاج مراجعة</span>
                    </div>
                    <div class="text-2xl font-black text-rose-800 dark:text-rose-300 mt-1">{{ counts.rejected ?? 0 }}</div>
                </button>
            </div>

            <p v-if="directUploads.serverless && !directUploads.enabled" class="alert-warning text-sm">
                الرفع المباشر غير مفعّل حاليًا. اربط Vercel Blob قبل رفع الملفات على بيئة serverless.
            </p>

            <!-- Upload Form -->
            <form v-if="showForm" class="card p-6 space-y-4 border-2 border-primary-200 dark:border-primary-900 shadow-lg" @submit.prevent="submit">
                <div class="flex items-center justify-between border-b border-surface-100 dark:border-surface-800 pb-3">
                    <h2 class="font-bold text-surface-900 dark:text-white flex items-center gap-2">
                        <span>📤 رفع مورد تعليمي جديد</span>
                    </h2>
                    <span class="text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 px-2.5 py-1 rounded-full font-medium">
                        يخضع لمراجعة واعتماد الإدارة
                    </span>
                </div>

                <div class="p-3.5 rounded-xl bg-primary-50/60 dark:bg-primary-950/30 border border-primary-100 dark:border-primary-900/50 text-xs text-primary-900 dark:text-primary-300 leading-relaxed">
                    💡 <strong>تنويه:</strong> بعد رفع الملف مباشرة، سيدخل في مرحلة مراجعة وتدقيق الإدارة لضمان الجودة. يمكنك متابعة حالة الملاحظات والمقترحات هنا، وبمجرد اعتماده ستتمكن من ربطه فوراً بتكليفات طلابك.
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="input-label" for="resource-title">عنوان المورد التعليمي *</label>
                        <input
                            id="resource-title"
                            v-model="form.title"
                            class="input"
                            required
                            minlength="3"
                            maxlength="255"
                            placeholder="مثال: مذكرة مراجعة ليلة الامتحان في مادة الفيزياء"
                        />
                        <p v-if="form.errors.title" class="text-xs text-red-500 mt-1">{{ form.errors.title }}</p>
                    </div>
                    <div>
                        <label class="input-label" for="resource-category">التصنيف</label>
                        <input
                            id="resource-category"
                            v-model="form.category"
                            class="input"
                            maxlength="80"
                            placeholder="مراجعة، ملخص، ورقة تدريب، بنك أسئلة..."
                        />
                    </div>
                    <div>
                        <label class="input-label" for="resource-tags">الوسوم (مفصولة بفواصل)</label>
                        <input
                            id="resource-tags"
                            v-model="form.tags"
                            class="input"
                            maxlength="1000"
                            placeholder="فيزياء، الصف الثالث، الفصل الأول"
                        />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="input-label" for="resource-description">وصف المورد وملاحظات المحتوى</label>
                        <textarea
                            id="resource-description"
                            v-model="form.description"
                            class="input"
                            rows="3"
                            maxlength="2000"
                            placeholder="نبذة عن المورد التعليمي وما يغطيه من وحدات أو دروس..."
                        ></textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="input-label" for="resource-file">الملف التعليمي (PDF, Word, PowerPoint, ZIP) *</label>
                        <input
                            id="resource-file"
                            type="file"
                            class="input"
                            accept=".pdf,.doc,.docx,.odt,.pptx,.zip,.png,.jpg,.jpeg"
                            @change="onAttachmentChange"
                        />
                        <p class="input-hint">الملفات المدعومة: PDF أو Word أو PowerPoint أو ZIP أو صورة، بحد أقصى 25 ميجابايت.</p>
                        <p v-if="attachmentError" class="text-xs text-red-500 mt-1">{{ attachmentError }}</p>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-surface-100 dark:border-surface-800">
                    <button type="button" class="btn-ghost btn-sm" @click="resetForm">إلغاء</button>
                    <button type="submit" class="btn-primary btn-sm" :disabled="form.processing || uploading">
                        {{ uploading || form.processing ? 'جارٍ الرفع والإرسال للمراجعة...' : 'حفظ وإرسال للمراجعة' }}
                    </button>
                </div>
            </form>

            <!-- Search & Filters -->
            <div class="card p-4 flex flex-col lg:flex-row gap-3">
                <input
                    v-model="search"
                    class="input flex-1"
                    placeholder="ابحث بالعنوان أو الوصف أو التصنيف..."
                    @keyup.enter="filter()"
                />
                <select v-model="category" class="input lg:w-52" @change="filter()">
                    <option value="">كل التصنيفات</option>
                    <option v-for="item in categories" :key="item" :value="item">{{ item }}</option>
                </select>
                <select v-model="statusFilter" class="input lg:w-48" @change="filter()">
                    <option value="">كل الحالات</option>
                    <option value="pending">⏳ بانتظار المراجعة</option>
                    <option value="approved">✅ المعتمدة</option>
                    <option value="rejected">❌ المرفوضة</option>
                </select>
                <button type="button" class="btn-outline btn-sm" @click="filter()">بحث</button>
            </div>

            <!-- Resource Cards Grid -->
            <div v-if="resourceRows.length" class="grid xl:grid-cols-2 gap-4">
                <article
                    v-for="resource in resourceRows"
                    :key="resource.id"
                    class="card p-5 space-y-4 border transition-all"
                    :class="[
                        resource.approval_status === 'pending' ? 'border-amber-200 dark:border-amber-900/40 bg-amber-50/10' : '',
                        resource.approval_status === 'rejected' ? 'border-rose-200 dark:border-rose-900/40 bg-rose-50/10' : '',
                        resource.approval_status === 'approved' ? 'border-surface-100 dark:border-surface-800' : '',
                    ]"
                >
                    <!-- Card Header -->
                    <div class="flex items-start gap-3">
                        <div class="w-11 h-11 rounded-xl bg-primary-50 dark:bg-primary-950 flex items-center justify-center text-primary-600 shrink-0">
                            <Icon name="attachment" class="w-5 h-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 class="font-bold text-surface-900 dark:text-white truncate text-base">{{ resource.title }}</h2>
                            <p class="text-xs text-surface-500 mt-1">
                                {{ resource.category }} · {{ resource.size_label }}
                                <span v-if="resource.created_at_human">· رفع منذ {{ resource.created_at_human }}</span>
                            </p>
                        </div>
                        <a
                            v-if="resource.file_url"
                            :href="resource.file_url"
                            target="_blank"
                            class="btn-outline btn-sm shrink-0"
                        >
                            فتح الملف
                        </a>
                    </div>

                    <!-- Status Banner -->
                    <div
                        class="px-3.5 py-2 rounded-xl text-xs flex items-center justify-between gap-2"
                        :class="statusBadgeClasses[resource.approval_status] || statusBadgeClasses.none"
                    >
                        <span class="font-bold flex items-center gap-1.5">
                            {{ statusLabels[resource.approval_status] || resource.approval_status }}
                        </span>
                        <span v-if="resource.approval_status === 'pending'" class="text-[11px] opacity-80">
                            قيد تدقيق الإدارة
                        </span>
                    </div>

                    <!-- Admin Feedback: Rejection Reason & Suggestions -->
                    <div v-if="resource.rejection_reason || resource.admin_notes" class="space-y-2">
                        <!-- Rejection reason -->
                        <div
                            v-if="resource.rejection_reason"
                            class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 text-rose-900 dark:text-rose-200 text-xs space-y-1"
                        >
                            <div class="font-bold flex items-center gap-1 text-rose-700 dark:text-rose-300">
                                <span>⛔ سبب الرفض من الإدارة:</span>
                            </div>
                            <p class="leading-relaxed">{{ resource.rejection_reason }}</p>
                        </div>

                        <!-- Suggestions / Notes -->
                        <div
                            v-if="resource.admin_notes"
                            class="p-3.5 rounded-xl bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-900/60 text-sky-900 dark:text-sky-200 text-xs space-y-1"
                        >
                            <div class="font-bold flex items-center gap-1 text-sky-700 dark:text-sky-300">
                                <span>💡 مقترحات وملاحظات الإدارة:</span>
                            </div>
                            <p class="leading-relaxed">{{ resource.admin_notes }}</p>
                        </div>
                    </div>

                    <p v-if="resource.description" class="text-sm text-surface-600 dark:text-surface-300 leading-relaxed">
                        {{ resource.description }}
                    </p>

                    <div v-if="resource.tags?.length" class="flex flex-wrap gap-1.5">
                        <span v-for="tag in resource.tags" :key="tag" class="badge-accent text-[10px]">#{{ tag }}</span>
                    </div>

                    <!-- Attached assignments (only when approved) -->
                    <div v-if="resource.assignments?.length" class="space-y-1.5 pt-2">
                        <div class="text-[11px] font-bold text-surface-500">التكليفات المربوطة المتاحة للطلاب:</div>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="assignment in resource.assignments" :key="assignment.id" class="badge-green text-[10px]">
                                {{ assignment.subject?.name ?? 'تكليف' }}<span v-if="assignment.grade?.name"> · {{ assignment.grade.name }}</span>
                                <button v-if="resource.owner?.id === currentUserId" type="button" class="ms-1 underline" @click="detach(resource, assignment.id)">×</button>
                            </span>
                        </div>
                    </div>

                    <!-- Footer Controls -->
                    <div class="pt-3 border-t border-surface-100 dark:border-surface-800 space-y-2">
                        <!-- If Approved: Can attach to assignments -->
                        <div v-if="resource.approval_status === 'approved'" class="flex items-center gap-2 flex-wrap">
                            <select
                                v-if="assignments.length"
                                v-model="selectedAssignment[resource.id]"
                                class="input input-sm flex-1 min-w-40"
                            >
                                <option value="">ربط المورد بتكليف طلاب...</option>
                                <option v-for="assignment in assignments" :key="assignment.id" :value="assignment.id">
                                    {{ assignment.subject?.name ?? 'تكليف' }}<span v-if="assignment.grade?.name"> · {{ assignment.grade.name }}</span>
                                </option>
                            </select>
                            <button
                                v-if="assignments.length"
                                type="button"
                                class="btn-outline btn-sm"
                                :disabled="!selectedAssignment[resource.id]"
                                @click="attach(resource)"
                            >
                                ربط بالطلاب
                            </button>
                            <button
                                v-if="resource.owner?.id === currentUserId"
                                type="button"
                                class="btn-ghost btn-sm text-red-500 ms-auto"
                                @click="destroy(resource)"
                            >
                                حذف
                            </button>
                        </div>

                        <!-- If Pending: Locked explanation -->
                        <div v-else-if="resource.approval_status === 'pending'" class="flex items-center justify-between gap-2 text-xs text-amber-700 dark:text-amber-400">
                            <span class="flex items-center gap-1.5">
                                <span>🔒</span>
                                <span>يلزم اعتماد الإدارة أولاً لتتمكن من ربط المورد بالطلاب.</span>
                            </span>
                            <button
                                v-if="resource.owner?.id === currentUserId"
                                type="button"
                                class="btn-ghost btn-sm text-red-500"
                                @click="destroy(resource)"
                            >
                                إلغاء وحذف
                            </button>
                        </div>

                        <!-- If Rejected: Guidance -->
                        <div v-else-if="resource.approval_status === 'rejected'" class="flex items-center justify-between gap-2 text-xs text-rose-700 dark:text-rose-400">
                            <span class="flex items-center gap-1.5">
                                <span>⚠️</span>
                                <span>يرجى مراجعة سبب الرفض أعلاه وتعديل الملف ثم إعادة الرفع.</span>
                            </span>
                            <button
                                v-if="resource.owner?.id === currentUserId"
                                type="button"
                                class="btn-outline btn-sm text-red-600 border-red-300"
                                @click="destroy(resource)"
                            >
                                حذف المورد المرفوض
                            </button>
                        </div>
                    </div>
                </article>
            </div>

            <div v-else class="card p-12 text-center text-surface-500 space-y-3">
                <Icon name="book" class="w-10 h-10 text-surface-300 mx-auto" />
                <h2 class="font-bold text-surface-700 dark:text-surface-200">لا توجد موارد مطابقة</h2>
                <p class="text-sm">ارفع أول مورد تعليمي لتبدأ بمراجعته واعتماده.</p>
            </div>

            <div v-if="resources?.links?.length > 3" class="flex justify-center gap-1">
                <Link
                    v-for="link in resources.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    :class="['btn-ghost btn-sm', link.active ? 'bg-primary-500/10 text-primary-600 font-bold' : '']"
                    v-html="link.label"
                />
            </div>
        </div>
    </DashboardLayout>
</template>
