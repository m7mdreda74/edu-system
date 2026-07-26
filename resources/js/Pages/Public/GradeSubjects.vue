<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';

defineProps({
    grade:    { type: Object, required: true },
    subjects: { type: Array, default: () => [] },
});

// Subject icons are stored as a free-text key; fall back to a book.
const iconFor = (icon) => {
    const known = ['calculator', 'atom', 'flask', 'dna', 'book', 'language', 'landmark', 'globe'];
    return known.includes(icon) ? icon : 'book';
};
</script>

<template>
    <Head :title="grade.name" />

    <AppLayout>
        <!-- Breadcrumb + heading -->
        <section class="hero-gradient text-white py-14">
            <div class="container-app px-4">
                <nav class="flex items-center gap-2 text-xs text-white/60 mb-4">
                    <Link :href="route('home')" class="hover:text-white transition-colors">الرئيسية</Link>
                    <span>/</span>
                    <span class="text-white/90">{{ grade.name }}</span>
                </nav>

                <h1 class="text-3xl sm:text-4xl font-black mb-2">{{ grade.name }}</h1>
                <p class="text-white/70 text-sm">اختر المادة لتشاهد المعلمين الذين يدرّسونها</p>
            </div>
        </section>

        <section class="section">
            <div class="container-app">
                <div v-if="subjects.length" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    <Link
                        v-for="subject in subjects"
                        :key="subject.id"
                        :href="route('subjects.teachers', { gradeKey: grade.key, subject: subject.id })"
                        class="card-hover p-6 flex flex-col items-center text-center gap-3 group"
                    >
                        <div class="w-14 h-14 rounded-2xl bg-primary-50 dark:bg-primary-950 flex items-center justify-center
                                    group-hover:scale-110 transition-transform duration-200">
                            <Icon :name="iconFor(subject.icon)" class="w-7 h-7 text-primary-600 dark:text-primary-400" />
                        </div>

                        <div>
                            <h3 class="font-bold text-surface-900 dark:text-white text-sm">{{ subject.name }}</h3>
                            <p v-if="subject.name_en" class="text-[11px] text-surface-400 font-latin">{{ subject.name_en }}</p>
                        </div>

                        <span class="badge-primary text-[10px]">
                            {{ subject.teachers_count }} {{ subject.teachers_count === 1 ? 'معلم' : 'معلمين' }}
                        </span>
                    </Link>
                </div>

                <div v-else class="card p-12 text-center">
                    <Icon name="info" class="w-10 h-10 text-surface-300 mx-auto mb-3" />
                    <h3 class="font-bold text-surface-700 dark:text-surface-200 mb-1">لا توجد مواد متاحة بعد</h3>
                    <p class="text-sm text-surface-400">لم يتم إسناد معلمين لهذه المرحلة حتى الآن.</p>
                    <Link :href="route('home')" class="btn-outline btn-sm mt-5 inline-flex">العودة للرئيسية</Link>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
