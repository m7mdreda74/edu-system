<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';
import { formatQAR, DAY_NAMES } from '@/lib/money';
import axios from 'axios';

const props = defineProps({
    subscription:       { type: Object, required: true },
    vodafoneCashNumber: { type: String, default: null },
});

const receiptFile    = ref(null);
const senderPhone    = ref('');
const couponCode     = ref('');
const couponState    = ref({ status: 'idle', message: '', discountedPrice: null });
const processing     = ref(false);
const errorMessage   = ref('');
const MAX_RECEIPT_BYTES = 8 * 1024 * 1024;
const RECEIPT_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

const basePrice  = computed(() => props.subscription.monthly_price ?? 0);
const finalPrice = computed(() => couponState.value.discountedPrice ?? basePrice.value);
const discount   = computed(() => Math.max(0, basePrice.value - finalPrice.value));

const scheduleText = computed(() => {
    const schedules = props.subscription.group?.schedules;
    if (!schedules?.length) return null;
    return schedules.map((s) => `${DAY_NAMES[s.day] ?? ''} ${s.start}–${s.end}`).join('، ');
});

async function applyCoupon() {
    if (!couponCode.value.trim()) return;

    couponState.value = { status: 'checking', message: '', discountedPrice: null };

    try {
        const res = await axios.post(route('checkout.coupon.check'), {
            coupon_code:     couponCode.value.trim(),
            subscription_id: props.subscription.id,
        });

        couponState.value = {
            status: 'valid',
            message: `تم تطبيق خصم ${res.data.discount_percent}%`,
            discountedPrice: res.data.discounted_price,
        };
    } catch (e) {
        couponState.value = {
            status: 'invalid',
            message: e.response?.data?.error ?? 'تعذّر التحقق من الكوبون.',
            discountedPrice: null,
        };
    }
}

function onReceiptChange(event) {
    const file = event.target.files?.[0] ?? null;
    if (file && (!RECEIPT_TYPES.includes(file.type) || file.size > MAX_RECEIPT_BYTES)) {
        receiptFile.value = null;
        event.target.value = '';
        errorMessage.value = 'Invalid receipt file or size.';
        return;
    }
    receiptFile.value = file;
    errorMessage.value = '';
}

function normalizeSenderPhone(value) {
    return value
        .replace(/[\u0660-\u0669]/g, (digit) => String(digit.codePointAt(0) - 0x0660))
        .replace(/[\u06F0-\u06F9]/g, (digit) => String(digit.codePointAt(0) - 0x06F0))
        .replace(/[\s().-]+/g, '');
}

async function submit() {
    errorMessage.value = '';

    if (!props.vodafoneCashNumber) {
        errorMessage.value = 'لم يتم ضبط رقم فودافون كاش لهذه المرحلة بعد. تواصل مع إدارة المنصة.';
        return;
    }
    const normalizedSenderPhone = normalizeSenderPhone(senderPhone.value.trim());
    if (!/^(?:\+20|0020|0)1\d{9}$/.test(normalizedSenderPhone)) {
        errorMessage.value = 'اكتب رقم الهاتف الذي حوّلت منه أولاً.';
        return;
    }
    if (!receiptFile.value) {
        errorMessage.value = 'ارفع إثبات التحويل.';
        return;
    }

    processing.value = true;

    const formData = new FormData();
    formData.append('payment_method', 'vodafone_cash');
    formData.append('sender_phone', normalizedSenderPhone);

    if (couponCode.value.trim()) {
        formData.append('coupon_code', couponCode.value.trim());
    }

    formData.append('receipt', receiptFile.value);

    try {
        const res = await axios.post(
            route('checkout.process', { subscription: props.subscription.id }),
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } },
        );

        if (res.data.redirect_url) {
            window.location.href = res.data.redirect_url;
        }
    } catch (e) {
        const validationErrors = e.response?.data?.errors ?? {};
        const firstValidationError = Object.values(validationErrors).flat()[0];
        errorMessage.value = firstValidationError ?? e.response?.data?.error ?? 'حدث خطأ أثناء إتمام العملية.';
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <Head :title="`الاشتراك — ${subscription.label}`" />

    <AppLayout>
        <div class="container-app px-4 py-10">
            <div class="flex items-center gap-2 mb-6">
                <Link :href="route('teachers.show', subscription.teacher?.id)" class="btn-ghost p-2" aria-label="رجوع">←</Link>
                <h1 class="text-xl font-black text-surface-900 dark:text-white">إتمام الاشتراك</h1>
            </div>

            <div class="grid lg:grid-cols-3 gap-6">
                <!-- ── Payment options ─────────────────────────────── -->
                <div class="lg:col-span-2 space-y-4">
                    <div v-if="errorMessage" class="alert-error">
                        <Icon name="error" class="w-5 h-5 shrink-0" />
                        <span>{{ errorMessage }}</span>
                    </div>

                    <!-- Vodafone Cash only -->
                    <div class="card p-5">
                        <h2 class="font-bold text-sm text-surface-900 dark:text-white mb-4">طريقة الدفع</h2>

                        <div class="rounded-xl border-2 border-primary-500 bg-primary-50/50 dark:bg-primary-950/30 p-4 flex items-start gap-3">
                            <Icon name="payments" class="w-5 h-5 text-primary-600 shrink-0" />
                            <div>
                                <div class="font-bold text-xs text-surface-900 dark:text-white">فودافون كاش فقط</div>
                                <div class="text-[11px] text-surface-500 dark:text-surface-400 mt-1">حوّل المبلغ إلى رقم المرحلة الموضح بالأسفل، ثم اكتب رقم الهاتف الذي حوّلت منه وارفع إثبات التحويل. لن يتفعّل الاشتراك إلا بعد مراجعة الأدمن.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Vodafone Cash transfer details -->
                    <div class="card p-5 space-y-4">
                        <h2 class="font-bold text-sm text-surface-900 dark:text-white">بيانات تحويل فودافون كاش</h2>

                        <div v-if="!vodafoneCashNumber" class="alert-error">
                            لم يتم ضبط رقم فودافون كاش لهذه المرحلة بعد. تواصل مع إدارة المنصة.
                        </div>

                        <template v-else>
                            <div class="rounded-xl border border-primary-200 bg-primary-50/60 p-4 dark:border-primary-900 dark:bg-primary-950/30">
                                <p class="text-xs font-bold text-surface-900 dark:text-white">حوّل المبلغ إلى رقم فودافون كاش التالي:</p>
                                <p class="mt-2 font-mono text-xl font-black tracking-wide text-primary-700 dark:text-primary-300" dir="ltr">{{ vodafoneCashNumber }}</p>
                            </div>

                            <div>
                                <label for="sender-phone" class="input-label">رقم الهاتف الذي حوّلت منه</label>
                                <input
                                    id="sender-phone"
                                    v-model="senderPhone"
                                    type="tel"
                                    inputmode="tel"
                                    dir="ltr"
                                    class="input font-mono"
                                    placeholder="01012345678"
                                    minlength="11"
                                    maxlength="20"
                                    pattern="(?:\+20|0020|0)1[0-9]{9}"
                                    required
                                />
                                <p class="input-hint">اكتب رقم محفظة فودافون كاش المُرسِلة بصيغة 01012345678.</p>
                            </div>

                            <div>
                                <label for="receipt" class="input-label">إثبات التحويل</label>
                                <input
                                    id="receipt"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp,application/pdf"
                                    required
                                    class="input"
                                    @change="onReceiptChange"
                                />
                                <p class="input-hint">ارفع صورة واضحة أو ملف PDF، بحد أقصى 8 ميجابايت.</p>
                            </div>
                        </template>
                    </div>

                    <!-- Coupon -->
                    <div class="card p-5">
                        <h2 class="font-bold text-sm text-surface-900 dark:text-white mb-3">كود خصم</h2>

                        <div class="flex gap-2">
                            <input
                                v-model="couponCode"
                                type="text"
                                class="input flex-1"
                                minlength="3"
                                maxlength="50"
                                placeholder="أدخل الكود"
                                @keyup.enter="applyCoupon"
                            />
                            <button
                                type="button"
                                class="btn-outline btn-sm shrink-0"
                                :disabled="couponState.status === 'checking'"
                                @click="applyCoupon"
                            >
                                {{ couponState.status === 'checking' ? '...' : 'تطبيق' }}
                            </button>
                        </div>

                        <p
                            v-if="couponState.message"
                            class="text-xs mt-2"
                            :class="couponState.status === 'valid' ? 'text-green-600' : 'text-red-500'"
                        >
                            {{ couponState.message }}
                        </p>
                    </div>
                </div>

                <!-- ── Summary ─────────────────────────────────────── -->
                <aside class="card p-5 h-fit lg:sticky lg:top-24">
                    <h2 class="font-bold text-sm text-surface-900 dark:text-white mb-4">ملخص الاشتراك</h2>

                    <div class="flex items-start gap-3 mb-4">
                        <div class="avatar-md">
                            <img v-if="subscription.teacher?.avatar" :src="subscription.teacher.avatar" :alt="subscription.teacher.name" class="w-full h-full object-cover" />
                            <span v-else class="text-primary-700 dark:text-primary-300 font-bold">
                                {{ subscription.teacher?.name?.charAt(0) }}
                            </span>
                        </div>

                        <div class="min-w-0">
                            <div class="font-bold text-sm text-surface-900 dark:text-white">
                                {{ subscription.subject?.name }}
                            </div>
                            <div class="text-xs text-surface-500 dark:text-surface-400">
                                {{ subscription.teacher?.name }}
                            </div>
                            <div class="text-[11px] text-surface-400 mt-0.5">
                                {{ subscription.type === 'private' ? 'حصص خاصة' : subscription.group?.name }}
                            </div>
                        </div>
                    </div>

                    <p v-if="scheduleText" class="text-[11px] text-surface-500 dark:text-surface-400 flex items-center gap-1.5 mb-4">
                        <Icon name="calendar" class="w-3.5 h-3.5" />
                        {{ scheduleText }}
                    </p>

                    <!-- A parent may be paying on the student's behalf. -->
                    <div v-if="subscription.student" class="text-[11px] text-surface-400 mb-4">
                        الطالب: <span class="font-bold text-surface-600 dark:text-surface-300">{{ subscription.student.name }}</span>
                    </div>

                    <div class="space-y-2 py-4 border-y border-surface-100 dark:border-surface-800 text-sm">
                        <div class="flex justify-between text-surface-600 dark:text-surface-300">
                            <span>اشتراك شهر</span>
                            <span>{{ formatQAR(basePrice) }}</span>
                        </div>
                        <div v-if="discount > 0" class="flex justify-between text-green-600">
                            <span>الخصم</span>
                            <span>-{{ formatQAR(discount) }}</span>
                        </div>
                    </div>

                    <div class="flex justify-between items-center py-4">
                        <span class="font-bold text-surface-900 dark:text-white">الإجمالي</span>
                        <span class="text-xl font-black text-primary-700 dark:text-primary-400">
                            {{ formatQAR(finalPrice) }}
                        </span>
                    </div>

                    <p class="text-[11px] text-surface-400 mb-4">
                        الفترة: {{ subscription.period_start }} إلى {{ subscription.period_end }}
                    </p>

                    <button
                        type="button"
                        class="btn-primary w-full justify-center"
                        :disabled="processing || !vodafoneCashNumber"
                        @click="submit"
                    >
                        {{ processing ? 'جارٍ رفع الإثبات...' : 'إرسال إثبات التحويل' }}
                    </button>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
