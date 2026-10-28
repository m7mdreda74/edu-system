<script setup>
import { computed, ref, watch } from 'vue';
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

// Subscription Wizard State (Step 1: Type -> Step 2: Schedule -> Step 3: Price & Checkout)
const subscribeStep = ref(1);
const selectedType = ref('group'); // 'group' | 'private'
const selectedGroupId = ref(null);
const selectedPrivateSlotId = ref(null);

const selectedGroup = computed(() => {
    if (!activeAssignment.value) return null;
    return activeAssignment.value.groups?.find((g) => g.id === selectedGroupId.value) ?? null;
});

const selectedPrivateSlot = computed(() => {
    if (!activeAssignment.value) return null;
    return activeAssignment.value.private_slots?.find((s) => s.id === selectedPrivateSlotId.value) ?? null;
});

const chosenPrice = computed(() => {
    if (!activeAssignment.value) return 0;
    if (selectedType.value === 'group') {
        return selectedGroup.value?.monthly_price ?? 0;
    }
    return activeAssignment.value.private_monthly_price ?? 0;
});

function selectType(type) {
    selectedType.value = type;
    selectedGroupId.value = null;
    selectedPrivateSlotId.value = null;
    subscribeStep.value = 2;
}

function selectGroup(group) {
    if (group.seats_left <= 0 && !group.is_subscribed) return;
    selectedGroupId.value = group.id;
    subscribeStep.value = 3;
}

function selectPrivateSlot(slot) {
    selectedPrivateSlotId.value = slot ? slot.id : 'flexible';
    subscribeStep.value = 3;
}

function confirmAndPay() {
    if (selectedType.value === 'group') {
        if (!selectedGroup.value) return;
        subscribeToGroup(selectedGroup.value.id);
    } else {
        if (!activeAssignment.value) return;
        subscribeToPrivate(activeAssignment.value.id);
    }
}

watch(activeAssignmentId, () => {
    subscribeStep.value = 1;
    selectedGroupId.value = null;
    selectedPrivateSlotId.value = null;
});

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

function formatDateTime(value, timezone = 'Asia/Qatar') {
    if (!value) return '';
    try {
        return new Date(value).toLocaleString('ar-QA', {
            timeZone: timezone || 'Asia/Qatar',
            dateStyle: 'medium',
            timeStyle: 'short',
        });
    } catch {
        return new Date(value).toLocaleString('ar-EG', { dateStyle: 'medium', timeStyle: 'short' });
    }
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
                            حجزك مؤكد: {{ formatDateTime(activeAssignment.free_intro_booking.starts_at) }} (توقيت الدوحة)
                        </div>

                        <div v-else-if="activeAssignment.free_intro_used" class="mt-4 rounded-xl bg-surface-100 dark:bg-surface-800 p-3 text-xs text-surface-500 dark:text-surface-300">
                            سبق لك حجز الحصة المجانية مع هذا المعلم في صف آخر.
                        </div>

                        <div v-else-if="activeAssignment.free_intro_slots?.length" class="mt-4 flex flex-wrap gap-2">
                            <button
                                v-for="slot in activeAssignment.free_intro_slots"
                                :key="slot.id"
                                type="button"
                                class="btn-outline btn-sm flex items-center gap-1.5"
                                :disabled="subscribing === `free-${slot.id}`"
                                @click="bookFreeIntro(slot.id)"
                            >
                                <span>{{ subscribing === `free-${slot.id}` ? 'جارٍ الحجز...' : formatDateTime(slot.starts_at, slot.timezone) }}</span>
                                <span class="text-[10px] text-surface-400 font-normal">(توقيت الدوحة)</span>
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

                    <!-- Interactive Subscription Wizard -->
                    <div v-if="activeAssignment" :key="activeAssignment.id" class="card p-6 md:p-8 border border-primary-500/20 bg-gradient-to-b from-surface-50 to-white dark:from-surface-900 dark:to-surface-950 shadow-xl rounded-3xl mb-8">
                        <!-- Wizard Top Header with Stepper Pills -->
                        <div class="flex items-center justify-between mb-8 pb-6 border-b border-surface-200 dark:border-surface-800 flex-wrap gap-4">
                            <div>
                                <span class="text-xs font-bold text-primary-600 dark:text-primary-400 bg-primary-500/10 px-3 py-1 rounded-full">
                                    الاشتراك مع المعلم
                                </span>
                                <h2 class="text-2xl font-black text-surface-900 dark:text-white mt-2">
                                    خطوات الاشتراك مع {{ teacher.name }}
                                </h2>
                                <p class="text-xs text-surface-500 mt-1">
                                    {{ activeAssignment.subject?.name }} — {{ activeAssignment.grade?.name }}
                                </p>
                            </div>

                            <!-- 3 Step Pills Indicator -->
                            <div class="flex items-center gap-1.5 sm:gap-2">
                                <!-- Step 1 pill -->
                                <button
                                    type="button"
                                    @click="subscribeStep = 1"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold transition-all"
                                    :class="subscribeStep === 1
                                        ? 'bg-primary-600 text-white shadow-md'
                                        : 'bg-surface-200 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-300'"
                                >
                                    <span class="w-4 h-4 rounded-full bg-white/20 flex items-center justify-center text-[10px]">1</span>
                                    <span>نوع الاشتراك</span>
                                </button>

                                <span class="text-surface-300 dark:text-surface-700">←</span>

                                <!-- Step 2 pill -->
                                <button
                                    type="button"
                                    @click="subscribeStep = 2"
                                    :disabled="!selectedType"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold transition-all"
                                    :class="subscribeStep === 2
                                        ? 'bg-primary-600 text-white shadow-md'
                                        : 'bg-surface-200 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-300 disabled:opacity-40'"
                                >
                                    <span class="w-4 h-4 rounded-full bg-white/20 flex items-center justify-center text-[10px]">2</span>
                                    <span>المواعيد المتاحة</span>
                                </button>

                                <span class="text-surface-300 dark:text-surface-700">←</span>

                                <!-- Step 3 pill -->
                                <button
                                    type="button"
                                    @click="(selectedGroup || (selectedType === 'private' && selectedPrivateSlotId)) ? subscribeStep = 3 : null"
                                    :disabled="!(selectedGroup || (selectedType === 'private' && selectedPrivateSlotId))"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold transition-all"
                                    :class="subscribeStep === 3
                                        ? 'bg-accent-500 text-surface-950 font-black shadow-md'
                                        : 'bg-surface-200 dark:bg-surface-800 text-surface-600 dark:text-surface-300 hover:bg-surface-300 disabled:opacity-40'"
                                >
                                    <span class="w-4 h-4 rounded-full bg-surface-950/20 flex items-center justify-center text-[10px]">3</span>
                                    <span>السعر والدفع</span>
                                </button>
                            </div>
                        </div>

                        <!-- ── STEP 1: SELECT GROUP VS PRIVATE (NO PRICES SHOWN) ── -->
                        <div v-if="subscribeStep === 1" class="space-y-6">
                            <div class="text-center md:text-start">
                                <h3 class="text-lg font-bold text-surface-900 dark:text-white">
                                    الخطوة 1: حدد نمط الحضور المناسب لك
                                </h3>
                                <p class="text-xs text-surface-500 mt-1">
                                    اختر ما إذا كنت ترغب في الانضمام لمجموعة دراسية تفاعلية أو جلسة خاصة فردية
                                </p>
                            </div>

                            <div class="grid md:grid-cols-2 gap-5">
                                <!-- Group Class Card -->
                                <div
                                    @click="selectType('group')"
                                    class="group relative card p-6 cursor-pointer border-2 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl flex flex-col justify-between"
                                    :class="selectedType === 'group'
                                        ? 'border-primary-500 bg-primary-500/5 ring-4 ring-primary-500/10'
                                        : 'border-surface-200 dark:border-surface-800 hover:border-primary-400'"
                                >
                                    <div>
                                        <div class="flex items-center justify-between gap-3 mb-4">
                                            <div class="w-12 h-12 rounded-2xl bg-primary-500/10 text-primary-600 flex items-center justify-center text-2xl font-bold">
                                                👥
                                            </div>
                                            <span class="badge-primary text-xs">الأكثر طلباً ⭐</span>
                                        </div>

                                        <h4 class="text-lg font-black text-surface-900 dark:text-white mb-2">
                                            مجموعة دراسية تفاعلية
                                        </h4>
                                        <p class="text-xs text-surface-600 dark:text-surface-300 leading-relaxed mb-4">
                                            شرح مباشر متكامل للمنهج وفق خطة الوزارة، حل الاختبارات والأسئلة الوزارية، ومتابعة دراسية شاملة مع إتاحة تسجيلات الحصص.
                                        </p>

                                        <div class="flex items-center gap-2 text-xs font-semibold text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-950/40 p-2.5 rounded-xl">
                                            <Icon name="clock" class="w-4 h-4 text-primary-500 shrink-0" />
                                            <span>{{ activeAssignment.groups?.length || 0 }} مجموعات متاحة بمواعيد مختلفة</span>
                                        </div>
                                    </div>

                                    <div class="mt-6 pt-4 border-t border-surface-200 dark:border-surface-800 flex items-center justify-between">
                                        <span class="text-xs font-bold text-surface-500">اختر موعد المجموعة</span>
                                        <span class="btn-primary btn-sm flex items-center gap-1 group-hover:gap-2 transition-all">
                                            <span>متابعة للمواعيد</span>
                                            <Icon name="arrowLeft" class="w-4 h-4" />
                                        </span>
                                    </div>
                                </div>

                                <!-- Private Tuition Card -->
                                <div
                                    v-if="activeAssignment.accepts_private"
                                    @click="selectType('private')"
                                    class="group relative card p-6 cursor-pointer border-2 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl flex flex-col justify-between"
                                    :class="selectedType === 'private'
                                        ? 'border-accent-500 bg-accent-500/5 ring-4 ring-accent-500/10'
                                        : 'border-surface-200 dark:border-surface-800 hover:border-accent-400'"
                                >
                                    <div>
                                        <div class="flex items-center justify-between gap-3 mb-4">
                                            <div class="w-12 h-12 rounded-2xl bg-accent-500/10 text-accent-600 flex items-center justify-center text-2xl font-bold">
                                                👤
                                            </div>
                                            <span class="badge-accent text-xs">متابعة فردية 100% 🎯</span>
                                        </div>

                                        <h4 class="text-lg font-black text-surface-900 dark:text-white mb-2">
                                            جلسة خاصة (برايفت 1-on-1)
                                        </h4>
                                        <p class="text-xs text-surface-600 dark:text-surface-300 leading-relaxed mb-4">
                                            تركيز كامل من المعلم معك بمفردك، إجابة مباشرة عن أسئلتك ونقاط ضعفك، وتدريب مكثف على الامتحانات في الموعد المخصص لك.
                                        </p>

                                        <div class="flex items-center gap-2 text-xs font-semibold text-accent-700 dark:text-accent-300 bg-accent-50 dark:bg-accent-950/40 p-2.5 rounded-xl">
                                            <Icon name="calendar" class="w-4 h-4 text-accent-500 shrink-0" />
                                            <span>{{ activeAssignment.private_slots?.length || 0 }} مواعيد خاصة منشورة متاحة</span>
                                        </div>
                                    </div>

                                    <div class="mt-6 pt-4 border-t border-surface-200 dark:border-surface-800 flex items-center justify-between">
                                        <span class="text-xs font-bold text-surface-500">اختر موعد الجلسة</span>
                                        <span class="btn-accent btn-sm flex items-center gap-1 group-hover:gap-2 transition-all">
                                            <span>متابعة للمواعيد</span>
                                            <Icon name="arrowLeft" class="w-4 h-4" />
                                        </span>
                                    </div>
                                </div>

                                <div v-else class="card p-6 border-dashed border-2 border-surface-300 dark:border-surface-700 flex flex-col items-center justify-center text-center">
                                    <Icon name="info" class="w-8 h-8 text-surface-400 mb-2" />
                                    <h4 class="font-bold text-surface-700 dark:text-surface-300 text-sm">الحصص الخاصة غير متاحة</h4>
                                    <p class="text-xs text-surface-400 mt-1">المعلم يكتفي بالمجموعات الدراسية لهذه المادة حالياً.</p>
                                </div>
                            </div>
                        </div>

                        <!-- ── STEP 2: SELECT AVAILABLE SCHEDULE (NO PRICES SHOWN) ── -->
                        <div v-else-if="subscribeStep === 2" class="space-y-6">
                            <div class="flex items-center justify-between flex-wrap gap-4">
                                <div>
                                    <h3 class="text-lg font-bold text-surface-900 dark:text-white">
                                        الخطوة 2: اختر الموعد المناسب لجدولك
                                    </h3>
                                    <p class="text-xs text-surface-500 mt-1">
                                        {{ selectedType === 'group' ? 'المجموعات الدراسية المتاحة ومواعيد انعقادها' : 'مواعيد الجلسات الخاصة المتاحة للحجز' }}
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    @click="subscribeStep = 1"
                                    class="btn-ghost btn-sm text-xs flex items-center gap-1.5"
                                >
                                    <Icon name="arrowRight" class="w-3.5 h-3.5" />
                                    <span>تغيير نوع الاشتراك ({{ selectedType === 'group' ? 'مجموعة' : 'برايفت' }})</span>
                                </button>
                            </div>

                            <!-- Groups Selection -->
                            <div v-if="selectedType === 'group'" class="space-y-4">
                                <div v-if="activeAssignment.groups?.length" class="grid sm:grid-cols-2 gap-4">
                                    <div
                                        v-for="group in activeAssignment.groups"
                                        :key="group.id"
                                        @click="selectGroup(group)"
                                        class="card p-5 cursor-pointer border-2 transition-all flex flex-col justify-between"
                                        :class="[
                                            group.seats_left <= 0 && !group.is_subscribed
                                                ? 'opacity-60 cursor-not-allowed bg-surface-100 dark:bg-surface-900 border-surface-200'
                                                : selectedGroupId === group.id
                                                    ? 'border-primary-500 bg-primary-500/5 shadow-md'
                                                    : 'border-surface-200 dark:border-surface-800 hover:border-primary-400'
                                        ]"
                                    >
                                        <div>
                                            <div class="flex items-start justify-between gap-3 mb-2">
                                                <h4 class="font-bold text-surface-900 dark:text-white text-base">
                                                    {{ group.name }}
                                                </h4>
                                                <span v-if="group.is_subscribed" class="badge-green text-[10px]">مشترك بالفعل</span>
                                                <span v-else-if="group.seats_left > 0" class="badge-primary text-[10px]">متاح للتسجيل</span>
                                                <span v-else class="badge-red text-[10px]">اكتمل العدد</span>
                                            </div>

                                            <div class="space-y-2 mt-3 text-xs">
                                                <div class="flex items-center gap-2 text-surface-700 dark:text-surface-200">
                                                    <Icon name="calendar" class="w-4 h-4 text-primary-500 shrink-0" />
                                                    <span class="font-bold">{{ formatSchedule(group.schedules) || 'الموعد بالتنسيق' }}</span>
                                                </div>
                                                <div class="flex items-center gap-2 text-surface-500">
                                                    <Icon name="student" class="w-4 h-4 text-surface-400 shrink-0" />
                                                    <span>{{ group.seats_left }} مقعد متبقٍ من {{ group.capacity }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-5 pt-3 border-t border-surface-200 dark:border-surface-800 flex items-center justify-between">
                                            <span class="text-xs text-surface-400 font-medium">انقر للاختيار</span>
                                            <button
                                                type="button"
                                                class="btn-primary btn-sm"
                                                :disabled="group.seats_left <= 0 && !group.is_subscribed"
                                            >
                                                {{ selectedGroupId === group.id ? 'تم الاختيار ✅' : 'اختيار ومتابعة ←' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="card p-8 text-center text-surface-400">
                                    لا توجد مجموعات متاحة حالياً لهذه المادة.
                                </div>
                            </div>

                            <!-- Private Slots Selection -->
                            <div v-else class="space-y-4">
                                <div v-if="activeAssignment.private_slots?.length" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                    <div
                                        v-for="slot in activeAssignment.private_slots"
                                        :key="slot.id"
                                        @click="selectPrivateSlot(slot)"
                                        class="card p-4 cursor-pointer border-2 transition-all flex flex-col justify-between"
                                        :class="selectedPrivateSlotId === slot.id
                                            ? 'border-accent-500 bg-accent-500/5 shadow-md'
                                            : 'border-surface-200 dark:border-surface-800 hover:border-accent-400'"
                                    >
                                        <div>
                                            <div class="flex items-center gap-2 mb-2">
                                                <span class="w-2.5 h-2.5 rounded-full bg-accent-500"></span>
                                                <span class="text-xs font-black text-surface-900 dark:text-white">{{ formatDateTime(slot.starts_at, slot.timezone) }} (توقيت الدوحة)</span>
                                            </div>
                                            <p class="text-[11px] text-surface-500">جلسة خاصة لطالب واحد مباشرة عبر المنصة</p>
                                        </div>

                                        <button
                                            type="button"
                                            class="btn-accent btn-sm mt-3 w-full justify-center"
                                        >
                                            {{ selectedPrivateSlotId === slot.id ? 'تم اختيار الموعد ✅' : 'اختيار هذا الموعد ←' }}
                                        </button>
                                    </div>
                                </div>

                                <div v-else class="card p-6 border-dashed border-2 text-center">
                                    <p class="text-sm font-bold text-surface-800 dark:text-surface-200 mb-1">
                                        لم يحدد المعلم مواعيد ثابتة بعد
                                    </p>
                                    <p class="text-xs text-surface-500 mb-4">
                                        يمكنك المتابعة لتأكيد الاشتراك وسيقوم المعلم بتنسيق الموعد المناسب معك فور التفعيل.
                                    </p>
                                    <button
                                        type="button"
                                        class="btn-accent btn-sm"
                                        @click="selectPrivateSlot(null)"
                                    >
                                        متابعة للاشتراك وتنسيق الموعد ←
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- ── STEP 3: PRICE REVEAL, CONFIRM & REDIRECT TO CHECKOUT ── -->
                        <div v-else-if="subscribeStep === 3" class="space-y-6">
                            <div class="flex items-center justify-between flex-wrap gap-4">
                                <div>
                                    <h3 class="text-lg font-bold text-surface-900 dark:text-white">
                                        الخطوة 3: تفاصيل وسعر الاشتراك وتأكيد الدفع
                                    </h3>
                                    <p class="text-xs text-surface-500 mt-1">
                                        راجع ملخص بيانات اشتراكك وقيمة الاشتراك الشهري قبل الانتقال لصفحة الدفع
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    @click="subscribeStep = 2"
                                    class="btn-ghost btn-sm text-xs flex items-center gap-1.5"
                                >
                                    <Icon name="arrowRight" class="w-3.5 h-3.5" />
                                    <span>تعديل الموعد المحدد</span>
                                </button>
                            </div>

                            <div class="grid md:grid-cols-12 gap-6 items-start">
                                <!-- Summary Details Box (7 Cols) -->
                                <div class="md:col-span-7 space-y-4">
                                    <div class="card p-5 border border-surface-200 dark:border-surface-800 space-y-4">
                                        <h4 class="text-xs font-bold text-surface-400 uppercase tracking-wider">
                                            بيانات الحجز والاشتراك
                                        </h4>

                                        <div class="flex items-center gap-3">
                                            <img
                                                :src="teacher.avatar || '/images/default-avatar.png'"
                                                class="w-12 h-12 rounded-2xl object-cover border border-surface-200 dark:border-surface-700"
                                                :alt="teacher.name"
                                            />
                                            <div>
                                                <div class="font-black text-surface-900 dark:text-white text-base">{{ teacher.name }}</div>
                                                <div class="text-xs text-primary-600 dark:text-primary-400 font-bold">
                                                    {{ activeAssignment.subject?.name }} — {{ activeAssignment.grade?.name }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="divide-y divide-surface-100 dark:divide-surface-800 pt-2 text-xs">
                                            <div class="py-2.5 flex items-center justify-between">
                                                <span class="text-surface-500">نوع الاشتراك</span>
                                                <span class="font-bold text-surface-900 dark:text-white">
                                                    {{ selectedType === 'group' ? 'مجموعة دراسية' : 'جلسة خاصة (برايفت)' }}
                                                </span>
                                            </div>

                                            <div class="py-2.5 flex items-center justify-between">
                                                <span class="text-surface-500">
                                                    {{ selectedType === 'group' ? 'المجموعة' : 'نمط الجلسة' }}
                                                </span>
                                                <span class="font-bold text-surface-900 dark:text-white">
                                                    {{ selectedType === 'group' ? selectedGroup?.name : 'متابعة فردية 1-on-1' }}
                                                </span>
                                            </div>

                                            <div class="py-2.5 flex items-center justify-between">
                                                <span class="text-surface-500">الموعد المحدد</span>
                                                <span class="font-bold text-primary-600 dark:text-primary-400">
                                                    {{ selectedType === 'group'
                                                        ? (formatSchedule(selectedGroup?.schedules) || 'الموعد المعتمد')
                                                        : (selectedPrivateSlot ? formatDateTime(selectedPrivateSlot.starts_at) : 'موعد مرن بالتنسيق مع المعلم')
                                                    }}
                                                </span>
                                            </div>

                                            <div class="py-2.5 flex items-center justify-between">
                                                <span class="text-surface-500">المقاعد المتبقية</span>
                                                <span class="font-bold text-surface-800 dark:text-surface-200">
                                                    {{ selectedType === 'group' ? selectedGroup?.seats_left + ' مقعد' : 'مقعد فردي مخصص لك' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3 p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-800 dark:text-emerald-300">
                                        <span class="text-lg">🛡️</span>
                                        <span>ضمان الجودة وأمان الدفع عبر بوابات الدفع الإلكتروني المعتمدة (Stripe). يمكنك الإلغاء في أي وقت.</span>
                                    </div>
                                </div>

                                <!-- Price Reveal & Action Card (5 Cols) -->
                                <div class="md:col-span-5">
                                    <div class="card p-6 border-2 border-primary-500/40 bg-gradient-to-br from-primary-500/10 via-accent-500/5 to-surface-50 dark:to-surface-900 shadow-xl rounded-3xl">
                                        <span class="text-[11px] font-bold text-primary-700 dark:text-primary-300 uppercase tracking-wider block mb-1">
                                            قيمة الاشتراك المعتمد
                                        </span>

                                        <!-- Big Price Display — Revealed in Step 3 Only -->
                                        <div class="my-4">
                                            <div class="text-3xl sm:text-4xl font-black text-surface-900 dark:text-white flex items-baseline gap-1.5">
                                                <span>{{ formatMonthly(chosenPrice) }}</span>
                                            </div>
                                            <p class="text-xs text-surface-500 mt-1.5">
                                                تجديد شهري تلقائي — لا توجد أي رسوم خفية إضافية
                                            </p>
                                        </div>

                                        <div class="space-y-3 pt-4 border-t border-surface-200 dark:border-surface-800">
                                            <template v-if="selectedGroup?.is_subscribed">
                                                <div class="p-3 bg-green-500/10 border border-green-500/25 rounded-2xl text-center text-xs font-bold text-green-700 dark:text-green-300">
                                                    أنت مشترك بالفعل في هذه المجموعة ✅
                                                </div>
                                                <Link :href="route('student.my-classes')" class="btn-primary w-full py-3 text-sm justify-center">
                                                    الانتقال إلى حصصي
                                                </Link>
                                            </template>

                                            <template v-else-if="selectedType === 'private' && activeAssignment.private_subscription?.status === 'pending'">
                                                <Link
                                                    :href="route('checkout.show', activeAssignment.private_subscription.id)"
                                                    class="btn-primary w-full py-3 text-sm justify-center font-bold"
                                                >
                                                    إكمال دفع اشتراك البرايفيت الحالي 💳
                                                </Link>
                                            </template>

                                            <template v-else>
                                                <!-- Primary Confirm & Checkout Button -->
                                                <button
                                                    type="button"
                                                    class="btn-primary w-full py-3.5 text-sm font-black justify-center shadow-lg shadow-primary-500/20 transform hover:scale-[1.02] transition-all flex items-center gap-2"
                                                    :disabled="subscribing !== null"
                                                    @click="confirmAndPay"
                                                >
                                                    <span v-if="subscribing" class="animate-spin text-base">⏳</span>
                                                    <span>{{ subscribing ? 'جارٍ التحويل لصفحة الدفع...' : 'تأكيد الاشتراك والانتقال للدفع 💳' }}</span>
                                                </button>

                                                <!-- Student Option: Ask parent to pay -->
                                                <button
                                                    v-if="isStudent && selectedType === 'group' && selectedGroup"
                                                    type="button"
                                                    class="btn-ghost w-full py-2.5 text-xs text-surface-600 dark:text-surface-300 justify-center"
                                                    @click="askParentToPay(selectedGroup.id)"
                                                >
                                                    اطلب من ولي الأمر الدفع 👨‍👦
                                                </button>
                                            </template>

                                            <button
                                                type="button"
                                                class="btn-ghost w-full py-2 text-xs text-surface-400 justify-center"
                                                @click="subscribeStep = 2"
                                            >
                                                تعديل الموعد المحدد
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
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
