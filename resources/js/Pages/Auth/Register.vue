<script setup>
import { useForm, Link } from '@inertiajs/vue3';

const form = useForm({
    name:                  '',
    email:                 '',
    password:              '',
    password_confirmation: '',
    grade_level:           'grade_12',
    role:                  'student',
});

const submit = () => form.post(route('register'));
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-surface-50 dark:bg-surface-950 px-6 py-12" dir="rtl" lang="ar">
        <div class="w-full max-w-lg">

            <!-- Logo -->
            <Link :href="route('home')" class="flex items-center justify-center gap-2 mb-8 group">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-primary-700
                            flex items-center justify-center shadow-glow-primary
                            group-hover:scale-110 transition-transform duration-200">
                    <span class="text-white font-black text-lg">ت</span>
                </div>
                <span class="text-2xl font-black text-gradient-primary">منصة التفوق</span>
            </Link>

            <div class="card p-8">
                <h1 class="text-2xl font-black text-surface-900 dark:text-white mb-2 text-center">
                    إنشاء حساب جديد 🎓
                </h1>
                <p class="text-surface-500 dark:text-surface-400 text-center mb-6 text-sm">
                    ابدأ رحلتك مجاناً — لا بطاقة بنكية مطلوبة
                </p>

                <form @submit.prevent="submit" class="space-y-4">

                    <!-- Name -->
                    <div>
                        <label class="input-label" for="reg-name">الاسم الكامل</label>
                        <input
                            id="reg-name"
                            v-model="form.name"
                            type="text"
                            class="input"
                            :class="{ 'border-red-500': form.errors.name }"
                            placeholder="محمد عبدالله"
                            autocomplete="name"
                            required
                            autofocus
                        />
                        <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="input-label" for="reg-email">البريد الإلكتروني</label>
                        <input
                            id="reg-email"
                            v-model="form.email"
                            type="email"
                            class="input"
                            :class="{ 'border-red-500': form.errors.email }"
                            placeholder="example@email.com"
                            autocomplete="username"
                            required
                        />
                        <p v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</p>
                    </div>

                    <!-- Role + Grade Level in row -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="input-label" for="reg-role">أنا</label>
                            <select id="reg-role" v-model="form.role" class="input">
                                <option value="student">طالب</option>
                                <option value="teacher">مدرس</option>
                                <option value="parent">ولي أمر</option>
                            </select>
                        </div>
                        <div v-if="form.role === 'student'">
                            <label class="input-label" for="reg-grade">الصف الدراسي</label>
                            <select id="reg-grade" v-model="form.grade_level" class="input">
                                <option v-for="gl in $page.props.grade_levels?.filter(g => g.key !== 'all')" :key="gl.key" :value="gl.key">
                                    {{ gl.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="input-label" for="reg-password">كلمة المرور</label>
                        <input
                            id="reg-password"
                            v-model="form.password"
                            type="password"
                            class="input"
                            :class="{ 'border-red-500': form.errors.password }"
                            placeholder="8 أحرف على الأقل"
                            autocomplete="new-password"
                            required
                        />
                        <p v-if="form.errors.password" class="text-red-500 text-xs mt-1">{{ form.errors.password }}</p>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label class="input-label" for="reg-confirm">تأكيد كلمة المرور</label>
                        <input
                            id="reg-confirm"
                            v-model="form.password_confirmation"
                            type="password"
                            class="input"
                            placeholder="••••••••"
                            autocomplete="new-password"
                            required
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="btn-primary w-full btn-lg mt-2"
                        :class="{ 'opacity-60': form.processing }"
                        id="register-submit-btn"
                    >
                        <span v-if="form.processing">⏳ جاري إنشاء الحساب...</span>
                        <span v-else">إنشاء الحساب مجاناً →</span>
                    </button>

                    <p class="text-center text-sm text-surface-500 dark:text-surface-400">
                        لديك حساب بالفعل؟
                        <Link :href="route('login')"
                              class="text-primary-600 dark:text-primary-400 font-semibold hover:underline">
                            تسجيل الدخول
                        </Link>
                    </p>
                </form>
            </div>
        </div>
    </div>
</template>
