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
const privateNote = ref('');

const embedUrl = computed(() => {
    const url = props.teacher.intro_video_url;
    if (!url) return null;

    const youtube = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([\w-]{11})/);
    if (youtube) return `https://www.youtube.com/embed/${youtube[1]}?rel=0`;

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

function requestPrivateLessons(assignmentId) {
    if (!authUser.value) {
        router.visit(route('login'));
        return;
    }

    subscribing.value = `private-${assignmentId}`;
    router.post(route('student.private-lesson-requests.store', { assignmentId }), {
        note: privateNote.value,
    }, {
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
        <section class="hero-gradient text-white py-14">
            <div class="container-app px-4">
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

                    <div class="card p-5 mb-5 border-accent-500/30 bg-accent-500/5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h3 class="font-black text-surface-900 dark:text-white">حصة تجريبية مجانية</h3>
                                <p class="text-xs text-surface-500 mt-1">
                                    احجز مسبقًا حصة واحدة مع المدرس بدون دفع أو إدخال بيانات مالية.
                                </p>
                            </div>
                            <span class="badge-green">مجانية 100%</span>
                        </div>

                        <div v-if="freeIntroBooking" class="mt-4 rounded-xl bg-green-500/10 p-3 text-sm text-green-700 dark:text-green-300">
                            حجزك مؤكد: {{ formatDateTime(freeIntroBooking.starts_at) }}
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

                        <p v-else class="mt-4 text-xs text-surface-400">
                            لا يوجد موعد مجاني منشور لهذا الصف حاليًا.
                        </p>
                    </div>

                    <div v-if="activeAssignment" class="grid lg:grid-cols-3 gap-5">
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

                                <div v-if="activeAssignment.has_private_subscription" class="space-y-2">
                                    <p class="text-xs font-bold text-surface-700 dark:text-surface-200">اختر موعدًا متاحًا</p>
                                    <button
                                        v-for="slot in activeAssignment.private_slots"
                                        :key="slot.id"
                                        type="button"
                                        class="btn-outline btn-sm w-full justify-center"
                                        :disabled="subscribing === `slot-${slot.id}`"
                                        @click="bookPrivateSlot(slot.id)"
                                    >
                                        {{ subscribing === `slot-${slot.id}` ? 'جارٍ الحجز...' : formatDateTime(slot.starts_at) }}
                                    </button>
                                    <p v-if="!activeAssignment.private_slots?.length" class="text-xs text-surface-400">لا توجد مواعيد برايفيت متاحة حاليًا.</p>
                                </div>

                                <Link
                                    v-else-if="activeAssignment.private_request?.conversation_id"
                                    :href="route('chat.index', { conversation: activeAssignment.private_request.conversation_id })"
                                    class="btn-accent btn-sm w-full justify-center"
                                >
                                    متابعة الاتفاق مع المدرس
                                </Link>

                                <template v-else>
                                    <label class="input-label" for="private-note">الأوقات المناسبة لك (اختياري)</label>
                                    <textarea
                                        id="private-note"
                                        v-model="privateNote"
                                        class="input min-h-20 mb-3"
                                        maxlength="1000"
                                        placeholder="مثال: الأحد والثلاثاء بعد الساعة 6 مساءً"
                                    ></textarea>

                                    <button
                                        type="button"
                                        class="btn-accent btn-sm w-full justify-center"
                                        :disabled="subscribing === `private-${activeAssignment.id}`"
                                        @click="requestPrivateLessons(activeAssignment.id)"
                                    >
                                        {{ subscribing === `private-${activeAssignment.id}` ? '...' : 'قدّم طلب حجز برايفت' }}
                                    </button>
                                </template>
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
