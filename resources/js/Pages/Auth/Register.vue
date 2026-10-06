<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { useForm, Link, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

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
const openDropdown = ref(null);
const activeOptionIndex = ref(0);

const stageOptions = [
    { key: 'primary', name: 'المرحلة الابتدائية' },
    { key: 'preparatory', name: 'المرحلة الإعدادية' },
    { key: 'secondary', name: 'المرحلة الثانوية' },
];

const selectedStageLabel = computed(() =>
    stageOptions.find(option => option.key === selectedStage.value)?.name || 'اختر المرحلة...'
);

const selectedGradeLabel = computed(() =>
    filteredGradeLevels.value.find(grade => grade.key === form.grade_level)?.name || 'اختر الصف...'
);

/** Grades that exist in the current stage */
const stageGrades = computed(() =>
    page.props.grade_levels?.filter(g => g.stage === selectedStage.value && g.key !== 'all') || []
);

/** Show tracks only after the student selects a tracked grade (11/12). */
const showTrackSelector = computed(() =>
    selectedStage.value === 'secondary'
    && stageGrades.value.some(g => g.key === form.grade_level && g.track)
);

/** Available tracks for the current stage */
const availableTracks = computed(() => {
    if (!stageGrades.value.some(g => g.track)) return [];
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
    const currentGrade = stageGrades.value.find(g => g.key === form.grade_level);
    const currentGradeNumber = currentGrade?.key.match(/^grade_(\d+)/)?.[1];

    // Keep the selected grade when it is still available. When switching
    // between tracks, select the same grade number in the new track instead
    // of falling back to the common grade 10 row (the first filtered item).
    const matchingGrade = filteredGradeLevels.value.find(g => g.key === form.grade_level)
        || filteredGradeLevels.value.find(g =>
            currentGradeNumber
            && g.key === `grade_${currentGradeNumber}_${selectedTrack.value}`
        )
        || filteredGradeLevels.value[0];

    form.grade_level = matchingGrade ? matchingGrade.key : '';
};

const dropdownOptions = (type) => type === 'stage' ? stageOptions : filteredGradeLevels.value;

const toggleDropdown = (type) => {
    if (form.processing || (type === 'grade' && !filteredGradeLevels.value.length)) return;

    if (openDropdown.value === type) {
        openDropdown.value = null;
        return;
    }

    const options = dropdownOptions(type);
    const selectedKey = type === 'stage' ? selectedStage.value : form.grade_level;
    const selectedIndex = options.findIndex(option => option.key === selectedKey);

    activeOptionIndex.value = selectedIndex >= 0 ? selectedIndex : 0;
    openDropdown.value = type;
};

const selectDropdownOption = (type, option) => {
    if (type === 'stage') {
        selectedStage.value = option.key;
        onStageChange();
    } else {
        form.grade_level = option.key;
    }

    openDropdown.value = null;
};

const handleDropdownKeydown = (event, type) => {
    const options = dropdownOptions(type);
    if (!options.length || form.processing) return;

    if (event.key === 'Escape') {
        event.preventDefault();
        openDropdown.value = null;
        return;
    }

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        if (openDropdown.value !== type) {
            toggleDropdown(type);
            return;
        }

        const direction = event.key === 'ArrowDown' ? 1 : -1;
        activeOptionIndex.value = (activeOptionIndex.value + direction + options.length) % options.length;
        return;
    }

    if (event.key === 'Home' || event.key === 'End') {
        event.preventDefault();
        activeOptionIndex.value = event.key === 'Home' ? 0 : options.length - 1;
        return;
    }

    if ((event.key === 'Enter' || event.key === ' ') && openDropdown.value === type) {
        event.preventDefault();
        selectDropdownOption(type, options[activeOptionIndex.value]);
    }
};

const closeDropdownOnOutsideClick = (event) => {
    if (!event.target.closest('[data-registration-dropdown]')) {
        openDropdown.value = null;
    }
};

// Initialize
onStageChange();

onMounted(() => document.addEventListener('click', closeDropdownOnOutsideClick));
onBeforeUnmount(() => document.removeEventListener('click', closeDropdownOnOutsideClick));

watch(() => form.role, (newRole) => {
    openDropdown.value = null;

    if (newRole !== 'student') {
        form.grade_level = null;
        form.parent_phone = '';
    } else {
        onStageChange();
    }
});

watch(() => form.grade_level, (gradeKey) => {
    const selectedGrade = stageGrades.value.find(g => g.key === gradeKey);

    // Grade 10 is common, so it must never carry a selected track.
    // For grades 11/12, reflect the track belonging to the selected grade.
    selectedTrack.value = selectedGrade?.track || '';
});

const submit = () => {
    form.email = platformEmail(emailPrefix.value);
    form.post(route('register'));
};
</script>

<template>
    <AppLayout>
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
                    <h2 class="text-xl font-bold text-white leading-tight">بوابة المجد التعليمية</h2>
                    <p class="text-white/60 text-xs mt-1">شريكك الأكاديمي للدرجات الكاملة</p>
                </div>
            </div>

            <!-- Central Illustration -->
            <div class="relative z-10 my-auto flex flex-col items-center text-center">
                <img src="/images/auth-sidebar.png" alt="بوابة المجد التعليمية" class="w-full max-w-[260px] rounded-2xl shadow-2xl border border-white/10 mb-6 hover:scale-105 transition-transform duration-500" />
                <blockquote class="text-white/90 text-sm font-medium leading-relaxed max-w-xs">
                    "طريقك نحو القمة يبدأ بخطوة.. بوابة المجد التعليمية شريكك للوصول للدرجات الكاملة."
                </blockquote>
            </div>

            <!-- Footer Meta -->
            <div class="relative z-10 flex items-center justify-between text-white/40 text-[10px]">
                <span>© 2026 بوابة المجد التعليمية</span>
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
                        <span class="text-2xl font-black text-white tracking-wide">بوابة المجد التعليمية</span>
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
                            maxlength="255"
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
                                maxlength="240"
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
                            inputmode="tel"
                            minlength="7"
                            maxlength="20"
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
                            minlength="7"
                            maxlength="20"
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
                                <div class="relative" data-registration-dropdown>
                                    <button
                                        id="reg-stage"
                                        type="button"
                                        class="w-full px-6 py-3 bg-white text-surface-900 rounded-full border border-transparent focus:outline-none focus:ring-4 focus:ring-primary-500/40 shadow-inner text-xs font-semibold transition-all flex items-center justify-between gap-3 text-right"
                                        :disabled="form.processing"
                                        role="combobox"
                                        aria-haspopup="listbox"
                                        :aria-expanded="openDropdown === 'stage'"
                                        aria-controls="reg-stage-options"
                                        @click="toggleDropdown('stage')"
                                        @keydown="handleDropdownKeydown($event, 'stage')"
                                    >
                                        <span>{{ selectedStageLabel }}</span>
                                        <span class="text-surface-500 text-base leading-none" aria-hidden="true">⌄</span>
                                    </button>
                                    <div
                                        v-if="openDropdown === 'stage'"
                                        id="reg-stage-options"
                                        role="listbox"
                                        aria-labelledby="reg-stage"
                                        class="absolute z-50 bottom-full inset-x-0 mb-2 max-h-56 overflow-y-auto rounded-2xl bg-white p-1.5 text-surface-900 shadow-2xl ring-1 ring-black/10"
                                    >
                                        <button
                                            v-for="(stage, index) in stageOptions"
                                            :key="stage.key"
                                            type="button"
                                            role="option"
                                            :aria-selected="selectedStage === stage.key"
                                            class="w-full rounded-xl px-4 py-2.5 text-right text-xs font-semibold transition-colors"
                                            :class="selectedStage === stage.key || activeOptionIndex === index ? 'bg-primary-100 text-primary-900' : 'hover:bg-surface-100'"
                                            @click="selectDropdownOption('stage', stage)"
                                        >{{ stage.name }}</button>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-white/95 mr-3" for="reg-grade">الصف الدراسي</label>
                                <div class="relative" data-registration-dropdown>
                                    <button
                                        id="reg-grade"
                                        type="button"
                                        class="w-full px-6 py-3 bg-white text-surface-900 rounded-full border border-transparent focus:outline-none focus:ring-4 focus:ring-primary-500/40 shadow-inner text-xs font-semibold transition-all flex items-center justify-between gap-3 text-right"
                                        :class="{ 'ring-2 ring-red-500': form.errors.grade_level }"
                                        :disabled="!filteredGradeLevels.length || form.processing"
                                        role="combobox"
                                        aria-haspopup="listbox"
                                        :aria-expanded="openDropdown === 'grade'"
                                        aria-controls="reg-grade-options"
                                        @click="toggleDropdown('grade')"
                                        @keydown="handleDropdownKeydown($event, 'grade')"
                                    >
                                        <span>{{ selectedGradeLabel }}</span>
                                        <span class="text-surface-500 text-base leading-none" aria-hidden="true">⌄</span>
                                    </button>
                                    <div
                                        v-if="openDropdown === 'grade'"
                                        id="reg-grade-options"
                                        role="listbox"
                                        aria-labelledby="reg-grade"
                                        class="absolute z-50 bottom-full inset-x-0 mb-2 max-h-56 overflow-y-auto rounded-2xl bg-white p-1.5 text-surface-900 shadow-2xl ring-1 ring-black/10"
                                    >
                                        <button
                                            v-for="(gl, index) in filteredGradeLevels"
                                            :key="gl.key"
                                            type="button"
                                            role="option"
                                            :aria-selected="form.grade_level === gl.key"
                                            class="w-full rounded-xl px-4 py-2.5 text-right text-xs font-semibold transition-colors"
                                            :class="form.grade_level === gl.key || activeOptionIndex === index ? 'bg-primary-100 text-primary-900' : 'hover:bg-surface-100'"
                                            @click="selectDropdownOption('grade', gl)"
                                        >{{ gl.name }}</button>
                                    </div>
                                </div>
                                <p v-if="form.errors.grade_level" class="text-red-400 text-xs mr-3 mt-1">{{ form.errors.grade_level }}</p>
                                <p v-else-if="!filteredGradeLevels.length" class="text-amber-300 text-xs mr-3 mt-1">لا توجد صفوف متاحة لهذه المرحلة حالياً.</p>
                            </div>
                        </div>

                        <!-- Track selector (only for selected grade 11/12) -->
                        <Transition
                            enter-active-class="transition-all duration-300 ease-out"
                            enter-from-class="opacity-0 -translate-y-2"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="transition-all duration-200"
                            leave-from-class="opacity-100"
                            leave-to-class="opacity-0"
                        >
                            <div v-if="showTrackSelector" class="space-y-1.5">
                                <label class="block text-xs font-bold text-white/95 mr-3">
                                    المسار الدراسي
                                </label>
                                <div class="flex flex-wrap gap-2">
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
                                minlength="8"
                                maxlength="255"
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
                                minlength="8"
                                maxlength="255"
                            />
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="space-y-3 pt-4">
                        <!-- Submit Button -->
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full py-3.5 bg-surface-950 hover:bg-surface-900 text-white rounded-full font-bold text-sm shadow-lg transition-all duration-200 active:scale-[0.98] flex items-center justify-center gap-2"
                            :class="{ 'opacity-65 cursor-not-allowed': form.processing }"
                            id="register-submit-btn"
                        >
                            <span v-if="form.processing" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            <span>{{ form.processing ? 'جاري إنشاء الحساب...' : 'إنشاء الحساب مجاناً' }}</span>
                        </button>

                        <!-- Login Link Button -->
                        <Link
                            :href="route('login')"
                            class="w-full py-3.5 bg-white hover:bg-surface-50 text-primary-900 rounded-full font-bold text-sm shadow-md transition-all duration-200 active:scale-[0.98] flex items-center justify-center"
                        >
                            تسجيل الدخول
                        </Link>
                    </div>
                </form>
            </div>
        </div>
        </div>
    </AppLayout>
</template>
