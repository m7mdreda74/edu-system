<script setup>
import { ref, onUnmounted } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import Icon from '@/Components/Icon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    canResetPassword: { type: Boolean },
    otpAvailable:     { type: Boolean, default: true },
    status:           { type: String, default: null },
});

// Mode: 'otp' (default for parents/students in Qatar) or 'password'
const authMode = ref(props.otpAvailable !== false ? 'otp' : 'password');
const showPassword = ref(false);

// Password Form
const form = useForm({
    login_field: '',
    password:    '',
    remember:    false,
});

const submitPassword = () => form.post(route('login'), { onFinish: () => form.reset('password') });

// OTP Form State
const otpStep = ref(1); // 1 = enter phone, 2 = enter code
const otpPhone = ref('');
const otpCode = ref('');
const otpRole = ref('parent');
const otpLoading = ref(false);
const otpError = ref('');
const otpSuccessMsg = ref('');
const devCode = ref('');
const resendSeconds = ref(0);
let timerInterval = null;

function startTimer() {
    resendSeconds.value = 60;
    clearInterval(timerInterval);
    timerInterval = setInterval(() => {
        if (resendSeconds.value > 0) {
            resendSeconds.value--;
        } else {
            clearInterval(timerInterval);
        }
    }, 1000);
}

onUnmounted(() => {
    clearInterval(timerInterval);
});

function normalizeLocalPhoneDigits(raw) {
    let digits = String(raw || '').replace(/\D+/g, '');
    if (digits.startsWith('974') && digits.length > 8) {
        digits = digits.slice(3);
    }
    return digits;
}

async function handleSendOtp() {
    otpError.value = '';
    otpSuccessMsg.value = '';
    
    const digits = normalizeLocalPhoneDigits(otpPhone.value);
    if (digits.length !== 8) {
        otpError.value = 'رقم الجوال يجب أن يكون 8 أرقام.';
        return;
    }

    otpLoading.value = true;
    try {
        const response = await axios.post(route('otp.send'), { phone: `+974${digits}` });
        if (response.data.success) {
            otpStep.value = 2;
            otpSuccessMsg.value = response.data.message || 'تم إرسال رمز التحقق.';
            if (response.data.dev_code) {
                devCode.value = response.data.dev_code;
                otpCode.value = response.data.dev_code;
            }
            startTimer();
        }
    } catch (err) {
        otpError.value = err.response?.data?.message || 'رقم الجوال يجب أن يكون 8 أرقام.';
    } finally {
        otpLoading.value = false;
    }
}

async function handleVerifyOtp() {
    otpError.value = '';
    const code = otpCode.value.trim();
    if (!code || code.length < 4) {
        otpError.value = 'يرجى إدخال رمز التحقق المكون من 6 أرقام.';
        return;
    }

    const digits = normalizeLocalPhoneDigits(otpPhone.value);

    otpLoading.value = true;
    try {
        const response = await axios.post(route('otp.verify'), {
            phone: `+974${digits}`,
            code: code,
            role: otpRole.value,
        });

        if (response.data.success && response.data.redirect_url) {
            router.visit(response.data.redirect_url);
        }
    } catch (err) {
        otpError.value = err.response?.data?.message || 'رمز التحقق غير صحيح، يرجى التأكد وإعادة المحاولة.';
    } finally {
        otpLoading.value = false;
    }
}

function resetToPhoneStep() {
    otpStep.value = 1;
    otpCode.value = '';
    otpError.value = '';
    devCode.value = '';
}
</script>

<template>
    <AppLayout>
        <div class="min-h-screen flex bg-gradient-to-br from-primary-950 via-primary-900 to-surface-950 text-white" dir="rtl" lang="ar">
        
        <!-- ── Left: Premium Decorative Panel ── -->
        <div class="hidden lg:flex flex-col justify-between w-[34%] bg-gradient-to-b from-primary-950 via-primary-900 to-surface-950 border-e border-white/10 p-12 shrink-0 overflow-hidden select-none relative">
            <!-- Decorative Glowing Orbs -->
            <div class="absolute -top-12 -left-12 w-64 h-64 rounded-full bg-accent-500/15 blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-12 -right-12 w-64 h-64 rounded-full bg-primary-500/20 blur-3xl pointer-events-none"></div>
            
            <!-- Branding Header -->
            <div class="relative z-10 flex flex-col items-start gap-4">
                <img src="/images/logo-icon.png" alt="بوابة المجد التعليمية" class="w-14 h-14 object-contain filter drop-shadow-md" />
                <div>
                    <h2 class="text-xl font-black text-white leading-tight">بوابة المجد التعليمية</h2>
                    <p class="text-accent-400 text-xs mt-1 font-semibold">بوابتك للتفوق والدرجات العالية في قطر</p>
                </div>
            </div>

            <!-- Central Highlights -->
            <div class="relative z-10 my-auto space-y-6">
                <div class="bg-white/5 backdrop-blur-md rounded-2xl p-6 border border-white/10 space-y-3">
                    <div class="flex items-center gap-3 text-accent-400 font-bold text-sm">
                        <Icon name="whatsapp" class="w-5 h-5 text-emerald-400" />
                        <span>تسجيل سلس عبر رقم الجوال</span>
                    </div>
                    <p class="text-white/80 text-xs leading-relaxed">
                        استخدم رقم الجوال أو البريد الإلكتروني مع كلمة المرور لتسجيل الدخول.
                    </p>
                </div>

                <div class="bg-white/5 backdrop-blur-md rounded-2xl p-6 border border-white/10 space-y-3">
                    <div class="flex items-center gap-3 text-accent-400 font-bold text-sm">
                        <Icon name="users" class="w-5 h-5 text-accent-400" />
                        <span>متابعة شاملة للأبناء</span>
                    </div>
                    <p class="text-white/80 text-xs leading-relaxed">
                        لوحة تحكم خاصة لولي الأمر لمتابعة الحضور المباشر، درجات الواجبات، تقارير الاختبارات، وحالة المدفوعات.
                    </p>
                </div>
            </div>

            <!-- Footer Meta -->
            <div class="relative z-10 flex items-center justify-between text-white/40 text-[10px]">
                <span>© 2026 بوابة المجد التعليمية — قطر</span>
                <span>جميع الحقوق محفوظة</span>
            </div>
        </div>

        <!-- ── Right: Main Auth Form ── -->
        <div class="flex-1 flex flex-col items-center justify-center px-4 sm:px-6 py-12 relative overflow-y-auto">
            
            <div class="w-full max-w-md space-y-6">
                
                <!-- Logo & Brand Header (Mobile/Tablet only) -->
                <div class="flex lg:hidden flex-col items-center text-center mb-2">
                    <Link :href="route('home')" class="inline-flex flex-col items-center gap-2 group mb-2">
                        <img src="/images/logo-icon.png" alt="بوابة المجد التعليمية" class="w-16 h-16 object-contain drop-shadow-xl" />
                        <span class="text-2xl font-black text-white">بوابة المجد التعليمية</span>
                    </Link>
                    <p class="text-accent-400 text-xs font-semibold">منصة المناهج القطرية والدولية الأولى</p>
                </div>

                <!-- Mode Switcher Tabs -->
                <div class="bg-white/10 p-1 rounded-2xl flex items-center border border-white/10 text-xs font-bold shadow-inner">
                    <button
                        id="tab-otp"
                        data-testid="tab-otp"
                        type="button"
                        @click="authMode = 'otp'"
                        class="flex-1 py-2.5 rounded-xl transition-all flex items-center justify-center gap-2"
                        :class="authMode === 'otp'
                            ? 'bg-gradient-to-r from-accent-500 to-accent-600 text-surface-950 font-black shadow-md'
                            : 'text-white/70 hover:text-white'"
                    >
                        <Icon name="phone" class="w-4 h-4" />
                        <span>رمز التحقق (OTP)</span>
                    </button>
                    <button
                        id="tab-password"
                        data-testid="tab-password"
                        type="button"
                        @click="authMode = 'password'"
                        class="flex-1 py-2.5 rounded-xl transition-all flex items-center justify-center gap-2"
                        :class="authMode === 'password'
                            ? 'bg-gradient-to-r from-accent-500 to-accent-600 text-surface-950 font-black shadow-md'
                            : 'text-white/70 hover:text-white'"
                    >
                        <Icon name="key" class="w-4 h-4" />
                        <span>كلمة المرور</span>
                    </button>
                </div>

                <!-- Status Message -->
                <div v-if="status" class="bg-emerald-600/30 border border-emerald-500/50 text-emerald-200 px-4 py-3 rounded-2xl text-xs text-center">
                    {{ status }}
                </div>

                <!-- ══════════════ TAB 1: OTP AUTH (MOBILE NUMBER) ══════════════ -->
                <div v-if="authMode === 'otp'" class="space-y-6 bg-white/5 backdrop-blur-md p-6 sm:p-8 rounded-3xl border border-white/10 shadow-xl">
                    
                    <div class="border-b border-white/10 pb-4 text-center">
                        <h3 class="text-lg font-black text-white">تسجيل الدخول السريع برقم الجوال</h3>
                        <p class="text-white/60 text-xs mt-1">أدخل رقم الجوال القطري (8 أرقام) لاستلام رمز التحقق المكون من 6 أرقام</p>
                    </div>

                    <!-- Step 1: Enter Phone Number -->
                    <form v-if="otpStep === 1" @submit.prevent="handleSendOtp" class="space-y-5">
                        
                        <!-- Role Choice for new signups -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-white/95 mr-2">
                                نوع الحساب
                            </label>
                            <div class="grid grid-cols-2 gap-3">
                                <label
                                    class="flex items-center justify-center gap-2 p-3 rounded-xl border cursor-pointer text-xs font-bold transition-all"
                                    :class="otpRole === 'parent'
                                        ? 'border-accent-400 bg-accent-500/20 text-accent-300'
                                        : 'border-white/10 bg-white/5 text-white/70 hover:bg-white/10'"
                                >
                                    <input type="radio" v-model="otpRole" value="parent" class="sr-only" />
                                    <Icon name="parent" class="w-4 h-4" />
                                    <span>ولي أمر</span>
                                </label>
                                <label
                                    class="flex items-center justify-center gap-2 p-3 rounded-xl border cursor-pointer text-xs font-bold transition-all"
                                    :class="otpRole === 'student'
                                        ? 'border-accent-400 bg-accent-500/20 text-accent-300'
                                        : 'border-white/10 bg-white/5 text-white/70 hover:bg-white/10'"
                                >
                                    <input type="radio" v-model="otpRole" value="student" class="sr-only" />
                                    <Icon name="student" class="w-4 h-4" />
                                    <span>طالب</span>
                                </label>
                            </div>
                        </div>

                        <!-- Phone Number Input with Fixed +974 Prefix -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-white/95 mr-2" for="otp_phone">
                                رقم الجوال القطري <span class="text-accent-400">*</span>
                            </label>
                            <div dir="ltr" class="flex items-center h-12 overflow-hidden rounded-xl bg-white border border-transparent focus-within:ring-4 focus-within:ring-accent-500/40 shadow-inner">
                                <span class="shrink-0 px-3.5 h-full flex items-center bg-surface-100 text-surface-800 font-black text-sm border-r border-surface-200 select-none">
                                    +974
                                </span>
                                <input
                                    id="otp_phone"
                                    v-model="otpPhone"
                                    type="tel"
                                    inputmode="numeric"
                                    maxlength="8"
                                    dir="ltr"
                                    class="min-w-0 flex-1 h-full px-4 bg-white text-surface-900 border-0 focus:outline-none focus:ring-0 placeholder-surface-400 text-sm font-bold tracking-wider"
                                    placeholder="55556666"
                                    required
                                    autofocus
                                />
                            </div>
                            <p v-if="otpError" class="text-red-400 text-xs font-semibold mr-2">{{ otpError }}</p>
                            <p v-else class="text-[11px] text-white/50 mr-2">أدخل 8 أرقام (مثال: 55556666)</p>
                        </div>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            :disabled="otpLoading"
                            class="w-full h-12 bg-gradient-to-r from-accent-500 to-accent-600 hover:from-accent-600 hover:to-accent-700 text-surface-950 rounded-xl font-black text-sm shadow-lg transition-all duration-200 active:scale-[0.98] flex items-center justify-center gap-2"
                            :class="{ 'opacity-65 cursor-not-allowed': otpLoading }"
                        >
                            <span v-if="otpLoading" class="w-4 h-4 border-2 border-surface-950 border-t-transparent rounded-full animate-spin"></span>
                            <span>{{ otpLoading ? 'جاري الإرسال...' : 'إرسال رمز التحقق (OTP)' }}</span>
                        </button>
                    </form>

                    <!-- Step 2: Enter Verification Code -->
                    <form v-else @submit.prevent="handleVerifyOtp" class="space-y-5">
                        <div class="text-center space-y-1">
                            <p class="text-xs text-white/80">
                                تم إرسال رمز التحقق إلى الرقم:
                                <span class="font-bold text-accent-300 font-mono" dir="ltr">+974 {{ normalizeLocalPhoneDigits(otpPhone) }}</span>
                            </p>
                            <button type="button" @click="resetToPhoneStep" class="text-[11px] text-accent-400 hover:underline">
                                تغيير رقم الجوال؟
                            </button>
                        </div>

                        <!-- Dev notice if applicable -->
                        <div v-if="devCode" class="bg-accent-500/20 border border-accent-400/40 text-accent-200 px-3 py-2 rounded-xl text-xs text-center font-mono">
                            ⚡ رمز التحقق الفوري: <b class="text-white">{{ devCode }}</b>
                        </div>

                        <!-- Code input -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-white/95 text-center" for="otp_code">
                                أدخل رمز التحقق المكون من 6 أرقام
                            </label>
                            <input
                                id="otp_code"
                                v-model="otpCode"
                                type="text"
                                maxlength="6"
                                dir="ltr"
                                class="w-full px-4 py-3.5 bg-white text-surface-950 rounded-2xl border border-transparent focus:outline-none focus:ring-4 focus:ring-accent-500/40 shadow-inner text-center font-mono text-2xl font-black tracking-[0.5em] transition-all"
                                placeholder="••••••"
                                required
                                autofocus
                            />
                        </div>

                        <!-- Error Banner -->
                        <div v-if="otpError" class="bg-red-500/20 border border-red-500/40 text-red-200 px-4 py-2.5 rounded-xl text-xs text-center">
                            {{ otpError }}
                        </div>

                        <!-- Verify Submit Button -->
                        <button
                            type="submit"
                            :disabled="otpLoading"
                            class="w-full py-3.5 bg-gradient-to-r from-accent-500 to-accent-600 hover:from-accent-600 hover:to-accent-700 text-surface-950 rounded-full font-black text-sm shadow-lg transition-all duration-200 active:scale-[0.98] flex items-center justify-center gap-2"
                            :class="{ 'opacity-65 cursor-not-allowed': otpLoading }"
                        >
                            <span v-if="otpLoading" class="w-4 h-4 border-2 border-surface-950 border-t-transparent rounded-full animate-spin"></span>
                            <span>{{ otpLoading ? 'جاري التحقق...' : 'تأكيد ودخول الحساب' }}</span>
                        </button>

                        <!-- Resend Countdown -->
                        <div class="text-center text-xs text-white/60">
                            <span v-if="resendSeconds > 0">
                                يمكنك إعادة طلب الرمز خلال <b class="text-white font-mono">{{ resendSeconds }}</b> ثانية
                            </span>
                            <button
                                v-else
                                type="button"
                                @click="handleSendOtp"
                                class="text-accent-400 hover:text-accent-300 font-bold hover:underline"
                            >
                                إعادة إرسال الرمز الآن
                            </button>
                        </div>
                    </form>

                </div>

                <!-- ══════════════ TAB 2: PASSWORD AUTH ══════════════ -->
                <form v-else @submit.prevent="submitPassword" class="space-y-6 bg-white/5 backdrop-blur-md p-8 rounded-3xl border border-white/10 shadow-xl">
                    
                    <!-- Login Field Input (Email or Phone) -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-white/95 mr-3" for="login_field">
                            البريد الإلكتروني أو رقم الهاتف <span class="text-red-400">*</span>
                        </label>
                        <div class="relative">
                            <input
                                id="login_field"
                                v-model="form.login_field"
                                type="text"
                                maxlength="255"
                                class="w-full px-6 py-3.5 bg-white text-surface-900 rounded-full border border-transparent focus:outline-none focus:ring-4 focus:ring-primary-500/40 shadow-inner placeholder-surface-400 text-sm font-semibold transition-all"
                                :class="{ 'ring-2 ring-red-500': form.errors.login_field }"
                                placeholder="أدخل البريد الإلكتروني أو رقم الهاتف..."
                                required
                                autofocus
                            />
                        </div>
                        <p v-if="form.errors.login_field" class="text-red-400 text-xs mr-3 mt-1">{{ form.errors.login_field }}</p>
                    </div>

                    <!-- Password Input -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center px-3">
                            <label class="block text-xs font-bold text-white/95" for="password">
                                كلمة المرور <span class="text-red-400">*</span>
                            </label>
                        </div>
                        <div class="relative">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                maxlength="255"
                                class="w-full px-6 py-3.5 pe-12 bg-white text-surface-900 rounded-full border border-transparent focus:outline-none focus:ring-4 focus:ring-primary-500/40 shadow-inner placeholder-surface-400 text-sm font-semibold transition-all"
                                :class="{ 'ring-2 ring-red-500': form.errors.password }"
                                placeholder="••••••••"
                                autocomplete="current-password"
                                required
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute end-4 top-1/2 -translate-y-1/2 text-surface-500 hover:text-surface-800 transition-colors p-1"
                                :title="showPassword ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'"
                                :aria-label="showPassword ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'"
                            >
                                <Icon :name="showPassword ? 'eyeOff' : 'eye'" class="w-5 h-5" />
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="text-red-400 text-xs mr-3 mt-1">{{ form.errors.password }}</p>
                    </div>

                    <!-- Remember Option -->
                    <div class="flex items-center gap-2 px-3">
                        <input id="remember" v-model="form.remember" type="checkbox"
                               class="w-4 h-4 text-primary-700 bg-white/10 border-white/20 rounded focus:ring-primary-500/40" />
                        <label for="remember" class="text-xs text-white/80 cursor-pointer">
                            تذكّرني في هذا المتصفح
                        </label>
                    </div>

                    <!-- Action Buttons -->
                    <div class="space-y-3 pt-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full h-12 bg-gradient-to-r from-accent-500 to-accent-600 hover:from-accent-600 hover:to-accent-700 text-surface-950 rounded-xl font-black text-sm shadow-lg transition-all duration-200 active:scale-[0.98] flex items-center justify-center gap-2"
                            :class="{ 'opacity-65 cursor-not-allowed': form.processing }"
                            id="login-submit-btn"
                        >
                            <span v-if="form.processing" class="w-4 h-4 border-2 border-surface-950 border-t-transparent rounded-full animate-spin"></span>
                            <span>{{ form.processing ? 'جاري تسجيل الدخول...' : 'تسجيل الدخول' }}</span>
                        </button>

                        <Link
                            :href="route('register')"
                            class="w-full h-12 bg-white hover:bg-surface-50 text-primary-900 rounded-xl font-bold text-sm shadow-md transition-all duration-200 active:scale-[0.98] flex items-center justify-center"
                        >
                            إنشاء حساب جديد
                        </Link>
                    </div>

                    <!-- Reset Password Link -->
                    <div v-if="canResetPassword" class="text-center">
                        <Link
                            :href="route('password.request')"
                            class="text-xs text-white/60 hover:text-white transition-colors"
                        >
                            نسيت كلمة المرور؟
                        </Link>
                    </div>
                </form>

            </div>
        </div>
        </div>
    </AppLayout>
</template>
