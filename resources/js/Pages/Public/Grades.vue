<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import GradeCard from '@/Components/GradeCard.vue';

const props = defineProps({
    grades: { type: Array, default: () => [] },
});

const selectedCurriculum = ref('all');

const curriculums = [
    { key: 'all',      label: 'جميع المناهج' },
    { key: 'qatari',   label: 'المنهج الوزاري القطري 🇶🇦' },
    { key: 'british',  label: 'المنهج البريطاني (IGCSE) 🇬🇧' },
    { key: 'american', label: 'المنهج الأمريكي 🇺🇸' },
];

const STAGE_ORDER = ['primary', 'preparatory', 'secondary'];

const filteredGrades = computed(() => {
    if (selectedCurriculum.value === 'all') return props.grades;
    return props.grades.filter(g => (g.curriculum || 'qatari') === selectedCurriculum.value);
});

const gradeGroups = computed(() => {
    const byStage = new Map();

    for (const grade of filteredGrades.value) {
        if (!byStage.has(grade.stage)) {
            byStage.set(grade.stage, {
                stage: grade.stage,
                label: grade.stage_label,
                grades: [],
            });
        }

        byStage.get(grade.stage).grades.push(grade);
    }

    return [...byStage.values()].sort(
        (a, b) => STAGE_ORDER.indexOf(a.stage) - STAGE_ORDER.indexOf(b.stage),
    );
});
</script>

<template>
    <Head title="كل الصفوف والمناهج الدراسية" />

    <AppLayout>
        <section class="hero-gradient text-white py-12">
            <div class="container-app px-4">
                <nav class="flex items-center gap-2 text-xs text-white/60 mb-4">
                    <Link :href="route('home')" class="hover:text-white transition-colors">الرئيسية</Link>
                    <span>/</span>
                    <span class="text-white/90">الصفوف الدراسية</span>
                </nav>

                <h1 class="text-3xl sm:text-4xl font-black mb-3">الصفوف والمناهج الدراسية</h1>
                <p class="text-white/70 text-sm">اختر نوع المنهج وصفك الدراسي للوصول إلى نخبة المعلمين والمحتوى التفاعلي.</p>
            </div>
        </section>

        <section class="section">
            <div class="container-app">
                <!-- Curriculum Filter Tabs -->
                <div class="mb-10 flex flex-wrap items-center gap-2.5 bg-surface-100 dark:bg-surface-800/80 p-1.5 rounded-2xl max-w-2xl border border-surface-200 dark:border-surface-700">
                    <button
                        v-for="curr in curriculums"
                        :key="curr.key"
                        type="button"
                        @click="selectedCurriculum = curr.key"
                        class="px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                        :class="selectedCurriculum === curr.key
                            ? 'bg-primary-600 text-white shadow-md'
                            : 'text-surface-600 dark:text-surface-300 hover:text-surface-900 dark:hover:text-white'"
                    >
                        <span>{{ curr.label }}</span>
                    </button>
                </div>
                <div v-if="gradeGroups.length" class="space-y-10">
                    <div v-for="group in gradeGroups" :key="group.stage">
                        <div class="flex items-center justify-between gap-3 mb-4">
                            <h2 class="text-lg font-black text-surface-900 dark:text-white">{{ group.label }}</h2>
                            <span class="badge-gray text-xs">{{ group.grades.length }} صف</span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 items-stretch">
                            <GradeCard
                                v-for="grade in group.grades"
                                :key="grade.key"
                                :grade="grade"
                                full-width
                            />
                        </div>
                    </div>
                </div>

                <div v-else class="card p-12 text-center">
                    <h2 class="font-bold text-surface-700 dark:text-surface-200 mb-2">لا توجد صفوف دراسية متاحة حاليًا</h2>
                    <Link :href="route('home')" class="btn-outline btn-sm mt-4 inline-flex">العودة للرئيسية</Link>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
