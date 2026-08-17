<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

defineProps({
    canResetPassword: { type: Boolean },
    status:           { type: String, default: null },
});

const form = useForm({
    login_field: '',
    password:    '',
    remember:    false,
});

const submit = () => form.post(route('login'), { onFinish: () => form.reset('password') });
</script>

<template>
    <div class="min-h-screen flex bg-gradient-to-br from-primary-900 via-primary-800 to-primary-950 text-white" dir="rtl" lang="ar">
        
        <!-- ── Left: Premium Decorative Panel ── -->
        <div class="hidden lg:flex flex-col justify-between w-[32%] bg-gradient-to-b from-primary-950 via-primary-900 to-surface-950 border-e border-white/10 p-12 shrink-0 overflow-hidden select-none relative">
            <!-- Decorative Glowing Orbs -->
            <div class="absolute -top-12 -left-12 w-64 h-64 rounded-full bg-accent-500/10 blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-12 -right-12 w-64 h-64 rounded-full bg-primary-500/20 blur-3xl pointer-events-none"></div>
            
            <!-- Branding Header -->
            <div class="relative z-10 flex flex-col items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-white flex items-center justify-center shadow-lg">
                    <span class="text-primary-800 font-black text-xl">ت</span>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-white leading-tight">منصة التفوق</h2>
                    <p class="text-white/60 text-xs mt-1">شريكك الأكاديمي للدرجات الكاملة</p>
                </div>
            </div>

            <!-- Central Illustration -->
            <div class="relative z-10 my-auto flex flex-col items-center text-center">
                <img src="/images/auth-sidebar.png" alt="منصة التفوق" class="w-full max-w-[260px] rounded-2xl shadow-2xl border border-white/10 mb-6 hover:scale-105 transition-transform duration-500" />
                <blockquote class="text-white/90 text-sm font-medium leading-relaxed max-w-xs">
                    "طريقك نحو القمة يبدأ بخطوة.. منصة التفوق شريكك للوصول للدرجات الكاملة."
                </blockquote>
            </div>

            <!-- Footer Meta -->
            <div class="relative z-10 flex items-center justify-between text-white/40 text-[10px]">
                <span>© 2026 منصة التفوق</span>
                <span>جميع الحقوق محفوظة</span>
            </div>
        </div>

        <!-- ── Right: Main Auth Form ── -->
        <div class="flex-1 flex flex-col items-center justify-center px-6 py-12 relative overflow-y-auto">
            
            <div class="w-full max-w-md space-y-8">
                
                <!-- Logo & Brand Header (Mobile/Tablet only) -->
                <div class="flex lg:hidden flex-col items-center text-center">
                    <Link :href="route('home')" class="inline-flex flex-col items-center gap-3 group mb-4">
                        <div class="w-16 h-16 rounded-2xl bg-white flex items-center justify-center shadow-2xl group-hover:scale-105 transition-transform duration-300">
                            <span class="text-primary-800 font-black text-3xl">ت</span>
                        </div>
                        <span class="text-3xl font-black text-white tracking-wide">منصة التفوق</span>
                    </Link>
                    <p class="text-white/70 text-sm">سجّل دخولك للمتابعة في رحلة التعلم</p>
                </div>

                <!-- Status Message -->
                <div v-if="status" class="bg-emerald-600/30 border border-emerald-500/50 text-emerald-200 px-4 py-3 rounded-2xl text-xs text-center">
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="space-y-6 bg-white/5 backdrop-blur-md p-8 rounded-3xl border border-white/10 shadow-xl">
                    
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
                                type="password"
                                class="w-full px-6 py-3.5 bg-white text-surface-900 rounded-full border border-transparent focus:outline-none focus:ring-4 focus:ring-primary-500/40 shadow-inner placeholder-surface-400 text-sm font-semibold transition-all"
                                :class="{ 'ring-2 ring-red-500': form.errors.password }"
                                placeholder="••••••••"
                                autocomplete="current-password"
                                required
                            />
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
                        <!-- Submit: Login Button -->
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full py-3.5 bg-surface-950 hover:bg-surface-900 text-white rounded-full font-bold text-sm shadow-lg transition-all duration-200 active:scale-[0.98] flex items-center justify-center gap-2"
                            :class="{ 'opacity-65 cursor-not-allowed': form.processing }"
                            id="login-submit-btn"
                        >
                            <span v-if="form.processing" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            <span>{{ form.processing ? 'جاري تسجيل الدخول...' : 'تسجيل الدخول' }}</span>
                        </button>

                        <!-- Register Button -->
                        <Link
                            :href="route('register')"
                            class="w-full py-3.5 bg-white hover:bg-surface-50 text-primary-900 rounded-full font-bold text-sm shadow-md transition-all duration-200 active:scale-[0.98] flex items-center justify-center"
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
</template>
