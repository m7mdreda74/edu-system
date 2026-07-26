<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import StatCard from '@/Components/StatCard.vue';
import { formatQAR } from '@/lib/money';
import { debounce } from '@/lib/debounce';

const props = defineProps({
    groups:  { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    terms:   { type: Array,  default: () => [] },
    stats:   { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const term   = ref(props.filters.term ?? '');

const apply = debounce(() => {
    router.get(route('admin.teaching-groups'), {
        search: search.value || undefined,
        status: status.value || undefined,
        term:   term.value || undefined,
    }, { preserveState: true, replace: true });
}, 300);

watch([search, status, term], apply);

function toggle(group) {
    const message = group.is_active
        ? `إيقاف "${group.name}"؟ لن تظهر للطلاب الجدد، والمشتركون الحاليون يحتفظون بوصولهم.`
        : `تفعيل "${group.name}"؟`;

    if (confirm(message)) {
        router.patch(route('admin.teaching-groups.toggle', { id: group.id }), {}, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="مجموعات التدريس" />

    <DashboardLayout>
        <div class="space-y-6">
            <header>
                <h1 class="text-2xl font-black text-surface-900 dark:text-white">مجموعات التدريس</h1>
                <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                    كل المجموعات على المنصة — المعلمون ينشئونها، وأنت تشرف عليها
                </p>
            </header>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <StatCard label="إجمالي المجموعات" :value="stats.total"    icon="courses" tone="primary" />
                <StatCard label="مفعّلة"            :value="stats.active"   icon="success" tone="green" />
                <StatCard label="متوقفة"            :value="stats.inactive" icon="lock"    tone="slate" />
                <StatCard label="بلا طلاب"          :value="stats.empty"    icon="info"    tone="accent" hint="تحتاج تسويق أو إيقاف" />
            </div>

            <!-- Filters -->
            <div class="card p-4 flex flex-wrap gap-3">
                <input v-model="search" type="text" class="input flex-1 min-w-[200px]" placeholder="ابحث باسم المجموعة أو المعلم..." />

                <select v-model="status" class="input w-auto">
                    <option value="">كل الحالات</option>
                    <option value="active">مفعّلة</option>
                    <option value="inactive">متوقفة</option>
                    <option value="full">مكتملة العدد</option>
                </select>

                <select v-model="term" class="input w-auto">
                    <option value="">كل الفصول</option>
                    <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.name }} {{ t.year_label }}</option>
                </select>
            </div>

            <!-- Table -->
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-surface-50 dark:bg-surface-900 text-xs text-surface-500">
                            <tr>
                                <th class="px-4 py-3 text-start font-bold">المجموعة</th>
                                <th class="px-4 py-3 text-start font-bold">المعلم</th>
                                <th class="px-4 py-3 text-start font-bold">الموعد</th>
                                <th class="px-4 py-3 text-start font-bold">الاشتراك</th>
                                <th class="px-4 py-3 text-start font-bold">الطلاب</th>
                                <th class="px-4 py-3 text-start font-bold">الحالة</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                            <tr v-for="group in groups.data" :key="group.id" class="hover:bg-surface-50/60 dark:hover:bg-surface-800/40">
                                <td class="px-4 py-3">
                                    <Link :href="route('admin.teaching-groups.show', { id: group.id })" class="font-bold text-xs text-surface-900 dark:text-white hover:text-primary-600">
                                        {{ group.name }}
                                    </Link>
                                    <div class="text-[10px] text-surface-400">{{ group.subject }} · {{ group.grade }}</div>
                                </td>

                                <td class="px-4 py-3">
                                    <div class="text-xs text-surface-800 dark:text-surface-100">{{ group.teacher?.name }}</div>
                                    <div v-if="group.materials_count" class="text-[10px] text-surface-400">{{ group.materials_count }} مادة</div>
                                </td>

                                <td class="px-4 py-3 text-[11px] text-surface-500">{{ group.schedule || '—' }}</td>

                                <td class="px-4 py-3 font-bold text-xs text-surface-800 dark:text-surface-100">
                                    {{ formatQAR(group.monthly_price) }}
                                </td>

                                <td class="px-4 py-3">
                                    <span class="text-xs" :class="group.is_full ? 'text-accent-600 font-bold' : 'text-surface-600 dark:text-surface-300'">
                                        {{ group.students_count }} / {{ group.capacity }}
                                    </span>
                                </td>

                                <td class="px-4 py-3">
                                    <span v-if="!group.is_active" class="badge-gray text-[10px]">متوقفة</span>
                                    <span v-else-if="group.is_full" class="badge-accent text-[10px]">مكتملة</span>
                                    <span v-else class="badge-green text-[10px]">مفعّلة</span>
                                </td>

                                <td class="px-4 py-3">
                                    <button type="button" class="btn-ghost btn-sm text-[11px]" @click="toggle(group)">
                                        {{ group.is_active ? 'إيقاف' : 'تفعيل' }}
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="!groups.data?.length">
                                <td colspan="7" class="px-4 py-12 text-center">
                                    <Icon name="courses" class="w-8 h-8 text-surface-300 mx-auto mb-2" />
                                    <p class="text-sm text-surface-400">لا توجد مجموعات مطابقة.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="groups.links?.length > 3" class="flex flex-wrap gap-1 p-4 border-t border-surface-100 dark:border-surface-800">
                    <Link
                        v-for="link in groups.links"
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
