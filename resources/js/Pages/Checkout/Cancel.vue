<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
</script>

<template>
    <AppLayout>
        <Head title="تم الإلغاء" />
        <div class="container-app px-4 py-20 max-w-lg text-center">
            <div class="text-7xl mb-6">😔</div>
            <h1 class="text-3xl font-black text-surface-900 dark:text-white mb-3">تم إلغاء الدفع</h1>
            <p class="text-surface-500 dark:text-surface-400 mb-8">
                لم تُكمل عملية الدفع. يمكنك المحاولة مرة أخرى في أي وقت.
            </p>
            <div class="flex gap-3 justify-center">
                <Link :href="route('courses.index')" class="btn-primary btn-lg">تصفح الكورسات</Link>
                <Link :href="route('dashboard')" class="btn-ghost">الداشبورد</Link>
            </div>
        </div>
    </AppLayout>
</template>
