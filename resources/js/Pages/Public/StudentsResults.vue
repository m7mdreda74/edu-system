<script setup>
import { ref, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';

const page = usePage();
const activeBatch = ref('all');

const normalizeResult = (result) => {
    if (!result || typeof result !== 'object') return null;

    const { school: _school, ...cleanResult } = result;

    return {
        name: cleanResult.name || '',
        title: cleanResult.title || 'نتيجة مميزة',
        grade: cleanResult.grade || 'الصف الدراسي غير محدد',
        desc: cleanResult.desc || '',
    };
};

const resultsData = computed(() => {
    const raw = page.props.settings?.home_results;

    if (raw !== undefined && raw !== null && raw !== '') {
        try {
            const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;

            return Array.isArray(parsed)
                ? parsed.map(normalizeResult).filter(Boolean)
                : [];
        } catch (error) {
            console.warn('Failed to parse home_results settings JSON:', error);
            return [];
        }
    }

    return [];
});

const batches = computed(() => [
    { id: 'all', name: 'جميع النتائج' },
    ...[...new Set(resultsData.value.map(result => result.title).filter(Boolean))]
        .map(title => ({ id: title, name: title })),
]);

const filteredResults = computed(() => {
    if (activeBatch.value === 'all') return resultsData.value;
    return resultsData.value.filter(result => result.title === activeBatch.value);
});
</script>

<template>
    <AppLayout>
        <Head title="نتائج طلابنا" />

        <div class="bg-transparent py-16" dir="rtl">
            <div class="container-app px-4 max-w-5xl">
                <!-- Header -->
                <div class="text-center mb-12">
                    <span class="badge bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300 mb-3 inline-block">قصص نجاح وتفوق طلابنا</span>
                    <h1 class="text-3xl md:text-4xl font-black text-surface-900 dark:text-white mb-4">لوحة شرف المتفوقين</h1>
                    <p class="text-surface-500 dark:text-surface-400 text-sm leading-relaxed max-w-2xl mx-auto">
                        نفخر بتقديم الدعم التعليمي لآلاف الطلاب في قطر. هنا نستعرض لوحة شرف الذين حققوا الدرجات الكاملة والمعدلات الاستثنائية في الشهادة الثانوية العامة.
                    </p>
                </div>

                <!-- Filters -->
                <div class="flex flex-wrap gap-2 justify-center mb-12">
                    <button type="button"
                        v-for="batch in batches"
                        :key="batch.id"
                        @click="activeBatch = batch.id"
                        class="px-4 py-2 rounded-xl text-xs font-bold border transition-all duration-200"
                        :class="activeBatch === batch.id
                            ? 'bg-primary-600 border-primary-600 text-white shadow-glow-primary'
                            : 'bg-white border-surface-200 text-surface-600 hover:bg-surface-50 dark:bg-surface-900 dark:border-surface-800 dark:text-white/80 dark:hover:bg-surface-800'"
                    >
                        {{ batch.name }}
                    </button>
                </div>

                <!-- Results Grid -->
                <div v-if="filteredResults.length" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 mb-16">
                    <div v-for="(student, index) in filteredResults" :key="index"
                        class="card p-6 flex flex-col items-center justify-between text-center hover:shadow-card-hover transition-all duration-300 border border-surface-200 dark:border-surface-850"
                    >
                        <div class="flex flex-col items-center w-full">
                            <!-- Avatar / Trophy representation -->
                            <div class="w-16 h-16 rounded-full overflow-hidden bg-primary-50 dark:bg-primary-950/40 text-primary-600 dark:text-primary-400 mb-4 border-2 border-primary-100 dark:border-primary-900 flex items-center justify-center relative">
                                <span class="text-lg font-black">{{ student.name.charAt(0) }}</span>
                                <div class="absolute -bottom-0.5 -end-0.5 bg-accent-500 text-white p-1 rounded-full border-2 border-white dark:border-surface-900">
                                    <Icon name="success" class="w-3 h-3 text-white" />
                                </div>
                            </div>

                            <!-- Batch Label / Badge -->
                            <div class="badge bg-accent-50 text-accent-700 dark:bg-accent-950/70 dark:text-accent-400 text-[10px] mb-2 font-bold">
                                {{ student.title }}
                            </div>

                            <!-- Name -->
                            <h3 class="font-bold text-surface-850 dark:text-white text-base mb-1">{{ student.name }}</h3>
                            
                            <!-- Achievement -->
                            <p class="text-xs text-surface-500 dark:text-surface-400 font-semibold mb-2">
                                {{ student.desc }}
                            </p>
                        </div>

                        <!-- Grade details -->
                        <div class="w-full pt-3 mt-4 border-t border-surface-100 dark:border-surface-800 text-[10px] text-surface-400 flex items-center justify-between">
                            <span>{{ student.grade || 'الصف الدراسي غير محدد' }}</span>
                            <span class="badge-gray px-2 py-0.5 text-[9px] rounded font-bold">
                                {{ batches.find(b => b.id === student.batch)?.name }}
                            </span>
                        </div>
                    </div>
                </div>
                <p v-else class="card mb-16 py-12 text-center text-sm text-surface-400">
                    لا توجد نتائج منشورة حاليًا.
                </p>

                <!-- Call to action -->
                <div class="card p-8 bg-gradient-to-br from-primary-900 to-primary-950 text-white text-center">
                    <h3 class="font-black text-xl mb-2">هل تريد الانضمام للوحة الشرف؟</h3>
                    <p class="text-xs text-white/70 max-w-md mx-auto mb-6 leading-relaxed">
                        سجل الآن في منصة التفوق وابدأ رحلتك التفاعلية مع أفضل معلمي قطر لضمان مستقبلك الدراسي المشرق.
                    </p>
                    <Link :href="route('register')" class="btn-accent py-2 px-6 text-xs font-bold inline-block rounded-xl transform transition duration-300 hover:scale-105">
                        سجل حساباً مجانياً الآن
                    </Link>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
