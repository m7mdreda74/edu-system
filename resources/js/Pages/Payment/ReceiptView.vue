<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import { formatQAR } from '@/lib/money';

const props = defineProps({
    payment: { type: Object, required: true },
});

function printReceipt() {
    window.print();
}

const formattedDate = computed(() => {
    if (!props.payment.paid_at) return '';
    const d = new Date(props.payment.paid_at);
    return d.toLocaleDateString('en-GB') + ' ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
});

const gatewayName = computed(() => {
    const gw = props.payment.gateway?.toLowerCase();
    if (gw === 'skipcash') return 'بوابة SkipCash القطرية (Apple Pay / بطاقة بنكية)';
    if (gw === 'fatora') return 'بوابة فاتورة القطرية Fatora';
    if (gw === 'tap') return 'بوابة Tap للمدفوعات';
    if (gw === 'manual') return 'تحويل بنكي مباشر معتمد';
    return props.payment.gateway || 'دفع إلكتروني';
});
</script>

<template>
    <Head :title="`إيصال سداد #${payment.invoice_number}`" />

    <div class="min-h-screen bg-surface-50 dark:bg-surface-950 py-8 px-4 sm:px-6 lg:px-8 text-surface-900 dark:text-white" dir="rtl" lang="ar">
        <div class="max-w-3xl mx-auto">
            
            <!-- Top Controls (Hidden when printing) -->
            <div class="print:hidden flex items-center justify-between mb-6">
                <Link
                    :href="route('dashboard')"
                    class="btn-outline flex items-center gap-2 text-xs py-2 px-4 rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-900 shadow-sm hover:border-primary-500 transition-colors"
                >
                    <Icon name="arrow-right" class="w-4 h-4" />
                    <span>العودة للوحة التحكم</span>
                </Link>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="printReceipt"
                        class="btn-primary flex items-center gap-2 text-xs py-2 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white font-bold shadow-md hover:shadow-glow-primary transition-all"
                    >
                        <Icon name="download" class="w-4 h-4" />
                        <span>طباعة / حفظ PDF</span>
                    </button>
                </div>
            </div>

            <!-- ── Printable Invoice Document ── -->
            <div class="bg-white dark:bg-surface-900 rounded-3xl border border-surface-200 dark:border-surface-800 shadow-2xl p-6 sm:p-10 relative overflow-hidden print:border-none print:shadow-none print:p-0">
                
                <!-- Gold Top Decorative Accent Bar -->
                <div class="absolute top-0 left-0 right-0 h-2 bg-gradient-to-r from-accent-600 via-accent-400 to-accent-600"></div>

                <!-- Invoice Header -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6 pb-8 border-b border-surface-100 dark:border-surface-800">
                    <div class="flex items-center gap-4">
                        <img src="/images/logo-icon.png" alt="بوابة المجد التعليمية" class="w-16 h-16 object-contain" />
                        <div>
                            <h1 class="text-xl sm:text-2xl font-black text-surface-950 dark:text-white tracking-tight">بوابة المجد التعليمية</h1>
                            <p class="text-xs text-surface-500 dark:text-surface-400 font-medium mt-0.5">منصة التدريس الأكاديمي الرائدة — دولة قطر</p>
                        </div>
                    </div>

                    <div class="text-start sm:text-end">
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 font-bold text-xs">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span>إيصال سداد إلكتروني معتمد</span>
                        </div>
                        <p class="text-sm font-black font-mono mt-2 text-surface-900 dark:text-white" dir="ltr">
                            #{{ payment.invoice_number }}
                        </p>
                        <p class="text-[11px] text-surface-400 mt-0.5" dir="ltr">
                            {{ formattedDate }}
                        </p>
                    </div>
                </div>

                <!-- Parties / Meta Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 py-6 border-b border-surface-100 dark:border-surface-800 text-xs">
                    <!-- Student details -->
                    <div class="space-y-1.5 bg-surface-50 dark:bg-surface-800/40 p-4 rounded-2xl">
                        <span class="text-[10px] font-bold text-surface-400 uppercase tracking-wider block">بيانات الطالب والمشترك:</span>
                        <p class="text-sm font-bold text-surface-900 dark:text-white">{{ payment.student?.name }}</p>
                        <p v-if="payment.student?.phone" class="text-surface-600 dark:text-surface-300 font-mono" dir="ltr">📱 {{ payment.student.phone }}</p>
                        <p v-if="payment.student?.grade_level" class="text-surface-500">الصف الدراسي: <span class="font-bold text-surface-700 dark:text-surface-200">{{ payment.student.grade_level }}</span></p>
                        <p class="text-surface-400 text-[10px]">كود الطالب: #{{ payment.student?.id }}</p>
                    </div>

                    <!-- Payment details -->
                    <div class="space-y-1.5 bg-surface-50 dark:bg-surface-800/40 p-4 rounded-2xl">
                        <span class="text-[10px] font-bold text-surface-400 uppercase tracking-wider block">تفاصيل العملية المالية:</span>
                        <p class="text-surface-600 dark:text-surface-300">
                            طريقة الدفع: <span class="font-semibold text-surface-900 dark:text-white">{{ gatewayName }}</span>
                        </p>
                        <p class="text-surface-600 dark:text-surface-300">
                            نوع الباقة: <span class="font-semibold text-surface-900 dark:text-white">{{ payment.billing_period }}</span>
                        </p>
                        <p v-if="payment.gateway_ref" class="text-surface-500 font-mono text-[10px]" dir="ltr">
                            Ref: {{ payment.gateway_ref }}
                        </p>
                        <p class="text-surface-500">
                            حالة الدفع: <span class="font-bold text-emerald-600 dark:text-emerald-400">مدفوع بالكامل (PAID)</span>
                        </p>
                    </div>
                </div>

                <!-- Line Items Table -->
                <div class="py-6">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-surface-200 dark:border-surface-700 text-surface-400">
                                <th class="text-start py-3 font-bold">البيان / الخدمة التعليمية</th>
                                <th class="text-center py-3 font-bold">المعلم</th>
                                <th class="text-center py-3 font-bold">المجموعة</th>
                                <th class="text-end py-3 font-bold">المبلغ (ر.ق)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                            <tr>
                                <td class="py-4">
                                    <p class="font-bold text-sm text-surface-900 dark:text-white">{{ payment.course?.subject }}</p>
                                    <p class="text-surface-400 text-[11px] mt-0.5">{{ payment.billing_period }} — حصص مباشرة + تسجيلات وتدريبات</p>
                                </td>
                                <td class="py-4 text-center font-medium text-surface-700 dark:text-surface-300">
                                    {{ payment.course?.teacher }}
                                </td>
                                <td class="py-4 text-center text-surface-500">
                                    {{ payment.course?.group }}
                                </td>
                                <td class="py-4 text-end font-bold text-sm font-mono text-surface-950 dark:text-white" dir="ltr">
                                    {{ formatQAR(payment.amount) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Totals & Calculations -->
                <div class="border-t border-surface-100 dark:border-surface-800 pt-6">
                    <div class="max-w-xs ms-auto space-y-2 text-xs">
                        <div class="flex justify-between text-surface-500">
                            <span>المجموع الفرعي:</span>
                            <span class="font-mono font-medium" dir="ltr">{{ formatQAR(payment.amount) }}</span>
                        </div>
                        <div v-if="payment.coupon" class="flex justify-between text-emerald-600 dark:text-emerald-400">
                            <span>خصم الكوبون ({{ payment.coupon.code }}):</span>
                            <span>{{ payment.coupon.discount_percent }}%</span>
                        </div>
                        <div class="flex justify-between text-base font-black border-t-2 border-surface-900 dark:border-surface-100 pt-2 text-surface-950 dark:text-white">
                            <span>إجمالي المدفوع:</span>
                            <span class="font-mono text-primary-600 dark:text-accent-400" dir="ltr">{{ formatQAR(payment.amount) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Official Footer Seal & Verification -->
                <div class="mt-10 pt-6 border-t border-surface-100 dark:border-surface-800 flex flex-col sm:flex-row items-center justify-between gap-4 text-center sm:text-start text-[11px] text-surface-400">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl border border-accent-500/40 bg-accent-50/30 dark:bg-accent-950/20 flex flex-col items-center justify-center text-accent-600 dark:text-accent-400 shrink-0">
                            <Icon name="success" class="w-5 h-5" />
                            <span class="text-[8px] font-black uppercase">معتمد</span>
                        </div>
                        <div>
                            <p class="font-bold text-surface-700 dark:text-surface-300">إيصال رسمي صادر آلياً من نظام بوابة المجد التعليمية</p>
                            <p class="text-surface-400 mt-0.5">الدعم المالي والفني متاح عبر واتساب على مدار الساعة</p>
                        </div>
                    </div>

                    <div class="text-[10px] text-surface-400 font-mono" dir="ltr">
                        AL-MAJD QA • TAX RECEIPT • {{ payment.invoice_number }}
                    </div>
                </div>

            </div>

        </div>
    </div>
</template>

<style scoped>
@media print {
    body {
        background: white !important;
        color: black !important;
    }
}
</style>
