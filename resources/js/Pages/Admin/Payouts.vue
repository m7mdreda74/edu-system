<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    payouts: { type: Array, required: true },
});

const isModalOpen = ref(false);
const editingPayout = ref(null);

const form = useForm({
    teacher_id: '',
    amount: '',
    platform_commission: '20',
    period_start: '',
    period_end: '',
    notes: '',
});

const payForm = useForm({
    notes: '',
});

function formatQAR(halala) {
    return new Intl.NumberFormat('ar-QA', { style: 'currency', currency: 'QAR', minimumFractionDigits: 2 })
        .format((halala ?? 0) / 100);
}

function openAddModal() {
    form.reset();
    isModalOpen.value = true;
}

function submitPayout() {
    // Convert QAR input amount to halala
    const payload = { ...form.data() };
    payload.amount = Math.round(parseFloat(form.amount) * 100);
    payload.platform_commission = Math.round(parseFloat(form.platform_commission));

    router.post(route('admin.payouts.store'), payload, {
        onSuccess: () => {
            isModalOpen.value = false;
            form.reset();
        }
    });
}

function markPaid(payout) {
    const notes = prompt('أضف أي ملاحظات أو مرجع لعملية الدفع (مثل رقم التحويل البنكي):');
    if (notes !== null) {
        payForm.notes = notes;
        payForm.post(route('admin.payouts.pay', { id: payout.id }));
    }
}

function deletePayout(id) {
    if (confirm('هل أنت متأكد من حذف هذه التسوية الماليّة؟')) {
        router.delete(route('admin.payouts.destroy', { id }));
    }
}
</script>

<template>
    <DashboardLayout>
        <Head title="إدارة تسويات أرباح المعلمين" />

        <div class="container-app px-4 py-10">
            <!-- Header -->
            <div class="flex items-center justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <Icon name="earnings" class="w-8 h-8 text-primary-500" />
                        <span>تسويات وأرباح المعلمين</span>
                    </h1>
                    <p class="text-surface-500 mt-1">إرسال وتوثيق عمليات سحب الأرباح للمعلمين والمستحقات الماليّة</p>
                </div>
                <button @click="openAddModal" class="btn-primary flex items-center gap-2">
                    <Icon name="plus" class="w-4 h-4" />
                    <span>تسجيل تسوية جديدة</span>
                </button>
            </div>

            <!-- Table -->
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-surface-50 dark:bg-surface-800 border-b border-surface-200 dark:border-surface-700">
                            <tr>
                                <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">المعلم</th>
                                <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">المبلغ الصافي</th>
                                <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">عمولة المنصة</th>
                                <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">الفترة الماليّة</th>
                                <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">الحالة</th>
                                <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">الملاحظات</th>
                                <th class="text-center px-6 py-4 font-bold text-surface-700 dark:text-surface-300">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                            <tr v-for="pay in payouts" :key="pay.id" class="hover:bg-surface-50/50 dark:hover:bg-surface-800/20">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-950/30 flex items-center justify-center font-bold text-primary-600">
                                            {{ pay.teacher?.name?.charAt(0) }}
                                        </div>
                                        <div>
                                            <div class="font-bold text-surface-900 dark:text-white">{{ pay.teacher?.name }}</div>
                                            <div class="text-xs text-surface-400">{{ pay.teacher?.email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 font-semibold text-green-600 dark:text-green-400">
                                    {{ formatQAR(pay.amount) }}
                                </td>
                                <td class="px-6 py-4 text-surface-500 dark:text-surface-400">
                                    {{ pay.platform_commission }}%
                                </td>
                                <td class="px-6 py-4 text-xs text-surface-500 dark:text-surface-400">
                                    {{ pay.period_start }} إلى {{ pay.period_end }}
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="pay.status === 'paid' ? 'badge-green' : 'badge-primary'">
                                        {{ pay.status === 'paid' ? 'مدفوع' : 'قيد الانتظار' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 max-w-xs truncate text-xs text-surface-500 dark:text-surface-400" :title="pay.notes">
                                    {{ pay.notes || '—' }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button v-if="pay.status !== 'paid'" @click="markPaid(pay)" 
                                                class="btn-success btn-xs py-1.5 px-3 rounded-lg"
                                        >
                                            تأكيد الدفع
                                        </button>
                                        <button v-if="pay.status !== 'paid'" @click="deletePayout(pay.id)"
                                                class="btn-ghost text-red-500 hover:bg-red-500/10 p-1.5 rounded-lg"
                                        >
                                            <Icon name="close" class="w-4 h-4" />
                                        </button>
                                        <span v-else class="text-xs text-surface-400">
                                             تم الدفع في {{ new Date(pay.paid_at).toLocaleDateString('ar') }}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="payouts.length === 0" class="p-16 text-center text-surface-400">
                    <Icon name="earnings" class="w-16 h-16 mx-auto text-surface-300 dark:text-surface-700 mb-4" />
                    <h3 class="text-lg font-bold text-surface-800 dark:text-surface-200 mb-2">لا توجد تسويات مالية</h3>
                    <p class="text-sm">لم يتم تسجيل أي تسوية أرباح للمعلمين بعد</p>
                </div>
            </div>

            <!-- Modal for Payout Creation -->
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/55 backdrop-blur-sm">
                    <div class="bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-3xl w-full max-w-lg p-6 overflow-hidden shadow-2xl relative" dir="rtl">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-black text-surface-900 dark:text-white">تسجيل تسوية مستحقات جديدة</h3>
                            <button @click="isModalOpen = false" class="btn-ghost p-1 rounded-full">
                                <Icon name="close" class="w-5 h-5 text-surface-500" />
                            </button>
                        </div>

                        <form @submit.prevent="submitPayout" class="space-y-4">
                            <div>
                                <label class="label mb-1">المعلم المستلم (User ID أو إيميل)</label>
                                <!-- Basic manual text input to avoid heavy user selection logic -->
                                <input v-model="form.teacher_id" type="number" required class="input" placeholder="أدخل كود المعرف (ID) للمعلم" />
                            </div>

                            <div>
                                <label class="label mb-1">المبلغ المطلوب سحبه (بالريال القطري)</label>
                                <input v-model="form.amount" type="number" step="0.01" required class="input" placeholder="مثال: 1500" />
                            </div>

                            <div>
                                <label class="label mb-1">نسبة عمولة المنصة (%)</label>
                                <input v-model="form.platform_commission" type="number" required class="input" placeholder="مثال: 20" />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="label mb-1">بداية الفترة الماليّة</label>
                                    <input v-model="form.period_start" type="date" required class="input" />
                                </div>
                                <div>
                                    <label class="label mb-1">نهاية الفترة الماليّة</label>
                                    <input v-model="form.period_end" type="date" required class="input" />
                                </div>
                            </div>

                            <div>
                                <label class="label mb-1">ملاحظات ومراجع الدفع</label>
                                <textarea v-model="form.notes" class="input h-20 resize-none" placeholder="مرجع البنك أو ملاحظات إضافية..."></textarea>
                            </div>

                            <div class="flex gap-3 pt-4">
                                <button type="submit" class="btn-primary flex-1">تسجيل التسوية</button>
                                <button type="button" @click="isModalOpen = false" class="btn-ghost flex-1">إلغاء</button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </div>
    </DashboardLayout>
</template>
