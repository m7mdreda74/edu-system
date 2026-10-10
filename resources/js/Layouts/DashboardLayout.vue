<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useAuthStore } from '@/stores/authStore';
import Icon from '@/Components/Icon.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import ValidationErrorBanner from '@/Components/ValidationErrorBanner.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';


const authStore = useAuthStore();
const page      = usePage();

// Auto-hide flash messages after 3 seconds
watch(() => page.props.flash, (newFlash) => {
    if (newFlash?.success || newFlash?.error) {
        setTimeout(() => {
            page.props.flash.success = null;
            page.props.flash.error = null;
        }, 3000);
    }
}, { deep: true, immediate: true });

const isSidebarOpen = ref(false);
const isDark        = ref(false);
const isSidebarCollapsed = ref(localStorage.getItem('sidebar_collapsed') === 'true');
let previousBodyOverflow = '';

onMounted(() => {
    document.documentElement.classList.add('dark');
    localStorage.setItem('theme', 'dark');
});

function closeSidebar() {
    isSidebarOpen.value = false;
}

// Layout components persist across Inertia visits, so close the mobile drawer
// explicitly when a navigation finishes instead of leaving the overlay open.
watch(() => page.url, closeSidebar);

watch(isSidebarOpen, (open) => {
    if (typeof window === 'undefined' || window.innerWidth >= 1024) return;

    if (open) {
        previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
    } else if (!document.querySelector('.modal-overlay')) {
        document.body.style.overflow = previousBodyOverflow;
        previousBodyOverflow = '';
    }
});

onUnmounted(() => {
    if (!document.querySelector('.modal-overlay')) {
        document.body.style.overflow = previousBodyOverflow;
    }
});



function toggleSidebarCollapse() {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;
    localStorage.setItem('sidebar_collapsed', isSidebarCollapsed.value ? 'true' : 'false');
}

// Navigation generator grouped by categories
const menuGroups = computed(() => {
    if (authStore.isAdmin) {
        return [
            {
                title: 'الرئيسية والمالية',
                links: [
                    { label: 'الرئيسية',     icon: 'dashboard', href: route('admin.dashboard'), name: 'admin.dashboard' },
                    { label: 'التقارير',      icon: 'chart',     href: route('admin.reports'),   name: 'admin.reports' },
                    { label: 'المدفوعات',    icon: 'payments',  href: route('admin.payments'),  name: 'admin.payments' },
                    { label: 'تسوية الأرباح',  icon: 'earnings',  href: route('admin.payouts'),   name: 'admin.payouts' },
                ]
            },
            {
                title: 'المحتوى التعليمي',
                links: [
                    { label: 'المراحل الدراسية',icon: 'courses',   href: route('admin.grade-levels'),name: 'admin.grade-levels' },
                    { label: 'المواد الدراسية',icon: 'globe',     href: route('admin.subjects'),  name: 'admin.subjects' },
                    { label: 'الفصول الدراسية',icon: 'calendar',  href: route('admin.academic-terms'), name: 'admin.academic-terms' },
                    { label: 'مجموعات التدريس',icon: 'clock',     href: route('admin.teaching-groups'), name: 'admin.teaching-groups' },
                    { label: 'اعتذارات الحصص', icon: 'calendar',  href: route('admin.session-apologies'), name: 'admin.session-apologies' },
                    { label: 'الاشتراكات',    icon: 'student',   href: route('admin.subscriptions'), name: 'admin.subscriptions' },
                    { label: 'التقييمات',      icon: 'chat',      href: route('admin.reviews'),   name: 'admin.reviews' },
                    { label: 'رسائل أولياء الأمور', icon: 'chat', href: route('chat.index'), name: 'chat.index' },
                ]
            },
            {
                title: 'المستخدمون والتسويق',
                links: [
                    { label: 'المستخدمون',   icon: 'users',     href: route('admin.users'),     name: 'admin.users' },
                    { label: 'كوبونات الخصم',  icon: 'payments',  href: route('admin.coupons'),   name: 'admin.coupons' },
                    { label: 'صفحات الموقع',   icon: 'courses',   href: route('admin.site-pages'),name: 'admin.site-pages' },
                    { label: 'إعدادات المنصة',icon: 'settings',  href: route('admin.settings'),  name: 'admin.settings' },
                ]
            }
        ];
    } else if (authStore.isTeacher) {
        return [
            {
                title: 'لوحة المعلم',
                links: [
                    { label: 'الرئيسية',     icon: 'dashboard', href: route('teacher.dashboard'), name: 'teacher.dashboard' },
                    { label: 'مجموعاتي',     icon: 'courses',   href: route('teacher.groups.index'), name: 'teacher.groups.index' },
                    { label: 'طلابي',        icon: 'student',   href: route('teacher.students.index'), name: 'teacher.students.index' },
                    { label: 'الخطة الأكاديمية', icon: 'book', href: route('teacher.teaching-schedule'), name: 'teacher.teaching-schedule' },
                    { label: 'الحصص المباشرة',icon: 'live',      href: route('teacher.live-sessions'), name: 'teacher.live-sessions' },
                    { label: 'الرسائل',      icon: 'chat',      href: route('chat.index'),       name: 'chat.index' },
                ]
            }
        ];
    } else if (authStore.isParent) {
        return [
            {
                title: 'لوحة ولي الأمر',
                links: [
                    { label: 'لوحة المتابعة', icon: 'dashboard', href: route('parent.dashboard'), name: 'parent.dashboard' },
                    { label: 'الرسائل', icon: 'chat', href: route('chat.index'), name: 'chat.index' },
                ]
            }
        ];
    } else {
        return [
            {
                title: 'لوحة الطالب',
                links: [
                    { label: 'الرئيسية', icon: 'dashboard', href: route('dashboard'),          name: 'dashboard' },
                    { label: 'مواد صفي', icon: 'book',      href: route('student.my-grade'),   name: 'student.my-grade' },
                    { label: 'حصصي',     icon: 'courses',   href: route('student.my-classes'), name: 'student.my-classes' },
                    { label: 'جدول حصصي', icon: 'calendar', href: route('student.schedule'),   name: 'student.schedule' },
                    { label: 'الرسائل',  icon: 'chat',      href: route('chat.index'),         name: 'chat.index' },
                ]
            }
        ];
    }
});

const isActive = (name) => {
    const url = page.url.toLowerCase();
    const compName = page.component?.toLowerCase() || '';
    const nameLower = name.toLowerCase();
    
    if (nameLower === 'dashboard') {
        return url === '/dashboard' || compName === 'dashboard' || compName === 'student/dashboard';
    }
    
    const parts = nameLower.split('.');
    if (parts.length === 1) {
        return url.includes(parts[0]) || compName.includes(parts[0]);
    }
    
    const group = parts[0];
    const sub = parts[1];
    return compName.startsWith(group) && (url.includes(sub) || compName.includes(sub));
};
</script>

<template>
    <div class="min-h-screen bg-transparent flex" dir="rtl" lang="ar">
        <!-- Global Confirm Dialog -->
        <ConfirmDialog />
        
        <!-- ── Mobile Sidebar Overlay ──────────────────────────────────────── -->
        <div v-if="isSidebarOpen" @click="closeSidebar"
             class="fixed inset-0 bg-black/50 z-40 lg:hidden backdrop-blur-sm transition-opacity"
             aria-hidden="true"></div>

        <!-- ── Sidebar ─────────────────────────────────────────────────────── -->
        <aside id="dashboard-sidebar" :class="[
            'fixed inset-y-0 start-0 z-50 bg-white dark:bg-surface-950 border-e border-surface-200 dark:border-surface-800 shadow-2xl transition-all duration-300 ease-in-out lg:static lg:translate-x-0',
            isSidebarOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0',
            isSidebarCollapsed ? 'w-64 lg:w-20' : 'w-64 lg:w-64'
        ]">
            <div class="h-full flex flex-col">
                <!-- Logo area -->
                <div class="h-16 flex items-center border-b border-surface-200 dark:border-surface-800 transition-all duration-300"
                     :class="isSidebarCollapsed ? 'px-0 justify-center' : 'px-6 justify-between'">
                    <Link :href="route('home')" prefetch cache-for="30s" class="flex items-center group" :class="isSidebarCollapsed ? 'gap-0' : 'gap-3'">
                        <img src="/images/logo-icon.png" alt="بوابة المجد التعليمية" class="w-9 h-9 object-contain shrink-0 drop-shadow-sm transition-transform duration-300 group-hover:scale-105" />
                        <span v-show="!isSidebarCollapsed" class="text-lg font-bold text-surface-900 dark:text-white tracking-wide group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">بوابة المجد التعليمية</span>
                    </Link>
                    <button v-show="!isSidebarCollapsed" type="button" @click="closeSidebar" class="lg:hidden text-surface-500 hover:text-surface-800 dark:text-surface-400 dark:hover:text-white" aria-label="إغلاق القائمة الجانبية">
                        ✕
                    </button>
                </div>

                <!-- User Info Small -->
                <Link :href="route('profile.edit')" prefetch cache-for="30s" class="flex items-center border-b border-surface-200 dark:border-surface-800 hover:bg-surface-50 dark:hover:bg-surface-900 transition-all duration-300 group"
                      :class="isSidebarCollapsed ? 'p-4 justify-center' : 'p-6 gap-3'">
                    <div class="avatar-md bg-surface-100 dark:bg-surface-800 border-2 border-surface-200 dark:border-surface-700 group-hover:border-primary-500 transition-colors shrink-0">
                        <img v-if="authStore.user?.avatar" :src="authStore.user.avatar" :alt="authStore.user.name" class="w-full h-full object-cover">
                        <span v-else class="text-surface-600 dark:text-surface-300 font-bold">{{ authStore.user?.name?.charAt(0) }}</span>
                    </div>
                    <div v-show="!isSidebarCollapsed" class="overflow-hidden flex-1">
                        <div class="text-sm font-bold text-surface-900 dark:text-white truncate group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">{{ authStore.user?.name }}</div>
                        <div class="text-xs text-primary-600 dark:text-primary-400 truncate">{{ authStore.isAdmin ? 'مدير المنصة' : (authStore.isTeacher ? 'مدرس' : (authStore.isParent ? 'ولي أمر' : 'طالب')) }}</div>
                    </div>
                    <Icon v-show="!isSidebarCollapsed" name="arrowLeft" class="w-4 h-4 text-surface-450 dark:text-surface-500 group-hover:translate-x-[-3px] transition-transform rtl-flip shrink-0" />
                </Link>

                <!-- Navigation -->
                <nav aria-label="التنقل الرئيسي" class="flex-1 overflow-y-auto py-6 space-y-6 transition-all duration-300" :class="isSidebarCollapsed ? 'px-2' : 'px-3'">
                    <div v-for="group in menuGroups" :key="group.title" class="space-y-2">
                        <!-- Group Title -->
                        <div v-show="!isSidebarCollapsed" class="px-4 text-[10px] font-bold text-surface-400 dark:text-surface-500 uppercase tracking-wider">
                            {{ group.title }}
                        </div>
                        <div v-show="isSidebarCollapsed" class="border-b border-surface-100 dark:border-surface-800 mx-2 my-2"></div>
                        
                        <div class="space-y-1">
                            <Link
                                v-for="link in group.links"
                                :key="link.name"
                                :href="link.href"
                                prefetch
                                cache-for="30s"
                                @click="closeSidebar"
                                class="flex items-center rounded-xl text-sm font-semibold transition-all duration-300 transform"
                                :class="[
                                    isActive(link.name)
                                        ? 'bg-primary-500/10 text-primary-700 dark:text-primary-300 font-bold ' + (!isSidebarCollapsed ? 'border-r-4 border-accent-500 rounded-l-xl rounded-r-none pr-3' : 'border border-primary-500/20')
                                        : 'text-surface-600 dark:text-surface-400 hover:bg-surface-100 dark:hover:bg-surface-800 hover:text-primary-600 dark:hover:text-white',
                                    isSidebarCollapsed ? 'justify-center p-2.5' : 'gap-3 px-4 py-2.5 hover:-translate-x-1'
                                ]"
                                :title="isSidebarCollapsed ? link.label : ''"
                                :aria-current="isActive(link.name) ? 'page' : undefined"
                            >
                                <Icon :name="link.icon" class="w-5 h-5 transition-transform duration-300 shrink-0" />
                                <span v-show="!isSidebarCollapsed">{{ link.label }}</span>
                            </Link>
                        </div>
                    </div>
                </nav>

                <!-- Logout -->
                <div class="p-4 border-t border-surface-200 dark:border-surface-800">
                    <Link :href="route('logout')" method="post" as="button"
                          class="w-full flex items-center justify-center rounded-xl text-sm font-semibold text-red-500 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-all duration-300"
                          :class="isSidebarCollapsed ? 'p-2.5' : 'gap-2 px-4 py-2.5'"
                          :title="isSidebarCollapsed ? 'تسجيل الخروج' : ''">
                        <Icon name="logout" class="w-4 h-4 shrink-0" />
                        <span v-show="!isSidebarCollapsed">تسجيل الخروج</span>
                    </Link>
                </div>
            </div>
        </aside>

        <!-- ── Main Content Area ─────────────────────────────────────────── -->
        <div class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
            
            <!-- Topbar -->
            <header class="h-16 bg-white dark:bg-surface-900 border-b border-surface-200 dark:border-surface-800 flex items-center justify-between px-4 lg:px-8 z-10 shrink-0">
                <div class="flex items-center gap-3">
                    <button type="button" @click="isSidebarOpen = true" class="lg:hidden btn-ghost p-2 rounded-lg text-surface-600 dark:text-surface-300" aria-label="فتح القائمة الجانبية" :aria-expanded="isSidebarOpen" aria-controls="dashboard-sidebar">
                        ☰
                    </button>
                    <!-- Collapse Desktop Sidebar Toggle -->
                    <button type="button" @click="toggleSidebarCollapse" class="hidden lg:flex btn-ghost p-2 rounded-lg text-surface-600 dark:text-surface-300 transition-all duration-200 active:scale-90" title="طي/توسيع القائمة الجانبية" aria-label="طي أو توسيع القائمة الجانبية" :aria-expanded="!isSidebarCollapsed">
                        <Icon name="menu" class="w-5 h-5" />
                    </button>
                    <!-- View Live Site -->
                    <Link :href="route('home')" prefetch cache-for="30s" class="hidden sm:flex items-center gap-2 text-sm font-medium text-surface-500 hover:text-primary-600 dark:text-surface-400 dark:hover:text-primary-400 transition-colors">
                        <Icon name="globe" class="w-4 h-4" />
                        <span>عرض المنصة</span>
                    </Link>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Notification Bell -->
                    <NotificationBell />


                </div>
            </header>

            <!-- Flash and validation messages -->
            <div class="fixed top-20 end-6 z-[55] w-full max-w-sm px-4 space-y-2 pointer-events-none">
                <ValidationErrorBanner />
                <Transition enter-active-class="transition ease-out duration-300 transform"
                            enter-from-class="-translate-y-4 opacity-0 scale-95"
                            enter-to-class="translate-y-0 opacity-100 scale-100"
                            leave-active-class="transition ease-in duration-200 transform"
                            leave-from-class="translate-y-0 opacity-100 scale-100"
                            leave-to-class="-translate-x-4 opacity-0 scale-95">
                    <div v-if="$page.props.flash?.success" class="pointer-events-auto flex items-center gap-3 bg-surface-900 border border-surface-800 text-white px-5 py-4 rounded-2xl shadow-glow-primary" dir="rtl">
                        <div class="w-8 h-8 rounded-full bg-green-500/10 text-green-500 flex items-center justify-center flex-shrink-0">
                            <Icon name="success" class="w-5 h-5 text-green-500 shrink-0" />
                        </div>
                        <div>
                            <h4 class="font-bold text-xs">تمت العملية بنجاح</h4>
                            <p class="text-[10px] text-surface-400 mt-0.5">{{ $page.props.flash.success }}</p>
                        </div>
                    </div>
                </Transition>
                <Transition enter-active-class="transition ease-out duration-300 transform"
                            enter-from-class="-translate-y-4 opacity-0 scale-95"
                            enter-to-class="translate-y-0 opacity-100 scale-100"
                            leave-active-class="transition ease-in duration-200 transform"
                            leave-from-class="translate-y-0 opacity-100 scale-100"
                            leave-to-class="-translate-x-4 opacity-0 scale-95">
                    <div v-if="$page.props.flash?.error" class="pointer-events-auto flex items-center gap-3 bg-surface-900 border border-surface-800 text-white px-5 py-4 rounded-2xl shadow-glow-primary" dir="rtl">
                        <div class="w-8 h-8 rounded-full bg-red-500/10 text-red-500 flex items-center justify-center flex-shrink-0">
                            <Icon name="error" class="w-5 h-5 text-red-500 shrink-0" />
                        </div>
                        <div>
                            <h4 class="font-bold text-xs">خطأ في العملية</h4>
                            <p class="text-[10px] text-surface-400 mt-0.5">{{ $page.props.flash.error }}</p>
                        </div>
                    </div>
                </Transition>
            </div>

            <!-- Page Content -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-transparent p-2 md:p-4 lg:p-6">
                <div class="dashboard-slot w-full min-h-full flex flex-col">
                    <slot />
                </div>
            </main>

            <!-- ── Floating WhatsApp Widget (Direct Support) ── -->
            <div v-if="$page.props.settings?.whatsapp_url"
                 class="fixed bottom-4 left-4 sm:bottom-6 sm:left-6 z-40 flex flex-col items-center gap-1.5 pointer-events-none"
            >
                <span class="hidden sm:block bg-surface-900 text-white text-[10px] font-bold px-3 py-1.5 rounded-xl shadow-lg border border-surface-800 pointer-events-none select-none">
                    واتساب الدعم
                </span>
                <a :href="$page.props.settings.whatsapp_url" 
                   target="_blank"
                   rel="noopener noreferrer"
                   class="pointer-events-auto w-12 h-12 rounded-full bg-emerald-500 hover:bg-emerald-600 flex items-center justify-center text-white shadow-lg transition-transform duration-300 transform hover:scale-110 shadow-glow-accent"
                   aria-label="التواصل مع الدعم عبر واتساب"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" class="w-6 h-6 fill-current text-white"><path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L32 503l138.2-36.2c32.5 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-82.1 21.5 21.9-80-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"/></svg>
                </a>
            </div>
        </div>
    </div>
</template>
