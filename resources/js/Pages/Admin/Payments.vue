<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    payments: { type: Object, required: true },
    filters:  { type: Object, default: () => ({}) },
});

const status = ref(props.filters.status ?? '');

function applyFilters() {
    router.get(route('admin.payments'), { status: status.value || undefined },
        { preserveState: true, replace: true });
}

function formatQAR(halala) {
    return new Intl.NumberFormat('ar-QA', { style: 'currency', currency: 'QAR', minimumFractionDigits: 0 })
        .format((halala ?? 0) / 100);
}

const statusColors = {
    paid:    'badge-green',
    pending: 'badge-primary',
    failed:  'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-400 px-2 py-0.5 rounded-full text-xs font-semibold',
    refunded:'badge-gray',
};

const statusLabels = { paid: 'مدفوع', pending: 'قيد الانتظار', failed: 'فشل', refunded: 'مُسترد' };
</script>

<template>
    <DashboardLayout>
        <Head title="المدفوعات" />

        <div class="container-app px-4 py-10">
            <div class="flex items-center justify-between gap-4 mb-8">
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
            <div class="card p-4 mb-6">
                <select v-model="status" @change="applyFilters" class="input w-48" id="payment-status-filter">
                    <option value="">كل الحالات</option>
                    <option value="paid">مدفوع</option>
                    <option value="pending">قيد الانتظار</option>
                    <option value="failed">فشل</option>
                    <option value="refunded">مُسترد</option>
                </select>
            </div>

            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-surface-50 dark:bg-surface-800 border-b border-surface-200 dark:border-surface-700">
                            <tr>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">#</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">الطالب</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">الكورس</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">المبلغ</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">البوابة</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">الحالة</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">التاريخ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-700">
                            <tr v-for="p in payments.data" :key="p.id"
                                class="hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors">
                                <td class="p-4 text-surface-400 font-mono text-xs">{{ p.id }}</td>
                                <td class="p-4">
                                    <div class="font-semibold text-surface-800 dark:text-white">{{ p.user?.name }}</div>
                                    <div class="text-xs text-surface-400">{{ p.user?.email }}</div>
                                </td>
                                <td class="p-4 text-surface-600 dark:text-surface-300 max-w-[180px]">
                                    <div class="line-clamp-1">{{ p.course?.title }}</div>
                                </td>
                                <td class="p-4 font-bold text-primary-700 dark:text-primary-400">
                                    {{ formatQAR(p.amount) }}
                                </td>
                                <td class="p-4">
                                    <span class="badge-gray text-xs">{{ p.gateway }}</span>
                                </td>
                                <td class="p-4">
                                    <span :class="statusColors[p.status]">
                                        {{ statusLabels[p.status] }}
                                    </span>
                                </td>
                                <td class="p-4 text-xs text-surface-400">{{ p.paid_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="!payments.data?.length" class="p-16 text-center text-surface-400 flex flex-col items-center justify-center gap-2">
                    <Icon name="payments" class="w-12 h-12 text-surface-400" />
                    <p>لا توجد مدفوعات</p>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
