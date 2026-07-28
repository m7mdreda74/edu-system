<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import { formatQAR } from '@/lib/money';
import { debounce } from '@/lib/debounce';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    subscriptions: { type: Object, required: true },
    filters:       { type: Object, default: () => ({}) },
    stats:         { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const type   = ref(props.filters.type ?? '');

const statusLabels = {
    active:    'فعّال',
    pending:   'بانتظار الدفع',
    expired:   'منتهي',
    cancelled: 'ملغي',
};

const statusClasses = {
    active:    'badge-green',
    pending:   'badge-accent',
    expired:   'badge-gray',
    cancelled: 'badge-red',
};

const applyFilters = debounce(() => {
    router.get(
        route('admin.subscriptions'),
        { search: search.value || undefined, status: status.value || undefined, type: type.value || undefined },
        { preserveState: true, replace: true },
    );
}, 300);

watch([search, status, type], applyFilters);

const { confirm } = useConfirm();

async function cancel(id) {
    const ok = await confirm({
        title: 'إلغاء الاشتراك',
        message: 'سيفقد الطالب مقعده في المجموعة.',
        confirmLabel: 'إلغاء',
        variant: 'danger',
    });
    if (ok) router.delete(route('admin.subscriptions.cancel', { id }));
}
</script>

<template>
    <Head title="الاشتراكات" />

    <DashboardLayout>
        <div class="space-y-6">
            <header>
                <h1 class="text-2xl font-black text-surface-900 dark:text-white">الاشتراكات</h1>
                <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                    كل اشتراكات الطلاب الشهرية مع المعلمين
                </p>
            </header>

            <!-- Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="card p-5">
                    <div class="text-2xl font-black text-green-600">{{ stats.active ?? 0 }}</div>
                    <div class="text-[11px] text-surface-400 mt-1">فعّال</div>
                </div>
                <div class="card p-5">
                    <div class="text-2xl font-black text-accent-600">{{ stats.pending ?? 0 }}</div>
                    <div class="text-[11px] text-surface-400 mt-1">بانتظار الدفع</div>
                </div>
                <div class="card p-5">
                    <div class="text-2xl font-black text-surface-500">{{ stats.expired ?? 0 }}</div>
                    <div class="text-[11px] text-surface-400 mt-1">منتهي</div>
                </div>
                <div class="card p-5">
                    <div class="text-2xl font-black text-primary-700 dark:text-primary-400">
                        {{ formatQAR(stats.monthly_recurring_revenue ?? 0) }}
                    </div>
                    <div class="text-[11px] text-surface-400 mt-1">دخل شهري متكرر</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card p-4 flex flex-wrap gap-3">
                <input v-model="search" type="text" class="input flex-1 min-w-[200px]" placeholder="ابحث باسم أو بريد الطالب..." />

                <select v-model="status" class="input w-auto">
                    <option value="">كل الحالات</option>
                    <option value="active">فعّال</option>
                    <option value="pending">بانتظار الدفع</option>
                    <option value="expired">منتهي</option>
                    <option value="cancelled">ملغي</option>
                </select>

                <select v-model="type" class="input w-auto">
                    <option value="">كل الأنواع</option>
                    <option value="group">مجموعات</option>
                    <option value="private">حصص خاصة</option>
                </select>
            </div>

            <!-- Table -->
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-surface-50 dark:bg-surface-900 text-xs text-surface-500">
                            <tr>
                                <th class="px-4 py-3 text-start font-bold">الطالب</th>
                                <th class="px-4 py-3 text-start font-bold">المادة / المعلم</th>
                                <th class="px-4 py-3 text-start font-bold">النوع</th>
                                <th class="px-4 py-3 text-start font-bold">السعر</th>
                                <th class="px-4 py-3 text-start font-bold">الفترة</th>
                                <th class="px-4 py-3 text-start font-bold">الحالة</th>
                                <th class="px-4 py-3 text-start font-bold"></th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                            <tr v-for="sub in subscriptions.data" :key="sub.id" class="hover:bg-surface-50/60 dark:hover:bg-surface-800/40">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-surface-900 dark:text-white text-xs">{{ sub.student?.name }}</div>
                                    <div class="text-[11px] text-surface-400">{{ sub.student?.email }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-xs text-surface-800 dark:text-surface-100">{{ sub.assignment?.subject?.name }}</div>
                                    <div class="text-[11px] text-surface-400">
                                        {{ sub.assignment?.teacher?.name }}
                                        <span v-if="sub.group"> — {{ sub.group.name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="badge-gray text-[10px]">
                                        {{ sub.type === 'private' ? 'خاصة' : 'مجموعة' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-bold text-xs text-surface-800 dark:text-surface-100">
                                    {{ formatQAR(sub.monthly_price) }}
                                </td>
                                <td class="px-4 py-3 text-[11px] text-surface-400">
                                    {{ sub.period_start }} → {{ sub.period_end }}
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="statusClasses[sub.status]" class="text-[10px]">
                                        {{ statusLabels[sub.status] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <button
                                        v-if="sub.status === 'active' || sub.status === 'pending'"
                                        type="button"
                                        class="btn-ghost btn-sm text-red-500"
                                        @click="cancel(sub.id)"
                                    >
                                        إلغاء
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="!subscriptions.data?.length">
                                <td colspan="7" class="px-4 py-12 text-center">
                                    <Icon name="info" class="w-8 h-8 text-surface-300 mx-auto mb-2" />
                                    <p class="text-sm text-surface-400">لا توجد اشتراكات مطابقة.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="subscriptions.links?.length > 3" class="flex flex-wrap gap-1 p-4 border-t border-surface-100 dark:border-surface-800">
                    <Link
                        v-for="link in subscriptions.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors"
                        :class="[
                            link.active ? 'bg-primary-600 text-white' : 'text-surface-500 hover:bg-surface-100 dark:hover:bg-surface-800',
                            !link.url && 'opacity-40 pointer-events-none',
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
