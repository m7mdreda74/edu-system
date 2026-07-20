<script setup>
import { ref, computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    course: { type: Object, required: true },
    manualMethods: { type: Array, default: () => [] },
    purchaseRequest: { type: Object, default: null }
});

const form = useForm({ coupon_code: '' });
const couponMsg    = ref('');
const couponError  = ref('');
const discountedPrice = ref(null);
const checkingCoupon  = ref(false);

// Manual payment refs
const paymentMethod = ref('gateway'); // gateway | manual
const selectedMethodIdx = ref(0);
const receiptFile = ref(null);
const receiptPreview = ref(null);

const selectedMethod = computed(() => props.manualMethods[selectedMethodIdx.value] || null);

function handleFileChange(event) {
    const file = event.target.files[0];
    if (file) {
        receiptFile.value = file;
        receiptPreview.value = URL.createObjectURL(file);
        couponError.value = '';
    }
}

function formatQAR(halala) {
    const formatted = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format((halala ?? 0) / 100);
    return `${formatted} ر.ق.`;
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

async function submit() {
    if (form.processing) return;

    if (isFree.value) {
        form.post(route('checkout.process', { slug: props.course.slug }));
        return;
    }

    couponError.value = '';

    // Handle Manual Payment Submission
    if (paymentMethod.value === 'manual') {
        if (!receiptFile.value) {
            couponError.value = 'يرجى رفع صورة إيصال التحويل لإتمام طلبك.';
            return;
        }

        form.processing = true;
        
        const formData = new FormData();
        formData.append('payment_method', 'manual');
        formData.append('coupon_code', form.coupon_code);
        formData.append('selected_method', JSON.stringify(selectedMethod.value));
        formData.append('receipt', receiptFile.value);
        if (props.purchaseRequest) {
            formData.append('purchase_request_id', props.purchaseRequest.id);
        }

        try {
            const res = await axios.post(route('checkout.process', { slug: props.course.slug }), formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            if (res.data.success) {
                // Redirect user to dashboard with success message
                window.location.href = res.data.redirect_url;
            }
        } catch (e) {
            console.error(e);
            couponError.value = e.response?.data?.error ?? 'حدث خطأ أثناء رفع إيصال الدفع، يرجى المحاولة لاحقاً.';
        } finally {
            form.processing = false;
        }
        return;
    }

    // Handle Gateway Payment Submission
    form.processing = true;
    try {
        const payload = {
            coupon_code: form.coupon_code,
            payment_method: 'gateway',
        };
        if (props.purchaseRequest) {
            payload.purchase_request_id = props.purchaseRequest.id;
        }
        const res = await axios.post(route('checkout.process', { slug: props.course.slug }), payload);

        if (res.data.redirect_url) {
            const width = 600;
            const height = 750;
            const left = (window.screen.width - width) / 2;
            const top = (window.screen.height - height) / 2;

            const popup = window.open(
                res.data.redirect_url,
                'PaymentGateway',
                `width=${width},height=${height},top=${top},left=${left},status=yes,scrollbars=yes`
            );

            // Fallback if popup is blocked
            if (!popup || popup.closed || typeof popup.closed === 'undefined') {
                window.location.href = res.data.redirect_url;
            } else {
                // Periodically check if popup is closed to reload dashboard in background
                const timer = setInterval(() => {
                    if (popup.closed) {
                        clearInterval(timer);
                        window.location.href = route('dashboard');
                    }
                }, 1200);
            }
        }
    } catch (e) {
        console.error(e);
        couponError.value = e.response?.data?.error ?? 'حدث خطأ أثناء معالجة الدفع، يرجى المحاولة لاحقاً.';
    } finally {
        form.processing = false;
    }
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

                    <!-- Purchase Request Info -->
                    <div v-if="purchaseRequest" class="card p-4 border-amber-300 dark:border-amber-900 bg-amber-500/5 flex items-start gap-3">
                        <div class="p-2 rounded-full bg-amber-100 dark:bg-amber-950/20 text-amber-600">
                            <Icon name="users" class="w-5 h-5 shrink-0" />
                        </div>
                        <div>
                            <div class="font-bold text-xs text-amber-700 dark:text-amber-350">أنت تدفع بالنيابة عن ابنك</div>
                            <div class="text-[11px] text-surface-600 dark:text-surface-400 mt-1 leading-relaxed">
                                سيتم تسجيل الطالب <span class="font-bold text-surface-900 dark:text-white">{{ purchaseRequest.student?.name }}</span> في الكورس فوراً بمجرد نجاح السداد.
                            </div>
                        </div>
                    </div>

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

                    <!-- Payment Method Select Tabs -->
                    <div v-if="!isFree && manualMethods && manualMethods.length > 0" class="card p-5 space-y-4">
                        <h3 class="font-semibold text-sm text-surface-700 dark:text-surface-300">اختر طريقة الدفع</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <button
                                type="button"
                                @click="paymentMethod = 'gateway'"
                                class="p-3.5 rounded-2xl border text-xs font-bold transition-all flex flex-col items-center justify-center gap-1.5"
                                :class="paymentMethod === 'gateway'
                                    ? 'border-primary-500 bg-primary-50/30 dark:bg-primary-950/20 text-primary-700 dark:text-primary-300 shadow-sm'
                                    : 'border-surface-200 dark:border-surface-800 text-surface-500 hover:bg-surface-50/50'"
                            >
                                <Icon name="payments" class="w-5 h-5 shrink-0" />
                                <span>الدفع الإلكتروني الفوري</span>
                            </button>
                            <button
                                type="button"
                                @click="paymentMethod = 'manual'"
                                class="p-3.5 rounded-2xl border text-xs font-bold transition-all flex flex-col items-center justify-center gap-1.5"
                                :class="paymentMethod === 'manual'
                                    ? 'border-primary-500 bg-primary-50/30 dark:bg-primary-950/20 text-primary-700 dark:text-primary-300 shadow-sm'
                                    : 'border-surface-200 dark:border-surface-800 text-surface-500 hover:bg-surface-50/50'"
                            >
                                <Icon name="settings" class="w-5 h-5 shrink-0" />
                                <span>تحويل بنكي / محفظة</span>
                            </button>
                        </div>
                    </div>

                    <!-- Manual Payment Flow Details -->
                    <div v-if="paymentMethod === 'manual' && selectedMethod" class="card p-5 space-y-4">
                        <div class="space-y-1.5">
                            <label class="block text-[11px] font-bold text-surface-500">اختر حساب التحويل</label>
                            <select v-model="selectedMethodIdx" class="input w-full text-xs">
                                <option v-for="(m, i) in manualMethods" :key="i" :value="i">
                                    {{ m.type === 'bank' ? 'تحويل بنكي' : 'محفظة إلكترونية' }} — {{ m.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Selected Account Details -->
                        <div class="p-4 rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50/40 dark:bg-surface-900/10 space-y-2">
                            <div class="flex justify-between text-xs">
                                <span class="text-surface-400">اسم صاحب الحساب:</span>
                                <span class="font-bold text-surface-800 dark:text-surface-200">{{ selectedMethod.account_name }}</span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span class="text-surface-400">رقم الحساب / الهاتف:</span>
                                <span class="font-mono font-bold text-primary-600 dark:text-primary-400 select-all">{{ selectedMethod.account_number }}</span>
                            </div>
                            <div class="h-px bg-surface-150 dark:bg-surface-800 my-2"></div>
                            <div class="text-[11px] text-surface-500 leading-relaxed whitespace-pre-line">
                                {{ selectedMethod.instructions }}
                            </div>
                        </div>

                        <!-- File Upload -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-surface-700 dark:text-surface-300">ارفع صورة إيصال الدفع / التحويل</label>
                            <div class="flex items-center justify-center w-full">
                                <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-surface-300 dark:border-surface-800 border-dashed rounded-2xl cursor-pointer hover:bg-surface-50/50 dark:hover:bg-surface-900/10 transition-colors relative overflow-hidden">
                                    <div v-if="!receiptPreview" class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <svg class="w-8 h-8 mb-3 text-surface-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                        <p class="mb-1 text-xs text-surface-500"><span class="font-semibold">اضغط هنا لرفع صورة الإيصال</span></p>
                                        <p class="text-[10px] text-surface-400">PNG, JPG up to 8MB</p>
                                    </div>
                                    <img v-else :src="receiptPreview" class="w-full h-full object-cover absolute inset-0" />
                                    <input type="file" class="hidden" accept="image/*" @change="handleFileChange" />
                                </label>
                            </div>
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
                                <span v-if="checkingCoupon" class="w-4 h-4 border-2 border-primary-500 border-t-transparent rounded-full animate-spin block"></span>
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
                                <span v-if="form.processing" class="flex items-center justify-center gap-2">
                                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>{{ paymentMethod === 'manual' ? 'جاري رفع الإيصال...' : 'جاري التحويل...' }}</span>
                                </span>
                                <span v-else-if="isFree" class="flex items-center justify-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V6a2 2 0 10-2 2h2zm0 0H4v13a2 2 0 002 2h12a2 2 0 002-2V8H12z" />
                                    </svg>
                                    التسجيل مجاناً
                                </span>
                                <span v-else-if="paymentMethod === 'manual'" class="flex items-center justify-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    إرسال تأكيد التحويل
                                </span>
                                <span v-else class="flex items-center justify-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                    </svg>
                                    انتقل للدفع الفوري
                                </span>
                            </button>
                        </form>

                        <p class="text-xs text-surface-400 flex items-center justify-center gap-1 mt-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            دفع آمن ومشفر — بيانات بطاقتك لا تصل إلينا
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
