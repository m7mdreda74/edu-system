<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    users:   { type: Object, required: true },  // paginated
    filters: { type: Object, default: () => ({}) },
});

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

const roleLabel  = { admin: 'مدير', teacher: 'مدرس', student: 'طالب' };
const roleColors = { admin: 'badge-accent', teacher: 'badge-primary', student: 'badge-gray' };
</script>

<template>
    <DashboardLayout>
        <Head title="إدارة المستخدمين" />

        <div class="container-app px-4 py-10">

            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <Icon name="users" class="w-8 h-8 text-primary-500" />
                        <span>المستخدمون</span>
                    </h1>
                    <p class="text-surface-500 dark:text-surface-400 mt-1">
                        {{ users.total?.toLocaleString('ar') }} مستخدم مسجّل
                    </p>
                </div>
                <Link :href="route('admin.dashboard')" class="btn-ghost">← الداشبورد</Link>
            </div>

            <!-- Filters -->
            <div class="card p-4 flex flex-wrap gap-3 mb-6">
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
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-surface-50 dark:bg-surface-800 border-b border-surface-200 dark:border-surface-700">
                            <tr>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">المستخدم</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">الدور</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">التسجيلات</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">الحالة</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">إجراء</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-700">
                            <tr v-for="user in users.data" :key="user.id"
                                class="hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="avatar-md bg-primary-100 dark:bg-primary-900 flex-shrink-0">
                                            <img v-if="user.avatar" :src="user.avatar" :alt="user.name"
                                                 class="w-full h-full object-cover rounded-full" />
                                            <span v-else class="text-primary-700 font-bold text-sm">
                                                {{ user.name?.charAt(0) }}
                                            </span>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-surface-800 dark:text-white">{{ user.name }}</div>
                                            <div class="text-xs text-surface-400">{{ user.email }}</div>
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
                                    {{ user.enrollments_count }}
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
                     class="p-4 border-t border-surface-100 dark:border-surface-700 flex gap-2 justify-center flex-wrap">
                    <Link
                        v-for="link in users.links" :key="link.label"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        class="px-3 py-1.5 rounded-lg text-sm border transition-colors"
                        :class="link.active
                            ? 'bg-primary-600 text-white border-primary-600'
                            : link.url
                                ? 'border-surface-200 dark:border-surface-600 text-surface-600 dark:text-surface-300 hover:bg-surface-50 dark:hover:bg-surface-700'
                                : 'opacity-40 cursor-not-allowed border-surface-200 dark:border-surface-600'"
                    />
                </div>
            </div>
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
                                <div class="text-xs text-surface-500">مستخدم عادي يدرس الكورسات</div>
                            </div>
                        </label>
                        
                        <label class="flex items-center gap-3 p-3 border border-surface-200 dark:border-surface-700 rounded-xl cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors" :class="{'border-primary-500 bg-primary-50 dark:bg-primary-900/20': selectedRole === 'teacher'}">
                            <input type="radio" v-model="selectedRole" value="teacher" class="text-primary-600 focus:ring-primary-500">
                            <div>
                                <div class="font-bold text-surface-800 dark:text-white">مدرس</div>
                                <div class="text-xs text-surface-500">يمكنه إنشاء وإدارة كورساته</div>
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
    </DashboardLayout>
</template>
