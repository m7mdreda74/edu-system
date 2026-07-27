<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import StatCard from '@/Components/StatCard.vue';
import { formatQAR } from '@/lib/money';

const props = defineProps({
    apologies: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({}) },
});

const selected = ref(null);
const form = useForm({ amount_qar: '', admin_note: '' });

const statusLabels = {
    pending: 'بانتظار القرار',
    makeup_scheduled: 'تم تحديد حصة تعويضية',
    deducted: 'تم تسجيل خصم',
};

const statusClasses = {
    pending: 'badge-accent',
    makeup_scheduled: 'badge-green',
    deducted: 'badge-red',
};

function filter(status = '') {
    router.get(route('admin.session-apologies'), { status: status || undefined }, {
        preserveState: true,
        replace: true,
    });
}

function openDeduction(apology) {
    selected.value = apology;
    form.reset();
}

function submitDeduction() {
    form.post(route('admin.session-apologies.deduct', selected.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = null;
            form.reset();
        },
    });
}

function formatDate(value) {
    return value
        ? new Date(value).toLocaleString('ar-EG', { dateStyle: 'medium', timeStyle: 'short' })
        : '—';
}
</script>

<template>
    <DashboardLayout>
        <Head title="اعتذارات الحصص" />

        <div class="space-y-6">
            <header>
                <h1 class="text-2xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                    <Icon name="calendar" class="h-7 w-7 text-primary-500" />
                    اعتذارات الحصص
                </h1>
                <p class="mt-1 text-sm text-surface-500">
                    راجع سبب الاعتذار. المدرس يستطيع تحديد حصة تعويضية، والخصم المالي لا يسجله إلا الأدمن.
                </p>
            </header>

            <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard label="بانتظار القرار" :value="stats.pending" icon="clock" tone="accent" />
                <StatCard label="تم تعويضها" :value="stats.makeup" icon="success" tone="green" />
                <StatCard label="تم خصمها" :value="stats.deducted" icon="payments" tone="red" />
                <StatCard label="خصومات للتسوية القادمة" :value="formatQAR(stats.pending_deductions)" icon="earnings" tone="primary" />
            </section>

            <div class="flex flex-wrap gap-2">
                <button :class="!filters.status ? 'btn-primary' : 'btn-outline'" @click="filter()">الكل</button>
                <button :class="filters.status === 'pending' ? 'btn-primary' : 'btn-outline'" @click="filter('pending')">بانتظار القرار</button>
                <button :class="filters.status === 'makeup_scheduled' ? 'btn-primary' : 'btn-outline'" @click="filter('makeup_scheduled')">حصة تعويضية</button>
                <button :class="filters.status === 'deducted' ? 'btn-primary' : 'btn-outline'" @click="filter('deducted')">خصم</button>
            </div>

            <section class="grid gap-4 xl:grid-cols-2">
                <article v-for="apology in apologies.data" :key="apology.id" class="card p-5 space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="avatar-md bg-primary-500/10">
                                <img v-if="apology.teacher?.avatar" :src="apology.teacher.avatar" class="h-full w-full object-cover" />
                                <span v-else class="font-black text-primary-600">{{ apology.teacher?.name?.charAt(0) }}</span>
                            </div>
                            <div>
                                <h2 class="font-black text-surface-900 dark:text-white">{{ apology.teacher?.name }}</h2>
                                <p class="text-xs text-surface-400">{{ apology.teacher?.email }}</p>
                            </div>
                        </div>
                        <span :class="statusClasses[apology.status]">{{ statusLabels[apology.status] }}</span>
                    </div>

                    <div class="rounded-2xl bg-surface-50 p-4 dark:bg-surface-900">
                        <b class="text-sm text-surface-900 dark:text-white">{{ apology.session?.title }}</b>
                        <p class="mt-1 text-xs text-surface-500">
                            {{ apology.session?.teaching_group?.name || 'حصة برايفيت' }} · {{ formatDate(apology.session?.scheduled_at) }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-bold text-surface-500">سبب اعتذار المدرس</p>
                        <p class="mt-1 whitespace-pre-line text-sm leading-7 text-surface-800 dark:text-surface-200">{{ apology.reason }}</p>
                    </div>

                    <div v-if="apology.status === 'makeup_scheduled'" class="rounded-xl border border-green-500/25 bg-green-500/10 p-3 text-sm text-green-700 dark:text-green-300">
                        الحصة التعويضية: {{ formatDate(apology.makeup_scheduled_at) }}
                    </div>

                    <div v-if="apology.status === 'deducted'" class="rounded-xl border border-red-500/25 bg-red-500/10 p-3 text-sm">
                        <p class="font-black text-red-600">قيمة الخصم: {{ formatQAR(apology.deduction_amount) }}</p>
                        <p v-if="apology.admin_note" class="mt-1 text-xs text-surface-500">{{ apology.admin_note }}</p>
                        <p class="mt-1 text-xs text-surface-500">
                            {{ apology.teacher_payout_id ? 'تم إدخاله في تسوية مالية' : 'سيُطبق على التسوية التالية' }}
                        </p>
                    </div>

                    <button v-if="apology.status === 'pending'" class="btn-primary w-full" @click="openDeduction(apology)">
                        تنفيذ خصم على المدرس
                    </button>
                </article>
            </section>

            <div v-if="!apologies.data.length" class="card p-12 text-center text-surface-400">
                لا توجد اعتذارات بهذه الحالة.
            </div>

            <nav v-if="apologies.links?.length > 3" class="flex flex-wrap justify-center gap-2">
                <Link
                    v-for="link in apologies.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    preserve-scroll
                    class="rounded-xl px-3 py-2 text-sm"
                    :class="[
                        link.active ? 'bg-primary-500 text-white' : 'bg-surface-100 dark:bg-surface-800',
                        !link.url ? 'pointer-events-none opacity-40' : '',
                    ]"
                    v-html="link.label"
                />
            </nav>
        </div>

        <div v-if="selected" class="fixed inset-0 z-[70] grid place-items-center bg-black/60 p-4" @click.self="selected = null">
            <form class="card w-full max-w-lg space-y-4 p-6" @submit.prevent="submitDeduction">
                <div>
                    <h2 class="text-xl font-black text-surface-900 dark:text-white">تسجيل خصم على {{ selected.teacher?.name }}</h2>
                    <p class="mt-1 text-sm text-surface-500">سيظهر الخصم في التسوية المالية التالية ولن يُطبّق مرتين.</p>
                </div>
                <div>
                    <label class="input-label">قيمة الخصم بالريال القطري</label>
                    <input v-model="form.amount_qar" type="number" min="0.01" step="0.01" class="input" required />
                    <p v-if="form.errors.amount_qar" class="error-msg">{{ form.errors.amount_qar }}</p>
                </div>
                <div>
                    <label class="input-label">ملاحظة الإدارة</label>
                    <textarea v-model="form.admin_note" rows="3" class="input" placeholder="سبب وقيمة الخصم"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-ghost" @click="selected = null">إلغاء</button>
                    <button class="btn-primary" :disabled="form.processing">تأكيد الخصم</button>
                </div>
            </form>
        </div>
    </DashboardLayout>
</template>
