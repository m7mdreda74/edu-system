<script setup>
import BrandLogo from '@/Components/BrandLogo.vue';
import ValidationErrorBanner from '@/Components/ValidationErrorBanner.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-surface-950 px-4 py-8 text-white sm:py-12" dir="rtl" lang="ar">
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
                <p class="mt-3 text-sm text-surface-400">حسابك التعليمي في مكان آمن</p>
            </div>

            <section class="card border border-surface-800 bg-surface-900/90 p-6 shadow-2xl backdrop-blur-xl sm:rounded-3xl sm:p-8">
                <slot />
            </section>

            <Link :href="route('home')" class="mt-6 block text-center text-xs font-semibold text-surface-400 transition-colors hover:text-accent-400">
                العودة إلى المنصة
            </Link>
        </main>
    </div>
</template>
