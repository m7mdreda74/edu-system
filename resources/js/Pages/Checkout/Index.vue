<script setup>
import { ref, computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    course: { type: Object, required: true },
});

const form = useForm({ coupon_code: '' });
const couponMsg    = ref('');
const couponError  = ref('');
const discountedPrice = ref(null);
const checkingCoupon  = ref(false);

function formatQAR(halala) {
    return new Intl.NumberFormat('ar-QA', {
        style: 'currency', currency: 'QAR', minimumFractionDigits: 0,
    }).format((halala ?? 0) / 100);
}

const effectivePrice = computed(() => {
    if (discountedPrice.value !== null) return discountedPrice.value;
    return props.course.effective_price !== undefined && props.course.effective_price !== null
        ? props.course.effective_price
        : (props.course.discount_price !== null && props.course.discount_price !== undefined
            ? props.course.discount_price
            : props.course.price);
});

const isFree = computed(() => effectivePrice.value === 0);

async function checkCoupon() {
    if (!form.coupon_code.trim()) return;
    checkingCoupon.value = true;
    couponMsg.value      = '';
    couponError.value    = '';

    try {
        const res = await axios.post(route('checkout.coupon.check'), {
            coupon_code: form.coupon_code,
            course_id:   props.course.id,
        });
        discountedPrice.value = res.data.discounted_price;
        couponMsg.value       = `خصم ${res.data.discount_percent}% مطبّق!`;
    } catch (e) {
        couponError.value    = e.response?.data?.error ?? 'كوبون غير صحيح';
        discountedPrice.value = null;
    } finally {
        checkingCoupon.value = false;
    }
}

function submit() {
    form.post(route('checkout.process', { slug: props.course.slug }));
}
</script>

<template>
    <AppLayout>
        <Head :title="`شراء — ${course.title}`" />

        <div class="container-app px-4 py-10 max-w-2xl">

            <div class="flex items-center gap-3 mb-8">
                <Link :href="route('courses.show', { slug: course.slug })" class="btn-ghost p-2">←</Link>
                <h1 class="text-2xl font-black text-surface-900 dark:text-white">إتمام الشراء</h1>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-6">

                <!-- ── Order Summary ─────────────────────────────── -->
                <div class="md:col-span-3 space-y-4">

                    <!-- Course Card -->
                    <div class="card p-5 flex gap-4">
                        <div class="w-20 h-16 rounded-xl overflow-hidden bg-surface-100 dark:bg-surface-800 flex-shrink-0">
                            <img v-if="course.thumbnail" :src="course.thumbnail" class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full flex items-center justify-center text-surface-400 bg-surface-100 dark:bg-surface-800">
                                <Icon name="courses" class="w-6 h-6" />
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h2 class="font-bold text-surface-900 dark:text-white text-sm leading-snug line-clamp-2">
                                {{ course.title }}
                            </h2>
                            <p class="text-xs text-surface-400 mt-1">
                                {{ course.teacher?.name }}
                            </p>
                        </div>
                    </div>

                    <!-- Coupon -->
                    <div class="card p-5">
                        <h3 class="font-semibold text-sm text-surface-700 dark:text-surface-300 mb-3">كوبون الخصم</h3>
                        <div class="flex gap-2">
                            <input
                                v-model="form.coupon_code"
                                type="text"
                                class="input flex-1 uppercase"
                                placeholder="أدخل كود الخصم"
                                id="coupon-input"
                                @keyup.enter="checkCoupon"
                            />
                            <button
                                @click="checkCoupon"
                                :disabled="checkingCoupon || !form.coupon_code"
                                class="btn-outline"
                            >
                                <span v-if="checkingCoupon">⏳</span>
                                <span v-else>تطبيق</span>
                            </button>
                        </div>
                        <p v-if="couponMsg"   class="text-green-600 text-xs mt-2">{{ couponMsg }}</p>
                        <p v-if="couponError" class="text-red-500 text-xs mt-2">{{ couponError }}</p>
                    </div>
                </div>

                <!-- ── Price & Submit ────────────────────────────── -->
                <div class="md:col-span-2">
                    <div class="card p-6 sticky top-24">
                        <h3 class="font-bold text-surface-800 dark:text-white mb-4">ملخص الطلب</h3>

                        <div class="space-y-2 mb-4">
                            <div class="flex justify-between text-sm">
                                <span class="text-surface-500">السعر الأصلي</span>
                                <span>{{ formatQAR(course.price) }}</span>
                            </div>
                            <div v-if="discountedPrice !== null" class="flex justify-between text-sm text-green-600">
                                <span>الخصم</span>
                                <span>-{{ formatQAR(course.effective_price - discountedPrice) }}</span>
                            </div>
                            <div class="h-px bg-surface-200 dark:bg-surface-700 my-2"></div>
                            <div class="flex justify-between font-bold text-lg">
                                <span>الإجمالي</span>
                                <span class="text-primary-700 dark:text-primary-400">
                                    {{ isFree ? 'مجاني' : formatQAR(effectivePrice) }}
                                </span>
                            </div>
                        </div>

                        <form @submit.prevent="submit">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="btn-accent w-full btn-lg"
                                id="pay-btn"
                            >
                                <span v-if="form.processing">⏳ جاري التحويل...</span>
                                <span v-else-if="isFree">🎁 التسجيل مجاناً</span>
                                <span v-else>💳 انتقل للدفع</span>
                            </button>
                        </form>

                        <p class="text-xs text-surface-400 text-center mt-3">
                            🔒 دفع آمن عبر Stripe — بيانات بطاقتك لا تصل إلينا
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
