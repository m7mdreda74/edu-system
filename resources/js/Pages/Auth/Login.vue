<script setup>
import { useForm, Link } from '@inertiajs/vue3';

defineProps({
    canResetPassword: { type: Boolean },
    status:           { type: String, default: null },
});

const form = useForm({
    email:    '',
    password: '',
    remember: false,
});

const submit = () => form.post(route('login'), { onFinish: () => form.reset('password') });
</script>

<template>
    <div class="min-h-screen flex" dir="rtl" lang="ar">
        <!-- ── Left: Auth Form ──────────────────────────────────── -->
        <div class="flex-1 flex items-center justify-center px-6 py-12 bg-white dark:bg-surface-950">
            <div class="w-full max-w-sm">

                <!-- Logo -->
                <Link :href="route('home')" class="flex items-center gap-2 mb-10 group">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-primary-700
                                flex items-center justify-center shadow-glow-primary
                                group-hover:scale-110 transition-transform duration-200">
                        <span class="text-white font-black text-lg">ت</span>
                    </div>
                    <span class="text-2xl font-black text-gradient-primary">منصة التفوق</span>
                </Link>

                <h1 class="text-3xl font-black text-surface-900 dark:text-white mb-2">
                    أهلاً بعودتك
                </h1>
                <p class="text-surface-500 dark:text-surface-400 mb-8">
                    سجّل دخولك للمتابعة في رحلة التعلم
                </p>

                <!-- Status Message -->
                <div v-if="status" class="alert-success mb-6">
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="space-y-5">

                    <!-- Email -->
                    <div>
                        <label class="input-label" for="email">البريد الإلكتروني</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="input"
                            :class="{ 'border-red-500 focus:ring-red-500': form.errors.email }"
                            placeholder="example@email.com"
                            autocomplete="username"
                            required
                            autofocus
                        />
                        <p v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</p>
                    </div>

                    <!-- Password -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="input-label mb-0" for="password">كلمة المرور</label>
                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400"
                            >
                                نسيت كلمة المرور؟
                            </Link>
                        </div>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            class="input"
                            :class="{ 'border-red-500 focus:ring-red-500': form.errors.password }"
                            placeholder="••••••••"
                            autocomplete="current-password"
                            required
                        />
                        <p v-if="form.errors.password" class="text-red-500 text-xs mt-1">{{ form.errors.password }}</p>
                    </div>

                    <!-- Remember -->
                    <div class="flex items-center gap-2">
                        <input id="remember" v-model="form.remember" type="checkbox"
                               class="w-4 h-4 text-primary-600 rounded" />
                        <label for="remember" class="text-sm text-surface-600 dark:text-surface-400 cursor-pointer">
                            تذكّرني
                        </label>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="btn-primary w-full btn-lg"
                        :class="{ 'opacity-60': form.processing }"
                        id="login-submit-btn"
                    >
                        <span v-if="form.processing" class="flex items-center justify-center gap-2">
                            <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            <span>جاري تسجيل الدخول...</span>
                        </span>
                        <span v-else class="flex items-center justify-center gap-2">
                            <span>تسجيل الدخول</span>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </span>
                    </button>

                    <p class="text-center text-sm text-surface-500 dark:text-surface-400">
                        ليس لديك حساب؟
                        <Link :href="route('register')"
                              class="text-primary-600 dark:text-primary-400 font-semibold hover:underline">
                            إنشاء حساب مجاني
                        </Link>
                    </p>
                </form>
            </div>
        </div>

        <!-- ── Right: Hero Graphic ──────────────────────────────── -->
        <div class="hidden lg:flex flex-1 hero-gradient items-center justify-center relative overflow-hidden">
            <div class="absolute inset-0 opacity-10">
                <div class="absolute top-20 start-20 w-80 h-80 rounded-full bg-white/30 blur-3xl"></div>
                <div class="absolute bottom-20 end-20 w-60 h-60 rounded-full bg-accent-400/40 blur-3xl"></div>
            </div>
            <div class="relative text-center text-white px-12 flex flex-col items-center">
                <div class="mb-6 text-white/90">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-20 h-20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.62 48.62 0 0112 20.904a48.62 48.62 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 019.897 5.837c-.896.3-1.783.57-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M12 13.489v5.526" />
                    </svg>
                </div>
                <h2 class="text-4xl font-black mb-4">طريقك للتفوق<br>يبدأ من هنا</h2>
                <p class="text-white/75 text-lg leading-relaxed">
                    انضم لآلاف الطلاب الذين<br>حققوا نتائج استثنائية
                </p>
                <div class="flex justify-center gap-8 mt-10">
                    <div v-for="stat in [
                        { value: '+500', label: 'طالب' },
                        { value: '+50',  label: 'كورس' },
                        { value: '98%',  label: 'رضا' },
                    ]" :key="stat.label" class="text-center">
                        <div class="text-3xl font-black">{{ stat.value }}</div>
                        <div class="text-white/60 text-sm">{{ stat.label }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
