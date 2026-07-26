<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';

const activeBatch = ref('all');

const batches = [
    { id: 'all', name: 'جميع الدفعات' },
    { id: '2026-1', name: 'دفعة 2026 الفصل الأول' },
    { id: '2025-2', name: 'دفعة 2025 الفصل الثاني' },
    { id: '2025-1', name: 'دفعة 2025 الفصل الأول' },
    { id: '2024', name: 'دفعة 2024' },
];

const resultsData = [
    // 2026-1
    { name: 'مريم الباكر', score: '100%', subject: 'الرياضيات والفيزياء', school: 'البيان الثانوية للبنات', batch: '2026-1' },
    { name: 'جاسم الكواري', score: '99.6%', subject: 'اللغة العربية والإنجليزية', school: 'عمر بن الخطاب الثانوية للبنين', batch: '2026-1' },
    { name: 'نورة المهندي', score: '100%', subject: 'الكيمياء والأحياء', school: 'الخور الثانوية للبنات', batch: '2026-1' },
    // 2025-2
    { name: 'سارة المهندي', score: '99.8%', subject: 'القسم العلمي وتكنولوجيا', school: 'آمنة بنت وهب الثانوية للبنات', batch: '2025-2' },
    { name: 'عبدالرحمن آل ثاني', score: '99.2%', subject: 'الرياضيات المتقدمة والفيزياء', school: 'قطر الثانوية للبنين', batch: '2025-2' },
    { name: 'فاطمة المناعي', score: '100%', subject: 'الأحياء والعلوم العامة', school: 'الشيماء الثانوية للبنات', batch: '2025-2' },
    // 2025-1
    { name: 'خالد آل ثاني', score: '100%', subject: 'الكيمياء والأحياء والعلوم', school: 'الدوحة الثانوية للبنين', batch: '2025-1' },
    { name: 'حمد البلوشي', score: '98.8%', subject: 'الرياضيات واللغة العربية', school: 'خليفة الثانوية للبنين', batch: '2025-1' },
    { name: 'شريفة الهيدوس', score: '99.5%', subject: 'الفيزياء والكيمياء', school: 'البيان الثانوية للبنات', batch: '2025-1' },
    // 2024
    { name: 'محمد الكواري', score: '100%', subject: 'الرياضيات والفيزياء والكيمياء', school: 'عمر بن الخطاب الثانوية للبنين', batch: '2024' },
    { name: 'عائشة الفضالة', score: '99.4%', subject: 'القسم العلمي', school: 'روضة بنت جاسم الثانوية للبنات', batch: '2024' },
    { name: 'عبدالله السويدي', score: '99.0%', subject: 'اللغة الإنجليزية والعلوم الاجتماعية', school: 'الجميلية الثانوية للبنين', batch: '2024' },
];

const filteredResults = computed(() => {
    if (activeBatch.value === 'all') return resultsData;
    return resultsData.filter(r => r.batch === activeBatch.value);
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
                    <button
                        v-for="batch in batches"
                        :key="batch.id"
                        @click="activeBatch = batch.id"
                        class="px-4 py-2 rounded-xl text-xs font-bold border transition-all duration-200"
                        :class="activeBatch === batch.id
                            ? 'bg-primary-600 border-primary-600 text-white shadow-glow-primary'
                            : 'bg-white border-surface-200 text-surface-650 hover:bg-surface-50 dark:bg-surface-900 dark:border-surface-800 dark:text-white/80 dark:hover:bg-surface-800'"
                    >
                        {{ batch.name }}
                    </button>
                </div>

                <!-- Results Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 mb-16">
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

                            <!-- Grade Label / Badge -->
                            <div class="badge bg-accent-50 text-accent-700 dark:bg-accent-950/70 dark:text-accent-400 text-[10px] mb-2 font-bold">
                                {{ student.score }}
                            </div>

                            <!-- Name -->
                            <h3 class="font-bold text-surface-850 dark:text-white text-base mb-1">{{ student.name }}</h3>
                            
                            <!-- Subject -->
                            <p class="text-xs text-surface-500 dark:text-surface-400 font-semibold mb-2">
                                تفوق في: {{ student.subject }}
                            </p>
                        </div>

                        <!-- School details -->
                        <div class="w-full pt-3 mt-4 border-t border-surface-100 dark:border-surface-800 text-[10px] text-surface-400 flex items-center justify-between">
                            <span>{{ student.school }}</span>
                            <span class="badge-gray px-2 py-0.5 text-[9px] rounded font-bold">
                                {{ batches.find(b => b.id === student.batch)?.name }}
                            </span>
                        </div>
                    </div>
                </div>

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
