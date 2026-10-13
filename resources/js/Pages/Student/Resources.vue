<script setup>
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

defineProps({
    resources: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="الموارد التعليمية" />
    <DashboardLayout>
        <div class="space-y-6">
            <header>
                <h1 class="text-2xl font-black text-surface-900 dark:text-white">الموارد التعليمية</h1>
                <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">ملفات شاركها معلموك مع التكليفات التي تدرسها.</p>
            </header>

            <div v-if="resources.length" class="grid md:grid-cols-2 gap-4">
                <article v-for="resource in resources" :key="resource.id" class="card p-5 flex items-start gap-3">
                    <div class="w-11 h-11 rounded-xl bg-primary-50 dark:bg-primary-950 flex items-center justify-center text-primary-600 shrink-0">
                        <Icon name="attachment" class="w-5 h-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-bold text-surface-900 dark:text-white">{{ resource.title }}</h2>
                        <p class="text-xs text-surface-500 mt-1">{{ resource.category }} · {{ resource.size_label }}</p>
                        <p v-if="resource.description" class="text-sm text-surface-600 dark:text-surface-300 mt-2">{{ resource.description }}</p>
                        <a v-if="resource.file_url" :href="resource.file_url" class="btn-primary btn-sm inline-flex mt-4">فتح المورد</a>
                    </div>
                </article>
            </div>
            <div v-else class="card p-12 text-center">
                <Icon name="book" class="w-10 h-10 text-surface-300 mx-auto mb-3" />
                <h2 class="font-bold text-surface-700 dark:text-surface-200">لا توجد موارد مرتبطة بحصصك بعد</h2>
                <p class="text-sm text-surface-400 mt-1">ستظهر هنا الملفات التي يربطها معلموك بتكليفاتك.</p>
            </div>
        </div>
    </DashboardLayout>
</template>
