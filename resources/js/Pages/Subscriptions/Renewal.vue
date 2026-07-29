<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import { formatMonthly } from '@/lib/money';

const props = defineProps({
    subscription: { type: Object, required: true },
    hasPendingRenewal: { type: Boolean, default: false },
    backUrl: { type: String, required: true },
});

const form = useForm({});

function renew() {
    form.post(route('subscriptions.renewal.store', {
        subscription: props.subscription.id,
    }));
}
</script>

<template>
    <Head title="تجديد الاشتراك" />

    <DashboardLayout>
        <div class="max-w-2xl mx-auto py-8">
            <section class="card overflow-hidden">
                <div class="p-6 sm:p-8 bg-amber-500/10 border-b border-amber-200 dark:border-amber-900">
                    <div class="w-14 h-14 rounded-2xl bg-amber-100 dark:bg-amber-950/50 flex items-center justify-center mb-5">
                        <Icon name="calendar" class="w-7 h-7 text-amber-600 dark:text-amber-400" />
                    </div>

                    <h1 class="text-2xl font-black text-surface-900 dark:text-white">
                        الحصة القادمة هي آخر حصة
                    </h1>
                    <p class="mt-2 text-sm leading-7 text-surface-600 dark:text-surface-300">
                        اشتراك
                        <span class="font-bold">{{ subscription.label }}</span>
                        ينتهي في {{ subscription.period_end }}. هل تود تجديد الاشتراك لشهر جديد؟
                    </p>
                </div>

                <div class="p-6 sm:p-8 space-y-5">
                    <div class="rounded-2xl bg-surface-50 dark:bg-surface-850 p-4 space-y-3">
                        <div class="flex items-center justify-between gap-4 text-sm">
                            <span class="text-surface-500">الطالب</span>
                            <span class="font-bold text-surface-900 dark:text-white">{{ subscription.student?.name }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4 text-sm">
                            <span class="text-surface-500">المادة والمعلم</span>
                            <span class="font-bold text-surface-900 dark:text-white">
                                {{ subscription.subject?.name }} — {{ subscription.teacher?.name }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between gap-4 text-sm">
                            <span class="text-surface-500">قيمة الشهر الجديد</span>
                            <span class="font-black text-primary-600 dark:text-primary-400">
                                {{ formatMonthly(subscription.monthly_price) }}
                            </span>
                        </div>
                    </div>

                    <p v-if="hasPendingRenewal" class="text-sm text-amber-700 dark:text-amber-400">
                        يوجد تجديد بانتظار الدفع بالفعل. اضغط متابعة الدفع لإكماله.
                    </p>

                    <div class="flex flex-col-reverse sm:flex-row gap-3">
                        <Link :href="backUrl" class="btn-outline flex-1 text-center">
                            ليس الآن
                        </Link>
                        <button
                            type="button"
                            class="btn-primary flex-1"
                            :disabled="form.processing"
                            @click="renew"
                        >
                            {{ form.processing ? 'جارٍ التحويل…' : (hasPendingRenewal ? 'متابعة الدفع' : 'نعم، جدّد الاشتراك') }}
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </DashboardLayout>
</template>
