<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    status: { type: Number, required: true },
});

const config = computed(() => ({
    404: {
        emoji: '🔍',
        title: 'الصفحة غير موجودة',
        description: 'عذراً، الصفحة التي تبحث عنها لم تعد موجودة أو تم نقلها.',
    },
    403: {
        emoji: '🔒',
        title: 'غير مصرح بالوصول',
        description: 'ليس لديك صلاحية للوصول إلى هذه الصفحة.',
    },
    500: {
        emoji: '⚙️',
        title: 'خطأ في الخادم',
        description: 'حدث خطأ غير متوقع. فريقنا التقني تم إبلاغه تلقائياً.',
    },
    503: {
        emoji: '🚧',
        title: 'الخدمة غير متاحة',
        description: 'المنصة في وضع الصيانة. سنعود قريباً!',
    },
}[props.status] ?? {
    emoji: '😕',
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

        <div class="relative text-center max-w-md">
            <!-- Animated emoji -->
            <div class="text-8xl mb-6 inline-block animate-bounce">{{ config.emoji }}</div>

            <!-- Error code -->
            <div class="text-9xl font-black text-white/10 leading-none mb-4 select-none">
                {{ status }}
            </div>

            <h1 class="text-3xl font-black text-white mb-3">{{ config.title }}</h1>
            <p class="text-white/70 mb-8 leading-relaxed">{{ config.description }}</p>

            <div class="flex gap-3 justify-center">
                <Link :href="route('home')" class="btn-accent btn-lg">
                    🏠 العودة للرئيسية
                </Link>
                <button @click="history.back()" class="btn glass text-white border-white/30 hover:bg-white/20 btn-lg">
                    ← رجوع
                </button>
            </div>
        </div>
    </div>
</template>
