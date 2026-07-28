<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    payouts: { type: Array, required: true },
    teachers: { type: Array, required: true },
    balances: { type: Object, default: () => ({}) },
    defaultCommission: { type: Number, default: 20 },
});

const createOpen = ref(false);
const payOpen = ref(false);
const selectedPayout = ref(null);
const selectedReceipt = ref(null);
const form = useForm({ teacher_id: '', period_start: '', period_end: '', notes: '', receipt: null });
const payForm = useForm({ receipt: null, notes: '' });
const { confirm } = useConfirm();

const selectedBalance = computed(() => props.balances[String(form.teacher_id)] ?? props.balances[form.teacher_id] ?? null);
const formatQAR = value => `${new Intl.NumberFormat('ar-QA', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format((value ?? 0) / 100)} ر.ق.`;

function submitPayout() {
    form.post(route('admin.payouts.store'), { forceFormData: true, onSuccess: () => { createOpen.value = false; form.reset(); } });
}

function setReceipt(event) {
    form.receipt = event.target.files[0] || null;
}

function openPay(payout) {
    selectedPayout.value = payout;
    payForm.reset();
    payOpen.value = true;
}

function submitPaymentProof() {
    payForm.post(route('admin.payouts.pay', selectedPayout.value.id), {
        forceFormData: true,
        onSuccess: () => { payOpen.value = false; payForm.reset(); },
    });
}

async function deletePayout(id) {
    const ok = await confirm({
        title: 'حذف التصفية',
        message: 'سيتم حذف التصفية وإرجاع معاملاتها إلى الرصيد المتاح.',
        confirmLabel: 'حذف',
        variant: 'danger',
    });
    if (ok) router.delete(route('admin.payouts.destroy', id));
}

const statusLabel = payout => payout.status === 'paid' ? 'تم التحويل' : 'تسوية معلقة';
</script>

<template>
    <DashboardLayout>
        <Head title="تصفية حسابات المدرسين" />
        <div class="container-app px-4 py-10 space-y-7">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white flex items-center gap-2"><Icon name="earnings" class="w-8 h-8 text-primary-500" />تصفية حسابات المدرسين</h1>
                    <p class="text-surface-500 mt-1">حساب تلقائي للمبيعات والعمولة، مع إثبات التحويل وإقرار الاستلام.</p>
                </div>
                <button @click="createOpen = true" class="btn-primary"><Icon name="plus" class="w-4 h-4" /> إنشاء تصفية</button>
            </div>

            <section class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
                <div v-for="teacher in teachers" :key="teacher.id" class="card p-5">
                    <div class="flex justify-between gap-3">
                        <div><h3 class="font-black text-surface-900 dark:text-white">{{ teacher.name }}</h3><p class="text-xs text-surface-400">عمولة المنصة الحالية: {{ teacher.commission_percent ?? defaultCommission }}%</p></div>
                        <span class="badge-primary">متاح</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mt-4 text-sm">
                        <div class="rounded-xl bg-green-50 dark:bg-green-950/20 p-3"><p class="text-xs text-surface-500">الصافي بعد الخصومات</p><b class="text-green-600">{{ formatQAR(balances[String(teacher.id)]?.net_teacher_earnings) }}</b></div>
                        <div class="rounded-xl bg-primary-50 dark:bg-primary-950/20 p-3"><p class="text-xs text-surface-500">عمولة المنصة</p><b class="text-primary-600">{{ formatQAR(balances[String(teacher.id)]?.platform_commission_amount) }}</b></div>
                    </div>
                    <p v-if="balances[String(teacher.id)]?.pending_deductions" class="mt-3 text-xs font-bold text-red-500">
                        خصومات الحصص المعلقة: {{ formatQAR(balances[String(teacher.id)]?.pending_deductions) }}
                    </p>
                </div>
            </section>

            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-surface-50 dark:bg-surface-800"><tr><th class="text-start p-4">المدرس</th><th class="text-start p-4">إجمالي الاشتراكات</th><th class="text-start p-4">المستحق قبل الخصم</th><th class="text-start p-4">خصم الحصص</th><th class="text-start p-4">الصافي</th><th class="text-start p-4">عمولة المنصة</th><th class="text-start p-4">الفترة</th><th class="text-start p-4">الحالة</th><th class="text-start p-4">الإجراءات</th></tr></thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                            <tr v-for="payout in payouts" :key="payout.id">
                                <td class="p-4"><b>{{ payout.teacher?.name }}</b><p class="text-xs text-surface-400">{{ payout.teacher?.email }}</p></td>
                                <td class="p-4">{{ formatQAR(payout.gross_amount) }}</td>
                                <td class="p-4">{{ formatQAR(payout.teacher_earnings ?? payout.amount) }}</td>
                                <td class="p-4 font-bold text-red-500">- {{ formatQAR(payout.deductions_amount) }}</td>
                                <td class="p-4 font-bold text-green-600">{{ formatQAR(payout.amount) }}</td>
                                <td class="p-4 text-primary-600">{{ formatQAR(payout.platform_commission_amount) }}</td>
                                <td class="p-4 text-xs">{{ payout.period_start }} — {{ payout.period_end }}</td>
                                <td class="p-4"><span :class="payout.status === 'paid' ? 'badge-green' : 'badge-primary'">{{ statusLabel(payout) }}</span></td>
                                <td class="p-4"><div class="flex flex-wrap gap-2">
                                    <button v-if="payout.status !== 'paid'" @click="openPay(payout)" class="btn-primary btn-sm">رفع إثبات الدفع</button>
                                    <button v-if="payout.receipt_path" @click="selectedReceipt = route('admin.payouts.receipt', payout.id)" class="btn-outline btn-sm">عرض الإثبات</button>
                                    <button v-if="payout.status !== 'paid'" @click="deletePayout(payout.id)" class="btn-ghost btn-sm text-red-500">حذف</button>
                                </div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="!payouts.length" class="p-12 text-center text-surface-400">لا توجد تصفيات حتى الآن.</p>
            </div>
        </div>

        <div v-if="createOpen" class="fixed inset-0 z-50 grid place-items-center p-4 bg-black/60">
            <form @submit.prevent="submitPayout" class="card p-6 w-full max-w-lg space-y-4">
                <h3 class="text-xl font-black">إنشاء تصفية من المعاملات المعتمدة</h3>
                <select v-model="form.teacher_id" class="input" required><option value="" disabled>اختر المدرس</option><option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">{{ teacher.name }}</option></select>
                <div v-if="selectedBalance" class="rounded-xl bg-surface-50 dark:bg-surface-800 p-3 text-sm space-y-1">
                    <p>المستحق قبل الخصم: <b>{{ formatQAR(selectedBalance.teacher_earnings) }}</b></p>
                    <p class="text-red-500">خصومات الحصص: <b>- {{ formatQAR(selectedBalance.pending_deductions) }}</b></p>
                    <p>صافي التسوية المتوقع: <b class="text-green-600">{{ formatQAR(selectedBalance.net_teacher_earnings) }}</b></p>
                </div>
                <div class="grid grid-cols-2 gap-3"><div><label class="input-label">من تاريخ</label><input v-model="form.period_start" type="date" dir="ltr" class="input" required /></div><div><label class="input-label">إلى تاريخ</label><input v-model="form.period_end" type="date" dir="ltr" class="input" required /></div></div>
                <div class="rounded-2xl border-2 border-dashed border-accent-400/60 bg-accent-50/30 dark:bg-accent-950/20 p-4"><label class="input-label">صورة إثبات تحويل مستحقات المدرس <span class="text-red-500">(اختياري هنا، ومطلوب قبل اعتبارها مدفوعة)</span></label><input type="file" accept="image/*" class="input" @change="setReceipt" /><p class="text-[11px] text-surface-500 mt-2">لو رفعت الصورة الآن سيتم تسجيل التصفية كـ «تم التحويل» مباشرة، وإلا ستظهر في الجدول بزر «رفع إثبات الدفع».</p></div>
                <textarea v-model="form.notes" class="input" placeholder="ملاحظات اختيارية"></textarea>
                <p v-if="Object.keys(form.errors).length" class="error-msg">{{ Object.values(form.errors)[0] }}</p>
                <div class="flex justify-end gap-2"><button type="button" @click="createOpen = false" class="btn-ghost">إلغاء</button><button class="btn-primary" :disabled="form.processing">إنشاء التصفية وتسجيل التحويل</button></div>
            </form>
        </div>

        <div v-if="payOpen" class="fixed inset-0 z-50 grid place-items-center p-4 bg-black/60">
            <form @submit.prevent="submitPaymentProof" class="card p-6 w-full max-w-lg space-y-4">
                <h3 class="text-xl font-black">تأكيد تحويل {{ formatQAR(selectedPayout?.amount) }}</h3>
                <div><label class="input-label">صورة إثبات التحويل</label><input type="file" accept="image/*" class="input" required @change="payForm.receipt = $event.target.files[0]" /><p v-if="payForm.errors.receipt" class="error-msg">{{ payForm.errors.receipt }}</p></div>
                <textarea v-model="payForm.notes" class="input" placeholder="رقم العملية أو ملاحظات التحويل"></textarea>
                <div class="flex justify-end gap-2"><button type="button" @click="payOpen = false" class="btn-ghost">إلغاء</button><button class="btn-primary" :disabled="payForm.processing">تسجيل الدفع</button></div>
            </form>
        </div>

        <div v-if="selectedReceipt" class="fixed inset-0 z-50 grid place-items-center p-4 bg-black/80" @click="selectedReceipt = null"><img :src="selectedReceipt" class="max-h-[85vh] max-w-3xl rounded-2xl object-contain" /></div>
    </DashboardLayout>
</template>
