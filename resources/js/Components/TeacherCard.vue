<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import SubscriptionBadge from '@/Components/SubscriptionBadge.vue';

const props = defineProps({
    teacher: { type: Object, required: true },
    // Carried into the profile link so the student lands on the right subject.
    gradeKey:  { type: String, default: null },
    subjectId: { type: [Number, String], default: null },
});

const showVideo = ref(false);

const coverImage = computed(() => (
    props.teacher.avatar
    || props.teacher.intro_video_thumbnail
    || props.teacher.subject?.image
    || null
));

const coverAlt = computed(() => {
    const subjectName = props.teacher.subject?.name;

    return subjectName
        ? [subjectName, props.teacher.name].join(' — ')
        : props.teacher.name;
});

const subjectArtwork = computed(() => {
    const icon = props.teacher.subject?.icon || 'book';
    const themes = {
        calculator: 'teacher-subject-art--math',
        language: 'teacher-subject-art--language',
        globe: 'teacher-subject-art--language',
        atom: 'teacher-subject-art--science',
        flask: 'teacher-subject-art--chemistry',
        dna: 'teacher-subject-art--biology',
        landmark: 'teacher-subject-art--social',
        chart: 'teacher-subject-art--business',
        settings: 'teacher-subject-art--technology',
        video: 'teacher-subject-art--arts',
        book: 'teacher-subject-art--general',
        student: 'teacher-subject-art--general',
        users: 'teacher-subject-art--general',
    };

    return {
        icon,
        name: props.teacher.subject?.name || 'منصة التفوق',
        theme: themes[icon] || 'teacher-subject-art--general',
    };
});

const profileUrl = computed(() => route('teachers.show', {
    id: props.teacher.id,
    ...(props.gradeKey ? { grade: props.gradeKey } : {}),
    ...(props.subjectId ? { subject: props.subjectId } : {}),
}));


/** Turn a YouTube/Vimeo link into something we can drop in an iframe. */
const embedUrl = computed(() => {
    const url = props.teacher.intro_video_url;
    if (!url) return null;

    const youtube = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([\w-]{11})/);
    if (youtube) return `https://www.youtube-nocookie.com/embed/${youtube[1]}?autoplay=1&rel=0`;

    const vimeo = url.match(/vimeo\.com\/(\d+)/);
    if (vimeo) return `https://player.vimeo.com/video/${vimeo[1]}?autoplay=1`;

    return url;
});
</script>

<template>
    <article class="teacher-card entity-card flex flex-col overflow-hidden">
        <!-- Teacher photo, then intro thumbnail, then subject artwork fallback -->
       <div class="relative aspect-video bg-surface-900 group">
           <img
                v-if="coverImage"
                :src="coverImage"
                :alt="coverAlt"
                class="w-full h-full object-cover"
               loading="lazy"
           />
            <div
                v-else
                :class="['teacher-subject-art', subjectArtwork.theme]"
                role="img"
                :aria-label="'صورة افتراضية لمادة ' + subjectArtwork.name"
            >
                <div class="teacher-subject-art__icon">
                    <Icon :name="subjectArtwork.icon" class="w-10 h-10" />
                </div>
                <span class="text-sm font-black">{{ subjectArtwork.name }}</span>
                <span class="text-[10px] text-white/70">كادر منصة التفوق</span>
           </div>

            <button
                type="button"
                v-if="teacher.intro_video_url"
                class="absolute inset-0 flex items-center justify-center bg-black/30 hover:bg-black/50 transition-colors"
                :aria-label="`شاهد فيديو تعريفي عن ${teacher.name}`"
                @click="showVideo = true"
            >
                <span class="w-14 h-14 rounded-full bg-accent-500 text-white flex items-center justify-center shadow-glow-accent transform group-hover:scale-110 transition-transform">
                    <Icon name="video" class="w-6 h-6" />
                </span>
            </button>

            <div v-else class="absolute bottom-2 start-2 badge bg-black/60 text-white text-[10px]">
                لا يوجد فيديو تعريفي
            </div>

            <div v-if="teacher.years_experience" class="absolute top-2 end-2 badge bg-black/60 text-white text-[10px]">
                {{ teacher.years_experience }} سنة خبرة
            </div>
        </div>

        <div class="entity-card-body flex-1">
            <div class="flex items-start justify-between gap-2">
                <h3 class="font-bold text-surface-900 dark:text-white text-sm leading-snug">
                    {{ teacher.name }}
                </h3>
                <span v-if="teacher.rating" class="badge-accent text-[10px] flex items-center gap-1 shrink-0">
                    ★ {{ teacher.rating }}
                </span>
            </div>

            <SubscriptionBadge
                v-if="teacher.is_subscribed !== undefined"
                :subscribed="teacher.is_subscribed"
                class="self-start"
            />

            <p v-if="teacher.headline" class="text-xs font-semibold text-primary-600 dark:text-primary-400">
                {{ teacher.headline }}
            </p>

            <p v-if="teacher.bio" class="text-xs text-surface-500 dark:text-surface-400 leading-relaxed line-clamp-3">
                {{ teacher.bio }}
            </p>

            <div v-if="teacher.subjects?.length" class="flex flex-wrap gap-1">
                <span v-for="subject in teacher.subjects" :key="subject" class="badge-gray text-[10px]">
                    {{ subject }}
                </span>
            </div>

            <div class="flex items-center gap-2 text-[11px] text-surface-400">
                <span v-if="teacher.groups_count">{{ teacher.groups_count }} مجموعة</span>
                <span v-if="teacher.accepts_private" class="badge-primary text-[10px]">حصص خاصة</span>
                <span v-if="teacher.has_free_seats === false" class="badge-red text-[10px]">مكتمل</span>
            </div>

            <div class="flex justify-end gap-2 pt-2 mt-auto border-t border-surface-100 dark:border-surface-700">
               <Link :href="profileUrl" :class="teacher.is_subscribed ? 'btn-outline btn-sm' : 'btn-primary btn-sm'">
                    عرض الملف
               </Link>
           </div>
        </div>
    </article>

    <!-- Intro video lightbox -->
    <Teleport to="body">
        <div
            v-if="showVideo && embedUrl"
            class="modal-overlay z-[60] bg-black/80"
            role="dialog"
            aria-modal="true"
            aria-label="فيديو المعلم"
            @click.self="showVideo = false"
        >
            <div class="w-full max-w-3xl">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-white font-bold text-sm">فيديو تعريفي — {{ teacher.name }}</h4>
                    <button type="button" class="text-white/70 hover:text-white p-2" aria-label="إغلاق" @click="showVideo = false">
                        <Icon name="close" class="w-5 h-5" />
                    </button>
                </div>
                <div class="aspect-video rounded-2xl overflow-hidden bg-black">
                    <iframe
                        :src="embedUrl"
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
</template>
