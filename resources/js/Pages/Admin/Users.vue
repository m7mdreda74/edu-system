<script setup>
import { ref, computed, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    users:   { type: Object, required: true },  // paginated
    filters: { type: Object, default: () => ({}) },
    defaultCommission: { type: Number, default: 20 },
});

const page = usePage();
const { confirm } = useConfirm();

const search = ref(props.filters.search ?? '');
const role   = ref(props.filters.role ?? '');

const debouncedSearch = useDebounceFn(() => applyFilters(), 300);

function applyFilters() {
    router.get(route('admin.users'), {
        search: search.value || undefined,
        role:   role.value   || undefined,
    }, { preserveState: true, replace: true });
}

function toggleActive(userId) {
    router.patch(route('admin.users.toggle', { id: userId }), {}, {
        onSuccess: () => {},
        preserveScroll: true,
    });
}

// ─── Add User Management ───
const createUserModalOpen = ref(false);
const createForm = useForm({
    name: '',
    email: '',
    phone: '',
    password: '',
    role: 'student',
    grade_level: '',
});

const selectedStage = ref('secondary');

const filteredGradeLevels = computed(() => {
    return page.props.grade_levels?.filter(g => g.stage === selectedStage.value && g.key !== 'all') || [];
});

const onStageChange = () => {
    const firstGrade = filteredGradeLevels.value[0];
    createForm.grade_level = firstGrade ? firstGrade.key : '';
};

watch(() => createForm.role, (newRole) => {
    if (newRole === 'student') {
        onStageChange();
    } else {
        createForm.grade_level = '';
    }
});

function openCreateModal() {
    createForm.reset();
    selectedStage.value = 'secondary';
    onStageChange();
    createUserModalOpen.value = true;
}

function submitCreateUser() {
    createForm.post(route('admin.users.store'), {
        onSuccess: () => {
            createUserModalOpen.value = false;
            createForm.reset();
        },
        preserveScroll: true,
    });
}

// ─── Role Management ───
const roleModalOpen = ref(false);
const editingUser   = ref(null);
const selectedRole  = ref('student');

function openRoleModal(user) {
    editingUser.value  = user;
    selectedRole.value = user.roles?.[0]?.name ?? 'student';
    roleModalOpen.value = true;
}

function updateRole() {
    if (!editingUser.value) return;
    router.patch(route('admin.users.role', { id: editingUser.value.id }), {
        role: selectedRole.value
    }, {
        onSuccess: () => {
            roleModalOpen.value = false;
        },
        preserveScroll: true,
    });
}

const roleLabel  = { admin: 'مدير', teacher: 'مدرس', student: 'طالب', parent: 'ولي أمر' };
const roleColors = { admin: 'badge-accent', teacher: 'badge-primary', student: 'badge-gray', parent: 'badge-green' };
const commissionModalOpen = ref(false);
const commissionUser = ref(null);
const commissionForm = useForm({ commission_percent: 20 });

function openCommissionModal(user) {
    commissionUser.value = user;
    commissionForm.commission_percent = user.commission_percent ?? props.defaultCommission;
    commissionModalOpen.value = true;
}

function updateCommission() {
    commissionForm.patch(route('admin.users.commission', { id: commissionUser.value.id }), {
        preserveScroll: true,
        onSuccess: () => { commissionModalOpen.value = false; },
    });
}

// ── Teacher photos ─────────────────────────────────────────────
// A teacher's photo is the first thing a visitor judges on the browse pages,
// so the platform sets it rather than the teacher.
const isTeacher = (user) => user.roles?.some((r) => r.name === 'teacher') ?? false;

const avatarModalOpen = ref(false);
const avatarUser = ref(null);
const avatarPreview = ref(null);
const avatarForm = useForm({ avatar: null });

function openAvatarModal(user) {
    avatarUser.value = user;
    avatarPreview.value = null;
    avatarForm.reset();
    avatarForm.clearErrors();
    avatarModalOpen.value = true;
}

function onAvatarPicked(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    avatarForm.avatar = file;
    avatarPreview.value = URL.createObjectURL(file);
}

function uploadAvatar() {
    avatarForm.post(route('admin.users.avatar', { id: avatarUser.value.id }), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { avatarModalOpen.value = false; avatarPreview.value = null; },
    });
}

async function removeAvatar() {
    const ok = await confirm({
        title: 'حذف الصورة الشخصية',
        message: `هل أنت متأكد من حذف صورة ${avatarUser.value?.name}؟`,
        confirmLabel: 'حذف',
        variant: 'danger',
    });
    if (!ok) return;

    router.delete(route('admin.users.avatar.delete', { id: avatarUser.value.id }), {
        preserveScroll: true,
        onSuccess: () => { avatarModalOpen.value = false; },
    });
}
</script>

<template>
    <DashboardLayout>
        <Head title="إدارة المستخدمين" />

        <div class="dashboard-data-page px-2 md:px-4 py-4 md:py-6">

            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <Icon name="users" class="w-8 h-8 text-primary-500" />
                        <span>المستخدمون</span>
                    </h1>
                    <p class="text-surface-500 dark:text-surface-400 mt-1">
                        {{ users.total?.toLocaleString('en') }} مستخدم مسجّل
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="openCreateModal" class="btn-primary flex items-center gap-2">
                        <Icon name="plus" class="w-4 h-4" />
                        <span>إضافة مستخدم جديد</span>
                    </button>
                    <Link :href="route('admin.dashboard')" class="btn-ghost">← الداشبورد</Link>
                </div>
            </div>

            <!-- Filters -->
            <div class="card p-4 flex flex-wrap gap-3">
                <input
                    v-model="search"
                    @input="debouncedSearch"
                    type="text"
                    placeholder="بحث بالاسم أو الإيميل..."
                    class="input flex-1 min-w-48"
                    id="users-search"
                />
                <select v-model="role" @change="applyFilters" class="input w-36" id="role-filter">
                    <option value="">كل الأدوار</option>
                    <option value="student">طلاب</option>
                    <option value="teacher">مدرسين</option>
                    <option value="admin">مديرين</option>
                </select>
            </div>

            <!-- Table -->
            <div class="card data-table-card">
                <div class="data-table-scroll no-scrollbar">
                    <table class="w-full text-sm">
                        <thead class="bg-surface-50 dark:bg-surface-800 border-b border-surface-200 dark:border-surface-700">
                            <tr>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">المستخدم</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">الدور</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">التسجيلات</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">عمولة المنصة</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">الحالة</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">إجراء</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-700">
                            <tr v-for="user in users.data" :key="user.id"
                                class="hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <!-- Teacher photos are public-facing, so they are set here -->
                                        <div class="relative group flex-shrink-0">
                                            <div class="avatar-md bg-primary-100 dark:bg-primary-900">
                                                <img v-if="user.avatar" :src="user.avatar" :alt="user.name"
                                                     class="w-full h-full object-cover rounded-full" />
                                                <span v-else class="text-primary-700 font-bold text-sm">
                                                    {{ user.name?.charAt(0) }}
                                                </span>
                                            </div>

                                            <button
                                                v-if="isTeacher(user)"
                                                type="button"
                                                class="absolute inset-0 rounded-full bg-black/55 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                                                title="تغيير صورة المعلم"
                                                @click="openAvatarModal(user)"
                                            >
                                                <Icon name="edit" class="w-3.5 h-3.5" />
                                            </button>
                                        </div>

                                        <div>
                                            <div class="font-semibold text-surface-800 dark:text-white">{{ user.name }}</div>
                                            <div class="text-xs text-surface-400">{{ user.email }}</div>
                                            <button
                                                v-if="isTeacher(user) && !user.avatar"
                                                type="button"
                                                class="text-[10px] font-bold text-accent-600 dark:text-accent-400 mt-0.5"
                                                @click="openAvatarModal(user)"
                                            >
                                                بدون صورة — أضفها
                                            </button>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <span v-for="r in user.roles" :key="r.name"
                                          :class="roleColors[r.name]"
                                          class="text-xs me-1"
                                    >{{ roleLabel[r.name] }}</span>
                                </td>
                                <td class="p-4 text-surface-600 dark:text-surface-300">
                                    {{ user.subscriptions_count }}
                                </td>
                                <td class="p-4">
                                    <button v-if="user.roles?.some(r => r.name === 'teacher')" @click="openCommissionModal(user)" class="btn-outline btn-sm">
                                        {{ user.commission_percent ?? defaultCommission }}%
                                    </button>
                                    <span v-else class="text-surface-300">—</span>
                                </td>
                                <td class="p-4">
                                    <span :class="user.is_active
                                        ? 'badge-green'
                                        : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-400 px-2 py-0.5 rounded-full text-xs font-semibold'"
                                    >
                                        {{ user.is_active ? 'نشط' : 'معطّل' }}
                                    </span>
                                </td>
                                <td class="p-4 flex gap-2">
                                    <button
                                        @click="toggleActive(user.id)"
                                        class="text-xs px-3 py-1.5 rounded-lg font-medium transition-colors"
                                        :class="user.is_active
                                            ? 'bg-red-50 text-red-600 hover:bg-red-100 dark:bg-red-950/50 dark:text-red-400'
                                            : 'bg-green-50 text-green-600 hover:bg-green-100 dark:bg-green-950/50 dark:text-green-400'"
                                        :id="`toggle-user-${user.id}`"
                                    >
                                        {{ user.is_active ? 'تعطيل' : 'تفعيل' }}
                                    </button>

                                    <button
                                        @click="openRoleModal(user)"
                                        class="text-xs px-3 py-1.5 rounded-lg font-medium bg-surface-100 text-surface-600 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300 dark:hover:bg-surface-700 transition-colors"
                                    >
                                        تعديل الدور
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty state -->
                <div v-if="!users.data?.length" class="p-16 text-center text-surface-400">
                    <div class="text-4xl mb-3">👥</div>
                    <p>لا توجد نتائج</p>
                </div>

                <!-- Pagination -->
                <div v-if="users.last_page > 1"
                     class="data-table-footer p-4 border-t border-surface-100 dark:border-surface-700 flex gap-2 justify-center flex-wrap">
                    <Link
                        v-for="link in users.links" :key="link.label"
                        :href="link.url ?? '#'"
                        class="px-3 py-1.5 rounded-lg text-sm border transition-colors"
                        :class="link.active
                            ? 'bg-primary-600 text-white border-primary-600'
                            : link.url
                                ? 'border-surface-200 dark:border-surface-600 text-surface-600 dark:text-surface-300 hover:bg-surface-50 dark:hover:bg-surface-700'
                                : 'opacity-40 cursor-not-allowed border-surface-200 dark:border-surface-600'"
                    >
                        <span v-html="link.label"></span>
                    </Link>
                </div>
            </div>
        </div>

        <!-- Role Modal -->
        <div v-if="commissionModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="commissionModalOpen = false"></div>
            <form @submit.prevent="updateCommission" class="relative card p-6 w-full max-w-sm space-y-4">
                <div>
                    <h3 class="text-xl font-black text-surface-900 dark:text-white">نسبة عمولة المنصة</h3>
                    <p class="text-xs text-surface-500 mt-1">للمدرس: {{ commissionUser?.name }} — تُثبت النسبة وقت كل اشتراك.</p>
                </div>
                <div>
                    <label class="input-label">النسبة من 0 إلى 100%</label>
                    <input v-model="commissionForm.commission_percent" type="number" min="0" max="100" class="input" required />
                    <p v-if="commissionForm.errors.commission_percent" class="error-msg">{{ commissionForm.errors.commission_percent }}</p>
                </div>
                <div class="flex gap-2 justify-end">
                    <button type="button" @click="commissionModalOpen = false" class="btn-ghost">إلغاء</button>
                    <button type="submit" class="btn-primary" :disabled="commissionForm.processing">حفظ النسبة</button>
                </div>
            </form>
        </div>

        <!-- Role Modal -->
        <div v-if="roleModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="roleModalOpen = false"></div>
            <div class="relative bg-white dark:bg-surface-900 rounded-2xl shadow-xl w-full max-w-sm overflow-hidden animate-fade-up">
                <div class="p-6">
                    <h3 class="text-xl font-bold text-surface-900 dark:text-white mb-4">تعديل دور المستخدم</h3>
                    <p class="text-sm text-surface-500 mb-4">اختر الصلاحية الجديدة للمستخدم <strong class="text-surface-800 dark:text-white">{{ editingUser?.name }}</strong>.</p>
                    
                    <div class="space-y-3">
                        <label class="flex items-center gap-3 p-3 border border-surface-200 dark:border-surface-700 rounded-xl cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors" :class="{'border-primary-500 bg-primary-50 dark:bg-primary-900/20': selectedRole === 'student'}">
                            <input type="radio" v-model="selectedRole" value="student" class="text-primary-600 focus:ring-primary-500">
                            <div>
                                <div class="font-bold text-surface-800 dark:text-white">طالب</div>
                                <div class="text-xs text-surface-500">طالب يشترك مع المعلمين</div>
                            </div>
                        </label>
                        
                        <label class="flex items-center gap-3 p-3 border border-surface-200 dark:border-surface-700 rounded-xl cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors" :class="{'border-primary-500 bg-primary-50 dark:bg-primary-900/20': selectedRole === 'teacher'}">
                            <input type="radio" v-model="selectedRole" value="teacher" class="text-primary-600 focus:ring-primary-500">
                            <div>
                                <div class="font-bold text-surface-800 dark:text-white">مدرس</div>
                                <div class="text-xs text-surface-500">يمكنه إنشاء وإدارة مجموعاته</div>
                            </div>
                        </label>
                        
                        <label class="flex items-center gap-3 p-3 border border-surface-200 dark:border-surface-700 rounded-xl cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors" :class="{'border-primary-500 bg-primary-50 dark:bg-primary-900/20': selectedRole === 'parent'}">
                            <input type="radio" v-model="selectedRole" value="parent" class="text-primary-600 focus:ring-primary-500">
                            <div>
                                <div class="font-bold text-surface-800 dark:text-white">ولي أمر</div>
                                <div class="text-xs text-surface-500">يمكنه متابعة دراسة ونتائج أبنائه</div>
                            </div>
                        </label>
                        
                        <label class="flex items-center gap-3 p-3 border border-surface-200 dark:border-surface-700 rounded-xl cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors" :class="{'border-primary-500 bg-primary-50 dark:bg-primary-900/20': selectedRole === 'admin'}">
                            <input type="radio" v-model="selectedRole" value="admin" class="text-primary-600 focus:ring-primary-500">
                            <div>
                                <div class="font-bold text-surface-800 dark:text-white">مدير المنصة</div>
                                <div class="text-xs text-surface-500">صلاحيات كاملة للتحكم في المنصة</div>
                            </div>
                        </label>
                    </div>
                </div>
                <div class="p-4 bg-surface-50 dark:bg-surface-950 flex justify-end gap-2 border-t border-surface-200 dark:border-surface-800">
                    <button @click="roleModalOpen = false" class="btn-ghost">إلغاء</button>
                    <button @click="updateRole" class="btn-primary">حفظ التغييرات</button>
                </div>
            </div>
        </div>

        <!-- Create User Modal -->
        <div v-if="createUserModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="createUserModalOpen = false"></div>
            <div class="relative bg-white dark:bg-surface-900 rounded-2xl shadow-xl w-full max-w-md overflow-hidden animate-fade-up">
                <form @submit.prevent="submitCreateUser">
                    <div class="p-6 space-y-4">
                        <h3 class="text-xl font-bold text-surface-900 dark:text-white">إضافة مستخدم جديد</h3>
                        
                        <!-- Name -->
                        <div>
                            <label class="block text-xs font-semibold text-surface-700 dark:text-surface-300 mb-1" for="create-name">الاسم الكامل</label>
                            <input id="create-name" v-model="createForm.name" type="text" class="input w-full text-sm" placeholder="أدخل الاسم الكامل..." required />
                            <p v-if="createForm.errors.name" class="text-red-500 text-xs mt-1">{{ createForm.errors.name }}</p>
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-xs font-semibold text-surface-700 dark:text-surface-300 mb-1" for="create-email">البريد الإلكتروني</label>
                            <input id="create-email" v-model="createForm.email" type="email" class="input w-full text-sm" placeholder="example@email.com" required />
                            <p v-if="createForm.errors.email" class="text-red-500 text-xs mt-1">{{ createForm.errors.email }}</p>
                        </div>

                        <!-- Phone -->
                        <div>
                            <label class="block text-xs font-semibold text-surface-700 dark:text-surface-300 mb-1" for="create-phone">رقم الهاتف / الجوال</label>
                            <input id="create-phone" v-model="createForm.phone" type="text" class="input w-full text-sm" placeholder="مثال: +97433554858" required />
                            <p v-if="createForm.errors.phone" class="text-red-500 text-xs mt-1">{{ createForm.errors.phone }}</p>
                        </div>

                        <!-- Password -->
                        <div>
                            <label class="block text-xs font-semibold text-surface-700 dark:text-surface-300 mb-1" for="create-password">كلمة المرور</label>
                            <input id="create-password" v-model="createForm.password" type="password" class="input w-full text-sm" placeholder="••••••••" required />
                            <p v-if="createForm.errors.password" class="text-red-500 text-xs mt-1">{{ createForm.errors.password }}</p>
                        </div>

                        <!-- Role -->
                        <div>
                            <label class="block text-xs font-semibold text-surface-700 dark:text-surface-300 mb-1" for="create-role">نوع الحساب (الدور)</label>
                            <select id="create-role" v-model="createForm.role" class="input w-full text-sm">
                                <option value="student">طالب</option>
                                <option value="teacher">مدرس</option>
                                <option value="parent">ولي أمر</option>
                                <option value="admin">مدير</option>
                            </select>
                        </div>

                        <!-- Grade Level for Student -->
                        <div v-if="createForm.role === 'student'" class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-surface-700 dark:text-surface-300 mb-1" for="create-stage">المرحلة الدراسية</label>
                                <select id="create-stage" v-model="selectedStage" class="input w-full text-sm" @change="onStageChange">
                                    <option value="primary">المرحلة الابتدائية</option>
                                    <option value="preparatory">المرحلة الإعدادية</option>
                                    <option value="secondary">المرحلة الثانوية</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-surface-700 dark:text-surface-300 mb-1" for="create-grade">الصف الدراسي</label>
                                <select id="create-grade" v-model="createForm.grade_level" class="input w-full text-sm" required>
                                    <option value="" disabled>اختر الصف...</option>
                                    <option v-for="gl in filteredGradeLevels" :key="gl.key" :value="gl.key">
                                        {{ gl.name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="p-4 bg-surface-50 dark:bg-surface-950 flex justify-end gap-2 border-t border-surface-200 dark:border-surface-800">
                        <button type="button" @click="createUserModalOpen = false" class="btn-ghost text-sm">إلغاء</button>
                        <button type="submit" :disabled="createForm.processing" class="btn-primary text-sm">
                            <span v-if="createForm.processing" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            <span>إضافة المستخدم</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── Teacher photo ─────────────────────────────────────── -->
        <div v-if="avatarModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="avatarModalOpen = false"></div>

            <div class="relative bg-white dark:bg-surface-900 rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
                <form @submit.prevent="uploadAvatar">
                    <div class="p-6">
                        <h3 class="text-lg font-bold text-surface-900 dark:text-white">صورة المعلم</h3>
                        <p class="text-xs text-surface-500 mt-1">
                            {{ avatarUser?.name }} — تظهر هذه الصورة للطلاب في صفحات التصفح وبطاقة المعلم.
                        </p>

                        <div class="flex items-center gap-4 mt-6">
                            <div class="w-20 h-20 rounded-full overflow-hidden bg-primary-100 dark:bg-primary-900 flex items-center justify-center border shrink-0">
                                <img v-if="avatarPreview || avatarUser?.avatar" :src="avatarPreview ?? avatarUser.avatar" class="w-full h-full object-cover" />
                                <span v-else class="text-primary-700 font-bold text-xl">{{ avatarUser?.name?.charAt(0) }}</span>
                            </div>

                            <div class="flex-1 min-w-0">
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="text-xs text-surface-500 file:me-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 w-full"
                                    @change="onAvatarPicked"
                                />
                                <p class="text-[10px] text-surface-400 mt-1.5">
                                    JPG أو PNG أو WebP، بحد أقصى 4 ميجابايت. تُحوَّل تلقائياً إلى WebP.
                                </p>
                                <p v-if="avatarForm.errors.avatar" class="text-xs text-red-500 mt-1">
                                    {{ avatarForm.errors.avatar }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-2 px-6 py-4 bg-surface-50 dark:bg-surface-950/50">
                        <button
                            v-if="avatarUser?.avatar"
                            type="button"
                            class="btn-ghost btn-sm text-red-500"
                            @click="removeAvatar"
                        >حذف الصورة</button>
                        <span v-else></span>

                        <div class="flex gap-2">
                            <button type="button" class="btn-ghost btn-sm" @click="avatarModalOpen = false">إلغاء</button>
                            <button type="submit" class="btn-primary btn-sm" :disabled="avatarForm.processing || !avatarForm.avatar">
                                {{ avatarForm.processing ? 'جارٍ الرفع...' : 'حفظ الصورة' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </DashboardLayout>
</template>

