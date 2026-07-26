<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
</script>

<template>
    <AppLayout>
        <Head title="تم الإلغاء" />
        <div class="container-app px-4 py-20 max-w-lg text-center flex flex-col items-center justify-center">
            <div class="p-5 bg-red-50 dark:bg-red-950/30 rounded-full text-red-500 mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h1 class="text-3xl font-black text-surface-900 dark:text-white mb-3">تم إلغاء الدفع</h1>
            <p class="text-surface-500 dark:text-surface-400 mb-8">
                لم تُكمل عملية الدفع. يمكنك المحاولة مرة أخرى في أي وقت.
            </p>
            <div class="flex gap-3 justify-center">
                <Link :href="route('home') + '#grades'" class="btn-primary btn-lg">تصفح المعلمين</Link>
                <Link :href="route('dashboard')" class="btn-ghost">الداشبورد</Link>
            </div>
        </div>
    </AppLayout>
</template>
