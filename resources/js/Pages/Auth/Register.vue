<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, Link, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

const page = usePage();

const form = useForm({
    name:                  '',
    email:                 '',
    phone:                 '',
    password:              '',
    password_confirmation: '',
    grade_level:           '',
    role:                  'student',
});

const selectedStage = ref('secondary');

const filteredGradeLevels = computed(() => {
    return page.props.grade_levels?.filter(g => g.stage === selectedStage.value && g.key !== 'all') || [];
});

const onStageChange = () => {
    const firstGrade = filteredGradeLevels.value[0];
    form.grade_level = firstGrade ? firstGrade.key : '';
};

// Initialize
onStageChange();

watch(() => form.role, (newRole) => {
    if (newRole !== 'student') {
        form.grade_level = null;
    } else {
        onStageChange();
    }
});

const submit = () => form.post(route('register'));
</script>

<template>
    <div class="min-h-screen flex bg-gradient-to-br from-primary-900 via-primary-850 to-primary-950 text-white" dir="rtl" lang="ar">
        
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
            
            <div class="w-full max-w-lg space-y-6 py-8">
                
                <!-- Logo & Brand Header (Mobile/Tablet only) -->
                <div class="flex lg:hidden flex-col items-center text-center">
                    <Link :href="route('home')" class="inline-flex flex-col items-center gap-3 group mb-2">
                        <div class="w-14 h-14 rounded-2xl bg-white flex items-center justify-center shadow-2xl group-hover:scale-105 transition-transform duration-300">
                            <span class="text-primary-800 font-black text-2xl">ت</span>
                        </div>
                        <span class="text-2xl font-black text-white tracking-wide">منصة التفوق</span>
                    </Link>
                    <p class="text-white/70 text-sm">ابدأ رحلتك التعليمية معنا مجاناً</p>
                </div>

                <form @submit.prevent="submit" class="space-y-4 bg-white/5 backdrop-blur-md p-8 rounded-3xl border border-white/10 shadow-xl">
                    
                    <h1 class="text-xl font-bold text-center text-white mb-2">إنشاء حساب جديد</h1>
                    
                    <!-- Name Input -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-white/95 mr-3" for="reg-name">
                            الاسم الكامل <span class="text-red-400">*</span>
                        </label>
                        <input
                            id="reg-name"
                            v-model="form.name"
                            type="text"
                            class="w-full px-6 py-3 bg-white text-surface-900 rounded-full border border-transparent focus:outline-none focus:ring-4 focus:ring-primary-500/40 shadow-inner placeholder-surface-400 text-xs font-semibold transition-all"
                            :class="{ 'ring-2 ring-red-500': form.errors.name }"
                            placeholder="مثال: محمد أحمد"
                            autocomplete="name"
                            required
                            autofocus
                        />
                        <p v-if="form.errors.name" class="text-red-400 text-xs mr-3 mt-1">{{ form.errors.name }}</p>
                    </div>

                    <!-- Email Input -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-white/95 mr-3" for="reg-email">
                            البريد الإلكتروني <span class="text-red-400">*</span>
                        </label>
                        <input
                            id="reg-email"
                            v-model="form.email"
                            type="email"
                            class="w-full px-6 py-3 bg-white text-surface-900 rounded-full border border-transparent focus:outline-none focus:ring-4 focus:ring-primary-500/40 shadow-inner placeholder-surface-400 text-xs font-semibold transition-all"
                            :class="{ 'ring-2 ring-red-500': form.errors.email }"
                            placeholder="example@email.com"
                            required
                        />
                        <p v-if="form.errors.email" class="text-red-400 text-xs mr-3 mt-1">{{ form.errors.email }}</p>
                    </div>

                    <!-- Phone Input -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-white/95 mr-3" for="reg-phone">
                            رقم الهاتف / الجوال <span class="text-red-400">*</span>
                        </label>
                        <input
                            id="reg-phone"
                            v-model="form.phone"
                            type="text"
                            class="w-full px-6 py-3 bg-white text-surface-900 rounded-full border border-transparent focus:outline-none focus:ring-4 focus:ring-primary-500/40 shadow-inner placeholder-surface-400 text-xs font-semibold transition-all"
                            :class="{ 'ring-2 ring-red-500': form.errors.phone }"
                            placeholder="مثال: +97433554858"
                            required
                        />
                        <p v-if="form.errors.phone" class="text-red-400 text-xs mr-3 mt-1">{{ form.errors.phone }}</p>
                    </div>

                    <!-- Stage + Grade Level in row -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-white/95 mr-3" for="reg-stage">المرحلة الدراسية</label>
                            <div class="relative">
                                <select id="reg-stage" v-model="selectedStage" class="w-full px-6 py-3 bg-white text-surface-900 rounded-full border border-transparent focus:outline-none focus:ring-4 focus:ring-primary-500/40 shadow-inner text-xs font-semibold transition-all appearance-none bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2020%2020%22%20fill%3D%22none%22%3E%3Cpath%20d%3D%22M7%209l3%203%203-3%22%20stroke%3D%22%236b7280%22%20stroke-width%3D%221.5%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%2F%3E%3C%2Fsvg%3E')] bg-[length:1.25rem] bg-[position:left_1rem_center] bg-no-repeat" @change="onStageChange">
                                    <option value="primary">المرحلة الابتدائية</option>
                                    <option value="preparatory">المرحلة الإعدادية</option>
                                    <option value="secondary">المرحلة الثانوية</option>
                                </select>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-white/95 mr-3" for="reg-grade">الصف الدراسي</label>
                            <div class="relative">
                                <select id="reg-grade" v-model="form.grade_level" class="w-full px-6 py-3 bg-white text-surface-900 rounded-full border border-transparent focus:outline-none focus:ring-4 focus:ring-primary-500/40 shadow-inner text-xs font-semibold transition-all appearance-none bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2020%2020%22%20fill%3D%22none%22%3E%3Cpath%20d%3D%22M7%209l3%203%203-3%22%20stroke%3D%22%236b7280%22%20stroke-width%3D%221.5%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%2F%3E%3C%2Fsvg%3E')] bg-[length:1.25rem] bg-[position:left_1rem_center] bg-no-repeat" required>
                                    <option value="" disabled>اختر الصف...</option>
                                    <option v-for="gl in filteredGradeLevels" :key="gl.key" :value="gl.key">
                                        {{ gl.name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Password Block -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Password -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-white/95 mr-3" for="reg-password">
                                كلمة المرور <span class="text-red-400">*</span>
                            </label>
                            <input
                                id="reg-password"
                                v-model="form.password"
                                type="password"
                                class="w-full px-6 py-3 bg-white text-surface-900 rounded-full border border-transparent focus:outline-none focus:ring-4 focus:ring-primary-500/40 shadow-inner placeholder-surface-400 text-xs font-semibold transition-all"
                                :class="{ 'ring-2 ring-red-500': form.errors.password }"
                                placeholder="8 أحرف على الأقل"
                                autocomplete="new-password"
                                required
                            />
                            <p v-if="form.errors.password" class="text-red-400 text-xs mr-3 mt-1">{{ form.errors.password }}</p>
                        </div>

                        <!-- Confirm Password -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-white/95 mr-3" for="reg-confirm">
                                تأكيد كلمة المرور <span class="text-red-400">*</span>
                            </label>
                            <input
                                id="reg-confirm"
                                v-model="form.password_confirmation"
                                type="password"
                                class="w-full px-6 py-3 bg-white text-surface-900 rounded-full border border-transparent focus:outline-none focus:ring-4 focus:ring-primary-500/40 shadow-inner placeholder-surface-400 text-xs font-semibold transition-all"
                                placeholder="••••••••"
                                autocomplete="new-password"
                                required
                            />
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="space-y-3 pt-4">
                        <!-- Submit Button -->
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full py-3.5 bg-surface-950 hover:bg-surface-900 text-white rounded-full font-bold text-sm shadow-lg transition-all duration-200 active:scale-98 flex items-center justify-center gap-2"
                            :class="{ 'opacity-65 cursor-not-allowed': form.processing }"
                            id="register-submit-btn"
                        >
                            <span v-if="form.processing" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            <span>{{ form.processing ? 'جاري إنشاء الحساب...' : 'إنشاء الحساب مجاناً' }}</span>
                        </button>

                        <!-- Login Link Button -->
                        <Link
                            :href="route('login')"
                            class="w-full py-3.5 bg-white hover:bg-surface-50 text-primary-900 rounded-full font-bold text-sm shadow-md transition-all duration-200 active:scale-98 flex items-center justify-center"
                        >
                            تسجيل الدخول
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
