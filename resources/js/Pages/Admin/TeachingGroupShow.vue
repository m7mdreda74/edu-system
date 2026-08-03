<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import { formatQAR } from '@/lib/money';

defineProps({
    group:         { type: Object, required: true },
    subscriptions: { type: Array,  default: () => [] },
});

const BADGES = {
    active: 'badge-green', pending: 'badge-accent',
    expired: 'badge-gray', cancelled: 'badge-red',
};

const LABELS = {
    active: 'فعّال', pending: 'بانتظار الدفع', expired: 'منتهي', cancelled: 'ملغي',
};

function deleteRecording(material) {
    if (!material.recording_session_id || !window.confirm(`حذف تسجيل «${material.title}» من المنصة؟`)) return;

    router.delete(route('admin.recorded-classes.destroy', material.recording_session_id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="group.name" />

    <DashboardLayout>
        <div class="space-y-6">
            <header>
                <nav class="text-xs text-surface-400 mb-2">
                    <Link :href="route('admin.teaching-groups')" class="hover:text-primary-500">مجموعات التدريس</Link>
                    <span class="mx-1">/</span>
                    <span>{{ group.name }}</span>
                </nav>

                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h1 class="text-2xl font-black text-surface-900 dark:text-white">{{ group.name }}</h1>
                            <span :class="group.is_active ? 'badge-green' : 'badge-gray'" class="text-[10px]">
                                {{ group.is_active ? 'مفعّلة' : 'متوقفة' }}
                            </span>
                        </div>
                        <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                            {{ group.subject }} · {{ group.grade }}
                        </p>
                    </div>

                    <Link :href="route('teachers.show', group.teacher?.id)" class="btn-outline btn-sm">
                        صفحة المعلم
                    </Link>
                </div>
            </header>

            <div class="grid lg:grid-cols-3 gap-5">
                <!-- Summary -->
                <aside class="card p-5 h-fit space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="avatar-md">
                            <img v-if="group.teacher?.avatar" :src="group.teacher.avatar" :alt="group.teacher.name" class="w-full h-full object-cover" />
                            <span v-else class="text-primary-700 dark:text-primary-300 font-bold">{{ group.teacher?.name?.charAt(0) }}</span>
                        </div>
                        <div class="min-w-0">
                            <div class="font-bold text-sm text-surface-900 dark:text-white truncate">{{ group.teacher?.name }}</div>
                            <div class="text-[11px] text-surface-400 truncate">{{ group.teacher?.email }}</div>
                        </div>
                    </div>

                    <div class="space-y-2 pt-4 border-t border-surface-100 dark:border-surface-800 text-sm">
                        <div class="flex justify-between">
                            <span class="text-surface-500">الاشتراك الشهري</span>
                            <span class="font-bold text-primary-700 dark:text-primary-400">{{ formatQAR(group.monthly_price) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-surface-500">السعة</span>
                            <span class="text-surface-800 dark:text-surface-100">{{ group.capacity }} طالب</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-surface-500">المقاعد المتبقية</span>
                            <span :class="group.seats_left === 0 ? 'text-accent-600 font-bold' : 'text-surface-800 dark:text-surface-100'">
                                {{ group.seats_left }}
                            </span>
                        </div>
                    </div>

                    <div v-if="group.schedule?.length" class="pt-4 border-t border-surface-100 dark:border-surface-800">
                        <div class="text-xs font-bold text-surface-700 dark:text-surface-200 mb-2">المواعيد</div>
                        <div class="space-y-1">
                            <div v-for="(slot, i) in group.schedule" :key="i" class="text-[11px] text-surface-500 flex items-center gap-1.5">
                                <Icon name="calendar" class="w-3 h-3" />
                                {{ slot.day }} {{ slot.start }}–{{ slot.end }}
                            </div>
                        </div>
                    </div>

                    <div v-if="group.materials?.length" class="pt-4 border-t border-surface-100 dark:border-surface-800">
                        <div class="text-xs font-bold text-surface-700 dark:text-surface-200 mb-2">
                            المواد التعليمية ({{ group.materials.length }})
                        </div>
                        <div class="space-y-1">
                            <div v-for="m in group.materials" :key="m.id" class="text-[11px] text-surface-500 flex items-center gap-1.5">
                                <span class="text-surface-300">{{ m.order }}.</span>
                                <span class="truncate">{{ m.title }}</span>
                                <button v-if="m.recording_session_id" type="button" class="ms-auto text-red-500 hover:text-red-600 font-bold shrink-0" @click="deleteRecording(m)">
                                    حذف التسجيل
                                </button>
                                <span v-if="m.is_free_preview" class="badge-green text-[9px] shrink-0">مجاني</span>
                            </div>
                        </div>
                    </div>
                </aside>

                <!-- Students -->
                <section class="lg:col-span-2 card overflow-hidden">
                    <h2 class="font-bold text-sm text-surface-900 dark:text-white p-5 pb-4">
                        الطلاب ({{ subscriptions.length }})
                    </h2>

                    <div v-if="subscriptions.length" class="divide-y divide-surface-100 dark:divide-surface-800">
                        <div v-for="sub in subscriptions" :key="sub.id" class="flex items-center gap-3 px-5 py-3">
                            <div class="avatar-sm">
                                <img v-if="sub.student?.avatar" :src="sub.student.avatar" :alt="sub.student.name" class="w-full h-full object-cover" />
                                <span v-else class="text-primary-700 dark:text-primary-300 font-bold text-xs">{{ sub.student?.name?.charAt(0) }}</span>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold text-surface-800 dark:text-surface-100 truncate">{{ sub.student?.name }}</div>
                                <div class="text-[10px] text-surface-400 truncate">{{ sub.student?.email }}</div>
                            </div>

                            <div class="text-[10px] text-surface-400 shrink-0 hidden sm:block">
                                <template v-if="sub.status === 'active'">متبقٍ {{ sub.days_remaining }} يوم</template>
                                <template v-else>{{ sub.period_end }}</template>
                            </div>

                            <span :class="BADGES[sub.status] ?? 'badge-gray'" class="text-[10px] shrink-0">
                                {{ LABELS[sub.status] ?? sub.status }}
                            </span>
                        </div>
                    </div>

                    <p v-else class="text-sm text-surface-400 text-center py-12">
                        لا يوجد طلاب في هذه المجموعة بعد.
                    </p>
                </section>
            </div>
        </div>
    </DashboardLayout>
</template>
