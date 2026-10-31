<script setup>
import { ref, computed, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';
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

const platformEmail = (prefix) => `${String(prefix ?? '').trim().toLowerCase()}@almagd.com`;

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

const editingGrade = ref('');

function openRoleModal(user) {
    editingUser.value  = user;
    selectedRole.value = user.roles?.[0]?.name ?? 'student';
    editingGrade.value = user.grade_level ?? '';
    roleModalOpen.value = true;
}

const assignableGradeLevels = computed(() =>
    (page.props.grade_levels ?? []).filter(g => g.key !== 'all'),
);

function updateGrade() {
    if (!editingUser.value || !editingGrade.value) return;
    router.patch(route('admin.users.grade', { id: editingUser.value.id }), {
        grade_level: editingGrade.value,
    }, {
        onSuccess: () => { roleModalOpen.value = false; },
        preserveScroll: true,
    });
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
        mediaForm.setError(mediaField.value, 'نوع الصورة أو حجمها غير مسموح. استخدم JPG أو PNG أو WebP ضمن الحجم المحدد.');
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

// ── Teacher Intro Video ──────────────────────────────────────────
const introVideoModalOpen = ref(false);
const introVideoUser = ref(null);
const videoSourceType = ref('upload'); // 'upload' | 'url'
const thumbnailSourceType = ref('upload'); // 'upload' | 'url'
const selectedVideoFile = ref(null);
const selectedVideoPreviewUrl = ref(null);
const selectedThumbnailFile = ref(null);
const selectedThumbnailPreviewUrl = ref(null);
const videoFileInput = ref(null);
const thumbnailFileInput = ref(null);

const introVideoForm = useForm({
    intro_video_file: null,
    intro_video_url: '',
    intro_video_thumbnail_file: null,
    intro_video_thumbnail: '',
    delete_video: false,
    delete_thumbnail: false,
});

function openIntroVideoModal(user) {
    introVideoUser.value = user;
    clearIntroVideoDrafts();

    introVideoForm.intro_video_file = null;
    introVideoForm.intro_video_url = user.intro_video_url ?? '';
    introVideoForm.intro_video_thumbnail_file = null;
    introVideoForm.intro_video_thumbnail = user.intro_video_thumbnail ?? '';
    introVideoForm.delete_video = false;
    introVideoForm.delete_thumbnail = false;
    introVideoForm.clearErrors();

    if (user.intro_video_url && !user.intro_video_url.startsWith('/storage/')) {
        videoSourceType.value = 'url';
    } else {
        videoSourceType.value = 'upload';
    }

    if (user.intro_video_thumbnail && !user.intro_video_thumbnail.startsWith('/storage/')) {
        thumbnailSourceType.value = 'url';
    } else {
        thumbnailSourceType.value = 'upload';
    }

    introVideoModalOpen.value = true;
}

function handleVideoFileSelect(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    if (file.size > 100 * 1024 * 1024) {
        alert('حجم ملف الفيديو يجب ألا يتجاوز 100 ميجابايت.');
        event.target.value = '';
        return;
    }

    selectedVideoFile.value = file;
    introVideoForm.intro_video_file = file;

    if (selectedVideoPreviewUrl.value) {
        URL.revokeObjectURL(selectedVideoPreviewUrl.value);
    }
    selectedVideoPreviewUrl.value = URL.createObjectURL(file);
}

function handleThumbnailFileSelect(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    if (file.size > 8 * 1024 * 1024) {
        alert('حجم صورة الغلاف يجب ألا يتجاوز 8 ميجابايت.');
        event.target.value = '';
        return;
    }

    selectedThumbnailFile.value = file;
    introVideoForm.intro_video_thumbnail_file = file;

    if (selectedThumbnailPreviewUrl.value) {
        URL.revokeObjectURL(selectedThumbnailPreviewUrl.value);
    }
    selectedThumbnailPreviewUrl.value = URL.createObjectURL(file);
}

function clearIntroVideoDrafts() {
    selectedVideoFile.value = null;
    if (selectedVideoPreviewUrl.value) {
        URL.revokeObjectURL(selectedVideoPreviewUrl.value);
        selectedVideoPreviewUrl.value = null;
    }
    selectedThumbnailFile.value = null;
    if (selectedThumbnailPreviewUrl.value) {
        URL.revokeObjectURL(selectedThumbnailPreviewUrl.value);
        selectedThumbnailPreviewUrl.value = null;
    }
    if (videoFileInput.value) videoFileInput.value.value = '';
    if (thumbnailFileInput.value) thumbnailFileInput.value.value = '';
}

function saveIntroVideo() {
    if (videoSourceType.value === 'url') {
        introVideoForm.intro_video_file = null;
    } else if (introVideoForm.intro_video_file) {
        introVideoForm.intro_video_url = '';
    }

    if (thumbnailSourceType.value === 'url') {
        introVideoForm.intro_video_thumbnail_file = null;
    } else if (introVideoForm.intro_video_thumbnail_file) {
        introVideoForm.intro_video_thumbnail = '';
    }

    introVideoForm.post(route('admin.users.intro-video', { id: introVideoUser.value.id }), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            introVideoModalOpen.value = false;
            clearIntroVideoDrafts();
        },
    });
}

async function removeIntroVideo() {
    const ok = await confirm({
        title: 'حذف الفيديو التعريفي',
        message: 'هل أنت متأكد من حذف الفيديو التعريفي الخاص بالمعلم ' + (introVideoUser.value?.name ?? '') + '؟',
        confirmLabel: 'حذف الفيديو',
        variant: 'danger',
    });
    if (!ok) return;

    router.delete(route('admin.users.intro-video.delete', { id: introVideoUser.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            introVideoModalOpen.value = false;
            clearIntroVideoDrafts();
        },
    });
}

function isDirectVideo(url) {
    if (!url) return false;
    return url.startsWith('/storage/') || url.startsWith('blob:') || /\.(mp4|webm|ogg|mov)(\?.*)?$/i.test(url);
}

function getEmbedVideoUrl(url) {
    if (!url) return '';
    const youtubeMatch = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([\w-]{11})/);
    if (youtubeMatch) return `https://www.youtube.com/embed/${youtubeMatch[1]}`;
    const vimeoMatch = url.match(/vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/[^\/]*\/videos\/|album\/(?:\d+\/)?video\/|video\/|)(\d+)/);
    if (vimeoMatch) return `https://player.vimeo.com/video/${vimeoMatch[1]}`;
    return url;
}

// ─── Admin Action / Audit History Modal ───
const auditModalOpen = ref(false);
const auditUser = ref(null);
const auditLoading = ref(false);
const auditEvents = ref([]);
const auditCreator = ref(null);

async function openAuditModal(user) {
    auditUser.value = user;
    auditCreator.value = user.creator ?? null;
    auditLoading.value = true;
    auditEvents.value = [];
    auditModalOpen.value = true;
    try {
        const res = await axios.get(route('admin.users.audit-logs', { id: user.id }));
        auditEvents.value = res.data.events || [];
        if (res.data.user?.creator) {
            auditCreator.value = res.data.user.creator;
        }
    } catch (err) {
        console.error('Failed to load audit logs:', err);
    } finally {
        auditLoading.value = false;
    }
}

function formatAuditDate(dateString) {
    if (!dateString) return '—';
    try {
        const d = new Date(dateString);
        return d.toLocaleString('ar-EG', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return dateString;
    }
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
                                            <div v-if="user.creator" class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-950/60 border border-primary-200/60 dark:border-primary-800/60 px-1.5 py-0.5 rounded mt-0.5" :title="`أضيف بواسطة الأدمن: ${user.creator.name} (${user.creator.email})`">
                                                <span>🛡️</span>
                                                <span>أنشئ بواسطة: {{ user.creator.name }}</span>
                                            </div>
                                            <div v-if="isTeacher(user)" class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-1">
                                                <button
                                                    type="button"
                                                    class="text-[10px] font-bold text-primary-600 dark:text-primary-400 hover:underline"
                                                    @click="openMediaModal(user, 'cover')"
                                                >
                                                    {{ user.profile_cover ? 'تعديل الغلاف' : '+ غلاف' }}
                                                </button>
                                                <button
                                                    v-if="!user.avatar"
                                                    type="button"
                                                    class="text-[10px] font-bold text-accent-600 dark:text-accent-400 hover:underline"
                                                    @click="openMediaModal(user, 'avatar')"
                                                >
                                                    + صورة
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 rounded transition-colors"
                                                    :class="user.intro_video_url
                                                        ? 'bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300 border border-primary-200 dark:border-primary-800'
                                                        : 'bg-surface-100 text-surface-600 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300'"
                                                    @click="openIntroVideoModal(user)"
                                                    :title="user.intro_video_url ? 'فيديو تعريفي مضاف — انقر للمشاهدة والتعديل' : 'إضافة فيديو تعريفي للمعلم'"
                                                >
                                                    <span>🎬</span>
                                                    <span>{{ user.intro_video_url ? 'الفيديو التعريفي' : '+ فيديو تعريفي' }}</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div>
                                        <span v-for="r in user.roles" :key="r.name"
                                              :class="roleColors[r.name]"
                                              class="text-xs me-1"
                                        >{{ roleLabel[r.name] }}</span>
                                        <span v-if="user.roles?.some(r => r.name === 'student') && user.grade_level"
                                              class="text-[11px] font-medium text-surface-500 dark:text-surface-400 block mt-1">
                                            {{ assignableGradeLevels.find(g => g.key === user.grade_level)?.name ?? user.grade_level }}
                                        </span>
                                    </div>
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
                                    <button
                                        v-if="isTeacher(user)"
                                        type="button"
                                        @click="openIntroVideoModal(user)"
                                        class="text-xs px-3 py-1.5 rounded-lg font-bold transition-colors flex items-center gap-1.5 shadow-sm"
                                        :class="user.intro_video_url
                                            ? 'bg-amber-100 text-amber-800 hover:bg-amber-200 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300 dark:border-amber-800'
                                            : 'bg-primary-50 text-primary-700 hover:bg-primary-100 dark:bg-primary-950/40 dark:text-primary-300'"
                                        title="رفع أو تعديل الفيديو التعريفي للمعلم"
                                    >
                                        <span>🎬</span>
                                        <span>{{ user.intro_video_url ? 'الفيديو التعريفي' : 'رفع فيديو تعريفي' }}</span>
                                    </button>

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

                                    <button type="button"
                                        @click="openAuditModal(user)"
                                        class="text-xs px-3 py-1.5 rounded-lg font-medium bg-purple-50 text-purple-700 hover:bg-purple-100 dark:bg-purple-950/40 dark:text-purple-300 dark:hover:bg-purple-900/50 transition-colors flex items-center gap-1"
                                        title="عرض سجل الإجراءات الإدارية لهذا المستخدم"
                                    >
                                        <span>📜</span>
                                        <span>سجل الإجراءات</span>
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

                    <div v-if="editingUser?.roles?.some(r => r.name === 'student') || selectedRole === 'student'" class="mt-5 pt-4 border-t border-surface-200 dark:border-surface-800">
                        <label class="block text-xs font-semibold text-surface-700 dark:text-surface-300 mb-1" for="edit-grade">الصف الدراسي للطالب</label>
                        <div class="flex gap-2">
                            <select id="edit-grade" v-model="editingGrade" class="input w-full text-sm">
                                <option value="" disabled>اختر الصف</option>
                                <option v-for="gl in assignableGradeLevels" :key="gl.key" :value="gl.key">{{ gl.name }}</option>
                            </select>
                            <button type="button" @click="updateGrade" :disabled="!editingGrade" class="btn-primary shrink-0">حفظ الصف</button>
                        </div>
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
                                <span class="shrink-0 px-3 text-xs font-bold text-primary-700 dark:text-primary-300">@almagd.com</span>
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

        <!-- ── Teacher Intro Video Modal ─────────────────────────────── -->
        <div v-if="introVideoModalOpen" class="modal-overlay z-50" role="dialog" aria-modal="true" aria-label="إدارة الفيديو التعريفي للمعلم">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="introVideoModalOpen = false"></div>

            <div class="relative modal-panel-compact bg-white dark:bg-surface-900 rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden border border-surface-200 dark:border-surface-800">
                <form @submit.prevent="saveIntroVideo">
                    <div class="p-6 max-h-[80vh] overflow-y-auto">
                        <div class="flex items-center justify-between pb-3 mb-4 border-b border-surface-100 dark:border-surface-800">
                            <div class="flex items-center gap-2">
                                <span class="text-2xl">🎬</span>
                                <div>
                                    <h3 class="text-lg font-bold text-surface-900 dark:text-white">الفيديو التعريفي للمعلم</h3>
                                    <p class="text-xs text-surface-500">
                                        المعلم: <strong class="text-surface-800 dark:text-surface-200">{{ introVideoUser?.name }}</strong>
                                    </p>
                                </div>
                            </div>
                            <button type="button" @click="introVideoModalOpen = false" class="text-surface-400 hover:text-surface-700 dark:hover:text-surface-200 text-lg font-bold">×</button>
                        </div>

                        <!-- Video Preview -->
                        <div v-if="selectedVideoPreviewUrl || introVideoForm.intro_video_url" class="mb-5">
                            <div class="text-xs font-semibold text-surface-600 dark:text-surface-300 mb-1.5 flex items-center justify-between">
                                <span class="flex items-center gap-1.5">
                                    <span>معاينة الفيديو:</span>
                                    <span v-if="selectedVideoPreviewUrl" class="badge-accent text-[10px]">ملف جديد محدد للرفع</span>
                                    <span v-else class="badge-green text-[10px]">الفيديو الحالي</span>
                                </span>
                                <a v-if="introVideoForm.intro_video_url && !selectedVideoPreviewUrl" :href="introVideoForm.intro_video_url" target="_blank" rel="noopener noreferrer" class="text-primary-600 dark:text-primary-400 hover:underline flex items-center gap-1 text-[11px]">
                                    فتح الرابط ↗
                                </a>
                            </div>
                            <div class="aspect-video w-full rounded-xl overflow-hidden bg-black border border-surface-200 dark:border-surface-700 shadow-inner relative">
                                <video
                                    v-if="selectedVideoPreviewUrl || isDirectVideo(introVideoForm.intro_video_url)"
                                    :src="selectedVideoPreviewUrl || introVideoForm.intro_video_url"
                                    controls
                                    class="w-full h-full object-contain"
                                ></video>
                                <iframe
                                    v-else-if="introVideoForm.intro_video_url"
                                    :src="getEmbedVideoUrl(introVideoForm.intro_video_url)"
                                    class="w-full h-full border-0"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen
                                ></iframe>
                            </div>
                        </div>

                        <!-- 1. Video Source Section -->
                        <div class="mb-5 bg-surface-50 dark:bg-surface-850 p-4 rounded-xl border border-surface-200 dark:border-surface-750">
                            <label class="block text-xs font-bold text-surface-800 dark:text-surface-100 mb-2">
                                📹 مصدر الفيديو التعريفي:
                            </label>

                            <!-- Mode Selector Tabs -->
                            <div class="flex gap-2 p-1 bg-surface-200/60 dark:bg-surface-800 rounded-lg mb-3">
                                <button
                                    type="button"
                                    class="flex-1 py-1.5 px-3 rounded-md text-xs font-bold transition-all flex items-center justify-center gap-1.5"
                                    :class="videoSourceType === 'upload' ? 'bg-white dark:bg-surface-900 text-primary-600 dark:text-primary-400 shadow-sm' : 'text-surface-600 dark:text-surface-400 hover:text-surface-900'"
                                    @click="videoSourceType = 'upload'"
                                >
                                    <span>📁</span>
                                    <span>رفع ملف فيديو مباشر</span>
                                </button>
                                <button
                                    type="button"
                                    class="flex-1 py-1.5 px-3 rounded-md text-xs font-bold transition-all flex items-center justify-center gap-1.5"
                                    :class="videoSourceType === 'url' ? 'bg-white dark:bg-surface-900 text-primary-600 dark:text-primary-400 shadow-sm' : 'text-surface-600 dark:text-surface-400 hover:text-surface-900'"
                                    @click="videoSourceType = 'url'"
                                >
                                    <span>🔗</span>
                                    <span>رابط فيديو خارجي</span>
                                </button>
                            </div>

                            <!-- Upload Mode -->
                            <div v-if="videoSourceType === 'upload'" class="space-y-2">
                                <input
                                    ref="videoFileInput"
                                    type="file"
                                    accept="video/mp4,video/webm,video/ogg,video/quicktime"
                                    class="hidden"
                                    @change="handleVideoFileSelect"
                                />

                                <div
                                    class="border-2 border-dashed border-surface-300 dark:border-surface-700 hover:border-primary-500 rounded-xl p-4 text-center cursor-pointer transition-colors bg-white dark:bg-surface-900"
                                    @click="videoFileInput?.click()"
                                >
                                    <div class="text-3xl mb-1">🎬</div>
                                    <div v-if="!selectedVideoFile" class="text-xs font-bold text-surface-700 dark:text-surface-200">
                                        انقر هنا لاختيار ملف فيديو من جهازك
                                    </div>
                                    <div v-else class="text-xs font-bold text-primary-600 dark:text-primary-400 flex items-center justify-center gap-2">
                                        <span>✓ {{ selectedVideoFile.name }}</span>
                                        <span class="text-surface-400 text-[11px]">({{ (selectedVideoFile.size / (1024 * 1024)).toFixed(1) }} MB)</span>
                                    </div>
                                    <p class="text-[11px] text-surface-400 mt-1">يدعم صيغ MP4, WebM, MOV بحجم يصل إلى 100 ميجابايت.</p>
                                </div>

                                <div v-if="selectedVideoFile" class="flex justify-end">
                                    <button type="button" class="text-xs text-red-500 hover:underline" @click="selectedVideoFile = null; selectedVideoPreviewUrl = null; introVideoForm.intro_video_file = null;">
                                        إلغاء الملف المختار
                                    </button>
                                </div>

                                <p v-if="introVideoForm.errors.intro_video_file" class="text-xs text-red-500 mt-1">
                                    {{ introVideoForm.errors.intro_video_file }}
                                </p>
                            </div>

                            <!-- URL Mode -->
                            <div v-else class="space-y-1">
                                <input
                                    id="intro_video_url"
                                    type="url"
                                    v-model="introVideoForm.intro_video_url"
                                    placeholder="https://www.youtube.com/watch?v=... أو رابط مباشر"
                                    class="input text-sm w-full font-mono text-xs bg-white dark:bg-surface-900"
                                    dir="ltr"
                                />
                                <p class="text-[11px] text-surface-400">يدعم روابط YouTube أو Vimeo أو أي رابط فيديو مباشر.</p>
                                <p v-if="introVideoForm.errors.intro_video_url" class="text-xs text-red-500 mt-1">
                                    {{ introVideoForm.errors.intro_video_url }}
                                </p>
                            </div>
                        </div>

                        <!-- 2. Thumbnail Section -->
                        <div class="mb-4 bg-surface-50 dark:bg-surface-850 p-4 rounded-xl border border-surface-200 dark:border-surface-750">
                            <label class="block text-xs font-bold text-surface-800 dark:text-surface-100 mb-2">
                                🖼️ صورة غلاف الفيديو (Thumbnail) — اختياري:
                            </label>

                            <!-- Mode Selector Tabs -->
                            <div class="flex gap-2 p-1 bg-surface-200/60 dark:bg-surface-800 rounded-lg mb-3">
                                <button
                                    type="button"
                                    class="flex-1 py-1.5 px-3 rounded-md text-xs font-bold transition-all flex items-center justify-center gap-1.5"
                                    :class="thumbnailSourceType === 'upload' ? 'bg-white dark:bg-surface-900 text-primary-600 dark:text-primary-400 shadow-sm' : 'text-surface-600 dark:text-surface-400 hover:text-surface-900'"
                                    @click="thumbnailSourceType = 'upload'"
                                >
                                    <span>📁</span>
                                    <span>رفع صورة من جهازك</span>
                                </button>
                                <button
                                    type="button"
                                    class="flex-1 py-1.5 px-3 rounded-md text-xs font-bold transition-all flex items-center justify-center gap-1.5"
                                    :class="thumbnailSourceType === 'url' ? 'bg-white dark:bg-surface-900 text-primary-600 dark:text-primary-400 shadow-sm' : 'text-surface-600 dark:text-surface-400 hover:text-surface-900'"
                                    @click="thumbnailSourceType = 'url'"
                                >
                                    <span>🔗</span>
                                    <span>رابط صورة خارجي</span>
                                </button>
                            </div>

                            <!-- Thumbnail Upload -->
                            <div v-if="thumbnailSourceType === 'upload'" class="space-y-2">
                                <input
                                    ref="thumbnailFileInput"
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    class="hidden"
                                    @change="handleThumbnailFileSelect"
                                />

                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex-1 border-2 border-dashed border-surface-300 dark:border-surface-700 hover:border-primary-500 rounded-xl p-3 text-center cursor-pointer transition-colors bg-white dark:bg-surface-900"
                                        @click="thumbnailFileInput?.click()"
                                    >
                                        <div v-if="!selectedThumbnailFile" class="text-xs font-bold text-surface-700 dark:text-surface-200">
                                            اختر صورة غلاف (JPG, PNG, WebP)
                                        </div>
                                        <div v-else class="text-xs font-bold text-primary-600 dark:text-primary-400">
                                            ✓ {{ selectedThumbnailFile.name }}
                                        </div>
                                        <p class="text-[10px] text-surface-400 mt-0.5">حد أقصى 8 ميجابايت (تُحول تلقائياً إلى WebP)</p>
                                    </div>

                                    <div v-if="selectedThumbnailPreviewUrl || introVideoForm.intro_video_thumbnail" class="w-20 h-14 rounded-lg overflow-hidden border border-surface-200 dark:border-surface-700 shrink-0 bg-surface-100">
                                        <img :src="selectedThumbnailPreviewUrl || introVideoForm.intro_video_thumbnail" alt="Thumbnail Preview" class="w-full h-full object-cover" />
                                    </div>
                                </div>

                                <p v-if="introVideoForm.errors.intro_video_thumbnail_file" class="text-xs text-red-500 mt-1">
                                    {{ introVideoForm.errors.intro_video_thumbnail_file }}
                                </p>
                            </div>

                            <!-- Thumbnail URL -->
                            <div v-else class="space-y-1">
                                <input
                                    id="intro_video_thumbnail"
                                    type="url"
                                    v-model="introVideoForm.intro_video_thumbnail"
                                    placeholder="https://example.com/thumbnail.jpg"
                                    class="input text-sm w-full font-mono text-xs bg-white dark:bg-surface-900"
                                    dir="ltr"
                                />
                                <p v-if="introVideoForm.errors.intro_video_thumbnail" class="text-xs text-red-500 mt-1">
                                    {{ introVideoForm.errors.intro_video_thumbnail }}
                                </p>
                            </div>
                        </div>

                        <!-- Upload Progress Bar -->
                        <div v-if="introVideoForm.progress" class="mb-4 bg-primary-50 dark:bg-primary-950/40 p-3 rounded-xl border border-primary-200 dark:border-primary-800">
                            <div class="flex items-center justify-between text-xs font-bold text-primary-700 dark:text-primary-300 mb-1">
                                <span>جارٍ رفع ملف الفيديو...</span>
                                <span>{{ introVideoForm.progress.percentage }}%</span>
                            </div>
                            <div class="w-full bg-primary-200 dark:bg-primary-900 h-2 rounded-full overflow-hidden">
                                <div class="bg-primary-600 h-full transition-all duration-300" :style="{ width: introVideoForm.progress.percentage + '%' }"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Actions -->
                    <div class="flex items-center justify-between gap-2 px-6 py-4 bg-surface-50 dark:bg-surface-950/50 border-t border-surface-100 dark:border-surface-800">
                        <button
                            v-if="introVideoUser?.intro_video_url"
                            type="button"
                            class="btn-ghost btn-sm text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40"
                            :disabled="introVideoForm.processing"
                            @click="removeIntroVideo"
                        >
                            حذف الفيديو
                        </button>
                        <span v-else></span>

                        <div class="flex gap-2">
                            <button type="button" class="btn-ghost btn-sm" :disabled="introVideoForm.processing" @click="introVideoModalOpen = false">
                                إلغاء
                            </button>
                            <button type="submit" class="btn-primary btn-sm flex items-center gap-1.5" :disabled="introVideoForm.processing">
                                <span v-if="introVideoForm.processing" class="animate-spin text-sm">⏳</span>
                                <span>{{ introVideoForm.processing ? 'جارٍ الرفع والحفظ...' : 'حفظ وتأكيد الفيديو' }}</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- User Audit History Modal -->
        <div v-if="auditModalOpen" class="modal-overlay z-50" role="dialog" aria-modal="true" aria-label="سجل الإجراءات الإدارية">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="auditModalOpen = false"></div>
            <div class="relative card p-6 w-full max-w-2xl max-h-[85vh] flex flex-col space-y-4 overflow-hidden shadow-2xl">
                <!-- Header -->
                <div class="flex items-start justify-between border-b border-surface-100 dark:border-surface-800 pb-3">
                    <div>
                        <h3 class="text-xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                            <span>📜</span>
                            <span>سجل الإجراءات الإدارية</span>
                        </h3>
                        <p class="text-xs text-surface-500 mt-1">
                            {{ auditUser?.name }} ({{ auditUser?.email }})
                        </p>
                    </div>
                    <button type="button" @click="auditModalOpen = false" class="btn-ghost btn-sm text-surface-400 hover:text-surface-600">✕</button>
                </div>

                <!-- Creator Info Banner -->
                <div v-if="auditCreator" class="p-3 rounded-xl bg-primary-50 dark:bg-primary-950/50 border border-primary-200 dark:border-primary-800 text-xs flex items-center gap-2 text-primary-900 dark:text-primary-200">
                    <span class="text-base">🛡️</span>
                    <div>
                        <span class="font-bold">منشئ الحساب:</span>
                        <span class="font-semibold ms-1">{{ auditCreator.name }}</span>
                        <span class="text-primary-600 dark:text-primary-400 ms-1 font-mono text-[11px]">({{ auditCreator.email }})</span>
                    </div>
                </div>

                <!-- Body / Timeline -->
                <div class="flex-1 overflow-y-auto space-y-3 pe-1 max-h-[55vh]">
                    <div v-if="auditLoading" class="p-8 text-center text-surface-400">
                        <div class="text-2xl mb-2 animate-pulse">⏳</div>
                        <p class="text-xs font-semibold">جارٍ تحميل سجل الإجراءات...</p>
                    </div>

                    <div v-else-if="!auditEvents.length" class="p-8 text-center text-surface-400">
                        <div class="text-3xl mb-2">📋</div>
                        <p class="text-sm font-semibold">لا توجد حركات إدارية مسجلة بعد لهذا الحساب.</p>
                        <p class="text-xs mt-1 text-surface-400">يتم تسجيل كل إجراء إداري جديد (تفعيل، تعطيل، تعديل دور، كلمة مرور، إلخ) باسم الأدمن المنفّذ.</p>
                    </div>

                    <div v-else class="space-y-2.5">
                        <div
                            v-for="ev in auditEvents"
                            :key="ev.id"
                            class="p-3.5 rounded-xl border border-surface-200 dark:border-surface-750 bg-white dark:bg-surface-850 hover:border-primary-300 dark:hover:border-primary-700 transition-colors"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="space-y-1">
                                    <div class="text-xs font-bold text-surface-900 dark:text-white flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-primary-500 shrink-0"></span>
                                        <span>{{ ev.description }}</span>
                                    </div>
                                    <div class="text-[11px] text-surface-500 dark:text-surface-400 flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-primary-700 dark:text-primary-300">
                                            بواسطة الأدمن: {{ ev.admin_name }}
                                        </span>
                                        <span v-if="ev.admin_email" class="text-surface-400 font-mono text-[10px]">({{ ev.admin_email }})</span>
                                        <span v-if="ev.ip_address" class="text-surface-400 text-[10px]">IP: {{ ev.ip_address }}</span>
                                    </div>
                                </div>
                                <span class="text-[10px] text-surface-400 whitespace-nowrap dir-ltr">
                                    {{ formatAuditDate(ev.created_at) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="border-t border-surface-100 dark:border-surface-800 pt-3 flex justify-end">
                    <button type="button" @click="auditModalOpen = false" class="btn-ghost btn-sm">إغلاق</button>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>

