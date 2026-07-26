<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TeacherCard from '@/Components/TeacherCard.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    grade:    { type: Object, required: true },
    subject:  { type: Object, required: true },
    teachers: { type: Array, default: () => [] },
});

const sortBy = ref('rating');

const sortedTeachers = computed(() => {
    const list = [...props.teachers];

    if (sortBy.value === 'price') {
        // Teachers with no group price sort last rather than as "free".
        return list.sort((a, b) => (a.cheapest_monthly ?? Infinity) - (b.cheapest_monthly ?? Infinity));
    }

    if (sortBy.value === 'experience') {
        return list.sort((a, b) => (b.years_experience ?? 0) - (a.years_experience ?? 0));
    }

    return list.sort((a, b) => (b.rating ?? 0) - (a.rating ?? 0));
});
</script>

<template>
    <Head :title="`${subject.name} — ${grade.name}`" />

    <AppLayout>
        <section class="hero-gradient text-white py-14">
            <div class="container-app px-4">
                <nav class="flex items-center gap-2 text-xs text-white/60 mb-4 flex-wrap">
                    <Link :href="route('home')" class="hover:text-white transition-colors">الرئيسية</Link>
                    <span>/</span>
                    <Link :href="route('grades.show', { key: grade.key })" class="hover:text-white transition-colors">{{ grade.name }}</Link>
                    <span>/</span>
                    <span class="text-white/90">{{ subject.name }}</span>
                </nav>

                <h1 class="text-3xl sm:text-4xl font-black mb-2">معلمو {{ subject.name }}</h1>
                <p class="text-white/70 text-sm">
                    شاهد الفيديو التعريفي لكل معلم، ولو عجبتك طريقة الشرح احجز معه مباشرة.
                </p>
            </div>
        </section>

        <section class="section">
            <div class="container-app">
                <div v-if="teachers.length" class="flex items-center justify-between gap-4 mb-6 flex-wrap">
                    <p class="text-sm text-surface-500 dark:text-surface-400">
                        {{ teachers.length }} معلم متاح لـ<span class="font-bold"> {{ grade.name }}</span>
                    </p>

                    <div class="flex items-center gap-2">
                        <label for="sort" class="text-xs text-surface-400">ترتيب حسب</label>
                        <select id="sort" v-model="sortBy" class="input py-1.5 text-xs w-auto">
                            <option value="rating">الأعلى تقييماً</option>
                            <option value="price">الأقل سعراً</option>
                            <option value="experience">الأكثر خبرة</option>
                        </select>
                    </div>
                </div>

                <div v-if="teachers.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <TeacherCard
                        v-for="teacher in sortedTeachers"
                        :key="teacher.assignment_id"
                        :teacher="teacher"
                        :grade-key="grade.key"
                        :subject-id="subject.id"
                    />
                </div>

                <div v-else class="card p-12 text-center">
                    <Icon name="teacher" class="w-10 h-10 text-surface-300 mx-auto mb-3" />
                    <h3 class="font-bold text-surface-700 dark:text-surface-200 mb-1">لا يوجد معلمون لهذه المادة بعد</h3>
                    <p class="text-sm text-surface-400">جرّب مادة أخرى أو تواصل معنا لطلب معلم.</p>
                    <Link :href="route('grades.show', { key: grade.key })" class="btn-outline btn-sm mt-5 inline-flex">
                        رجوع لمواد {{ grade.name }}
                    </Link>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
