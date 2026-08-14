<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import SubscriptionBadge from '@/Components/SubscriptionBadge.vue';
import { formatMonthly } from '@/lib/money';

defineProps({
    grade:    { type: Object, default: null },
    subjects: { type: Array, default: () => [] },
    summary:  { type: Object, default: () => ({}) },
    grades:   { type: Array, default: () => [] },
});

const KNOWN_ICONS = ['calculator', 'atom', 'flask', 'dna', 'book', 'language', 'landmark', 'globe', 'student', 'users', 'chart', 'settings', 'video'];
const iconFor = (icon) => (KNOWN_ICONS.includes(icon) ? icon : 'book');

// Only one subject is expanded at a time — a grade has a dozen, and every one
// of them carrying a teacher list at once is a wall.
const openSubject = ref(null);
const toggle = (id) => (openSubject.value = openSubject.value === id ? null : id);

const playing = ref(null);

function embed(url) {
    if (!url) return null;
    const youtube = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([\w-]{11})/);
    if (youtube) return `https://www.youtube-nocookie.com/embed/${youtube[1]}?autoplay=1&rel=0`;
    const vimeo = url.match(/vimeo\.com\/(\d+)/);
    if (vimeo) return `https://player.vimeo.com/video/${vimeo[1]}?autoplay=1`;
    return url;
}

const subscribing = ref(null);

function subscribe(groupId) {
    subscribing.value = groupId;
    router.post(route('student.subscribe.group', { groupId }), {}, {
        onFinish: () => (subscribing.value = null),
    });
}
</script>

<template>
    <Head title="مواد صفي" />

    <DashboardLayout>
        <div class="space-y-6">

            <!-- No grade on the account yet -->
            <template v-if="!grade">
                <header>
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white">مواد صفي</h1>
                </header>

                <div class="card p-12 text-center">
                    <Icon name="student" class="w-10 h-10 text-surface-300 mx-auto mb-3" />
                    <h3 class="font-bold text-surface-700 dark:text-surface-200 mb-1">لم تحدّد صفك الدراسي بعد</h3>
                    <p class="text-sm text-surface-400 mb-5">
                        حدّد صفك من صفحة الحساب لتظهر لك مواد منهجك ومعلموها.
                    </p>
                    <Link :href="route('profile.edit')" class="btn-primary btn-sm">تحديث الحساب</Link>
                </div>
            </template>

            <template v-else>
                <!-- ── Header ─────────────────────────────────── -->
                <header class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h1 class="text-2xl font-black text-surface-900 dark:text-white">{{ grade.name }}</h1>
                            <span v-if="grade.track_label" class="badge-primary text-[10px]">{{ grade.track_label }}</span>
                        </div>
                        <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                            كل مواد منهجك، ومعلمو كل مادة
                        </p>
                    </div>

                    <div class="card px-4 py-3 text-center">
                        <div class="text-lg font-black text-surface-900 dark:text-white">
                            {{ summary.subscribed }} / {{ summary.total }}
                        </div>
                        <div class="text-[10px] text-surface-400">مواد مشترك فيها</div>
                    </div>
                </header>

                <!-- ── Subjects ───────────────────────────────── -->
                <div class="space-y-3">
                    <section
                        v-for="subject in subjects"
                        :key="subject.id"
                        class="card overflow-hidden"
                        :class="subject.is_subscribed ? 'border-s-4 border-green-500' : 'border-s-4 border-red-400'"
                    >
                        <!-- Subject header — the status line the student reads first -->
                        <button
                            type="button"
                            class="w-full flex items-center gap-4 p-4 text-start hover:bg-surface-50 dark:hover:bg-surface-800/50 transition-colors"
                            @click="toggle(subject.id)"
                        >
                            <div
                                class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0"
                                :class="subject.is_subscribed
                                    ? 'bg-green-50 dark:bg-green-950/40 text-green-600 dark:text-green-400'
                                    : 'bg-surface-100 dark:bg-surface-800 text-surface-400'"
                            >
                                <Icon :name="iconFor(subject.icon)" class="w-5 h-5" />
                            </div>

                            <div class="flex-1 min-w-0">
                                <h2 class="font-bold text-sm text-surface-900 dark:text-white">{{ subject.name }}</h2>
                                <p
                                    class="text-xs mt-0.5"
                                    :class="subject.is_subscribed
                                        ? 'text-green-700 dark:text-green-400'
                                        : 'text-red-600 dark:text-red-400'"
                                >
                                    <template v-if="subject.is_subscribed">
                                        مشترك مع {{ subject.subscribed_with }}
                                    </template>
                                    <template v-else-if="subject.teachers.length">
                                        غير مشترك مع أي معلم في هذه المادة
                                    </template>
                                    <template v-else>
                                        لا يوجد معلمون لهذه المادة بعد
                                    </template>
                                </p>
                            </div>

                            <span class="badge-gray text-[10px] shrink-0 hidden sm:inline-flex">
                                {{ subject.teachers.length }} معلم
                            </span>

                            <Icon
                                name="arrowLeft"
                                class="w-4 h-4 text-surface-400 shrink-0 transition-transform"
                                :class="openSubject === subject.id ? '-rotate-90' : 'rotate-0'"
                            />
                        </button>

                        <!-- Teachers -->
                        <div v-if="openSubject === subject.id" class="border-t border-surface-100 dark:border-surface-800">
                            <div v-if="subject.teachers.length" class="divide-y divide-surface-100 dark:divide-surface-800">
                                <article v-for="teacher in subject.teachers" :key="teacher.id" class="p-4">
                                    <div class="flex items-start gap-4 flex-wrap">
                                        <!-- Intro video / avatar -->
                                        <div class="relative w-28 aspect-video rounded-xl overflow-hidden bg-surface-900 shrink-0 group">
                                            <img
                                                v-if="teacher.intro_video_thumbnail"
                                                :src="teacher.intro_video_thumbnail"
                                                :alt="teacher.name"
                                                class="w-full h-full object-cover opacity-80"
                                            />
                                            <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-primary-700 to-primary-900">
                                                <span class="text-xl font-black text-white/90">{{ teacher.name?.charAt(0) }}</span>
                                            </div>

                                            <button
                                                v-if="teacher.intro_video_url"
                                                type="button"
                                                class="absolute inset-0 flex items-center justify-center bg-black/35 hover:bg-black/55 transition-colors"
                                                :aria-label="`شاهد فيديو ${teacher.name}`"
                                                @click="playing = teacher"
                                            >
                                                <span class="w-9 h-9 rounded-full bg-accent-500 flex items-center justify-center">
                                                    <Icon name="video" class="w-4 h-4 text-white" />
                                                </span>
                                            </button>
                                        </div>

                                        <!-- Details -->
                                        <div class="flex-1 min-w-[200px]">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <h3 class="font-bold text-sm text-surface-900 dark:text-white">{{ teacher.name }}</h3>
                                                <SubscriptionBadge :subscribed="teacher.is_subscribed" />
                                                <span v-if="teacher.rating" class="badge-accent text-[10px]">★ {{ teacher.rating }}</span>
                                            </div>

                                            <p v-if="teacher.headline" class="text-xs font-semibold text-primary-600 dark:text-primary-400 mt-1">
                                                {{ teacher.headline }}
                                            </p>

                                            <p v-if="teacher.bio" class="text-xs text-surface-500 dark:text-surface-400 mt-1 leading-relaxed line-clamp-2">
                                                {{ teacher.bio }}
                                            </p>

                                            <div class="flex items-center gap-2 mt-2 flex-wrap text-[11px] text-surface-400">
                                                <span v-if="teacher.years_experience">{{ teacher.years_experience }} سنة خبرة</span>
                                                <span v-if="teacher.cheapest_monthly !== null" class="font-black text-primary-700 dark:text-primary-400 text-xs">
                                                    {{ formatMonthly(teacher.cheapest_monthly) }}
                                                </span>
                                                <span v-if="!teacher.has_free_seats" class="badge-red text-[10px]">اكتمل العدد</span>
                                            </div>
                                        </div>

                                        <!-- Actions -->
                                        <div class="flex flex-col gap-2 shrink-0 w-full sm:w-auto">
                                            <template v-if="teacher.is_subscribed">
                                                <Link
                                                    v-if="subject.learn_group_id"
                                                    :href="route('student.learn', { groupId: subject.learn_group_id })"
                                                    class="btn-primary btn-sm justify-center"
                                                >ادخل الحصة</Link>
                                            </template>

                                            <template v-else>
                                                <button
                                                    v-if="teacher.groups.length && teacher.has_free_seats"
                                                    type="button"
                                                    class="btn-primary btn-sm justify-center"
                                                    :disabled="subscribing === teacher.groups[0].id"
                                                    @click="subscribe(teacher.groups[0].id)"
                                                >
                                                    {{ subscribing === teacher.groups[0].id ? '...' : 'اشترك' }}
                                                </button>

                                                <Link :href="teacher.profile_url" class="btn-outline btn-sm justify-center">
                                                    اطّلع على المعلم
                                                </Link>
                                            </template>
                                        </div>
                                    </div>
                                </article>

                                <div class="p-4 bg-surface-50 dark:bg-surface-900/40 text-center">
                                    <Link :href="subject.browse_url" class="text-xs font-bold text-primary-600 dark:text-primary-400">
                                        اطّلع على معلم آخر في {{ subject.name }} ←
                                    </Link>
                                </div>
                            </div>

                            <p v-else class="text-sm text-surface-400 text-center py-8">
                                لم يُسنَد معلم لهذه المادة في صفك بعد.
                            </p>
                        </div>
                    </section>
                </div>
            </template>
        </div>

        <!-- Intro video lightbox -->
        <Teleport to="body">
            <div
                v-if="playing"
                class="modal-overlay z-[60] bg-black/80"
                @click.self="playing = null"
            >
                <div class="w-full max-w-3xl">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-white font-bold text-sm">فيديو تعريفي — {{ playing.name }}</h4>
                        <button type="button" class="text-white/70 hover:text-white p-2" aria-label="إغلاق" @click="playing = null">
                            <Icon name="close" class="w-5 h-5" />
                        </button>
                    </div>
                    <div class="aspect-video rounded-2xl overflow-hidden bg-black">
                        <iframe
                            :src="embed(playing.intro_video_url)"
                            class="w-full h-full"
                            frameborder="0"
                            referrerpolicy="strict-origin-when-cross-origin"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                        ></iframe>
                    </div>
                </div>
            </div>
        </Teleport>
    </DashboardLayout>
</template>
