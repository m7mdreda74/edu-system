<script setup>
import { ref, computed, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useAuthStore } from '@/stores/authStore';
import Icon from '@/Components/Icon.vue';
import NotificationBell from '@/Components/NotificationBell.vue';

import { useCartStore } from '@/stores/cartStore';

const authStore = useAuthStore();
const cartStore = useCartStore();
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
const isDark        = ref(document.documentElement.classList.contains('dark'));
const isSidebarCollapsed = ref(localStorage.getItem('sidebar_collapsed') === 'true');

function toggleDark() {
    isDark.value = !isDark.value;
    document.documentElement.classList.toggle('dark', isDark.value);
}

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
                    { label: 'المدفوعات',    icon: 'payments',  href: route('admin.payments'),  name: 'admin.payments' },
                    { label: 'تسوية الأرباح',  icon: 'earnings',  href: route('admin.payouts'),   name: 'admin.payouts' },
                ]
            },
            {
                title: 'المحتوى التعليمي',
                links: [
                    { label: 'المراحل الدراسية',icon: 'courses',   href: route('admin.grade-levels'),name: 'admin.grade-levels' },
                    { label: 'الكورسات',     icon: 'courses',   href: route('admin.courses'),   name: 'admin.courses' },
                    { label: 'المواد الدراسية',icon: 'globe',     href: route('admin.subjects'),  name: 'admin.subjects' },
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
                    { label: 'كورساتي',      icon: 'courses',   href: route('teacher.courses'),   name: 'teacher.courses' },
                    { label: 'الحصص المباشرة',icon: 'live',      href: route('teacher.live-sessions'), name: 'teacher.live-sessions' },
                    { label: 'الرسائل',      icon: 'chat',      href: route('chat.index'),       name: 'chat.index' },
                    { label: 'أرباحي',       icon: 'earnings',  href: '#',                       name: 'teacher.earnings' },
                ]
            }
        ];
    } else if (authStore.isParent) {
        return [
            {
                title: 'لوحة ولي الأمر',
                links: [
                    { label: 'لوحة المتابعة', icon: 'dashboard', href: route('parent.dashboard'), name: 'parent.dashboard' },
                ]
            }
        ];
    } else {
        return [
            {
                title: 'لوحة الطالب',
                links: [
                    { label: 'الرئيسية', icon: 'dashboard', href: route('dashboard'),         name: 'dashboard' },
                    { label: 'دوراتي',   icon: 'courses',   href: route('courses.index'),     name: 'courses' },
                    { label: 'الرسائل',  icon: 'chat',      href: route('chat.index'),        name: 'chat.index' },
                    { label: 'شهاداتي',  icon: 'student',   href: '#',                        name: 'certificates' },
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
        
        <!-- ── Mobile Sidebar Overlay ──────────────────────────────────────── -->
        <div v-if="isSidebarOpen" @click="isSidebarOpen = false"
             class="fixed inset-0 bg-black/50 z-40 lg:hidden backdrop-blur-sm transition-opacity"></div>

        <!-- ── Sidebar ─────────────────────────────────────────────────────── -->
        <aside :class="[
            'fixed inset-y-0 start-0 z-50 bg-white dark:bg-surface-950 border-e border-surface-200 dark:border-surface-800 shadow-2xl transition-all duration-300 ease-in-out lg:static lg:translate-x-0',
            isSidebarOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0',
            isSidebarCollapsed ? 'w-64 lg:w-20' : 'w-64 lg:w-64'
        ]">
            <div class="h-full flex flex-col">
                <!-- Logo area -->
                <div class="h-16 flex items-center border-b border-surface-200 dark:border-surface-800 transition-all duration-300"
                     :class="isSidebarCollapsed ? 'px-0 justify-center' : 'px-6 justify-between'">
                    <Link :href="route('home')" class="flex items-center group" :class="isSidebarCollapsed ? 'gap-0' : 'gap-3'">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary-500 to-primary-700
                                    flex items-center justify-center shadow-glow-primary shrink-0">
                                <span class="text-white font-bold text-xs">ت</span>
                        </div>
                        <span v-show="!isSidebarCollapsed" class="text-lg font-bold text-surface-900 dark:text-white tracking-wide group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">التفوق</span>
                    </Link>
                    <button v-show="!isSidebarCollapsed" @click="isSidebarOpen = false" class="lg:hidden text-surface-500 hover:text-surface-800 dark:text-surface-400 dark:hover:text-white">
                        ✕
                    </button>
                </div>

                <!-- User Info Small -->
                <Link :href="route('profile.edit')" class="flex items-center border-b border-surface-200 dark:border-surface-800 hover:bg-surface-50 dark:hover:bg-surface-900 transition-all duration-300 group"
                      :class="isSidebarCollapsed ? 'p-4 justify-center' : 'p-6 gap-3'">
                    <div class="avatar-md bg-surface-100 dark:bg-surface-800 border-2 border-surface-200 dark:border-surface-700 group-hover:border-primary-500 transition-colors shrink-0">
                        <img v-if="authStore.user?.avatar" :src="authStore.user.avatar" class="w-full h-full object-cover">
                        <span v-else class="text-surface-600 dark:text-surface-300 font-bold">{{ authStore.user?.name?.charAt(0) }}</span>
                    </div>
                    <div v-show="!isSidebarCollapsed" class="overflow-hidden flex-1">
                        <div class="text-sm font-bold text-surface-900 dark:text-white truncate group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">{{ authStore.user?.name }}</div>
                        <div class="text-xs text-primary-600 dark:text-primary-400 truncate">{{ authStore.isAdmin ? 'مدير المنصة' : (authStore.isTeacher ? 'مدرس' : 'طالب') }}</div>
                    </div>
                    <Icon v-show="!isSidebarCollapsed" name="arrowLeft" class="w-4 h-4 text-surface-450 dark:text-surface-500 group-hover:translate-x-[-3px] transition-transform rtl-flip shrink-0" />
                </Link>

                <!-- Navigation -->
                <nav class="flex-1 overflow-y-auto py-6 space-y-6 transition-all duration-300" :class="isSidebarCollapsed ? 'px-2' : 'px-3'">
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
                                class="flex items-center rounded-xl text-sm font-semibold transition-all duration-300 transform"
                                :class="[
                                    isActive(link.name)
                                        ? 'bg-primary-500/10 text-primary-700 dark:text-primary-300 font-bold ' + (!isSidebarCollapsed ? 'border-r-4 border-accent-500 rounded-l-xl rounded-r-none pr-3' : 'border border-primary-500/20')
                                        : 'text-surface-600 dark:text-surface-400 hover:bg-surface-100 dark:hover:bg-surface-800 hover:text-primary-600 dark:hover:text-white',
                                    isSidebarCollapsed ? 'justify-center p-2.5' : 'gap-3 px-4 py-2.5 hover:-translate-x-1'
                                ]"
                                :title="isSidebarCollapsed ? link.label : ''"
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
                          class="w-full flex items-center justify-center rounded-xl text-sm font-semibold text-red-500 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-all duration-350"
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
                    <button @click="isSidebarOpen = true" class="lg:hidden btn-ghost p-2 rounded-lg text-surface-600 dark:text-surface-300">
                        ☰
                    </button>
                    <!-- Collapse Desktop Sidebar Toggle -->
                    <button @click="toggleSidebarCollapse" class="hidden lg:flex btn-ghost p-2 rounded-lg text-surface-600 dark:text-surface-300 transition-all duration-200 active:scale-90" title="طي/توسيع القائمة الجانبية">
                        <Icon name="menu" class="w-5 h-5" />
                    </button>
                    <!-- View Live Site -->
                    <Link :href="route('home')" class="hidden sm:flex items-center gap-2 text-sm font-medium text-surface-500 hover:text-primary-600 dark:text-surface-400 dark:hover:text-primary-400 transition-colors">
                        <Icon name="globe" class="w-4 h-4" />
                        <span>عرض المنصة</span>
                    </Link>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Cart -->
                    <Link v-if="cartStore.hasItem && cartStore.item?.slug" :href="route('checkout.show', { slug: cartStore.item.slug })"
                        class="relative btn-ghost p-2 rounded-lg"
                    >
                        <Icon name="cart" class="w-5 h-5 text-surface-600 dark:text-surface-300" />
                        <span class="absolute -top-1 -start-1 w-4 h-4 bg-accent-500 text-white
                                     rounded-full text-xs flex items-center justify-center font-bold">
                            1
                        </span>
                    </Link>

                    <!-- Notification Bell -->
                    <NotificationBell />

                    <!-- Dark mode toggle -->
                    <button @click="toggleDark" class="btn-ghost p-2 rounded-lg text-lg bg-surface-100 dark:bg-surface-800 transition-all duration-300 transform active:scale-95">
                        <Icon v-if="isDark" name="sun" class="w-5 h-5 text-amber-500" />
                        <Icon v-else name="moon" class="w-5 h-5 text-indigo-500" />
                    </button>
                </div>
            </header>

            <!-- Flash Messages -->
            <div class="fixed top-20 left-6 z-55 w-full max-w-sm px-4 space-y-2 pointer-events-none">
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
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-transparent p-4 lg:p-8">
                <div class="max-w-7xl mx-auto">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
