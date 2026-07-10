<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    coupons: { type: Array, required: true },
});

const isModalOpen = ref(false);

const form = useForm({
    code: '',
    discount_percent: '',
    expires_at: '',
    usage_limit: '',
});

function openAddModal() {
    form.reset();
    isModalOpen.value = true;
}

function submitCoupon() {
    form.post(route('admin.coupons.store'), {
        onSuccess: () => {
            isModalOpen.value = false;
            form.reset();
        }
    });
}

function toggleStatus(id) {
    router.patch(route('admin.coupons.toggle', { id }), {}, { preserveScroll: true });
}

function deleteCoupon(id) {
    if (confirm('هل أنت متأكد من حذف هذا الكوبون؟')) {
        router.delete(route('admin.coupons.destroy', { id }));
    }
}
</script>

<template>
    <DashboardLayout>
        <Head title="إدارة كوبونات الخصم" />

        <div class="container-app px-4 py-10">
            <!-- Header -->
            <div class="flex items-center justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <Icon name="payments" class="w-8 h-8 text-primary-500" />
                        <span>كوبونات الخصم والترويج</span>
                    </h1>
                    <p class="text-surface-500 mt-1">إنشاء كوبونات التخفيض للطلاب لزيادة المبيعات والاشتراكات</p>
                </div>
                <button @click="openAddModal" class="btn-primary flex items-center gap-2">
                    <Icon name="plus" class="w-4 h-4" />
                    <span>إنشاء كوبون جديد</span>
                </button>
            </div>

            <!-- Table -->
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-surface-50 dark:bg-surface-800 border-b border-surface-200 dark:border-surface-700">
                            <tr>
                                <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">رمز الكوبون</th>
                                <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">نسبة الخصم</th>
                                <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">حد الاستخدام</th>
                                <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">عدد الاستخدامات</th>
                                <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">تاريخ الانتهاء</th>
                                <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">الحالة</th>
                                <th class="text-center px-6 py-4 font-bold text-surface-700 dark:text-surface-300">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                            <tr v-for="coupon in coupons" :key="coupon.id" class="hover:bg-surface-50/50 dark:hover:bg-surface-800/20">
                                <td class="px-6 py-4 font-mono font-bold text-base text-primary-600 dark:text-primary-400">
                                    {{ coupon.code }}
                                </td>
                                <td class="px-6 py-4 font-semibold text-surface-900 dark:text-white">
                                    %{{ coupon.discount_percent }} خصم
                                </td>
                                <td class="px-6 py-4 text-surface-500">
                                    {{ coupon.usage_limit || 'غير محدود' }}
                                </td>
                                <td class="px-6 py-4 text-surface-500">
                                    {{ coupon.used_count }}
                                </td>
                                <td class="px-6 py-4 text-xs text-surface-500">
                                    {{ coupon.expires_at ? new Date(coupon.expires_at).toLocaleDateString('ar') : 'مستمر' }}
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="coupon.is_active ? 'badge-green' : 'badge-gray'">
                                        {{ coupon.is_active ? 'نشط' : 'معطل' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button @click="toggleStatus(coupon.id)" 
                                                class="btn-outline btn-xs py-1.5 px-3 rounded-lg"
                                        >
                                            {{ coupon.is_active ? 'تعطيل' : 'تنشيط' }}
                                        </button>
                                        <button @click="deleteCoupon(coupon.id)"
                                                class="btn-ghost text-red-500 hover:bg-red-500/10 p-1.5 rounded-lg"
                                        >
                                            <Icon name="close" class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="coupons.length === 0" class="p-16 text-center text-surface-400">
                    <Icon name="payments" class="w-16 h-16 mx-auto text-surface-300 dark:text-surface-700 mb-4" />
                    <h3 class="text-lg font-bold text-surface-800 dark:text-surface-200 mb-2">لا توجد كوبونات خصم</h3>
                    <p class="text-sm">لم تقم بإنشاء أي كوبون خصم بعد</p>
                </div>
            </div>

            <!-- Modal for Coupon Creation -->
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/55 backdrop-blur-sm">
                    <div class="bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-3xl w-full max-w-lg p-6 overflow-hidden shadow-2xl relative" dir="rtl">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-black text-surface-900 dark:text-white">إنشاء كوبون خصم جديد</h3>
                            <button @click="isModalOpen = false" class="btn-ghost p-1 rounded-full">
                                <Icon name="close" class="w-5 h-5 text-surface-500" />
                            </button>
                        </div>

                        <form @submit.prevent="submitCoupon" class="space-y-4">
                            <div>
                                <label class="label mb-1">رمز الكوبون (كود الاستخدام)</label>
                                <input v-model="form.code" type="text" required class="input font-mono uppercase" placeholder="مثال: BACKTOSCHOOL" />
                            </div>

                            <div>
                                <label class="label mb-1">نسبة الخصم (%)</label>
                                <input v-model="form.discount_percent" type="number" min="1" max="100" required class="input" placeholder="مثال: 15" />
                            </div>

                            <div>
                                <label class="label mb-1">الحد الأقصى للاستخدام (عدد المرات الإجمالي)</label>
                                <input v-model="form.usage_limit" type="number" class="input" placeholder="مثال: 100 (اتركه فارغاً للاستخدام غير المحدود)" />
                            </div>

                            <div>
                                <label class="label mb-1">تاريخ انتهاء الصلاحية</label>
                                <input v-model="form.expires_at" type="date" class="input" />
                            </div>

                            <div class="flex gap-3 pt-4">
                                <button type="submit" class="btn-primary flex-1">حفظ الكوبون</button>
                                <button type="button" @click="isModalOpen = false" class="btn-ghost flex-1">إلغاء</button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </div>
    </DashboardLayout>
</template>
