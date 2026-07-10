<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    stats:          { type: Object, required: true },
    recentPayments: { type: Array,  default: () => [] },
});

function formatQAR(halala) {
    return new Intl.NumberFormat('ar-QA', {
        style: 'currency', currency: 'QAR', minimumFractionDigits: 0,
    }).format((halala ?? 0) / 100);
}

// Simple bar chart from revenue_chart data
const maxChartValue = computed(() =>
    Math.max(...(props.stats.revenue_chart?.map(r => r.amount) ?? [1]))
);
</script>

<template>
    <DashboardLayout>
        <Head title="لوحة الإدارة" />

        <div class="container-app px-4 py-10">

            <!-- Header -->
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <Icon name="lock" class="w-8 h-8 text-primary-500" />
                        <span>لوحة الإدارة</span>
                    </h1>
                    <p class="text-surface-500 dark:text-surface-400 mt-1">نظرة شاملة على المنصة</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('admin.users')"    class="btn-outline btn-sm">المستخدمون</Link>
                    <Link :href="route('admin.courses')"  class="btn-outline btn-sm">الكورسات</Link>
                    <Link :href="route('admin.payments')" class="btn-outline btn-sm">المدفوعات</Link>
                </div>
            </div>

            <!-- ── Stats Cards ──────────────────────────────── -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-10">
                <div v-for="card in [
                    { label: 'إجمالي المستخدمين', value: stats.total_users,       icon: 'users', bg: 'bg-primary-50 text-primary-600 dark:bg-primary-950/40 dark:text-primary-400' },
                    { label: 'الطلاب',             value: stats.total_students,    icon: 'student', bg: 'bg-teal-50 text-teal-600 dark:bg-teal-950/40 dark:text-teal-400' },
                    { label: 'المدرسين',            value: stats.total_teachers,   icon: 'teacher', bg: 'bg-accent-50 text-accent-600 dark:bg-accent-950/40 dark:text-accent-400' },
                    { label: 'الكورسات المنشورة',  value: stats.total_courses,    icon: 'courses', bg: 'bg-purple-50 text-purple-600 dark:bg-purple-950/40 dark:text-purple-400' },
                ]" :key="card.label"
                    class="card p-5 flex items-center gap-4 transition-all duration-300 transform hover:scale-102 hover:shadow-card-hover"
                >
                    <div class="p-4 rounded-2xl" :class="card.bg">
                        <Icon :name="card.icon" class="w-8 h-8" />
                    </div>
                    <div>
                        <div class="text-2xl font-black text-surface-900 dark:text-white">{{ card.value?.toLocaleString('ar') }}</div>
                        <div class="text-xs text-surface-500 dark:text-surface-400 mt-0.5">{{ card.label }}</div>
                    </div>
                </div>
            </div>

            <!-- ── Revenue Cards ────────────────────────────── -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-10">
                <div class="card p-6">
                    <div class="text-xs text-surface-400 mb-1">إجمالي الإيرادات</div>
                    <div class="text-3xl font-black text-primary-700 dark:text-primary-400">
                        {{ formatQAR(stats.total_revenue) }}
                    </div>
                </div>
                <div class="card p-6">
                    <div class="text-xs text-surface-400 mb-1">إيرادات هذا الشهر</div>
                    <div class="text-3xl font-black text-green-700 dark:text-green-400">
                        {{ formatQAR(stats.monthly_revenue) }}
                    </div>
                </div>
                <div class="card p-6">
                    <div class="text-xs text-surface-400 mb-1">إجمالي التسجيلات</div>
                    <div class="text-3xl font-black text-accent-700 dark:text-accent-400">
                        {{ stats.total_enrollments?.toLocaleString('ar') }}
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                <!-- ── Revenue Chart ──────────────────────────── -->
                <div class="card p-6">
                    <h2 class="font-bold text-surface-800 dark:text-white mb-5">الإيرادات (آخر 6 أشهر)</h2>

                    <div v-if="stats.revenue_chart?.length" class="flex items-end gap-2 h-40">
                        <div
                            v-for="bar in stats.revenue_chart"
                            :key="bar.label"
                            class="flex-1 flex flex-col items-center gap-1"
                        >
                            <div class="w-full bg-primary-500/20 dark:bg-primary-900/40 rounded-t-lg relative group"
                                 :style="{ height: Math.max((bar.amount / maxChartValue) * 140, 8) + 'px' }"
                            >
                                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-primary-600 to-primary-400
                                            rounded-t-lg transition-all duration-300 group-hover:from-primary-500 group-hover:to-primary-300"
                                     :style="{ height: '100%' }"
                                ></div>
                                <!-- Tooltip -->
                                <div class="absolute -top-8 start-1/2 -translate-x-1/2 bg-surface-800 text-white
                                            text-xs rounded px-2 py-1 whitespace-nowrap opacity-0 group-hover:opacity-100
                                            transition-opacity duration-150 pointer-events-none z-10">
                                    {{ formatQAR(bar.amount) }}
                                </div>
                            </div>
                            <div class="text-xs text-surface-400 text-center leading-tight">{{ bar.label }}</div>
                        </div>
                    </div>
                    <div v-else class="h-40 flex items-center justify-center text-surface-400 text-sm">
                        لا توجد بيانات بعد
                    </div>
                </div>

                <!-- ── Recent Payments ──────────────────────────── -->
                <div class="card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="font-bold text-surface-800 dark:text-white">آخر المدفوعات</h2>
                        <Link :href="route('admin.payments')" class="text-primary-600 text-xs hover:underline">
                            عرض الكل
                        </Link>
                    </div>

                    <div v-if="recentPayments.length" class="space-y-3">
                        <div v-for="payment in recentPayments" :key="payment.id"
                             class="flex items-center gap-3 p-3 rounded-xl hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors"
                        >
                            <div class="avatar-md bg-primary-100 dark:bg-primary-900 flex-shrink-0">
                                <span class="text-primary-700 dark:text-primary-300 font-bold text-sm">
                                    {{ payment.user?.name?.charAt(0) }}
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-semibold text-surface-800 dark:text-surface-100 line-clamp-1">
                                    {{ payment.user?.name }}
                                </div>
                                <div class="text-xs text-surface-400 line-clamp-1">{{ payment.course?.title }}</div>
                            </div>
                            <div class="text-sm font-bold text-primary-700 dark:text-primary-400 flex-shrink-0">
                                {{ formatQAR(payment.amount) }}
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center text-surface-400 text-sm py-8">
                        لا توجد مدفوعات بعد
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
