<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useAuthStore } from '@/stores/authStore';
import { useCartStore } from '@/stores/cartStore';
import Icon from '@/Components/Icon.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import axios from 'axios';

const authStore = useAuthStore();
const cartStore = useCartStore();
const page      = usePage();

const mobileMenuOpen = ref(false);
const isDark         = ref(false);

const searchQuery = ref('');
const searchResults = ref([]);

async function onSearchInput() {
    if (searchQuery.value.length < 2) {
        searchResults.value = [];
        return;
    }

    try {
        const res = await axios.get(route('courses.autocomplete'), {
            params: { q: searchQuery.value }
        });
        searchResults.value = res.data;
    } catch (e) {
        console.warn('Autocomplete search failed:', e.message);
    }
}

function clearSearch() {
    searchQuery.value = '';
    searchResults.value = [];
}

function toggleDark() {
    isDark.value = !isDark.value;
    document.documentElement.classList.toggle('dark', isDark.value);
}

const navLinks = computed(() => {
    const raw = page.props.settings?.navbar_links;
    if (raw) {
        try {
            const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
            if (Array.isArray(parsed) && parsed.length > 0) {
                return parsed;
            }
        } catch (e) {}
    }
    return [
        { label: 'الرئيسية',     href: route('home'),              name: 'home' },
        { label: 'الكورسات',     href: route('courses.index'),     name: 'courses.index' },
        { label: 'من نحن',       href: route('about'),             name: 'about' },
        { label: 'نتائج طلابنا',  href: route('students_results'),  name: 'students_results' },
        { label: 'تطبيقاتنا',    href: route('our_apps'),          name: 'our_apps' },
        { label: 'تواصل معنا',   href: route('contact'),           name: 'contact' },
    ];
});

const footerLinks = computed(() => {
    const raw = page.props.settings?.footer_links;
    if (raw) {
        try {
            const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
            if (Array.isArray(parsed) && parsed.length > 0) {
                return parsed;
            }
        } catch (e) {}
    }
    return [
        { label: 'الرئيسية',     href: route('home'),              name: 'home' },
        { label: 'الكورسات',     href: route('courses.index'),     name: 'courses.index' },
        { label: 'من نحن',       href: route('about'),             name: 'about' },
        { label: 'نتائج طلابنا',  href: route('students_results'),  name: 'students_results' },
        { label: 'تطبيقاتنا',    href: route('our_apps'),          name: 'our_apps' },
        { label: 'تواصل معنا',   href: route('contact'),           name: 'contact' },
    ];
});

const socialLinks = computed(() => {
    const raw = page.props.settings?.footer_social_links;
    if (raw) {
        try {
            const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
            if (Array.isArray(parsed) && parsed.length > 0) {
                return parsed;
            }
        } catch (e) {}
    }
    return [
        { platform: 'facebook', url: '#', icon: 'facebook' },
        { platform: 'instagram', url: '#', icon: 'instagram' },
        { platform: 'whatsapp', url: '#', icon: 'whatsapp' },
        { platform: 'youtube', url: '#', icon: 'live' },
    ];
});

const isActive = (link) => {
    if (!link) return false;
    const name = link.name || '';
    if (name && page.component?.startsWith(name.split('.')[0])) return true;
    
    const path = window.location.pathname;
    const href = link.href || '';
    if (href === '/' && path === '/') return true;
    if (href !== '/' && href.startsWith('/') && path.startsWith(href)) return true;
    return false;
};
</script>

<template>
    <div class="min-h-screen flex flex-col">

        <!-- ── Navbar ──────────────────────────────────────────────── -->
        <nav class="navbar">
            <div class="container-app px-4">
                <div class="flex items-center justify-between h-16">

                    <!-- Logo -->
                    <Link :href="route('home')" class="flex items-center gap-2 group">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary-500 to-primary-700
                                    flex items-center justify-center shadow-glow-primary
                                    group-hover:scale-110 transition-transform duration-200">
                            <span class="text-white font-bold text-sm">{{ ($page.props.settings?.platform_name ?? 'تفوّق').charAt(0) }}</span>
                        </div>
                        <span class="text-xl font-bold text-gradient-primary hidden sm:block">{{ $page.props.settings?.platform_name ?? 'التفوق' }}</span>
                    </Link>

                    <!-- Desktop Search Bar -->
                    <div class="hidden lg:block relative flex-1 max-w-xs mx-4" dir="rtl">
                        <div class="relative">
                            <input v-model="searchQuery" @input="onSearchInput" type="text" placeholder="البحث عن كورسات أو معلمين..." class="w-full bg-surface-100 dark:bg-black/45 border border-transparent dark:border-surface-700/40 focus:border-primary-500 focus:dark:border-accent-500 rounded-xl px-4 py-2 text-xs text-surface-900 dark:text-white placeholder-surface-400 dark:placeholder-white/35 focus:outline-none focus:ring-0" />
                            <Icon name="search" class="w-4 h-4 text-surface-400 dark:text-surface-300 absolute left-3 top-2.5" />
                        </div>

                        <!-- Autocomplete Dropdown -->
                        <div v-if="searchResults.length > 0 && searchQuery.length >= 2" class="absolute z-50 left-0 right-0 mt-2 bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-xl shadow-xl overflow-hidden divide-y divide-surface-100 dark:divide-surface-800">
                            <Link v-for="course in searchResults" :key="course.id" :href="route('courses.show', { slug: course.slug })" @click="clearSearch" class="flex items-center justify-between p-3 hover:bg-surface-50 dark:hover:bg-surface-800/50 transition-colors">
                                <div class="text-start">
                                    <div class="font-bold text-xs text-surface-900 dark:text-white">{{ course.title }}</div>
                                    <div class="text-[10px] text-surface-400 mt-0.5">بواسطة: {{ course.teacher?.name }}</div>
                                </div>
                                <span class="text-[10px] font-bold text-primary-500 flex-shrink-0">عرض 🔗</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Desktop Nav Links -->
                    <div class="hidden md:flex items-center gap-1">
                        <Link
                            v-for="link in navLinks"
                            :key="link.label"
                            :href="link.href"
                            class="px-4 py-2 text-sm font-semibold transition-all duration-200"
                            :class="isActive(link)
                                ? 'text-primary-600 dark:text-primary-300 border-b-2 border-accent-500 font-bold rounded-t-xl bg-primary-500/5'
                                : 'text-surface-600 hover:text-primary-600 dark:text-surface-300 dark:hover:text-white'"
                        >
                            {{ link.label }}
                        </Link>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2">

                        <!-- Dark mode toggle -->
                        <button @click="toggleDark"
                            class="btn-ghost p-2 rounded-lg text-lg transition-all duration-300 transform active:scale-95"
                            :title="isDark ? 'وضع النهار' : 'الوضع الداكن'"
                        >
                            <Icon v-if="isDark" name="sun" class="w-5 h-5 text-amber-500" />
                            <Icon v-else name="moon" class="w-5 h-5 text-indigo-500" />
                        </button>

                        <!-- Notification Bell -->
                        <NotificationBell v-if="!authStore.isGuest" />

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

                        <!-- Guest Actions -->
                        <template v-if="authStore.isGuest">
                            <Link :href="route('login')"    class="btn-ghost btn-sm">تسجيل الدخول</Link>
                            <Link :href="route('register')" class="btn-primary btn-sm">إنشاء حساب</Link>
                        </template>

                        <!-- User Menu -->
                        <template v-else>
                            <Link
                                v-if="authStore.isTeacher"
                                :href="route('teacher.dashboard')"
                                class="btn-outline btn-sm hidden sm:flex"
                            >لوحة المعلم</Link>
                            <Link
                                v-if="authStore.isAdmin"
                                :href="route('admin.dashboard')"
                                class="btn-outline btn-sm hidden sm:flex"
                            >الإدارة</Link>

                            <Link :href="route('dashboard')" class="flex items-center gap-2 btn-ghost rounded-xl px-3 py-2">
                                <div class="avatar-sm bg-primary-100 dark:bg-primary-900">
                                    <img v-if="authStore.user?.avatar"
                                         :src="authStore.user.avatar"
                                         :alt="authStore.user.name"
                                         class="w-full h-full object-cover">
                                    <span v-else class="text-primary-700 dark:text-primary-300 font-bold text-xs">
                                        {{ authStore.user?.name?.charAt(0) }}
                                    </span>
                                </div>
                                <span class="text-sm font-semibold hidden sm:block text-surface-700 dark:text-surface-200">
                                    {{ authStore.user?.name }}
                                </span>
                            </Link>
                        </template>

                        <!-- Mobile menu button -->
                        <button @click="mobileMenuOpen = !mobileMenuOpen"
                            class="md:hidden btn-ghost p-2 rounded-lg">
                            <Icon :name="mobileMenuOpen ? 'close' : 'menu'" class="w-5 h-5" />
                        </button>
                    </div>
                </div>

                <!-- Mobile Menu -->
                <Transition
                    enter-active-class="transition-all duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition-all duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-2"
                >
                    <div v-if="mobileMenuOpen" class="md:hidden py-3 border-t border-surface-200 dark:border-surface-700">
                        <Link
                            v-for="link in navLinks"
                            :key="link.label"
                            :href="link.href"
                            class="block px-4 py-2.5 rounded-lg text-sm font-semibold mb-1"
                            :class="isActive(link)
                                ? 'bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300'
                                : 'text-surface-600 dark:text-surface-300'"
                            @click="mobileMenuOpen = false"
                        >{{ link.label }}</Link>
                    </div>
                </Transition>
            </div>
        </nav>

        <!-- ── Flash Messages ─────────────────────────────────────── -->
        <div class="fixed top-20 start-1/2 -translate-x-1/2 z-50 w-full max-w-md px-4 space-y-2 pointer-events-none">
            <Transition enter-active-class="animate-fade-up" leave-active-class="animate-fade-in">
                <div v-if="$page.props.flash?.success" class="alert-success shadow-card pointer-events-auto flex items-center gap-2">
                    <Icon name="success" class="w-5 h-5 text-green-500 shrink-0" />
                    <span>{{ $page.props.flash.success }}</span>
                </div>
            </Transition>
            <Transition enter-active-class="animate-fade-up">
                <div v-if="$page.props.flash?.error" class="alert-error shadow-card pointer-events-auto flex items-center gap-2">
                    <Icon name="error" class="w-5 h-5 text-red-500 shrink-0" />
                    <span>{{ $page.props.flash.error }}</span>
                </div>
            </Transition>
        </div>

        <!-- ── Main Content ───────────────────────────────────────── -->
        <main class="flex-1 pt-16">
            <slot />
        </main>

        <!-- ── Footer ─────────────────────────────────────────────── -->
        <footer class="bg-surface-900 dark:bg-surface-950 text-surface-300 py-12 mt-auto">
            <div class="container-app px-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
                    <div>
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-primary-500 to-primary-700
                                        flex items-center justify-center">
                                <span class="text-white font-bold text-xs">{{ ($page.props.settings?.platform_name ?? 'تفوّق').charAt(0) }}</span>
                            </div>
                            <span class="text-white font-bold text-lg">{{ $page.props.settings?.platform_name ?? 'منصة التفوق' }}</span>
                        </div>
                        <p class="text-sm text-surface-400 leading-relaxed">
                            {{ $page.props.settings?.footer_desc ?? 'منصة تعليمية متخصصة في مواد المرحلة الثانوية، نحو مستقبل أفضل لكل طالب.' }}
                        </p>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-3">روابط سريعة</h4>
                        <ul class="space-y-2 text-sm">
                            <li v-for="link in footerLinks" :key="link.label">
                                <Link :href="link.href" class="hover:text-primary-400 transition-colors">{{ link.label }}</Link>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-3">تواصل معنا</h4>
                        <p class="text-sm text-surface-400 mb-4">{{ $page.props.settings?.contact_email ?? 'support@altafawwuq.com' }}</p>
                        
                        <!-- Social Links -->
                        <div v-if="socialLinks.length > 0" class="flex items-center gap-3">
                            <a v-for="social in socialLinks" :key="social.platform" :href="social.url" target="_blank"
                               class="w-8 h-8 rounded-lg bg-surface-800 hover:bg-primary-600 flex items-center justify-center text-white transition-colors"
                               :title="social.platform"
                            >
                                <Icon :name="social.icon || social.platform" class="w-4 h-4 text-white" />
                            </a>
                        </div>
                    </div>
                </div>
                <div class="divider border-surface-700 pt-6 text-center text-xs text-surface-500">
                    © {{ new Date().getFullYear() }} {{ $page.props.settings?.platform_name ?? 'منصة التفوق' }} — جميع الحقوق محفوظة
                </div>
            </div>
        </footer>

        <!-- ── Floating WhatsApp Widget ───────────────────────────── -->
        <div v-if="$page.props.settings?.whatsapp_url" 
             class="fixed bottom-6 left-6 z-40 flex flex-col items-center gap-1.5"
        >
            <!-- Label above -->
            <span class="bg-surface-900 text-white text-[10px] font-bold px-3 py-1.5 rounded-xl shadow-lg border border-surface-800 pointer-events-none select-none">
                تواصل معنا
            </span>
            <!-- Glowing Yellow/Amber WhatsApp Button -->
            <a :href="$page.props.settings.whatsapp_url" 
               target="_blank"
               class="w-12 h-12 rounded-full bg-accent-500 hover:bg-accent-600 flex items-center justify-center text-white shadow-lg transition-transform duration-300 transform hover:scale-110 shadow-glow-accent"
               aria-label="Contact support on WhatsApp"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" class="w-6 h-6 fill-current text-white"><path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L32 503l138.2-36.2c32.5 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-82.1 21.5 21.9-80-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"/></svg>
            </a>
        </div>
    </div>
</template>
