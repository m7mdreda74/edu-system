<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, Link, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

const page = usePage();

const form = useForm({
    name:                  '',
    email:                 '',
    phone:                 '',
    parent_phone:          '',
    password:              '',
    password_confirmation: '',
    grade_level:           '',
    role:                  'student',
});

const emailPrefix = ref('');

const platformEmail = (prefix) => `${String(prefix ?? '').trim().toLowerCase()}@altafawwuq.com`;

const selectedStage = ref('secondary');
const selectedTrack = ref(''); // only relevant for grade 11/12 secondary

/** Grades that exist in the current stage */
const stageGrades = computed(() =>
    page.props.grade_levels?.filter(g => g.stage === selectedStage.value && g.key !== 'all') || []
);

/** Does the current stage have any track-ed grades (11/12)? */
const hasTrackedGrades = computed(() =>
    selectedStage.value === 'secondary' && stageGrades.value.some(g => g.track)
);

/** Available tracks for the current stage */
const availableTracks = computed(() => {
    if (!hasTrackedGrades.value) return [];
    const tracks = [...new Set(stageGrades.value.filter(g => g.track).map(g => g.track))];
    return tracks.map(t => ({
        key: t,
        label: {
            science:    'المسار العلمي',
            arts:       'مسار الآداب والإنسانيات',
            technology: 'المسار التكنولوجي',
        }[t] || t,
    }));
});

/** Grades visible after stage + track filter */
const filteredGradeLevels = computed(() => {
    let grades = stageGrades.value;

    if (selectedStage.value === 'secondary' && selectedTrack.value) {
        // Show common grade 10 (no track) + grades matching the selected track
        grades = grades.filter(g => !g.track || g.track === selectedTrack.value);
    }

    return grades;
});

const onStageChange = () => {
    selectedTrack.value = '';
    const firstGrade = filteredGradeLevels.value[0];
    form.grade_level = firstGrade ? firstGrade.key : '';
};

const onTrackChange = () => {
    const firstGrade = filteredGradeLevels.value[0];
    form.grade_level = firstGrade ? firstGrade.key : '';
};

// Initialize
onStageChange();

watch(() => form.role, (newRole) => {
    if (newRole !== 'student') {
        form.grade_level = null;
        form.parent_phone = '';
    } else {
        onStageChange();
    }
});

const submit = () => {
    form.email = platformEmail(emailPrefix.value);
    form.post(route('register'));
};
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
                    
                    <!-- Account Type -->
                    <div class="space-y-2">
                        <p class="block text-xs font-bold text-white/95 mr-3">نوع الحساب</p>
                        <div class="grid grid-cols-2 gap-3">
                            <button
                                type="button"
                                class="px-4 py-3 rounded-2xl text-xs font-bold transition-all border"
                                :class="form.role === 'student'
                                    ? 'bg-accent-500 text-white border-accent-500 shadow-lg'
                                    : 'bg-white/10 text-white/80 border-white/20 hover:bg-white/20'"
                                @click="form.role = 'student'"
                            >حساب طالب</button>
                            <button
                                type="button"
                                class="px-4 py-3 rounded-2xl text-xs font-bold transition-all border"
                                :class="form.role === 'parent'
                                    ? 'bg-accent-500 text-white border-accent-500 shadow-lg'
                                    : 'bg-white/10 text-white/80 border-white/20 hover:bg-white/20'"
                                @click="form.role = 'parent'"
                            >حساب ولي أمر</button>
                        </div>
                        <p class="text-white/60 text-[11px] mr-3">
                            ولي الأمر ينشئ حسابه أولًا، ثم يربط حساب الطالب من لوحة المتابعة باستخدام رقم جوال الطالب.
                        </p>
                        <p v-if="form.errors.role" class="text-red-400 text-xs mr-3 mt-1">{{ form.errors.role }}</p>
                    </div>

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
                        <div
                            dir="ltr"
                            class="flex items-center overflow-hidden rounded-full bg-white text-surface-900 border border-transparent focus-within:ring-4 focus-within:ring-primary-500/40 shadow-inner transition-all"
                            :class="{ 'ring-2 ring-red-500': form.errors.email }"
                        >
                            <input
                                id="reg-email"
                                v-model="emailPrefix"
                                type="text"
                                class="min-w-0 flex-1 px-6 py-3 bg-transparent border-0 focus:outline-none focus:ring-0 placeholder-surface-400 text-xs font-semibold"
                                placeholder="username"
                                autocomplete="username"
                                required
                            />
                            <span class="shrink-0 pe-5 text-xs font-bold text-primary-700">@altafawwuq.com</span>
                        </div>
                        <p class="text-white/60 text-[11px] mr-3">نطاق البريد ثابت لحسابات المنصة.</p>
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

                    <!-- Parent Phone Input -->
                    <div v-if="form.role === 'student'" class="space-y-1.5">
                        <label class="block text-xs font-bold text-white/95 mr-3" for="reg-parent-phone">
                            رقم ولي الأمر المرتبط بالمنصة <span class="text-red-400">*</span>
                        </label>
                        <input
                            id="reg-parent-phone"
                            v-model="form.parent_phone"
                            type="text"
                            inputmode="tel"
                            class="w-full px-6 py-3 bg-white text-surface-900 rounded-full border border-transparent focus:outline-none focus:ring-4 focus:ring-primary-500/40 shadow-inner placeholder-surface-400 text-xs font-semibold transition-all"
                            :class="{ 'ring-2 ring-red-500': form.errors.parent_phone }"
                            placeholder="يرجى تسجيل رقم ولي الأمر المربوط بالمنصة"
                            required
                        />
                        <p class="text-white/60 text-[11px] mr-3">يجب أن يكون ولي الأمر قد أنشأ حسابه على المنصة بهذا الرقم.</p>
                        <p v-if="form.errors.parent_phone" class="text-red-400 text-xs mr-3 mt-1">{{ form.errors.parent_phone }}</p>
                    </div>

                    <!-- Stage + Grade Level + Track -->
                    <div v-if="form.role === 'student'" class="space-y-4">
                        <!-- Row: Stage + Grade -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-white/95 mr-3" for="reg-stage">المرحلة الدراسية</label>
                                <div class="relative">
                                    <select id="reg-stage" v-model="selectedStage" class="w-full px-6 py-3 bg-white text-surface-900 rounded-full border border-transparent focus:outline-none focus:ring-4 focus:ring-primary-500/40 shadow-inner text-xs font-semibold transition-all appearance-none bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2020%2020%22%20fill%3D%22none%22%3E%3Cpath%20d%3D%22M7%209l3%203%203-3%22%20stroke%3D%22%236b7280%22%20stroke-width%3D%221.5%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%2F%3E%3C%2Fsvg%3E')] bg-[length:1.25rem] bg-[position:left_1rem_center] bg-no-repeat" :disabled="form.processing" @change="onStageChange">
                                        <option value="primary">المرحلة الابتدائية</option>
                                        <option value="preparatory">المرحلة الإعدادية</option>
                                        <option value="secondary">المرحلة الثانوية</option>
                                    </select>
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-white/95 mr-3" for="reg-grade">الصف الدراسي</label>
                                <div class="relative">
                                    <select id="reg-grade" v-model="form.grade_level" class="w-full px-6 py-3 bg-white text-surface-900 rounded-full border border-transparent focus:outline-none focus:ring-4 focus:ring-primary-500/40 shadow-inner text-xs font-semibold transition-all appearance-none bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2020%2020%22%20fill%3D%22none%22%3E%3Cpath%20d%3D%22M7%209l3%203%203-3%22%20stroke%3D%22%236b7280%22%20stroke-width%3D%221.5%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%2F%3E%3C%2Fsvg%3E')] bg-[length:1.25rem] bg-[position:left_1rem_center] bg-no-repeat" :class="{ 'ring-2 ring-red-500': form.errors.grade_level }" :disabled="!filteredGradeLevels.length || form.processing" required>
                                        <option value="" disabled>اختر الصف...</option>
                                        <option v-for="gl in filteredGradeLevels" :key="gl.key" :value="gl.key">
                                            {{ gl.name }}
                                        </option>
                                    </select>
                                </div>
                                <p v-if="form.errors.grade_level" class="text-red-400 text-xs mr-3 mt-1">{{ form.errors.grade_level }}</p>
                                <p v-else-if="!filteredGradeLevels.length" class="text-amber-300 text-xs mr-3 mt-1">لا توجد صفوف متاحة لهذه المرحلة حالياً.</p>
                            </div>
                        </div>

                        <!-- Track selector (secondary stage + has tracks) -->
                        <Transition
                            enter-active-class="transition-all duration-300 ease-out"
                            enter-from-class="opacity-0 -translate-y-2"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="transition-all duration-200"
                            leave-from-class="opacity-100"
                            leave-to-class="opacity-0"
                        >
                            <div v-if="hasTrackedGrades" class="space-y-1.5">
                                <label class="block text-xs font-bold text-white/95 mr-3">
                                    المسار الدراسي <span class="text-white/50 font-normal">(اختياري للصف العاشر المشترك)</span>
                                </label>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        @click="selectedTrack = ''; onTrackChange()"
                                        class="px-4 py-2 rounded-full text-xs font-bold transition-all border"
                                        :class="selectedTrack === ''
                                            ? 'bg-white text-primary-800 border-white'
                                            : 'bg-white/10 text-white/80 border-white/20 hover:bg-white/20'"
                                    >كل المسارات</button>
                                    <button
                                        v-for="track in availableTracks"
                                        :key="track.key"
                                        type="button"
                                        @click="selectedTrack = track.key; onTrackChange()"
                                        class="px-4 py-2 rounded-full text-xs font-bold transition-all border"
                                        :class="selectedTrack === track.key
                                            ? 'bg-accent-500 text-white border-accent-500'
                                            : 'bg-white/10 text-white/80 border-white/20 hover:bg-white/20'"
                                    >{{ track.label }}</button>
                                </div>
                            </div>
                        </Transition>
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
