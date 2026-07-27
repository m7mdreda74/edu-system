<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TeacherCard from '@/Components/TeacherCard.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    teachers: { type: Array, default: () => [] },
});

const search = ref('');

const filteredTeachers = computed(() => {
    const query = search.value.trim().toLocaleLowerCase();

    if (!query) return props.teachers;

    return props.teachers.filter((teacher) => {
        const haystack = [
            teacher.name,
            teacher.headline,
            ...(teacher.subjects ?? []),
            ...(teacher.grades ?? []),
        ].filter(Boolean).join(' ').toLocaleLowerCase();

        return haystack.includes(query);
    });
});
</script>

<template>
    <Head title="المعلمون" />

    <AppLayout>
        <section class="hero-gradient text-white py-14">
            <div class="container-app px-4">
                <nav class="flex items-center gap-2 text-xs text-white/60 mb-5">
                    <Link :href="route('home')" class="hover:text-white transition-colors">الرئيسية</Link>
                    <span>/</span>
                    <span class="text-white/90">المعلمون</span>
                </nav>

                <div class="flex items-end justify-between gap-6 flex-wrap">
                    <div>
                        <span class="badge bg-white/15 text-white mb-3 inline-flex">نخبة الكادر التعليمي</span>
                        <h1 class="text-3xl sm:text-4xl font-black mb-3">تعرّف على معلمينا</h1>
                        <p class="text-white/70 text-sm max-w-2xl leading-relaxed">
                            اختر المعلم المناسب لك، شاهد الفيديو التعريفي، وتصفح المواد والصفوف والمجموعات المتاحة.
                        </p>
                    </div>
                    <div class="rounded-2xl bg-white/10 border border-white/10 px-5 py-4 text-center">
                        <div class="text-2xl font-black">{{ teachers.length }}</div>
                        <div class="text-xs text-white/60">معلم متاح</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="container-app px-4">
                <div class="flex items-center justify-between gap-4 mb-8 flex-wrap">
                    <div>
                        <h2 class="text-xl font-black text-surface-900 dark:text-white">كل المعلمين</h2>
                        <p class="text-xs text-surface-500 dark:text-surface-400 mt-1">
                            {{ filteredTeachers.length }} نتيجة مطابقة
                        </p>
                    </div>

                    <div class="relative w-full sm:w-80" dir="rtl">
                        <input
                            v-model="search"
                            type="search"
                            placeholder="ابحث باسم المعلم أو المادة أو الصف..."
                            class="input w-full pe-10"
                        />
                        <Icon name="search" class="w-4 h-4 text-surface-400 absolute end-3 top-3" />
                    </div>
                </div>

                <div v-if="filteredTeachers.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <TeacherCard
                        v-for="teacher in filteredTeachers"
                        :key="teacher.id"
                        :teacher="teacher"
                    />
                </div>

                <div v-else class="card p-12 text-center">
                    <Icon name="teacher" class="w-12 h-12 text-surface-300 mx-auto mb-4" />
                    <h3 class="font-bold text-surface-800 dark:text-white mb-2">لا يوجد معلم مطابق للبحث</h3>
                    <p class="text-sm text-surface-500">جرّب اسمًا أو مادة أو صفًا مختلفًا.</p>
                    <button v-if="search" type="button" class="btn-outline btn-sm mt-5" @click="search = ''">عرض كل المعلمين</button>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
