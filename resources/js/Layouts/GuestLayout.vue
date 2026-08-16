<script setup>
import BrandLogo from '@/Components/BrandLogo.vue';
import ValidationErrorBanner from '@/Components/ValidationErrorBanner.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0"
    >
        <div class="fixed top-4 start-1/2 z-50 w-full max-w-md -translate-x-1/2 px-4 pointer-events-none">
            <ValidationErrorBanner />
        </div>

        <div>
            <Link href="/">
                <BrandLogo />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg"
        >
            <slot />
        </div>
    </div>
</template>
