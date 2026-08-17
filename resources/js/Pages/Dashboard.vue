<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <DashboardLayout>
        <Head title="لوحة التحكم" />

        <div class="dashboard-data-page" dir="rtl">
            <section class="hero-gradient relative overflow-hidden rounded-3xl p-6 text-white shadow-card md:p-8">
                <div class="pointer-events-none absolute -top-20 -end-16 h-48 w-48 rounded-full bg-accent-400/20 blur-3xl"></div>
                <div class="relative max-w-2xl">
                    <span class="badge mb-4 bg-white/15 text-white">لوحة الطالب</span>
                    <h1 class="text-2xl font-black md:text-3xl">
                        أهلاً {{ page.props.auth?.user?.name || 'بك' }}
                    </h1>
                    <p class="mt-3 text-sm leading-relaxed text-white/80 md:text-base">
                        تابع موادك وحصصك وجدولك الدراسي من مكان واحد، وابدأ الخطوة التالية في رحلتك التعليمية.
                    </p>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link :href="route('student.my-grade')" class="card-hover group p-5 text-start">
                    <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 transition-colors group-hover:bg-primary-100 dark:bg-primary-950 dark:text-primary-300">
                        <span class="text-xl" aria-hidden="true">📚</span>
                    </div>
                    <h2 class="font-black text-surface-900 dark:text-white">مواد صفي</h2>
                    <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">استكشف المواد والمعلمين المتاحين لك.</p>
                </Link>

                <Link :href="route('student.my-classes')" class="card-hover group p-5 text-start">
                    <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-2xl bg-accent-50 text-accent-600 transition-colors group-hover:bg-accent-100 dark:bg-accent-950 dark:text-accent-300">
                        <span class="text-xl" aria-hidden="true">🎓</span>
                    </div>
                    <h2 class="font-black text-surface-900 dark:text-white">حصصي</h2>
                    <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">راجع اشتراكاتك ومواعيد حصصك.</p>
                </Link>

                <Link :href="route('student.schedule')" class="card-hover group p-5 text-start">
                    <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-2xl bg-surface-100 text-surface-600 transition-colors group-hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300">
                        <span class="text-xl" aria-hidden="true">🗓️</span>
                    </div>
                    <h2 class="font-black text-surface-900 dark:text-white">جدول حصصي</h2>
                    <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">اعرف حصتك القادمة واستعد لها.</p>
                </Link>
            </section>
        </div>
    </DashboardLayout>
</template>
