<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    grade:         { type: Object, required: true },
    subjects:      { type: Array, default: () => [] },
    siblingTracks: { type: Array, default: () => [] },
});

// Subject icons are stored as a free-text key; fall back to a book.
const KNOWN_ICONS = ['calculator', 'atom', 'flask', 'dna', 'book', 'language', 'landmark', 'globe', 'student', 'users', 'chart', 'settings', 'video'];
const iconFor = (icon) => (KNOWN_ICONS.includes(icon) ? icon : 'book');

const available = computed(() => props.subjects.filter((s) => s.teachers_count > 0));
const comingSoon = computed(() => props.subjects.filter((s) => s.teachers_count === 0));
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

                <div class="flex items-center gap-3 flex-wrap mb-2">
                    <h1 class="text-3xl sm:text-4xl font-black">{{ grade.name }}</h1>
                    <span v-if="grade.track_label" class="badge bg-white/20 text-white text-xs">
                        {{ grade.track_label }}
                    </span>
                </div>

                <p class="text-white/70 text-sm">
                    مواد {{ grade.stage_label }} — اختر المادة لتشاهد المعلمين الذين يدرّسونها
                </p>

                <!-- Wrong track? one click across -->
                <div v-if="siblingTracks.length" class="mt-5 flex items-center gap-2 flex-wrap">
                    <span class="text-xs text-white/50">مسار آخر:</span>
                    <Link
                        v-for="sibling in siblingTracks"
                        :key="sibling.key"
                        :href="route('grades.show', { key: sibling.key })"
                        class="badge bg-white/10 hover:bg-white/20 text-white text-xs transition-colors"
                    >
                        {{ sibling.track_label }}
                    </Link>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="container-app">
                <!-- Subjects with teachers -->
                <div v-if="available.length">
                    <h2 class="text-sm font-black text-surface-800 dark:text-surface-100 mb-4">
                        متاحة الآن ({{ available.length }})
                    </h2>

                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                        <Link
                            v-for="subject in available"
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
                </div>

                <!-- The rest of the curriculum, not yet staffed -->
                <div v-if="comingSoon.length" :class="available.length ? 'mt-12' : ''">
                    <h2 class="text-sm font-black text-surface-800 dark:text-surface-100 mb-1">
                        باقي مواد المنهج
                    </h2>
                    <p class="text-xs text-surface-400 mb-4">
                        مقرّرة على {{ grade.name }} — نعمل على إتاحة معلمين لها قريباً
                    </p>

                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                        <div
                            v-for="subject in comingSoon"
                            :key="subject.id"
                            class="card p-6 flex flex-col items-center text-center gap-3 opacity-60"
                        >
                            <div class="w-14 h-14 rounded-2xl bg-surface-100 dark:bg-surface-800 flex items-center justify-center">
                                <Icon :name="iconFor(subject.icon)" class="w-7 h-7 text-surface-400" />
                            </div>

                            <div>
                                <h3 class="font-bold text-surface-700 dark:text-surface-300 text-sm">{{ subject.name }}</h3>
                                <p v-if="subject.name_en" class="text-[11px] text-surface-400 font-latin">{{ subject.name_en }}</p>
                            </div>

                            <span class="badge-gray text-[10px]">قريباً</span>
                        </div>
                    </div>
                </div>

                <div v-if="!subjects.length" class="card p-12 text-center">
                    <Icon name="info" class="w-10 h-10 text-surface-300 mx-auto mb-3" />
                    <h3 class="font-bold text-surface-700 dark:text-surface-200 mb-1">لا توجد مواد مسجّلة لهذا الصف</h3>
                    <Link :href="route('home')" class="btn-outline btn-sm mt-5 inline-flex">العودة للرئيسية</Link>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
