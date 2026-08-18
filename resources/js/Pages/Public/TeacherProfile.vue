<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';
import { formatMonthly, formatSchedule } from '@/lib/money';

const props = defineProps({
    teacher:     { type: Object, required: true },
    assignments: { type: Array, default: () => [] },
    reviews:     { type: Array, default: () => [] },
    focus:       { type: Object, default: () => ({ grade: null, subject: null }) },
    freeIntroBooking: { type: Object, default: null },
});

const page      = usePage();
const authUser  = computed(() => page.props.auth?.user ?? null);
const isStudent = computed(() => authUser.value?.roles?.includes('student') ?? false);

// Land on the assignment the student navigated in from, when there is one.
const initialAssignment = props.assignments.find(
    (a) => a.subject?.id === props.focus.subject && a.grade?.key === props.focus.grade,
) ?? props.assignments[0] ?? null;

const activeAssignmentId = ref(initialAssignment?.id ?? null);

const activeAssignment = computed(
    () => props.assignments.find((a) => a.id === activeAssignmentId.value) ?? null,
);

const playingVideo = ref(false);

const embedUrl = computed(() => {
    const url = props.teacher.intro_video_url;
    if (!url) return null;

    const youtube = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([\w-]{11})/);
    if (youtube) return `https://www.youtube-nocookie.com/embed/${youtube[1]}?rel=0`;

    const vimeo = url.match(/vimeo\.com\/(\d+)/);
    if (vimeo) return `https://player.vimeo.com/video/${vimeo[1]}`;

    return url;
});

const subscribing = ref(null);

function subscribeToGroup(groupId) {
    if (!authUser.value) {
        router.visit(route('login'));
        return;
    }

    subscribing.value = `group-${groupId}`;
    router.post(route('student.subscribe.group', { groupId }), {}, {
        onFinish: () => (subscribing.value = null),
    });
}

function subscribeToPrivate(assignmentId) {
    if (!authUser.value) {
        router.visit(route('login'));
        return;
    }

    subscribing.value = `private-${assignmentId}`;
    router.post(route('student.subscribe.private', { assignmentId }), {}, {
        onFinish: () => (subscribing.value = null),
    });
}

function askParentToPay(groupId) {
    router.post(route('student.purchase-requests.store'), { teaching_group_id: groupId });
}

function bookFreeIntro(slotId) {
    if (!authUser.value) {
        router.visit(route('login'));
        return;
    }

    subscribing.value = `free-${slotId}`;
    router.post(route('student.free-intro-sessions.store', { slotId }), {}, {
        preserveScroll: true,
        onFinish: () => (subscribing.value = null),
    });
}

function bookPrivateSlot(slotId) {
    if (!authUser.value) {
        router.visit(route('login'));
        return;
    }

    subscribing.value = `slot-${slotId}`;
    router.post(route('student.private-slots.book', { slotId }), {}, {
        preserveScroll: true,
        onFinish: () => (subscribing.value = null),
    });
}

function formatDateTime(value) {
    return new Date(value).toLocaleString('ar-EG', { dateStyle: 'medium', timeStyle: 'short' });
}

function formatDuration(seconds) {
    const total = Number(seconds || 0);
    if (!total) return '';

    const minutes = Math.floor(total / 60);
    const hours = Math.floor(minutes / 60);

    return hours > 0
        ? `${hours}س ${minutes % 60}د`
        : `${minutes}د`;
}

const otherTeachersUrl = computed(() => (
    props.focus.grade && props.focus.subject
        ? route('subjects.teachers', { gradeKey: props.focus.grade, subject: props.focus.subject })
        : route('teachers.index')
));
</script>

<template>
    <Head :title="teacher.name" />

    <AppLayout>
        <!-- ── Hero: who they are + intro video ─────────────────────── -->
        <section class="relative isolate overflow-hidden text-white py-14">
            <img
                :src="teacher.profile_cover || '/images/home-hero-bg.png'"
                alt=""
                aria-hidden="true"
                class="absolute inset-0 h-full w-full object-cover"
            />
            <div class="absolute inset-0 bg-gradient-to-l from-surface-950/95 via-surface-950/75 to-surface-950/35"></div>
            <div class="absolute inset-0 bg-primary-950/20"></div>

            <div class="container-app relative z-10 px-4">
                <nav v-if="activeAssignment" class="flex items-center gap-2 text-xs text-white/60 mb-6 flex-wrap">
                    <Link :href="route('grades.show', { key: activeAssignment.grade.key })" class="hover:text-white transition-colors">
                        {{ activeAssignment.grade.name }}
                    </Link>
                    <span>/</span>
                    <Link
                        :href="route('subjects.teachers', { gradeKey: activeAssignment.grade.key, subject: activeAssignment.subject.id })"
                        class="hover:text-white transition-colors"
                    >
                        {{ activeAssignment.subject.name }}
                    </Link>
                    <span>/</span>
                    <span class="text-white/90">{{ teacher.name }}</span>
                </nav>

                <div class="grid lg:grid-cols-2 gap-8 items-center">
                    <div>
                        <div class="flex items-center gap-4 mb-5">
                            <div class="avatar-xl bg-white/10 border-2 border-white/20">
                                <img v-if="teacher.avatar" :src="teacher.avatar" :alt="teacher.name" class="w-full h-full object-cover" />
                                <span v-else class="text-white font-black">{{ teacher.name?.charAt(0) }}</span>
                            </div>

                            <div>
                                <h1 class="text-2xl sm:text-3xl font-black">{{ teacher.name }}</h1>
                                <p v-if="teacher.headline" class="text-accent-300 text-sm font-semibold mt-1">
                                    {{ teacher.headline }}
                                </p>
                            </div>
                        </div>

                        <p v-if="teacher.bio" class="text-white/75 text-sm leading-relaxed mb-6">
                            {{ teacher.bio }}
                        </p>

                        <div class="flex flex-wrap gap-3">
                            <div v-if="teacher.rating" class="glass rounded-xl px-4 py-2.5">
                                <div class="text-lg font-black text-accent-300">★ {{ teacher.rating }}</div>
                                <div class="text-[11px] text-white/60">التقييم</div>
                            </div>
                            <div v-if="teacher.years_experience" class="glass rounded-xl px-4 py-2.5">
                                <div class="text-lg font-black">{{ teacher.years_experience }}</div>
                                <div class="text-[11px] text-white/60">سنوات الخبرة</div>
                            </div>
                            <div class="glass rounded-xl px-4 py-2.5">
                                <div class="text-lg font-black">{{ teacher.students_count }}</div>
                                <div class="text-[11px] text-white/60">طالب حالياً</div>
                            </div>
                            <Link :href="otherTeachersUrl" class="btn-outline btn-sm border-white/30 text-white hover:bg-white/10">
                                مشاهدة مدرس آخر
                            </Link>
                        </div>
                    </div>

                    <!-- Intro video -->
                    <div class="rounded-2xl overflow-hidden bg-black/40 border border-white/15 aspect-video relative">
                        <iframe
                            v-if="playingVideo && embedUrl"
                            :src="`${embedUrl}${embedUrl.includes('?') ? '&' : '?'}autoplay=1`"
                            class="w-full h-full"
                            frameborder="0"
                            referrerpolicy="strict-origin-when-cross-origin"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                        ></iframe>

                        <template v-else>
                            <img
                                v-if="teacher.intro_video_thumbnail"
                                :src="teacher.intro_video_thumbnail"
                                :alt="teacher.name"
                                class="w-full h-full object-cover opacity-70"
                            />
                            <button
                                v-if="embedUrl"
                                type="button"
                                class="absolute inset-0 flex flex-col items-center justify-center gap-3 hover:bg-black/20 transition-colors"
                                @click="playingVideo = true"
                            >
                                <span class="w-16 h-16 rounded-full bg-accent-500 flex items-center justify-center shadow-glow-accent">
                                    <Icon name="video" class="w-7 h-7 text-white" />
                                </span>
                                <span class="text-sm font-bold">شاهد طريقة الشرح</span>
                            </button>
                            <div v-else class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-white/50">
                                <Icon name="video" class="w-10 h-10" />
                                <span class="text-xs">لم يضف المعلم فيديو تعريفي بعد</span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Subjects and booking ─────────────────────────────────── -->
        <section class="section">
            <div class="container-app">
                <div v-if="assignments.length">
                    <h2 class="text-xl font-black text-surface-900 dark:text-white mb-4">مواعيد المدرس وخيارات الحجز</h2>

                    <!-- Subject tabs -->
                    <div class="flex flex-wrap gap-2 mb-6">
                        <button
                            v-for="assignment in assignments"
                            :key="assignment.id"
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-bold transition-all"
                            :class="assignment.id === activeAssignmentId
                                ? 'bg-primary-600 text-white shadow-glow-primary'
                                : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-200 dark:hover:bg-surface-700'"
                            @click="activeAssignmentId = assignment.id"
                        >
                            {{ assignment.subject?.name }}
                            <span class="opacity-60"> — {{ assignment.grade?.name }}</span>
                        </button>
                    </div>

                    <div :key="'free-intro-' + activeAssignment.id" class="card p-5 mb-5 border-accent-500/30 bg-accent-500/5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h3 class="font-black text-surface-900 dark:text-white">حصة تجريبية مجانية</h3>
                                <p class="text-xs text-surface-500 mt-1">
                                    شاهد التسجيل المجاني أو احجز موعدًا مباشرًا واحدًا مع المدرس بدون دفع أو إدخال بيانات مالية.
                                </p>
                            </div>
                            <span class="badge-green">مجانية 100%</span>
                        </div>

                        <div v-if="activeAssignment.free_intro_booking" class="mt-4 rounded-xl bg-green-500/10 p-3 text-sm text-green-700 dark:text-green-300">
                            حجزك مؤكد: {{ formatDateTime(activeAssignment.free_intro_booking.starts_at) }}
                        </div>

                        <div v-else-if="activeAssignment.free_intro_used" class="mt-4 rounded-xl bg-surface-100 dark:bg-surface-800 p-3 text-xs text-surface-500 dark:text-surface-300">
                            سبق لك حجز الحصة المجانية مع هذا المعلم في صف آخر.
                        </div>

                        <div v-else-if="activeAssignment.free_intro_slots?.length" class="mt-4 flex flex-wrap gap-2">
                            <button
                                v-for="slot in activeAssignment.free_intro_slots"
                                :key="slot.id"
                                type="button"
                                class="btn-outline btn-sm"
                                :disabled="subscribing === `free-${slot.id}`"
                                @click="bookFreeIntro(slot.id)"
                            >
                                {{ subscribing === `free-${slot.id}` ? 'جارٍ الحجز...' : formatDateTime(slot.starts_at) }}
                            </button>
                        </div>

                        <p v-else-if="!activeAssignment.free_recordings?.length" class="mt-4 text-xs text-surface-400">
                            لا يوجد موعد مجاني منشور لهذا الصف حاليًا.
                        </p>

                        <div v-if="activeAssignment.free_recordings?.length" class="mt-5 border-t border-accent-500/20 pt-5">
                            <div class="flex items-center justify-between gap-3 mb-3">
                                <div>
                                    <h4 class="font-black text-surface-900 dark:text-white">شاهد التسجيل المجاني</h4>
                                    <p class="text-xs text-surface-500 mt-1">لو فاتك موعد اللايف، تقدر تشاهد الشرح المسجل في أي وقت.</p>
                                </div>
                                <Icon name="video" class="w-5 h-5 text-accent-600" />
                            </div>

                            <div class="space-y-4">
                                <article v-for="recording in activeAssignment.free_recordings" :key="recording.id" class="overflow-hidden rounded-2xl border border-surface-200 bg-white dark:border-surface-700 dark:bg-surface-900">
                                    <video
                                        :src="recording.stream_url"
                                        controls
                                        preload="metadata"
                                        class="aspect-video w-full bg-black object-contain"
                                        :aria-label="recording.title"
                                    ></video>
                                    <div class="p-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <h5 class="font-bold text-sm text-surface-900 dark:text-white">{{ recording.title }}</h5>
                                            <span v-if="formatDuration(recording.duration_seconds)" class="shrink-0 text-[11px] text-surface-400">
                                                {{ formatDuration(recording.duration_seconds) }}
                                            </span>
                                        </div>
                                        <p v-if="recording.description" class="mt-2 text-xs leading-6 text-surface-500 dark:text-surface-400">
                                            {{ recording.description }}
                                        </p>
                                    </div>
                                </article>
                            </div>
                        </div>
                    </div>

                    <div v-if="activeAssignment" :key="activeAssignment.id" class="grid lg:grid-cols-3 gap-5">
                        <!-- Group list -->
                        <div class="lg:col-span-2 space-y-3">
                            <article
                                v-for="group in activeAssignment.groups"
                                :key="group.id"
                                class="card p-5"
                            >
                                <div class="flex items-start justify-between gap-4 flex-wrap">
                                    <div class="flex-1 min-w-[200px]">
                                        <h3 class="font-bold text-surface-900 dark:text-white text-sm mb-1">
                                            {{ group.name }}
                                        </h3>
                                        <p class="text-xs text-surface-500 dark:text-surface-400 flex items-center gap-1.5">
                                            <Icon name="calendar" class="w-3.5 h-3.5" />
                                            {{ formatSchedule(group.schedules) || 'الموعد غير محدد' }}
                                        </p>
                                        <p class="text-[11px] text-surface-400 mt-1">
                                            {{ group.seats_left }} مقعد متبقٍ من {{ group.capacity }}
                                        </p>
                                    </div>

                                    <div class="text-end">
                                        <div class="text-lg font-black text-primary-700 dark:text-primary-400">
                                            {{ formatMonthly(group.monthly_price) }}
                                        </div>

                                        <div class="flex flex-col gap-1.5 mt-2">
                                            <span v-if="group.is_subscribed" class="badge-green text-[10px]">مشترك بالفعل</span>

                                            <template v-else-if="group.seats_left > 0">
                                                <button
                                                    type="button"
                                                    class="btn-primary btn-sm"
                                                    :disabled="subscribing === `group-${group.id}`"
                                                    @click="subscribeToGroup(group.id)"
                                                >
                                                    {{ subscribing === `group-${group.id}` ? '...' : 'احجز المجموعة' }}
                                                </button>

                                                <button
                                                    v-if="isStudent"
                                                    type="button"
                                                    class="btn-ghost btn-sm text-[10px]"
                                                    @click="askParentToPay(group.id)"
                                                >
                                                    اطلب من ولي الأمر
                                                </button>
                                            </template>

                                            <span v-else class="badge-red text-[10px]">اكتمل العدد</span>
                                        </div>
                                    </div>
                                </div>
                            </article>

                            <div v-if="!activeAssignment.groups.length" class="card p-8 text-center">
                                <p class="text-sm text-surface-400">لا توجد مجموعات متاحة لهذه المادة حالياً.</p>
                            </div>
                        </div>

                        <!-- Private tuition -->
                        <aside class="card p-5 h-fit">
                            <h3 class="font-bold text-surface-900 dark:text-white text-sm mb-2">حصص خاصة</h3>

                            <template v-if="activeAssignment.accepts_private">
                                <p class="text-xs text-surface-500 dark:text-surface-400 mb-4 leading-relaxed">كل موعد برايفيت متاح لطالب واحد فقط ويختفي فور حجزه.</p>

                                <div class="text-xl font-black text-primary-700 dark:text-primary-400 mb-4">
                                    {{ formatMonthly(activeAssignment.private_monthly_price) }}
                                </div>

                                <div class="space-y-2">
                                    <p class="text-xs font-bold text-surface-700 dark:text-surface-200">المواعيد المنشورة من المدرس</p>
                                    <div
                                        v-for="slot in activeAssignment.private_slots"
                                        :key="slot.id"
                                        class="flex items-center justify-between gap-2 rounded-xl border border-surface-200 dark:border-surface-700 px-3 py-2"
                                    >
                                        <span class="text-xs">{{ formatDateTime(slot.starts_at) }}</span>
                                        <button
                                            v-if="activeAssignment.has_private_subscription"
                                            type="button"
                                            class="btn-outline btn-sm"
                                            :disabled="subscribing === `slot-${slot.id}`"
                                            @click="bookPrivateSlot(slot.id)"
                                        >
                                            {{ subscribing === `slot-${slot.id}` ? 'جارٍ الحجز...' : 'احجز الموعد' }}
                                        </button>
                                        <span v-else class="text-[11px] text-surface-400">يتاح الحجز بعد الاشتراك</span>
                                    </div>
                                    <p v-if="!activeAssignment.private_slots?.length" class="text-xs text-surface-400">لا توجد مواعيد برايفيت منشورة حاليًا.</p>
                                </div>

                                <Link
                                    v-if="activeAssignment.private_subscription?.status === 'pending'"
                                    :href="route('checkout.show', activeAssignment.private_subscription.id)"
                                    class="btn-primary btn-sm w-full justify-center"
                                >
                                    إكمال دفع اشتراك البرايفيت
                                </Link>
                                <button
                                    v-else-if="!activeAssignment.has_private_subscription"
                                    type="button"
                                    class="btn-accent btn-sm w-full justify-center"
                                    :disabled="subscribing === `private-${activeAssignment.id}`"
                                    @click="subscribeToPrivate(activeAssignment.id)"
                                >
                                    {{ subscribing === `private-${activeAssignment.id}` ? 'جارٍ التحويل للدفع...' : 'اشترك برايفيت ثم اختر الموعد' }}
                                </button>

                                <Link
                                    v-if="!activeAssignment.has_private_subscription && activeAssignment.private_request?.conversation_id"
                                    :href="route('chat.index', { conversation: activeAssignment.private_request.conversation_id })"
                                    class="btn-ghost btn-sm w-full justify-center"
                                >
                                    متابعة الرسائل
                                </Link>
                            </template>

                            <p v-else class="text-xs text-surface-400">
                                هذا المعلم لا يقدّم حصصاً خاصة في هذه المادة حالياً.
                            </p>
                        </aside>
                    </div>
                </div>

                <div v-else class="card p-12 text-center">
                    <Icon name="calendar" class="w-10 h-10 text-surface-300 mx-auto mb-3" />
                    <p class="text-sm text-surface-400">لم يضف هذا المعلم أي مواد أو مجموعات بعد.</p>
                </div>
            </div>
        </section>

        <!-- ── Reviews ──────────────────────────────────────────────── -->
        <section v-if="reviews.length" class="section pt-0">
            <div class="container-app">
                <h2 class="text-xl font-black text-surface-900 dark:text-white mb-4">آراء الطلاب</h2>

                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <article v-for="review in reviews" :key="review.id" class="card p-5">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="avatar-sm">
                                <img v-if="review.author?.avatar" :src="review.author.avatar" :alt="review.author.name" class="w-full h-full object-cover" />
                                <span v-else class="text-primary-700 dark:text-primary-300 font-bold text-xs">
                                    {{ review.author?.name?.charAt(0) }}
                                </span>
                            </div>
                            <div class="min-w-0">
                                <div class="text-xs font-bold text-surface-800 dark:text-surface-100 truncate">
                                    {{ review.author?.name }}
                                </div>
                                <div class="text-[10px] text-accent-500">{{ '★'.repeat(review.rating) }}</div>
                            </div>
                        </div>

                        <p v-if="review.comment" class="text-xs text-surface-600 dark:text-surface-300 leading-relaxed">
                            {{ review.comment }}
                        </p>
                    </article>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
