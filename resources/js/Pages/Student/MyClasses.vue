<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import { formatMonthly, DAY_NAMES } from '@/lib/money';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    subscriptions: { type: Array, default: () => [] },
});

const active   = computed(() => props.subscriptions.filter((s) => s.is_active));
const pending  = computed(() => props.subscriptions.filter((s) => s.status === 'pending'));
const inactive = computed(() => props.subscriptions.filter((s) => !s.is_active && s.status !== 'pending'));

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

const { confirm } = useConfirm();

function scheduleText(group) {
    if (!group?.schedules?.length) return 'الموعد غير محدد';
    return group.schedules.map((s) => `${DAY_NAMES[s.day] ?? ''} ${s.start}–${s.end}`).join('، ');
}

function renew(id) {
    router.post(route('student.subscriptions.renew', { id }));
}

async function cancel(id) {
    const ok = await confirm({
        title: 'إلغاء الاشتراك',
        message: 'هل أنت متأكد من إلغاء اشتراكك؟ ستفقد مقعدك في المجموعة.',
        confirmLabel: 'إلغاء',
        variant: 'warning',
    });
    if (ok) router.delete(route('student.subscriptions.cancel', { id }));
}
</script>

<template>
    <Head title="حصصي" />

    <DashboardLayout>
        <div class="space-y-8">
            <header>
                <h1 class="text-2xl font-black text-surface-900 dark:text-white">حصصي</h1>
                <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                    اشتراكاتك الشهرية مع المعلمين
                </p>
            </header>

            <!-- Awaiting payment -->
            <section v-if="pending.length">
                <h2 class="text-sm font-black text-surface-800 dark:text-surface-100 mb-3 flex items-center gap-2">
                    <Icon name="clock" class="w-4 h-4 text-accent-500" />
                    بانتظار الدفع
                </h2>

                <div class="grid gap-3 sm:grid-cols-2">
                    <article v-for="sub in pending" :key="sub.id" class="card p-4 border-s-4 border-accent-500">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="font-bold text-sm text-surface-900 dark:text-white truncate">
                                    {{ sub.subject?.name }} — {{ sub.teacher?.name }}
                                </h3>
                                <p class="text-xs text-surface-400 mt-0.5">
                                    {{ sub.type === 'private' ? 'حصص خاصة' : sub.group?.name }}
                                </p>
                            </div>
                            <span :class="statusClasses[sub.status]" class="text-[10px] shrink-0">
                                {{ statusLabels[sub.status] }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between gap-2 mt-4">
                            <span class="text-sm font-black text-primary-700 dark:text-primary-400">
                                {{ formatMonthly(sub.monthly_price) }}
                            </span>
                            <Link :href="route('checkout.show', { subscription: sub.id })" class="btn-primary btn-sm">
                                أكمل الدفع
                            </Link>
                        </div>
                    </article>
                </div>
            </section>

            <!-- Active -->
            <section>
                <h2 class="text-sm font-black text-surface-800 dark:text-surface-100 mb-3 flex items-center gap-2">
                    <Icon name="success" class="w-4 h-4 text-green-500" />
                    الاشتراكات الفعّالة ({{ active.length }})
                </h2>

                <div v-if="active.length" class="grid gap-4 lg:grid-cols-2">
                    <article v-for="sub in active" :key="sub.id" class="card p-5">
                        <div class="flex items-start gap-3 mb-4">
                            <div class="avatar-md">
                                <img v-if="sub.teacher?.avatar" :src="sub.teacher.avatar" :alt="sub.teacher.name" class="w-full h-full object-cover" />
                                <span v-else class="text-primary-700 dark:text-primary-300 font-bold">
                                    {{ sub.teacher?.name?.charAt(0) }}
                                </span>
                            </div>

                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-sm text-surface-900 dark:text-white">
                                    {{ sub.subject?.name }}
                                </h3>
                                <p class="text-xs text-surface-500 dark:text-surface-400">
                                    {{ sub.teacher?.name }}
                                    <span v-if="sub.group"> — {{ sub.group.name }}</span>
                                    <span v-else class="badge-primary text-[10px] ms-1">حصص خاصة</span>
                                </p>
                            </div>

                            <span class="badge-green text-[10px] shrink-0">{{ statusLabels[sub.status] }}</span>
                        </div>

                        <p v-if="sub.group" class="text-xs text-surface-500 dark:text-surface-400 flex items-center gap-1.5 mb-3">
                            <Icon name="calendar" class="w-3.5 h-3.5" />
                            {{ scheduleText(sub.group) }}
                        </p>

                        <!-- Content progress -->
                        <div v-if="sub.progress !== null" class="mb-3">
                            <div class="flex items-center justify-between text-[11px] text-surface-400 mb-1">
                                <span>تقدمك في المحتوى</span>
                                <span class="font-bold text-primary-600 dark:text-primary-400">{{ sub.progress }}%</span>
                            </div>
                            <div class="h-1.5 rounded-full bg-surface-100 dark:bg-surface-800 overflow-hidden">
                                <div class="h-full bg-primary-500 rounded-full transition-all duration-500"
                                     :style="{ width: sub.progress + '%' }"></div>
                            </div>
                        </div>

                        <!-- Renewal warning -->
                        <div
                            class="text-[11px] mb-4"
                            :class="sub.days_remaining <= 7 ? 'text-accent-600 dark:text-accent-400 font-bold' : 'text-surface-400'"
                        >
                            <template v-if="sub.days_remaining > 0">
                                متبقٍ {{ sub.days_remaining }} يوم — يتجدد في {{ sub.period_end }}
                            </template>
                            <template v-else>ينتهي اليوم</template>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <Link v-if="sub.group" :href="route('student.learn', { groupId: sub.group.id })" class="btn-primary btn-sm">
                                ادخل الحصة
                            </Link>

                            <Link
                                v-if="sub.certificate_ready && sub.group"
                                :href="route('student.certificate', { groupId: sub.group.id })"
                                class="btn-accent btn-sm"
                            >
                                شهادتي
                            </Link>

                            <button type="button" class="btn-outline btn-sm" @click="renew(sub.id)">جدّد شهر</button>
                            <button type="button" class="btn-ghost btn-sm text-red-500" @click="cancel(sub.id)">إلغاء</button>
                        </div>
                    </article>
                </div>

                <div v-else class="card p-10 text-center">
                    <Icon name="courses" class="w-10 h-10 text-surface-300 mx-auto mb-3" />
                    <h3 class="font-bold text-surface-700 dark:text-surface-200 mb-1">لسه مش مشترك في أي حصص</h3>
                    <p class="text-sm text-surface-400 mb-5">اختر صفك، ثم المادة، وشوف المعلمين المتاحين.</p>
                    <Link :href="route('home') + '#grades'" class="btn-primary btn-sm">تصفح المعلمين</Link>
                </div>
            </section>

            <!-- History -->
            <section v-if="inactive.length">
                <h2 class="text-sm font-black text-surface-800 dark:text-surface-100 mb-3">اشتراكات سابقة</h2>

                <div class="card divide-y divide-surface-100 dark:divide-surface-800">
                    <div v-for="sub in inactive" :key="sub.id" class="flex items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-surface-800 dark:text-surface-100 truncate">
                                {{ sub.subject?.name }} — {{ sub.teacher?.name }}
                            </div>
                            <div class="text-[11px] text-surface-400">
                                {{ sub.period_start }} إلى {{ sub.period_end }}
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <span :class="statusClasses[sub.status]" class="text-[10px]">{{ statusLabels[sub.status] }}</span>
                            <button type="button" class="btn-outline btn-sm" @click="renew(sub.id)">أعد الاشتراك</button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </DashboardLayout>
</template>
