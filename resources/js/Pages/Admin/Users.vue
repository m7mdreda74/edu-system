<script setup>
import { ref, computed, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
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
const togglingUserIds = ref(new Set());

function applyFilters() {
    router.get(route('admin.users'), {
        search: search.value || undefined,
        role:   role.value   || undefined,
    }, { preserveState: true, replace: true });
}

function toggleActive(userId) {
    togglingUserIds.value = new Set(togglingUserIds.value).add(userId);
    router.patch(route('admin.users.toggle', { id: userId }), {}, {
        // Re-fetch the paginated collection after every toggle. Keeping the
        // previous page state here can leave the button showing the old state
        // after the first request, making the second click appear to be a no-op.
        preserveState: false,
        preserveScroll: true,
        onFinish: () => {
            const next = new Set(togglingUserIds.value);
            next.delete(userId);
            togglingUserIds.value = next;
        },
    });
}

// ─── Add User Management ───
const createUserModalOpen = ref(false);
const createForm = useForm({
    name: '',
    email: '',
    phone: '',
    parent_phone: '',
    password: '',
    role: 'student',
    grade_level: '',
});

const emailPrefix = ref('');

const platformEmail = (prefix) => `${String(prefix ?? '').trim().toLowerCase()}@altafawwuq.com`;

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
        createForm.parent_phone = '';
    }
});

function openCreateModal() {
    createForm.reset();
    emailPrefix.value = '';
    selectedStage.value = 'secondary';
    onStageChange();
    createUserModalOpen.value = true;
}

function submitCreateUser() {
    createForm.email = platformEmail(emailPrefix.value);
    createForm.post(route('admin.users.store'), {
        onSuccess: () => {
            createUserModalOpen.value = false;
            createForm.reset();
        },
        preserveScroll: true,
    });
}

// Passwords are never displayed. The administrator can only set a new one.
const passwordModalOpen = ref(false);
const passwordUser = ref(null);
const passwordForm = useForm({ password: '', password_confirmation: '' });

function openPasswordModal(user) {
    passwordUser.value = user;
    passwordForm.reset();
    passwordForm.clearErrors();
    passwordModalOpen.value = true;
}

function resetUserPassword() {
    passwordForm.patch(route('admin.users.password', { id: passwordUser.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            passwordModalOpen.value = false;
            passwordUser.value = null;
            passwordForm.reset();
        },
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

const mediaModalOpen = ref(false);
const mediaUser = ref(null);
const mediaType = ref('avatar');
const mediaPreview = ref(null);
const mediaForm = useForm({ avatar: null, profile_cover: null });

const mediaField = computed(() => mediaType.value === 'cover' ? 'profile_cover' : 'avatar');
const mediaTitle = computed(() => mediaType.value === 'cover' ? 'كافر بروفايل المعلم' : 'صورة المعلم');
const mediaIsCover = computed(() => mediaType.value === 'cover');
const existingMedia = computed(() => {
    if (!mediaUser.value) return null;

    return mediaIsCover.value ? mediaUser.value.profile_cover : mediaUser.value.avatar;
});
const selectedMediaFile = computed(() => mediaForm[mediaField.value]);
const mediaError = computed(() => mediaForm.errors[mediaField.value]);

function openMediaModal(user, type = 'avatar') {
    mediaUser.value = user;
    mediaType.value = type;
    mediaPreview.value = null;
    mediaForm.reset();
    mediaForm.clearErrors();
    mediaModalOpen.value = true;
}

function onMediaPicked(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    const maxBytes = (mediaIsCover.value ? 8 : 4) * 1024 * 1024;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > maxBytes) {
        mediaForm[mediaField.value] = null;
        event.target.value = '';
        mediaForm.setError(mediaField.value, 'Invalid image format or size.');
        return;
    }

    mediaForm[mediaField.value] = file;
    mediaForm.clearErrors(mediaField.value);
    mediaPreview.value = URL.createObjectURL(file);
}

function uploadMedia() {
    const routeName = mediaIsCover.value ? 'admin.users.cover' : 'admin.users.avatar';

    mediaForm.post(route(routeName, { id: mediaUser.value.id }), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { mediaModalOpen.value = false; mediaPreview.value = null; },
    });
}

async function removeMedia() {
    const routeName = mediaIsCover.value ? 'admin.users.cover.delete' : 'admin.users.avatar.delete';
    const mediaName = mediaIsCover.value ? 'كافر البروفايل' : 'الصورة الشخصية';

    const ok = await confirm({
        title: 'حذف ' + mediaName,
        message: 'هل أنت متأكد من حذف ' + mediaName + ' الخاصة بـ ' + mediaUser.value?.name + '؟',
        confirmLabel: 'حذف',
        variant: 'danger',
    });
    if (!ok) return;

    router.delete(route(routeName, { id: mediaUser.value.id }), {
        preserveScroll: true,
        onSuccess: () => { mediaModalOpen.value = false; },
    });
}
</script>

<template>
    <DashboardLayout>
        <Head title="إدارة المستخدمين" />

        <div class="dashboard-data-page">

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
                    <button type="button" @click="openCreateModal" class="btn-primary flex items-center gap-2">
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
                    maxlength="100"
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
                    <table class="data-table">
                        <thead class="bg-surface-50 dark:bg-surface-800 border-b border-surface-200 dark:border-surface-700">
                            <tr>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">المستخدم</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">الدور</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">التسجيلات</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">عمولة المنصة</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">الحالة</th>
                                <th class="data-table-actions text-start p-4 font-semibold text-surface-600 dark:text-surface-300">إجراء</th>
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
                                                aria-label="تغيير صورة المعلم"
                                                @click="openMediaModal(user, 'avatar')"
                                            >
                                                <Icon name="edit" class="w-3.5 h-3.5" />
                                            </button>
                                        </div>

                                        <div>
                                            <div class="font-semibold text-surface-800 dark:text-white">{{ user.name }}</div>
                                            <div class="text-xs text-surface-400">{{ user.email }}</div>
                                            <button
                                                v-if="isTeacher(user)"
                                                type="button"
                                                class="block text-[10px] font-bold text-primary-600 dark:text-primary-400 mt-0.5"
                                                @click="openMediaModal(user, 'cover')"
                                            >
                                                {{ user.profile_cover ? 'تعديل الغلاف' : 'إضافة غلاف البروفايل' }}
                                            </button>
                                            <button
                                                v-if="isTeacher(user) && !user.avatar"
                                                type="button"
                                                class="text-[10px] font-bold text-accent-600 dark:text-accent-400 mt-0.5"
                                                @click="openMediaModal(user, 'avatar')"
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
                                    <button type="button" v-if="user.roles?.some(r => r.name === 'teacher')" @click="openCommissionModal(user)" class="btn-outline btn-sm">
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
                                <td class="data-table-actions p-4">
                                    <div class="flex flex-wrap gap-2">
                                    <button type="button"
                                        @click="openPasswordModal(user)"
                                        class="text-xs px-3 py-1.5 rounded-lg font-medium bg-primary-50 text-primary-700 hover:bg-primary-100 dark:bg-primary-950/40 dark:text-primary-300 dark:hover:bg-primary-900/50 transition-colors"
                                    >
                                        كلمة المرور
                                    </button>
                                    <button type="button"
                                        @click="toggleActive(user.id)"
                                        :disabled="togglingUserIds.has(user.id)"
                                        class="text-xs px-3 py-1.5 rounded-lg font-medium transition-colors"
                                        :class="user.is_active
                                            ? 'bg-red-50 text-red-600 hover:bg-red-100 dark:bg-red-950/50 dark:text-red-400'
                                            : 'bg-green-50 text-green-600 hover:bg-green-100 dark:bg-green-950/50 dark:text-green-400'"
                                        :id="`toggle-user-${user.id}`"
                                    >
                                        {{ togglingUserIds.has(user.id) ? 'جارٍ التحديث...' : (user.is_active ? 'تعطيل' : 'تفعيل') }}
                                    </button>

                                    <button type="button"
                                        @click="openRoleModal(user)"
                                        class="text-xs px-3 py-1.5 rounded-lg font-medium bg-surface-100 text-surface-600 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300 dark:hover:bg-surface-700 transition-colors"
                                    >
                                        تعديل الدور
                                    </button>
                                    </div>
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

                <DataTablePagination :paginator="users" item-label="مستخدم" />
            </div>
        </div>

        <!-- Password reset modal -->
        <div v-if="passwordModalOpen" class="modal-overlay z-50" role="dialog" aria-modal="true" aria-label="تعيين كلمة مرور">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="passwordModalOpen = false"></div>
            <form @submit.prevent="resetUserPassword" class="relative modal-panel-compact card p-6 w-full max-w-sm space-y-4">
                <div>
                    <h3 class="text-xl font-black text-surface-900 dark:text-white">تعيين كلمة مرور</h3>
                    <p class="text-xs text-surface-500 mt-1">
                        {{ passwordUser?.name }} — كلمات المرور الحالية لا يمكن عرضها لأنها مخزنة بشكل مشفّر.
                    </p>
                </div>
                <div>
                    <label class="input-label" for="admin-reset-password">كلمة المرور الجديدة</label>
                    <input id="admin-reset-password" v-model="passwordForm.password" type="password" class="input" autocomplete="new-password" minlength="8" maxlength="255" required />
                    <p v-if="passwordForm.errors.password" class="error-msg">{{ passwordForm.errors.password }}</p>
                </div>
                <div>
                    <label class="input-label" for="admin-reset-password-confirmation">تأكيد كلمة المرور</label>
                    <input id="admin-reset-password-confirmation" v-model="passwordForm.password_confirmation" type="password" class="input" autocomplete="new-password" minlength="8" maxlength="255" required />
                    <p v-if="passwordForm.errors.password_confirmation" class="error-msg">{{ passwordForm.errors.password_confirmation }}</p>
                </div>
                <div class="flex gap-2 justify-end">
                    <button type="button" @click="passwordModalOpen = false" class="btn-ghost">إلغاء</button>
                    <button type="submit" class="btn-primary" :disabled="passwordForm.processing">حفظ كلمة المرور</button>
                </div>
            </form>
        </div>

        <!-- Role Modal -->
        <div v-if="commissionModalOpen" class="modal-overlay z-50" role="dialog" aria-modal="true" aria-label="نسبة عمولة المنصة">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="commissionModalOpen = false"></div>
            <form @submit.prevent="updateCommission" class="relative modal-panel-compact card p-6 w-full max-w-sm space-y-4">
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
        <div v-if="roleModalOpen" class="modal-overlay z-50" role="dialog" aria-modal="true" aria-label="تعديل دور المستخدم">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="roleModalOpen = false"></div>
            <div class="relative modal-panel-compact bg-white dark:bg-surface-900 rounded-2xl shadow-xl w-full max-w-sm animate-fade-up">
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
                    <button type="button" @click="roleModalOpen = false" class="btn-ghost">إلغاء</button>
                    <button type="button" @click="updateRole" class="btn-primary">حفظ التغييرات</button>
                </div>
            </div>
        </div>

        <!-- Create User Modal -->
        <div v-if="createUserModalOpen" class="modal-overlay z-50" role="dialog" aria-modal="true" aria-label="إضافة مستخدم جديد">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="createUserModalOpen = false"></div>
            <div class="relative modal-panel-compact bg-white dark:bg-surface-900 rounded-2xl shadow-xl w-full max-w-md animate-fade-up">
                <form @submit.prevent="submitCreateUser">
                    <div class="p-6 space-y-4">
                        <h3 class="text-xl font-bold text-surface-900 dark:text-white">إضافة مستخدم جديد</h3>
                        
                        <!-- Name -->
                        <div>
                            <label class="block text-xs font-semibold text-surface-700 dark:text-surface-300 mb-1" for="create-name">الاسم الكامل</label>
                            <input id="create-name" v-model="createForm.name" type="text" maxlength="255" class="input w-full text-sm" placeholder="أدخل الاسم الكامل..." required />
                            <p v-if="createForm.errors.name" class="text-red-500 text-xs mt-1">{{ createForm.errors.name }}</p>
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-xs font-semibold text-surface-700 dark:text-surface-300 mb-1" for="create-email">البريد الإلكتروني</label>
                            <div dir="ltr" class="flex items-center overflow-hidden rounded-xl border border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-950">
                                <input id="create-email" v-model="emailPrefix" type="text" maxlength="240" class="min-w-0 flex-1 input border-0 rounded-none text-sm" placeholder="username" autocomplete="username" required />
                                <span class="shrink-0 px-3 text-xs font-bold text-primary-700 dark:text-primary-300">@altafawwuq.com</span>
                            </div>
                            <p class="text-surface-400 text-[11px] mt-1">نطاق البريد ثابت لحسابات المنصة.</p>
                            <p v-if="createForm.errors.email" class="text-red-500 text-xs mt-1">{{ createForm.errors.email }}</p>
                        </div>

                        <!-- Phone -->
                        <div>
                            <label class="block text-xs font-semibold text-surface-700 dark:text-surface-300 mb-1" for="create-phone">رقم الهاتف / الجوال</label>
                            <input id="create-phone" v-model="createForm.phone" type="text" inputmode="tel" minlength="7" maxlength="20" class="input w-full text-sm" placeholder="مثال: +97433554858" required />
                            <p v-if="createForm.errors.phone" class="text-red-500 text-xs mt-1">{{ createForm.errors.phone }}</p>
                        </div>

                        <!-- Parent phone for Student -->
                        <div v-if="createForm.role === 'student'">
                            <label class="block text-xs font-semibold text-surface-700 dark:text-surface-300 mb-1" for="create-parent-phone">رقم جوال ولي الأمر <span class="text-red-500">*</span></label>
                            <input id="create-parent-phone" v-model="createForm.parent_phone" type="tel" inputmode="tel" minlength="7" maxlength="20" class="input w-full text-sm" placeholder="نفس الرقم المسجل بحساب ولي الأمر" required />
                            <p class="mt-1 text-[11px] text-surface-400">يُستخدم لربط الطالب مباشرة بحساب ولي الأمر.</p>
                            <p v-if="createForm.errors.parent_phone" class="text-red-500 text-xs mt-1">{{ createForm.errors.parent_phone }}</p>
                        </div>

                        <!-- Password -->
                        <div>
                            <label class="block text-xs font-semibold text-surface-700 dark:text-surface-300 mb-1" for="create-password">كلمة المرور</label>
                            <input id="create-password" v-model="createForm.password" type="password" minlength="8" maxlength="255" class="input w-full text-sm" placeholder="••••••••" required />
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
                                <select id="create-grade" v-model="createForm.grade_level" class="input w-full text-sm" :class="{ 'border-red-500': createForm.errors.grade_level }" :disabled="!filteredGradeLevels.length || createForm.processing" required>
                                    <option value="" disabled>اختر الصف...</option>
                                    <option v-for="gl in filteredGradeLevels" :key="gl.key" :value="gl.key">
                                        {{ gl.name }}
                                    </option>
                                </select>
                                <p v-if="createForm.errors.grade_level" class="mt-1 text-red-500 text-xs">{{ createForm.errors.grade_level }}</p>
                                <p v-else-if="!filteredGradeLevels.length" class="mt-1 text-amber-600 text-xs">لا توجد صفوف متاحة لهذه المرحلة حالياً.</p>
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
        <div v-if="mediaModalOpen" class="modal-overlay z-50" role="dialog" aria-modal="true" aria-label="إدارة صور المعلم">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="mediaModalOpen = false"></div>

            <div class="relative modal-panel-compact bg-white dark:bg-surface-900 rounded-2xl shadow-xl w-full max-w-md">
                <form @submit.prevent="uploadMedia">
                    <div class="p-6">
                        <h3 class="text-lg font-bold text-surface-900 dark:text-white">{{ mediaTitle }}</h3>
                        <p class="text-xs text-surface-500 mt-1">
                            {{ mediaUser?.name }} —
                            {{ mediaIsCover
                                ? 'تظهر في أعلى البروفايل العام للمعلم.'
                                : 'تظهر للطلاب في صفحات التصفح وبطاقة المعلم.' }}
                        </p>

                        <div class="mt-6">
                            <div
                                :class="mediaIsCover
                                    ? 'w-full aspect-[16/5] rounded-2xl'
                                    : 'w-20 h-20 rounded-full mx-auto'"
                                class="overflow-hidden bg-primary-100 dark:bg-primary-900 flex items-center justify-center border"
                            >
                                <img
                                    v-if="mediaPreview || existingMedia"
                                    :src="mediaPreview ?? existingMedia"
                                    :alt="mediaUser?.name || 'صورة المعلم'"
                                    class="w-full h-full object-cover"
                                />
                                <span v-else class="text-primary-700 font-bold text-xl">
                                    {{ mediaIsCover ? 'الصورة الافتراضية' : mediaUser?.name?.charAt(0) }}
                                </span>
                            </div>

                            <div class="min-w-0 mt-4">
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="text-xs text-surface-500 file:me-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 w-full"
                                    @change="onMediaPicked"
                                />
                                <p class="text-[10px] text-surface-400 mt-1.5">
                                    {{ mediaIsCover ? 'يفضل صورة أفقية. ' : '' }}JPG أو PNG أو WebP، بحد أقصى {{ mediaIsCover ? '8' : '4' }} ميجابايت. تُحوَّل تلقائياً إلى WebP.
                                </p>
                                <p v-if="mediaError" class="text-xs text-red-500 mt-1">
                                    {{ mediaError }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-2 px-6 py-4 bg-surface-50 dark:bg-surface-950/50">
                        <button
                            v-if="existingMedia"
                            type="button"
                            class="btn-ghost btn-sm text-red-500"
                            @click="removeMedia"
                        >حذف {{ mediaIsCover ? 'الغلاف' : 'الصورة' }}</button>
                        <span v-else></span>

                        <div class="flex gap-2">
                            <button type="button" class="btn-ghost btn-sm" @click="mediaModalOpen = false">إلغاء</button>
                            <button type="submit" class="btn-primary btn-sm" :disabled="mediaForm.processing || !selectedMediaFile">
                                {{ mediaForm.processing ? 'جارٍ الرفع...' : (mediaIsCover ? 'حفظ الغلاف' : 'حفظ الصورة') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </DashboardLayout>
</template>

