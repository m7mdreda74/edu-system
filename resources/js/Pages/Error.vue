<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    status: { type: Number, required: true },
});

const config = computed(() => ({
    404: {
        title: 'الصفحة غير موجودة',
        description: 'عذراً، الصفحة التي تبحث عنها لم تعد موجودة أو تم نقلها.',
    },
    403: {
        title: 'غير مصرح بالوصول',
        description: 'ليس لديك صلاحية للوصول إلى هذه الصفحة.',
    },
    500: {
        title: 'خطأ في الخادم',
        description: 'حدث خطأ غير متوقع. فريقنا التقني تم إبلاغه تلقائياً.',
    },
    503: {
        title: 'الخدمة غير متاحة',
        description: 'المنصة في وضع الصيانة. سنعود قريباً!',
    },
}[props.status] ?? {
    title: 'حدث خطأ',
    description: 'شيء ما لم يسير كما ينبغي.',
}));
</script>

<template>
    <div class="min-h-screen hero-gradient flex items-center justify-center px-4" dir="rtl" lang="ar">
        <!-- Background decoration -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute top-10 start-10 w-96 h-96 rounded-full bg-white/5 blur-3xl"></div>
            <div class="absolute bottom-10 end-10 w-64 h-64 rounded-full bg-accent-400/10 blur-3xl"></div>
        </div>

        <div class="relative text-center max-w-md flex flex-col items-center justify-center">
            <!-- Animated SVG instead of emoji -->
            <div class="mb-6 flex justify-center">
                <template v-if="status === 404">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-24 h-24 text-accent-400 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                </template>
                <template v-else-if="status === 403">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-24 h-24 text-accent-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                </template>
                <template v-else-if="status === 500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-24 h-24 text-accent-400 animate-spin" style="animation-duration: 8s" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.43l-1.003.828c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.43l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.991l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </template>
                <template v-else-if="status === 503">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-24 h-24 text-accent-400 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </template>
                <template v-else>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-24 h-24 text-accent-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                </template>
            </div>

            <!-- Error code -->
            <div class="text-9xl font-black text-white/10 leading-none mb-4 select-none">
                {{ status }}
            </div>

            <h1 class="text-3xl font-black text-white mb-3">{{ config.title }}</h1>
            <p class="text-white/70 mb-8 leading-relaxed">{{ config.description }}</p>

            <div class="flex gap-3 justify-center">
                <Link :href="route('home')" class="btn-accent btn-lg flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    العودة للرئيسية
                </Link>
                <button @click="history.back()" class="btn glass text-white border-white/30 hover:bg-white/20 btn-lg">
                    ← رجوع
                </button>
            </div>
        </div>
    </div>
</template>
