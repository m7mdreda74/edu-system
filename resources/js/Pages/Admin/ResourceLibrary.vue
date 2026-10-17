<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    resources: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    counts: { type: Object, default: () => ({ total: 0, pending: 0, approved: 0, rejected: 0 }) },
});

const currentStatus = ref(props.filters.status ?? '');
const search = ref(props.filters.search ?? '');

// Modals state
const activeResource = ref(null);
const showApproveModal = ref(false);
const showRejectModal = ref(false);
const showNotesModal = ref(false);

const approveForm = useForm({
    admin_notes: '',
});

const rejectForm = useForm({
    rejection_reason: '',
    admin_notes: '',
});

const notesForm = useForm({
    admin_notes: '',
    rejection_reason: '',
});

function applyFilter(newStatus = null) {
    if (newStatus !== null) {
        currentStatus.value = newStatus;
    }
    router.get(route('admin.resource-library'), {
        status: currentStatus.value || undefined,
        search: search.value || undefined,
    }, { preserveState: true, replace: true });
}

function openApproveModal(resource) {
    activeResource.value = resource;
    approveForm.reset();
    approveForm.admin_notes = resource.admin_notes || '';
    showApproveModal.value = true;
}

function confirmApprove() {
    if (!activeResource.value) return;
    approveForm.patch(route('admin.resource-library.approve', activeResource.value.id), {
        onSuccess: () => {
            showApproveModal.value = false;
            activeResource.value = null;
        },
    });
}

function openRejectModal(resource) {
    activeResource.value = resource;
    rejectForm.reset();
    rejectForm.rejection_reason = resource.rejection_reason || '';
    rejectForm.admin_notes = resource.admin_notes || '';
    showRejectModal.value = true;
}

function confirmReject() {
    if (!activeResource.value) return;
    rejectForm.patch(route('admin.resource-library.reject', activeResource.value.id), {
        onSuccess: () => {
            showRejectModal.value = false;
            activeResource.value = null;
        },
    });
}

function openNotesModal(resource) {
    activeResource.value = resource;
    notesForm.reset();
    notesForm.admin_notes = resource.admin_notes || '';
    notesForm.rejection_reason = resource.rejection_reason || '';
    showNotesModal.value = true;
}

function confirmUpdateNotes() {
    if (!activeResource.value) return;
    notesForm.patch(route('admin.resource-library.update-notes', activeResource.value.id), {
        onSuccess: () => {
            showNotesModal.value = false;
            activeResource.value = null;
        },
    });
}

const statusBadgeClasses = {
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-300 dark:border-amber-700',
    approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700',
    rejected: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 border border-rose-300 dark:border-rose-700',
    none: 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300',
};

const statusLabels = {
    pending: '⏳ بانتظار المراجعة والاعتماد',
    approved: '✅ معتمد ومتاح للطلاب',
    rejected: '❌ مرفوض من الإدارة',
    none: 'خاص للمعلم',
};
</script>

<template>
    <Head title="مراجعة مكتبة الموارد التعليمية" />
    <DashboardLayout>
        <div class="space-y-6 max-w-7xl mx-auto">
            <!-- Header -->
            <header class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <span class="p-2 rounded-xl bg-primary-100 dark:bg-primary-950 text-primary-600 dark:text-primary-400">
                            <Icon name="book" class="w-6 h-6" />
                        </span>
                        مراجعة مكتبة الموارد التعليمية
                    </h1>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                        فحص ملفات الموارد المرفوعة من المعلمين، اعتمادها مع تدوين المقترحات والملاحظات، أو رفضها مع بيان الأسباب.
                    </p>
                </div>
            </header>

            <!-- Status Tabs & Filter -->
            <div class="card p-4 space-y-4">
                <div class="flex items-center gap-2 flex-wrap border-b border-surface-100 dark:border-surface-800 pb-3">
                    <button
                        type="button"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5"
                        :class="currentStatus === '' ? 'bg-primary-600 text-white shadow-sm' : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300 hover:bg-surface-200'"
                        @click="applyFilter('')"
                    >
                        <span>كل الموارد</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-black/20 text-white">{{ counts.total }}</span>
                    </button>
                    <button
                        type="button"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5"
                        :class="currentStatus === 'pending' ? 'bg-amber-600 text-white shadow-sm' : 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 hover:bg-amber-100'"
                        @click="applyFilter('pending')"
                    >
                        <span>⏳ بانتظار المراجعة</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-black/20 text-white">{{ counts.pending }}</span>
                    </button>
                    <button
                        type="button"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5"
                        :class="currentStatus === 'approved' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 hover:bg-emerald-100'"
                        @click="applyFilter('approved')"
                    >
                        <span>✅ معتمدة</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-black/20 text-white">{{ counts.approved }}</span>
                    </button>
                    <button
                        type="button"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5"
                        :class="currentStatus === 'rejected' ? 'bg-rose-600 text-white shadow-sm' : 'bg-rose-50 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300 hover:bg-rose-100'"
                        @click="applyFilter('rejected')"
                    >
                        <span>❌ مرفوضة</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-black/20 text-white">{{ counts.rejected }}</span>
                    </button>
                </div>

                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="relative flex-1">
                        <input
                            v-model="search"
                            type="text"
                            class="input w-full ps-10"
                            placeholder="ابحث بالعنوان، اسم المعلم، التصنيف..."
                            @keyup.enter="applyFilter(null)"
                        />
                        <span class="absolute inset-y-0 start-3 flex items-center text-surface-400 pointer-events-none">
                            <Icon name="search" class="w-4 h-4" />
                        </span>
                    </div>
                    <button type="button" class="btn-primary btn-sm shrink-0" @click="applyFilter(null)">
                        بحث
                    </button>
                    <button
                        v-if="search || currentStatus"
                        type="button"
                        class="btn-ghost btn-sm shrink-0 text-surface-500"
                        @click="search = ''; applyFilter('')"
                    >
                        إعادة ضبط
                    </button>
                </div>
            </div>

            <!-- Resource Cards List -->
            <div v-if="resources.data?.length" class="space-y-4">
                <article
                    v-for="resource in resources.data"
                    :key="resource.id"
                    class="card p-5 transition-all border border-surface-100 dark:border-surface-800 hover:border-surface-300 dark:hover:border-surface-700 space-y-4"
                >
                    <!-- Top bar: Teacher info and Status -->
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-surface-100 dark:bg-surface-800 flex items-center justify-center font-bold text-surface-700 dark:text-surface-200">
                                {{ resource.owner?.name?.charAt(0) || 'م' }}
                            </div>
                            <div>
                                <h3 class="font-bold text-surface-900 dark:text-white text-base">
                                    {{ resource.title }}
                                </h3>
                                <div class="text-xs text-surface-500 dark:text-surface-400 flex items-center gap-2 mt-0.5">
                                    <span class="font-medium text-surface-700 dark:text-surface-300">المعلم: {{ resource.owner?.name }}</span>
                                    <span>·</span>
                                    <span>{{ resource.category }}</span>
                                    <span>·</span>
                                    <span>{{ resource.size_label }}</span>
                                    <span v-if="resource.created_at_human">· رفع منذ {{ resource.created_at_human }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Status badge -->
                        <span
                            class="px-3 py-1 rounded-full text-xs font-bold shrink-0"
                            :class="statusBadgeClasses[resource.approval_status] || statusBadgeClasses.none"
                        >
                            {{ statusLabels[resource.approval_status] || resource.approval_status }}
                        </span>
                    </div>

                    <!-- Description & tags -->
                    <p v-if="resource.description" class="text-sm text-surface-600 dark:text-surface-300 leading-relaxed bg-surface-50 dark:bg-surface-900/50 p-3 rounded-lg">
                        {{ resource.description }}
                    </p>

                    <div v-if="resource.tags?.length" class="flex flex-wrap gap-1.5">
                        <span
                            v-for="tag in resource.tags"
                            :key="tag"
                            class="px-2 py-0.5 rounded-md text-[11px] font-medium bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300"
                        >
                            #{{ tag }}
                        </span>
                    </div>

                    <!-- Admin Feedback Displays -->
                    <div v-if="resource.rejection_reason || resource.admin_notes" class="space-y-2 pt-2">
                        <!-- Rejection reason -->
                        <div
                            v-if="resource.rejection_reason"
                            class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/50 text-rose-900 dark:text-rose-200 text-xs space-y-1"
                        >
                            <div class="font-bold flex items-center gap-1.5 text-rose-700 dark:text-rose-300">
                                <span>⛔ سبب الرفض:</span>
                            </div>
                            <p class="leading-relaxed">{{ resource.rejection_reason }}</p>
                        </div>

                        <!-- Suggestions / Notes -->
                        <div
                            v-if="resource.admin_notes"
                            class="p-3.5 rounded-xl bg-sky-50 dark:bg-sky-950/30 border border-sky-200 dark:border-sky-900/50 text-sky-900 dark:text-sky-200 text-xs space-y-1"
                        >
                            <div class="font-bold flex items-center gap-1.5 text-sky-700 dark:text-sky-300">
                                <span>💡 ملاحظات ومقترحات الإدارة للمعلم:</span>
                            </div>
                            <p class="leading-relaxed">{{ resource.admin_notes }}</p>
                        </div>
                    </div>

                    <!-- Review metadata -->
                    <div v-if="resource.approved_by || resource.rejected_at" class="text-[11px] text-surface-400 flex items-center gap-3">
                        <span v-if="resource.approved_by">اعتمد بواسطة: {{ resource.approved_by.name }} ({{ resource.approved_at }})</span>
                        <span v-if="resource.rejected_at">تاريخ الرفض: {{ resource.rejected_at }}</span>
                    </div>

                    <!-- Actions Footer -->
                    <div class="flex items-center justify-between gap-3 pt-3 border-t border-surface-100 dark:border-surface-800 flex-wrap">
                        <div class="flex items-center gap-2">
                            <a
                                v-if="resource.file_url"
                                :href="resource.file_url"
                                target="_blank"
                                class="btn-outline btn-sm flex items-center gap-1.5"
                            >
                                <Icon name="attachment" class="w-4 h-4" />
                                <span>معاينة وتحميل الملف</span>
                            </a>
                            <span v-if="resource.original_name" class="text-xs text-surface-400">
                                ({{ resource.original_name }})
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <!-- Pending Actions -->
                            <template v-if="resource.approval_status === 'pending'">
                                <button
                                    type="button"
                                    class="btn-primary btn-sm bg-emerald-600 hover:bg-emerald-700 border-none flex items-center gap-1"
                                    @click="openApproveModal(resource)"
                                >
                                    <span>اعتماد وموافقة</span>
                                </button>
                                <button
                                    type="button"
                                    class="btn-outline btn-sm text-rose-600 border-rose-300 hover:bg-rose-50 dark:border-rose-800 dark:hover:bg-rose-950 flex items-center gap-1"
                                    @click="openRejectModal(resource)"
                                >
                                    <span>رفض مع أسباب</span>
                                </button>
                            </template>

                            <!-- Approved / Rejected Actions -->
                            <template v-else>
                                <button
                                    type="button"
                                    class="btn-ghost btn-sm text-surface-600 dark:text-surface-300 flex items-center gap-1"
                                    @click="openNotesModal(resource)"
                                >
                                    <span>✏️ تعديل الملاحظات</span>
                                </button>

                                <button
                                    v-if="resource.approval_status === 'rejected'"
                                    type="button"
                                    class="btn-outline btn-sm text-emerald-600 border-emerald-300 hover:bg-emerald-50 dark:border-emerald-800"
                                    @click="openApproveModal(resource)"
                                >
                                    إعادة الاعتماد
                                </button>

                                <button
                                    v-if="resource.approval_status === 'approved'"
                                    type="button"
                                    class="btn-ghost btn-sm text-rose-600 hover:bg-rose-50"
                                    @click="openRejectModal(resource)"
                                >
                                    سحب الاعتماد ورفض
                                </button>
                            </template>
                        </div>
                    </div>
                </article>
            </div>

            <!-- Empty state -->
            <div v-else class="card p-12 text-center text-surface-500 space-y-3">
                <div class="w-12 h-12 rounded-full bg-surface-100 dark:bg-surface-800 flex items-center justify-center mx-auto text-surface-400">
                    <Icon name="book" class="w-6 h-6" />
                </div>
                <h3 class="font-bold text-surface-800 dark:text-surface-200 text-base">لا توجد موارد مطابقة</h3>
                <p class="text-xs text-surface-400 max-w-sm mx-auto">
                    لا توجد ملفات تحتاج للمراجعة حالياً ضمن التصفية المحددة.
                </p>
            </div>

            <!-- Pagination -->
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

        <!-- Approve Modal -->
        <div v-if="showApproveModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="card p-6 max-w-lg w-full space-y-4 shadow-2xl animate-in fade-in zoom-in-95">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-black text-surface-900 dark:text-white flex items-center gap-2">
                            <span>✅ اعتماد المورد التعليمي</span>
                        </h3>
                        <p class="text-xs text-surface-500 mt-1">
                            سيتم اعتماد المورد "{{ activeResource?.title }}" وإتاحته للمعلم لربطه بتكليفات وحصص الطلاب.
                        </p>
                    </div>
                    <button type="button" class="text-surface-400 hover:text-surface-600" @click="showApproveModal = false">✕</button>
                </div>

                <div class="space-y-2">
                    <label class="input-label text-xs">
                        ملاحظات أو مقترحات للمعلم (اختياري)
                    </label>
                    <textarea
                        v-model="approveForm.admin_notes"
                        class="input w-full text-xs"
                        rows="4"
                        placeholder="اكتب أي مقترحات، إشادات أو توجيهات للمعلم بشأن هذا المحتوى..."
                    ></textarea>
                    <p class="text-[11px] text-surface-400">ستصل هذه المقترحات والملاحظات للمعلم مع إشعار الاعتماد.</p>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-surface-100 dark:border-surface-800">
                    <button type="button" class="btn-ghost btn-sm" @click="showApproveModal = false">إلغاء</button>
                    <button
                        type="button"
                        class="btn-primary btn-sm bg-emerald-600 hover:bg-emerald-700 border-none"
                        :disabled="approveForm.processing"
                        @click="confirmApprove"
                    >
                        {{ approveForm.processing ? 'جارٍ الاعتماد...' : 'تأكيد الاعتماد والموافقة' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Reject Modal -->
        <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="card p-6 max-w-lg w-full space-y-4 shadow-2xl animate-in fade-in zoom-in-95">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-black text-rose-600 dark:text-rose-400 flex items-center gap-2">
                            <span>❌ رفض المورد التعليمي</span>
                        </h3>
                        <p class="text-xs text-surface-500 mt-1">
                            يرجى توضيح سبب الرفض وإضافة المقترحات لمساعدة المعلم على تعديل الملف وإعادة رفعه.
                        </p>
                    </div>
                    <button type="button" class="text-surface-400 hover:text-surface-600" @click="showRejectModal = false">✕</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="input-label text-xs text-rose-700 dark:text-rose-300 font-bold">
                            سبب الرفض (إلزامي) *
                        </label>
                        <textarea
                            v-model="rejectForm.rejection_reason"
                            class="input w-full text-xs"
                            rows="3"
                            required
                            placeholder="مثال: الملف غير واضح الجودة / المحتوى غير مطابق لمنهج المرحلة / الملف تالف أو ناقص صفحات..."
                        ></textarea>
                        <p v-if="rejectForm.errors.rejection_reason" class="text-xs text-rose-500 mt-1">
                            {{ rejectForm.errors.rejection_reason }}
                        </p>
                    </div>

                    <div>
                        <label class="input-label text-xs">
                            مقترحات وتوجيهات لتعديل الملف (اختياري)
                        </label>
                        <textarea
                            v-model="rejectForm.admin_notes"
                            class="input w-full text-xs"
                            rows="3"
                            placeholder="مثال: يرجى تصدير الملف بدقة أعلى أو إرفاق صيغة PDF بدلاً من الصور..."
                        ></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-surface-100 dark:border-surface-800">
                    <button type="button" class="btn-ghost btn-sm" @click="showRejectModal = false">إلغاء</button>
                    <button
                        type="button"
                        class="btn-primary btn-sm bg-rose-600 hover:bg-rose-700 border-none"
                        :disabled="rejectForm.processing || !rejectForm.rejection_reason?.trim()"
                        @click="confirmReject"
                    >
                        {{ rejectForm.processing ? 'جارٍ الرفض...' : 'تأكيد رفض المورد' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Notes Modal -->
        <div v-if="showNotesModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="card p-6 max-w-lg w-full space-y-4 shadow-2xl animate-in fade-in zoom-in-95">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-black text-surface-900 dark:text-white flex items-center gap-2">
                            <span>✏️ تعديل ملاحظات وتوجيهات الإدارة</span>
                        </h3>
                        <p class="text-xs text-surface-500 mt-1">
                            تحديث المقترحات أو أسباب الرفض الظاهرة للمعلم.
                        </p>
                    </div>
                    <button type="button" class="text-surface-400 hover:text-surface-600" @click="showNotesModal = false">✕</button>
                </div>

                <div class="space-y-3">
                    <div v-if="activeResource?.approval_status === 'rejected'">
                        <label class="input-label text-xs text-rose-700 dark:text-rose-300 font-bold">
                            سبب الرفض
                        </label>
                        <textarea
                            v-model="notesForm.rejection_reason"
                            class="input w-full text-xs"
                            rows="2"
                        ></textarea>
                    </div>

                    <div>
                        <label class="input-label text-xs">
                            مقترحات وملاحظات الإدارة للمعلم
                        </label>
                        <textarea
                            v-model="notesForm.admin_notes"
                            class="input w-full text-xs"
                            rows="4"
                            placeholder="اكتب التوجيهات أو المقترحات..."
                        ></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-surface-100 dark:border-surface-800">
                    <button type="button" class="btn-ghost btn-sm" @click="showNotesModal = false">إلغاء</button>
                    <button
                        type="button"
                        class="btn-primary btn-sm"
                        :disabled="notesForm.processing"
                        @click="confirmUpdateNotes"
                    >
                        {{ notesForm.processing ? 'جارٍ الحفظ...' : 'حفظ التعديلات' }}
                    </button>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
