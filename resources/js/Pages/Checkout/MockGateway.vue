<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    payment: { type: Object, required: true },
    subscription: { type: Object, default: null },
});

function formatQAR(halala) {
    const formatted = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format((halala ?? 0) / 100);
    return `${formatted} ر.ق.`;
}

function handleSuccess() {
    router.post(route('checkout.mock_gateway.complete', { ref: props.payment.gateway_ref }));
}

function handleCancel() {
    router.post(route('checkout.mock_gateway.cancel', { ref: props.payment.gateway_ref }));
}
</script>

<template>
    <AppLayout>
        <Head title="بوابة الدفع التجريبية" />

        <div class="container-app px-4 py-16 max-w-xl text-center" dir="rtl">
            <div class="card p-8 md:p-10 border-2 border-primary-100 dark:border-primary-900 shadow-2xl relative overflow-hidden">
                <!-- Decorative top bar -->
                <div class="absolute top-0 left-0 right-0 h-2 bg-gradient-to-r from-primary-600 via-primary-400 to-accent-500"></div>

                <!-- Title -->
                <div class="flex flex-col items-center justify-center mb-6">
                    <div class="p-4 bg-primary-50 dark:bg-primary-950/50 text-primary-600 dark:text-primary-400 rounded-2xl mb-4">
                        <Icon name="payments" class="w-12 h-12" />
                    </div>
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white">بوابة الدفع الافتراضية</h1>
                    <p class="text-xs text-surface-400 dark:text-surface-500 mt-1">تفوّق — بيئة الاختبار والتجربة</p>
                </div>

                <!-- Alert/Warning box -->
                <div class="alert-info text-start p-4 rounded-2xl mb-6 text-sm flex gap-3 items-start">
                    <Icon name="info" class="w-5 h-5 text-primary-500 flex-shrink-0 mt-0.5" />
                    <div class="leading-relaxed text-surface-700 dark:text-surface-300">
                        <strong>وضع التطوير والتجربة:</strong>
                        هذه الصفحة تحاكي بوابة الدفع الخارجية (Stripe). لن يتم خصم أي أموال حقيقية من حسابك، وتُستخدم لاختبار وتفعيل الربط التلقائي والوظائف الأمنية والمالية مع المدرسين وأولياء الأمور.
                    </div>
                </div>

                <!-- Course Summary -->
                <div class="bg-surface-50 dark:bg-surface-800/40 rounded-2xl p-5 mb-8 border border-surface-100 dark:border-surface-800 text-start">
                    <div class="text-xs font-semibold text-surface-400 mb-2">تفاصيل الطلب</div>
                    <h3 class="font-bold text-surface-900 dark:text-white text-base leading-snug mb-1">
                        {{ subscription?.label }}
                    </h3>
                    <p class="text-xs text-surface-500 mb-4">المعلم: {{ subscription?.teacher?.name }}</p>

                    <div class="flex justify-between items-center pt-3 border-t border-surface-150 dark:border-surface-800">
                        <span class="text-sm text-surface-600 dark:text-surface-300 font-medium">المبلغ المطلوب سداده</span>
                        <span class="text-xl font-black text-primary-700 dark:text-primary-400">
                            {{ formatQAR(payment.amount) }}
                        </span>
                    </div>
                </div>

                <!-- Mock Actions -->
                <div class="flex flex-col gap-3">
                    <button
                        @click="handleSuccess"
                        class="btn-primary btn-lg w-full flex items-center justify-center gap-2 transition-transform duration-200 hover:scale-102"
                        id="simulate-success-btn"
                    >
                        <span>إتمام عملية الدفع بنجاح</span>
                    </button>
                    <button
                        @click="handleCancel"
                        class="btn-outline btn-lg w-full flex items-center justify-center gap-2 transition-transform duration-200 hover:scale-102 text-red-600 border-red-200 hover:bg-red-50 dark:text-red-400 dark:border-red-950 dark:hover:bg-red-950/20"
                        id="simulate-cancel-btn"
                    >
                        <span>إلغاء عملية الدفع والعودة</span>
                    </button>
                </div>

                <!-- Footer secure badge -->
                <div class="mt-6 flex items-center justify-center gap-1.5 text-[10px] text-surface-400">
                    <Icon name="lock" class="w-3.5 h-3.5" />
                    <span>تشفير 256-bit آمن ونظيف</span>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
