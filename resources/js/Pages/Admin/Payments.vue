<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    payments: { type: Object, required: true },
    filters:  { type: Object, default: () => ({}) },
});

const { confirm } = useConfirm();
const status = ref(props.filters.status ?? '');
const selectedReceipt = ref(null);

function applyFilters() {
    router.get(route('admin.payments'), { status: status.value || undefined },
        { preserveState: true, replace: true });
}

function formatQAR(halala) {
    const formatted = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format((halala ?? 0) / 100);
    return `${formatted} ر.ق.`;
}

function formatCompactDate(value) {
    if (!value) return '—';

    return String(value).replace('T', ' ').slice(0, 16);
}

function formatTransferMethod(payment) {
    if (payment.gateway === 'vodafone_cash') return 'فودافون كاش';

    return 'سجل سابق';
}

function canReview(payment) {
    return ['vodafone_cash', 'manual'].includes(payment.gateway)
        && payment.status === 'pending_verification';
}

function isPdfReceipt(payment) {
    return String(payment.receipt_path ?? '').toLowerCase().endsWith('.pdf');
}

function viewReceipt(payment) {
    const url = route('admin.payments.receipt', payment.id);

    if (isPdfReceipt(payment)) {
        window.open(url, '_blank', 'noopener');
        return;
    }

    selectedReceipt.value = url;
}

const statusColors = {
    paid:    'badge-green',
    pending: 'badge-primary',
    pending_verification: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 px-2 py-0.5 rounded-full text-xs font-semibold',
    failed:  'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-400 px-2 py-0.5 rounded-full text-xs font-semibold',
    refunded:'badge-gray',
};

const statusLabels = {
    paid: 'مدفوع',
    pending: 'قيد الانتظار',
    pending_verification: 'قيد التحقق يدوياً',
    failed: 'فشل / مرفوض',
    refunded: 'مُسترد'
};

async function approvePayment(p) {
    const ok = await confirm({
        title: 'اعتماد الدفع',
        message: 'هل أنت متأكد من صحة إيصال الدفع وتفعيل الاشتراك للطالب؟',
        confirmLabel: 'نعم، اعتماد',
        variant: 'info',
    });
    if (ok) {
        router.post(route('admin.payments.approve', { payment: p.id }), { note: 'تمت مطابقة إيصال التحويل واعتماده.' });
    }
}

async function rejectPayment(p) {
    const result = await confirm({
        title: 'رفض إيصال التحويل',
        message: `سيتم تسجيل سبب الرفض وإغلاق طلب التحويل.`,
        confirmLabel: 'رفض',
        cancelLabel: 'إلغاء',
        variant: 'danger',
        inputLabel: 'سبب الرفض',
        inputPlaceholder: 'اكتب سبب رفض إيصال التحويل...',
    });
    if (result?.confirmed) {
        router.post(route('admin.payments.reject', { payment: p.id }), { reason: result.value });
    }
}

</script>

<template>
    <DashboardLayout>
        <Head title="المدفوعات" />

        <div class="dashboard-data-page">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <Icon name="payments" class="w-8 h-8 text-primary-500" />
                        <span>المدفوعات</span>
                    </h1>
                    <p class="text-surface-500 mt-1">{{ payments.total }} عملية دفع</p>
                </div>
                <Link :href="route('admin.dashboard')" class="btn-ghost">← الداشبورد</Link>
            </div>

            <!-- Status filter -->
            <div class="card p-4">
                <select v-model="status" @change="applyFilters" class="input w-48" id="payment-status-filter">
                    <option value="">كل الحالات</option>
                    <option value="paid">مدفوع</option>
                    <option value="pending">قيد الانتظار</option>
                    <option value="pending_verification">قيد التحقق (يدوي)</option>
                    <option value="failed">فشل / مرفوض</option>
                    <option value="refunded">مُسترد</option>
                </select>
            </div>

            <div class="card data-table-card">
                <div class="data-table-scroll no-scrollbar">
                    <table class="data-table payments-table">
                        <colgroup>
                            <col class="payment-col-id" />
                            <col class="payment-col-student" />
                            <col class="payment-col-subscription" />
                            <col class="payment-col-amount" />
                            <col class="payment-col-split" />
                            <col class="payment-col-method" />
                            <col class="payment-col-status" />
                            <col class="payment-col-date" />
                            <col class="payment-col-actions" />
                        </colgroup>
                        <thead class="bg-surface-50 dark:bg-surface-800 border-b border-surface-200 dark:border-surface-700">
                            <tr>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">#</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">الطالب</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">الاشتراك</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">المبلغ</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">التوزيع المالي</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">طريقة التحويل / رقم المُحوِّل</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">الحالة</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">التاريخ</th>
                                <th class="data-table-actions text-start p-4 font-semibold text-surface-600 dark:text-surface-300">التحقق والتحكم</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-700">
                            <tr v-for="p in payments.data" :key="p.id"
                                class="hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors"
                                :class="p.status === 'pending_verification' ? 'bg-amber-500/5 dark:bg-amber-500/5' : ''">
                                <td class="p-4 text-surface-400 font-mono">{{ p.id }}</td>
                                <td class="p-4 payment-truncate" :title="`${p.user?.name || ''} — ${p.user?.email || ''}`">
                                    <span class="font-semibold text-surface-800 dark:text-white">{{ p.user?.name }}</span>
                                    <span v-if="p.user?.email" class="text-surface-400"> — {{ p.user.email }}</span>
                                </td>
                                <td class="p-4 payment-truncate text-surface-600 dark:text-surface-300" :title="p.subscription?.assignment?.subject?.name ?? 'اشتراك'">
                                    {{ p.subscription?.assignment?.subject?.name ?? "اشتراك" }}
                                </td>
                                <td class="p-4 font-bold text-primary-700 dark:text-primary-400">
                                    {{ formatQAR(p.amount) }}
                                </td>
                                <td class="p-4 payment-truncate" :title="`المدرس: ${formatQAR(p.teacher_earnings)} — المنصة: ${formatQAR(p.platform_commission_amount)}`">
                                    <span class="text-green-600">مدرس: {{ formatQAR(p.teacher_earnings) }}</span>
                                    <span class="mx-1 text-surface-400">|</span>
                                    <span class="text-primary-600">منصة: {{ formatQAR(p.platform_commission_amount) }}<span v-if="p.commission_percent !== null"> ({{ p.commission_percent }}%)</span></span>
                                </td>
                                <td class="p-4 payment-truncate" :title="p.sender_phone ? `فودافون كاش — من ${p.sender_phone}` : p.gateway_ref">
                                    <span class="badge-gray payment-compact-badge">{{ formatTransferMethod(p) }}</span>
                                    <span v-if="p.sender_phone" class="block mt-1 text-[10px] text-surface-500 dark:text-surface-400 font-mono" dir="ltr">{{ p.sender_phone }}</span>
                                </td>
                                <td class="p-4">
                                    <span class="payment-compact-badge" :class="statusColors[p.status]">
                                        {{ statusLabels[p.status] }}
                                    </span>
                                </td>
                                <td class="p-4 font-mono text-surface-400" :title="p.paid_at || p.created_at || ''">{{ formatCompactDate(p.paid_at || p.created_at) }}</td>
                                <td class="data-table-actions p-3">
                                    <div class="payment-actions flex flex-nowrap items-center gap-1">
                                        <button v-if="p.receipt_path" type="button" @click="viewReceipt(p)" class="btn-outline payment-action-button flex items-center gap-1">
                                            <Icon name="eye" class="w-3 h-3 shrink-0" />
                                            <span>{{ isPdfReceipt(p) ? 'عرض الملف' : 'عرض الإيصال' }}</span>
                                        </button>
                                        <template v-if="canReview(p)">
                                            <button type="button" @click="approvePayment(p)" class="btn-primary payment-action-button flex items-center gap-1 bg-green-600 hover:bg-green-700 border-none">
                                                <Icon name="success" class="w-3 h-3 shrink-0" />
                                                <span>موافقة</span>
                                            </button>
                                            <button type="button" @click="rejectPayment(p)" class="payment-action-button inline-flex items-center gap-1 rounded-lg bg-red-600 font-bold text-white transition-colors hover:bg-red-700">
                                                <Icon name="close" class="h-3 w-3 shrink-0" />
                                                <span>رفض</span>
                                            </button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="!payments.data?.length" class="flex-1 p-16 text-center text-surface-400 flex flex-col items-center justify-center gap-2">
                    <Icon name="payments" class="w-12 h-12 text-surface-400" />
                    <p>لا توجد مدفوعات</p>
                </div>
                <DataTablePagination :paginator="payments" item-label="عملية دفع" />
            </div>

            <!-- Receipt Modal Viewer -->
            <div v-if="selectedReceipt" class="modal-overlay z-50 bg-surface-950/85 backdrop-blur-sm" @click="selectedReceipt = null">
                <div class="modal-panel-compact relative max-w-2xl w-full bg-white dark:bg-surface-900 rounded-3xl p-6 shadow-2xl space-y-4" @click.stop>
                    <div class="flex justify-between items-center border-b border-surface-150 dark:border-surface-800 pb-3">
                        <h3 class="font-bold text-base text-surface-900 dark:text-white flex items-center gap-2">
                            <Icon name="eye" class="w-5 h-5 text-amber-500" />
                            <span>صورة إيصال التحويل</span>
                        </h3>
                        <button @click="selectedReceipt = null" class="btn-ghost p-1.5 rounded-lg text-surface-400 hover:text-surface-700">✕</button>
                    </div>
                    <div class="w-full max-h-[70vh] rounded-2xl overflow-hidden bg-surface-50 dark:bg-surface-950 border border-surface-200 dark:border-surface-800 p-2">
                        <img :src="selectedReceipt" class="w-full max-h-[65vh] object-contain mx-auto rounded-xl" />
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>

<style scoped>
.payments-table {
    font-size: 0.625rem;
}

.payments-table th,
.payments-table td {
    padding-block: 0.25rem !important;
    padding-inline: 0.375rem !important;
    white-space: nowrap !important;
    overflow-wrap: normal !important;
}

.payments-table tbody > tr > td {
    height: 2.5rem;
}

.payment-truncate {
    overflow: hidden;
    text-overflow: ellipsis;
}

.payment-compact-badge {
    display: inline-block;
    max-width: 100%;
    overflow: hidden;
    padding: 0.125rem 0.375rem !important;
    font-size: 0.625rem !important;
    line-height: 1rem;
    text-overflow: ellipsis;
    vertical-align: middle;
    white-space: nowrap;
}

.payment-action-button {
    flex: none;
    min-height: 1.5rem !important;
    padding: 0.2rem 0.375rem !important;
    font-size: 0.625rem !important;
    line-height: 1rem;
    white-space: nowrap;
}

.payment-actions {
    width: 100%;
    justify-content: flex-start;
    direction: rtl;
}

@media (min-width: 1280px) {
    .payments-table {
        table-layout: fixed;
    }

    .payment-col-id { width: 3.5%; }
    .payment-col-student { width: 15.5%; }
    .payment-col-subscription { width: 10%; }
    .payment-col-amount { width: 7%; }
    .payment-col-split { width: 12%; }
    .payment-col-method { width: 12%; }
    .payment-col-status { width: 8.5%; }
    .payment-col-date { width: 8.5%; }
    .payment-col-actions { width: 23%; }
}
</style>

