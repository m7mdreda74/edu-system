<script setup>
import BrandLogo from '@/Components/BrandLogo.vue';
import ValidationErrorBanner from '@/Components/ValidationErrorBanner.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-surface-50 px-4 py-8 dark:bg-surface-950 sm:py-12" dir="rtl" lang="ar">
        <div class="pointer-events-none absolute -top-32 -start-24 h-80 w-80 rounded-full bg-accent-400/15 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-40 -end-24 h-96 w-96 rounded-full bg-primary-500/10 blur-3xl"></div>

        <div class="fixed top-4 left-1/2 z-[70] w-full max-w-md -translate-x-1/2 px-4 pointer-events-none">
            <ValidationErrorBanner />
        </div>

        <main class="relative z-10 w-full max-w-lg">
            <div class="mb-6 flex flex-col items-center text-center">
                <Link :href="route('home')" class="group inline-flex flex-col items-center gap-3" aria-label="العودة إلى الصفحة الرئيسية">
                    <BrandLogo />
                </Link>
                <p class="mt-3 text-sm text-surface-500 dark:text-surface-400">حسابك التعليمي في مكان آمن</p>
            </div>

            <section class="card border border-surface-200/70 bg-white/90 p-6 shadow-xl backdrop-blur-xl dark:border-surface-800 dark:bg-surface-900/90 sm:rounded-3xl sm:p-8">
                <slot />
            </section>

            <Link :href="route('home')" class="mt-6 block text-center text-xs font-semibold text-surface-500 transition-colors hover:text-primary-600 dark:text-surface-400 dark:hover:text-accent-400">
                العودة إلى المنصة
            </Link>
        </main>
    </div>
</template>
