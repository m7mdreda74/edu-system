<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import { debounce } from '@/lib/debounce';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    reviews: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    counts:  { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search ?? '');

const applySearch = debounce(() => {
    router.get(route('admin.reviews'),
        { status: props.filters.status, search: search.value || undefined },
        { preserveState: true, replace: true });
}, 300);

watch(search, applySearch);

function setStatus(status) {
    router.get(route('admin.reviews'), { status, search: search.value || undefined }, { preserveState: true });
}

const approve = (id) => router.patch(route('admin.reviews.approve', { id }), {}, { preserveScroll: true });
const reject  = (id) => router.patch(route('admin.reviews.reject',  { id }), {}, { preserveScroll: true });
const { confirm } = useConfirm();

async function destroy(id) {
    const ok = await confirm({
        title: 'حذف التقييم',
        message: 'سيتم حذف هذا التقييم نهائياً.',
        confirmLabel: 'حذف',
        variant: 'danger',
    });
    if (ok) router.delete(route('admin.reviews.destroy', { id }), { preserveScroll: true });
}

async function approveAll() {
    const ok = await confirm({
        title: 'اعتماد جميع التقييمات',
        message: `سيتم اعتماد ${props.counts.pending} تقييم معلّق ونشرها للطلاب.`,
        confirmLabel: 'اعتماد الجميع',
        variant: 'info',
    });
    if (ok) router.post(route('admin.reviews.approve-all'));
}
</script>

<template>
    <Head title="تقييمات المعلمين" />

    <DashboardLayout>
        <div class="space-y-6">
            <header class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white">تقييمات المعلمين</h1>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                        لا يظهر أي تقييم على صفحة المعلم قبل اعتماده من هنا
                    </p>
                </div>

                <button
                    v-if="counts.pending > 0"
                    type="button"
                    class="btn-primary btn-sm"
                    @click="approveAll"
                >
                    اعتماد الكل ({{ counts.pending }})
                </button>
            </header>

            <!-- Tabs + search -->
            <div class="card p-4 flex flex-wrap items-center gap-3">
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-colors"
                        :class="filters.status === 'pending'
                            ? 'bg-accent-500 text-white'
                            : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300'"
                        @click="setStatus('pending')"
                    >
                        بانتظار الاعتماد ({{ counts.pending }})
                    </button>

                    <button
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-colors"
                        :class="filters.status === 'approved'
                            ? 'bg-green-600 text-white'
                            : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300'"
                        @click="setStatus('approved')"
                    >
                        معتمدة ({{ counts.approved }})
                    </button>

                    <button
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-colors"
                        :class="filters.status === 'all'
                            ? 'bg-primary-600 text-white'
                            : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300'"
                        @click="setStatus('all')"
                    >
                        الكل
                    </button>
                </div>

                <input v-model="search" type="text" class="input flex-1 min-w-[200px]" placeholder="ابحث باسم المعلم أو الطالب..." />
            </div>

            <!-- List -->
            <div v-if="reviews.data?.length" class="space-y-3">
                <article v-for="review in reviews.data" :key="review.id" class="card p-5">
                    <div class="flex items-start gap-4 flex-wrap">
                        <div class="avatar-md shrink-0">
                            <img v-if="review.user?.avatar" :src="review.user.avatar" :alt="review.user.name" class="w-full h-full object-cover" />
                            <span v-else class="text-primary-700 dark:text-primary-300 font-bold">{{ review.user?.name?.charAt(0) }}</span>
                        </div>

                        <div class="flex-1 min-w-[220px]">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-bold text-sm text-surface-900 dark:text-white">{{ review.user?.name }}</span>
                                <span class="text-xs text-surface-400">قيّم</span>
                                <Link :href="route('teachers.show', review.teacher?.id)" class="font-bold text-sm text-primary-600 dark:text-primary-400 hover:underline">
                                    {{ review.teacher?.name }}
                                </Link>
                                <span :class="review.is_approved ? 'badge-green' : 'badge-accent'" class="text-[10px]">
                                    {{ review.is_approved ? 'معتمد' : 'معلّق' }}
                                </span>
                            </div>

                            <div class="text-accent-500 text-sm mt-1">
                                {{ '★'.repeat(review.rating) }}<span class="text-surface-300">{{ '★'.repeat(5 - review.rating) }}</span>
                            </div>

                            <p v-if="review.comment" class="text-sm text-surface-600 dark:text-surface-300 mt-2 leading-relaxed">
                                {{ review.comment }}
                            </p>
                            <p v-else class="text-xs text-surface-400 mt-2 italic">تقييم بدون تعليق</p>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <button v-if="!review.is_approved" type="button" class="btn-primary btn-sm" @click="approve(review.id)">
                                اعتماد
                            </button>
                            <button v-else type="button" class="btn-outline btn-sm" @click="reject(review.id)">
                                إخفاء
                            </button>
                            <button type="button" class="btn-ghost btn-sm text-red-500" @click="destroy(review.id)">
                                <Icon name="trash" class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </article>

                <!-- Pagination -->
                <div v-if="reviews.links?.length > 3" class="card p-4 flex flex-wrap gap-1">
                    <Link
                        v-for="link in reviews.links"
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

            <div v-else class="card p-12 text-center">
                <Icon name="success" class="w-10 h-10 text-green-400 mx-auto mb-3" />
                <h3 class="font-bold text-surface-700 dark:text-surface-200 mb-1">
                    {{ filters.status === 'pending' ? 'لا توجد تقييمات بانتظار الاعتماد' : 'لا توجد تقييمات' }}
                </h3>
                <p class="text-sm text-surface-400">كل شيء تحت السيطرة.</p>
            </div>
        </div>
    </DashboardLayout>
</template>
