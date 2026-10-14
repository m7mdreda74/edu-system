<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import StatCard from '@/Components/StatCard.vue';
import { formatQAR } from '@/lib/money';

const props = defineProps({
    analytics: { type: Object, required: true },
});

const dayNames = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
const revenueMax = computed(() => Math.max(...(props.analytics.revenue?.months ?? []).map((month) => month.amount), 1));
const peakMax = computed(() => Math.max(...(props.analytics.peak_sessions ?? []).map((item) => item.sessions), 1));

function percent(value) {
    return `${Number(value ?? 0).toLocaleString('ar-EG', { maximumFractionDigits: 1 })}%`;
}

function peakLabel(item) {
    return `${dayNames[item.day_of_week] ?? '—'} · ${String(item.hour).padStart(2, '0')}:00`;
}
</script>

<template>
    <DashboardLayout>
        <Head title="تحليلات المنصة" />

        <div class="dashboard-data-page space-y-6" dir="rtl">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-black text-surface-900 dark:text-white">
                        <Icon name="chart" class="h-7 w-7 text-primary-500" />
                        تحليلات المنصة
                    </h1>
                    <p class="mt-1 text-sm text-surface-500">قراءة مجمعة للإيرادات، الاشتراكات، التعلم، التقييمات ومواعيد الحصص.</p>
                </div>
                <span class="badge-gray">تحديث كل {{ Math.round((analytics.cache_ttl_seconds ?? 900) / 60) }} دقيقة</span>
            </header>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard label="إيرادات آخر 12 شهرًا" :value="analytics.revenue?.total ?? 0" money icon="payments" tone="primary" />
                <StatCard label="نسبة إكمال الدروس" :value="percent(analytics.completion?.rate)" icon="book" tone="green" />
                <StatCard label="معدل فقدان المشتركين" :value="percent(analytics.churn?.rate)" hint="بعد أول اشتراك ومرور 3 أشهر" icon="users" tone="accent" />
                <StatCard label="طلاب عينة الفقدان" :value="analytics.churn?.eligible_students ?? 0" hint="العينة المؤهلة للتحليل" icon="student" tone="slate" />
            </section>

            <section class="card p-5">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-black text-surface-900 dark:text-white">الإيرادات الشهرية</h2>
                        <p class="text-xs text-surface-500">المدفوعات المؤكدة فقط</p>
                    </div>
                    <span class="text-sm font-bold text-primary-600">{{ formatQAR(analytics.revenue?.total ?? 0) }}</span>
                </div>
                <div class="grid h-48 grid-cols-12 items-end gap-2">
                    <div v-for="month in (analytics.revenue?.months ?? [])" :key="month.key" class="group flex h-full flex-col items-center justify-end gap-2">
                        <span class="text-[10px] text-surface-500 opacity-0 transition group-hover:opacity-100">{{ formatQAR(month.amount) }}</span>
                        <div class="w-full rounded-t-lg bg-primary-500/80 transition hover:bg-primary-500" :style="{ height: `${Math.max((month.amount / revenueMax) * 100, month.amount ? 5 : 1)}%` }" :title="`${month.label}: ${formatQAR(month.amount)}`" />
                        <span class="truncate text-[10px] text-surface-500">{{ month.label }}</span>
                    </div>
                </div>
            </section>

            <div class="grid gap-6 xl:grid-cols-2">
                <section class="card overflow-hidden">
                    <div class="border-b border-surface-200 p-5 dark:border-surface-700">
                        <h2 class="text-lg font-black text-surface-900 dark:text-white">أعلى المجموعات إيرادًا</h2>
                        <p class="text-xs text-surface-500">بحسب المدفوعات المؤكدة</p>
                    </div>
                    <div v-if="analytics.top_groups?.length" class="divide-y divide-surface-100 dark:divide-surface-800">
                        <div v-for="group in analytics.top_groups" :key="group.id" class="flex items-center justify-between gap-4 p-4">
                            <div class="min-w-0">
                                <div class="truncate font-bold text-surface-900 dark:text-white">{{ group.name }}</div>
                                <div class="text-xs text-surface-500">{{ group.subject || 'بدون مادة' }} · {{ group.sales }} مبيعات</div>
                            </div>
                            <span class="shrink-0 font-black text-primary-600">{{ formatQAR(group.revenue) }}</span>
                        </div>
                    </div>
                    <p v-else class="p-5 text-sm text-surface-500">لا توجد مبيعات مؤكدة بعد.</p>
                </section>

                <section class="card overflow-hidden">
                    <div class="border-b border-surface-200 p-5 dark:border-surface-700">
                        <h2 class="text-lg font-black text-surface-900 dark:text-white">متوسط تقييم المدرسين</h2>
                        <p class="text-xs text-surface-500">التقييمات المعتمدة فقط</p>
                    </div>
                    <div v-if="analytics.ratings?.length" class="divide-y divide-surface-100 dark:divide-surface-800">
                        <div v-for="rating in analytics.ratings" :key="rating.teacher_id" class="flex items-center justify-between gap-4 p-4">
                            <div class="min-w-0">
                                <div class="truncate font-bold text-surface-900 dark:text-white">{{ rating.teacher }}</div>
                                <div class="text-xs text-surface-500">{{ rating.subject || 'بدون مادة' }} · {{ rating.reviews }} تقييمات</div>
                            </div>
                            <span class="shrink-0 font-black text-accent-600">{{ rating.average }} / 5</span>
                        </div>
                    </div>
                    <p v-else class="p-5 text-sm text-surface-500">لا توجد تقييمات معتمدة بعد.</p>
                </section>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <section class="card p-5">
                    <h2 class="text-lg font-black text-surface-900 dark:text-white">التقدم التعليمي</h2>
                    <div class="mt-5 grid grid-cols-2 gap-4 text-center">
                        <div class="rounded-2xl bg-surface-50 p-4 dark:bg-surface-800"><div class="text-2xl font-black text-green-600">{{ analytics.completion?.completed ?? 0 }}</div><div class="mt-1 text-xs text-surface-500">دروس مكتملة</div></div>
                        <div class="rounded-2xl bg-surface-50 p-4 dark:bg-surface-800"><div class="text-2xl font-black text-surface-700 dark:text-surface-200">{{ analytics.completion?.total ?? 0 }}</div><div class="mt-1 text-xs text-surface-500">سجلات تقدم</div></div>
                    </div>
                    <p class="mt-4 text-xs text-surface-500">النسبة محسوبة من سجلات متابعة الدروس المسجلة حاليًا.</p>
                </section>

                <section class="card p-5">
                    <h2 class="text-lg font-black text-surface-900 dark:text-white">أوقات ذروة الحصص المباشرة</h2>
                    <div v-if="analytics.peak_sessions?.length" class="mt-5 space-y-3">
                        <div v-for="item in analytics.peak_sessions" :key="`${item.day_of_week}-${item.hour}`" class="flex items-center gap-3">
                            <span class="w-28 shrink-0 text-xs font-bold text-surface-600 dark:text-surface-300">{{ peakLabel(item) }}</span>
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800"><div class="h-full rounded-full bg-accent-500" :style="{ width: `${(item.sessions / peakMax) * 100}%` }" /></div>
                            <span class="w-12 text-left text-xs text-surface-500">{{ item.sessions }}</span>
                        </div>
                    </div>
                    <p v-else class="mt-5 text-sm text-surface-500">لا توجد حصص مجدولة بعد.</p>
                </section>
            </div>
        </div>
    </DashboardLayout>
</template>
