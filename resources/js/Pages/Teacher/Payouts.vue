<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

defineProps({ payouts: { type: Array, required: true } });
const selectedReceipt = ref(null);
const selectedPayout = ref(null);
const acknowledgeOpen = ref(false);
const form = useForm({ note: '' });
const formatQAR = value => `${new Intl.NumberFormat('ar-QA', { minimumFractionDigits: 2 }).format((value ?? 0) / 100)} ر.ق.`;

function openAcknowledge(payout) {
    selectedPayout.value = payout;
    form.reset();
    acknowledgeOpen.value = true;
}

function acknowledge() {
    form.post(route('teacher.payouts.acknowledge', selectedPayout.value.id), {
        onSuccess: () => { acknowledgeOpen.value = false; },
    });
}
</script>

<template>
    <DashboardLayout>
        <Head title="أرباحي وتصفية الحساب" />
        <div class="container-app px-4 py-10 space-y-7">
            <div><h1 class="text-3xl font-black text-surface-900 dark:text-white flex items-center gap-2"><Icon name="earnings" class="w-8 h-8 text-primary-500" />أرباحي وتصفية الحساب</h1><p class="text-surface-500 mt-1">راجع تفاصيل كل تصفية وإثبات التحويل، ثم أكد الاستلام.</p></div>

            <div class="grid md:grid-cols-2 gap-5">
                <article v-for="payout in payouts" :key="payout.id" class="card p-6 space-y-4">
                    <div class="flex justify-between"><div><p class="text-xs text-surface-400">صافي المستحقات</p><h2 class="text-2xl font-black text-green-600">{{ formatQAR(payout.teacher_earnings ?? payout.amount) }}</h2></div><span :class="payout.teacher_acknowledged_at ? 'badge-green' : payout.status === 'paid' ? 'badge-accent' : 'badge-primary'">{{ payout.teacher_acknowledged_at ? 'تم إقرار الاستلام' : payout.status === 'paid' ? 'تم التحويل' : 'قيد التجهيز' }}</span></div>
                    <div class="grid grid-cols-2 gap-3 text-xs"><div class="rounded-xl bg-surface-50 dark:bg-surface-800 p-3"><span class="text-surface-400">إجمالي المبيعات</span><b class="block mt-1">{{ formatQAR(payout.gross_amount) }}</b></div><div class="rounded-xl bg-surface-50 dark:bg-surface-800 p-3"><span class="text-surface-400">عمولة المنصة</span><b class="block mt-1">{{ formatQAR(payout.platform_commission_amount) }}</b></div></div>
                    <p class="text-xs text-surface-500">الفترة: {{ payout.period_start }} إلى {{ payout.period_end }}</p>
                    <div class="flex gap-2"><button v-if="payout.receipt_path" @click="selectedReceipt = route('teacher.payouts.receipt', payout.id)" class="btn-outline btn-sm">عرض إثبات التحويل</button><button v-if="payout.status === 'paid' && !payout.teacher_acknowledged_at" @click="openAcknowledge(payout)" class="btn-primary btn-sm">أقر باستلام المبلغ</button></div>
                    <p v-if="payout.teacher_acknowledgment_note" class="text-xs text-surface-500">ملاحظة الاستلام: {{ payout.teacher_acknowledgment_note }}</p>
                </article>
            </div>
            <p v-if="!payouts.length" class="card p-12 text-center text-surface-400">لا توجد تصفيات مالية مسجلة حتى الآن.</p>
        </div>

        <div v-if="acknowledgeOpen" class="fixed inset-0 z-50 grid place-items-center p-4 bg-black/60">
            <form @submit.prevent="acknowledge" class="card p-6 w-full max-w-md space-y-4"><h3 class="text-xl font-black">إقرار استلام المستحقات</h3><p class="text-sm text-surface-500">أنت تؤكد استلام مبلغ {{ formatQAR(selectedPayout?.amount) }} بعد مراجعة إثبات التحويل.</p><textarea v-model="form.note" class="input" placeholder="ملاحظة اختيارية"></textarea><div class="flex justify-end gap-2"><button type="button" @click="acknowledgeOpen = false" class="btn-ghost">إلغاء</button><button class="btn-primary" :disabled="form.processing">تأكيد الاستلام</button></div></form>
        </div>
        <div v-if="selectedReceipt" class="fixed inset-0 z-50 grid place-items-center p-4 bg-black/80" @click="selectedReceipt = null"><img :src="selectedReceipt" class="max-h-[85vh] max-w-3xl rounded-2xl object-contain" /></div>
    </DashboardLayout>
</template>
