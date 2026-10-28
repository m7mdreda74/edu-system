<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import { formatQAR } from '@/lib/money';

const props = defineProps({
    activeSubscriptions: { type: Array, default: () => [] },
    expiringSoon:        { type: Array, default: () => [] },
    upcomingSessions:    { type: Array, default: () => [] },
    pendingPayments:     { type: Array, default: () => [] },
    pendingParentLinks:  { type: Array, default: () => [] },
    continueLearning:    { type: Object, default: null },
    announcements:       { type: Array, default: () => [] },
    gamification:        { type: Object, default: null },
    stats:               { type: Object, default: () => ({}) },
});

function respondToParentLink(linkId, action) {
    router.post(route(`student.parent-links.${action}`, { linkId }), {}, {
        preserveScroll: true,
    });
}

function formatDateTime(value) {
    if (!value) return '';
    return new Date(value).toLocaleString('ar-QA', {
        weekday: 'long',
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const greetingTime = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'صباح الخير والنشاط';
    return 'مساء الخير والتميز';
});
</script>

<template>
    <Head title="لوحة الطالب — بوابة المجد التعليمية" />

    <DashboardLayout>
        <div class="space-y-8 pb-12">

            <!-- ── Top Header ──────────────────────────────────────── -->
            <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-accent-500 bg-accent-500/10 px-2.5 py-0.5 rounded-full border border-accent-500/20">
                            {{ greetingTime }}
                        </span>
                        <span class="text-xs text-surface-400">·</span>
                        <span class="text-xs text-surface-400">بوابة المجد التعليمية</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black text-surface-900 dark:text-white mt-1">
                        مرحباً، {{ $page.props.auth.user?.name }} 👋
                    </h1>
                    <p class="text-xs sm:text-sm text-surface-500 dark:text-surface-400 mt-0.5">
                        تابع دراستك، أنجز واجباتك، واستعد لحصصك واختباراتك القادمة بكل ثقة.
                    </p>
                </div>

                <div class="flex items-center gap-2.5 flex-wrap">
                    <Link
                        :href="route('student.assessments')"
                        class="btn-primary btn-sm flex items-center gap-1.5 font-bold shadow-md shadow-primary-500/20"
                    >
                        <Icon name="assignment" class="w-4 h-4" />
                        <span>الواجبات والتقييمات</span>
                        <span
                            v-if="stats.totalPendingEvaluations > 0"
                            class="ms-1 px-1.5 py-0.5 rounded-full text-[10px] font-black bg-red-600 text-white"
                        >
                            {{ stats.totalPendingEvaluations }}
                        </span>
                    </Link>
                    <Link :href="route('student.schedule')" class="btn-outline btn-sm flex items-center gap-1.5 font-bold">
                        <Icon name="calendar" class="w-4 h-4" />
                        <span>جدول الحصص</span>
                    </Link>
                    <Link :href="route('student.resources')" class="btn-ghost btn-sm flex items-center gap-1.5">
                        <Icon name="book" class="w-4 h-4" />
                        <span>الموارد التعليمية</span>
                    </Link>
                </div>
            </header>

            <!-- ── Pending Parent Link Requests ────────────────────── -->
            <section v-if="props.pendingParentLinks.length" class="card p-5 border-amber-300 dark:border-amber-900 bg-amber-500/5">
                <div class="flex items-center gap-2 font-bold text-surface-900 dark:text-white">
                    <Icon name="users" class="w-5 h-5 text-amber-500" />
                    طلبات ربط حسابات أولياء الأمور
                </div>
                <p class="mt-1 text-xs text-surface-500 dark:text-surface-400">
                    لا يحصل ولي الأمر على تقاريرك أو صلاحيات المتابعة إلا بعد موافقتك الصريحة.
                </p>
                <div class="mt-4 space-y-3">
                    <div v-for="link in props.pendingParentLinks" :key="link.id" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border border-surface-200 dark:border-surface-700 p-3 bg-surface-900/40">
                        <div>
                            <p class="text-sm font-bold text-surface-900 dark:text-white">{{ link.parent?.name }}</p>
                            <p class="text-xs text-surface-400">يرغب في ربط حسابه كـ {{ link.relationship === 'father' ? 'أب' : (link.relationship === 'mother' ? 'أم' : 'ولي أمر') }}</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="btn-primary btn-sm" @click="respondToParentLink(link.id, 'approve')">موافقة وتأكيد</button>
                            <button type="button" class="btn-outline btn-sm text-red-400 border-red-500/30" @click="respondToParentLink(link.id, 'reject')">رفض</button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── Continue Learning Hero (متابعة التعلم) ─────────── -->
            <section v-if="continueLearning && continueLearning.group_id" class="card p-5 sm:p-6 border-primary-500/30 bg-gradient-to-r from-primary-950/40 via-surface-900 to-surface-900 shadow-lg">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-5">
                    <div class="min-w-0 space-y-1.5">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-[10px] px-2.5 py-0.5 rounded-full font-black bg-primary-500/20 text-primary-300 border border-primary-500/30 flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-primary-400 animate-pulse"></span>
                                <span>متابعة التعلم</span>
                            </span>
                            <span class="text-xs font-bold text-surface-300">{{ continueLearning.subject?.name }}</span>
                            <span class="text-surface-500">·</span>
                            <span class="text-xs text-surface-400">{{ continueLearning.group_name }}</span>
                            <span v-if="continueLearning.teacher">·</span>
                            <span v-if="continueLearning.teacher" class="text-xs text-surface-400">{{ continueLearning.teacher.name }}</span>
                        </div>
                        <h2 class="text-lg sm:text-xl font-black text-white leading-snug truncate">
                            {{ continueLearning.lesson_title }}
                        </h2>
                        <p class="text-xs text-surface-400">
                            {{ continueLearning.watched_seconds > 0 ? 'استأنف مشاهدة الشرح وحل الواجبات المرتبطة به.' : 'ابدأ دراسة هذا الدرس واستكشف المذكرة والكويز.' }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2.5 shrink-0">
                        <Link
                            :href="route('student.learn', { groupId: continueLearning.group_id, progress: 1 })"
                            class="btn-outline btn-sm flex items-center gap-1.5 font-bold text-xs"
                        >
                            <Icon name="chart" class="w-3.5 h-3.5" />
                            <span>خريطة المنهج</span>
                        </Link>
                        <Link
                            :href="route('student.learn', { groupId: continueLearning.group_id })"
                            class="btn-primary btn-sm flex items-center gap-2 font-bold px-4"
                        >
                            <span>{{ continueLearning.watched_seconds > 0 ? 'استكمل الدرس' : 'ابدأ الدرس الآن' }}</span>
                            <span>←</span>
                        </Link>
                    </div>
                </div>
            </section>

            <!-- ── Core Metric Cards ──────────────────────────────── -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <!-- المواد والاشتراكات -->
                <Link :href="route('student.my-classes')" class="card p-4 transition-all hover:border-primary-500/40">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] text-surface-400 font-bold">موادي المشترك بها</span>
                        <div class="p-2 rounded-xl bg-primary-500/10 text-primary-400">
                            <Icon name="courses" class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-2 text-2xl font-black text-surface-900 dark:text-white">{{ stats.activeSubscriptions ?? 0 }}</div>
                    <div class="mt-1 text-[10px] text-surface-400">مجموعات وفصول نشطة</div>
                </Link>

                <!-- الواجبات والتقييمات المطلوبة -->
                <Link :href="route('student.assessments')" class="card p-4 transition-all hover:border-amber-500/40">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] text-surface-400 font-bold">واجبات وتقييمات مطلوبة</span>
                        <div class="p-2 rounded-xl bg-amber-500/10 text-amber-400">
                            <Icon name="assignment" class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-2 text-2xl font-black text-amber-500">{{ stats.totalPendingEvaluations ?? 0 }}</div>
                    <div class="mt-1 text-[10px] text-surface-400">
                        {{ stats.pendingAssignments ?? 0 }} واجب · {{ stats.pendingAssessments ?? 0 }} اختبار متاح
                    </div>
                </Link>

                <!-- الحصص القادمة -->
                <Link :href="route('student.schedule')" class="card p-4 transition-all hover:border-red-500/40">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] text-surface-400 font-bold">الحصص المباشرة</span>
                        <div class="p-2 rounded-xl bg-red-500/10 text-red-400">
                            <Icon name="live" class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-2 text-2xl font-black text-red-400">{{ stats.upcomingSessions ?? 0 }}</div>
                    <div class="mt-1 text-[10px] text-surface-400">حصص مجدولة هذا الأسبوع</div>
                </Link>

                <!-- المعلمون المشرفون -->
                <div class="card p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] text-surface-400 font-bold">المعلمون المشرفون</span>
                        <div class="p-2 rounded-xl bg-accent-500/10 text-accent-400">
                            <Icon name="teacher" class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-2 text-2xl font-black text-surface-900 dark:text-white">{{ stats.teachers ?? 0 }}</div>
                    <div class="mt-1 text-[10px] text-surface-400">نخبة كفاءات تعليمية</div>
                </div>
            </div>

            <!-- ── Active Subjects with Visual Progress Bars ───────── -->
            <section v-if="activeSubscriptions.length" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base sm:text-lg font-black text-surface-900 dark:text-white flex items-center gap-2">
                            <span>📚</span>
                            <span>مقرراتي وتقدمي الأكاديمي</span>
                        </h2>
                        <p class="text-xs text-surface-400">نسب الإنجاز الحقيقية لكل مقرر وفقاً للدروس المكتملة فعلياً.</p>
                    </div>
                    <Link :href="route('student.my-classes')" class="text-xs font-bold text-primary-400 hover:underline">
                        عرض الكل ←
                    </Link>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="sub in activeSubscriptions"
                        :key="sub.id"
                        class="card p-4 sm:p-5 flex flex-col justify-between hover:border-primary-500/40 transition-all space-y-4"
                    >
                        <div class="space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-10 h-10 rounded-xl bg-primary-500/10 text-primary-400 flex items-center justify-center font-bold text-base shrink-0">
                                        {{ sub.subject?.name?.charAt(0) ?? '📖' }}
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="font-bold text-white text-sm truncate">{{ sub.subject?.name }}</h3>
                                        <p class="text-[11px] text-surface-400 truncate">{{ sub.group?.name ? sub.group.name + ' · ' : (sub.type === 'private' ? 'متابعة فردية · ' : '') }}{{ sub.teacher?.name }}</p>
                                    </div>
                                </div>

                                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold bg-surface-800 text-surface-300 shrink-0">
                                    متبقٍ {{ sub.days_remaining }} يوم
                                </span>
                            </div>

                            <!-- Progress Bar -->
                            <div class="space-y-1.5 pt-1">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-surface-400 text-[11px]">نسبة الإنجاز</span>
                                    <span class="font-bold text-primary-400">{{ sub.progress_percent }}%</span>
                                </div>
                                <div class="w-full h-2 rounded-full bg-surface-800 overflow-hidden">
                                    <div
                                        class="h-full bg-gradient-to-r from-primary-600 to-accent-500 rounded-full transition-all duration-500"
                                        :style="{ width: `${sub.progress_percent}%` }"
                                    ></div>
                                </div>
                                <div class="text-[10px] text-surface-500 text-end">
                                    {{ sub.completed_lessons }} من {{ sub.total_lessons }} درس مكتمل
                                </div>
                            </div>
                        </div>

                        <!-- Card Action -->
                        <div class="pt-3 border-t border-surface-800/80 flex items-center justify-between gap-2">
                            <template v-if="sub.group?.id">
                                <Link
                                    :href="route('student.learn', { groupId: sub.group.id, progress: 1 })"
                                    class="text-xs text-surface-400 hover:text-white font-bold flex items-center gap-1"
                                >
                                    <Icon name="chart" class="w-3.5 h-3.5" />
                                    <span>الخريطة</span>
                                </Link>
                                <Link
                                    :href="route('student.learn', { groupId: sub.group.id })"
                                    class="btn-primary btn-xs font-bold flex items-center gap-1"
                                >
                                    <span>دخول غرفة الشرح</span>
                                    <span>←</span>
                                </Link>
                            </template>
                            <template v-else>
                                <span class="text-xs text-surface-400 font-bold flex items-center gap-1">
                                    <Icon name="courses" class="w-3.5 h-3.5" />
                                    <span>{{ sub.type === 'private' ? 'متابعة فردية (برايفت)' : 'اشتراك مفعل' }}</span>
                                </span>
                                <Link
                                    :href="route('student.schedule')"
                                    class="btn-primary btn-xs font-bold flex items-center gap-1"
                                >
                                    <span>مواعيد الحصص</span>
                                    <span>←</span>
                                </Link>
                            </template>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── Upcoming Live Sessions ─────────────────────────── -->
            <section class="space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base sm:text-lg font-black text-surface-900 dark:text-white flex items-center gap-2">
                            <Icon name="live" class="w-4 h-4 text-red-500" />
                            <span>الحصص المباشرة القادمة</span>
                        </h2>
                        <p class="text-xs text-surface-400">مواعيد حصص البث المباشر التفاعلية مع معلميك.</p>
                    </div>
                    <Link :href="route('student.schedule')" class="text-xs font-bold text-primary-400 hover:underline">
                        عرض الجدول كاملاً ←
                    </Link>
                </div>

                <div v-if="upcomingSessions.length" class="card divide-y divide-surface-100 dark:divide-surface-800">
                    <div v-for="session in upcomingSessions" :key="session.id" class="flex items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-surface-800 dark:text-surface-100 truncate">
                                {{ session.title }}
                            </div>
                            <div class="text-[11px] text-surface-400 flex items-center gap-2 flex-wrap mt-0.5">
                                <span class="font-bold text-primary-400">{{ session.subject ?? 'حصة تعليمية' }}</span>
                                <span>·</span>
                                <span>{{ session.teacher?.name }}</span>
                                <span>·</span>
                                <span>{{ formatDateTime(session.scheduled_at) }}</span>
                            </div>
                        </div>

                        <Link
                            v-if="session.status === 'live'"
                            :href="route('live-sessions.room', session.id)"
                            class="btn-primary btn-sm shrink-0 flex items-center gap-1.5 animate-pulse font-bold"
                        >
                            <span class="w-2 h-2 rounded-full bg-red-400 animate-ping"></span>
                            <span>ادخل الآن 🔴</span>
                        </Link>
                        <span v-else class="badge-gray text-[10px] shrink-0 flex items-center gap-1" title="يُتاح الدخول فور بدء المعلم للحصة">
                            <Icon name="clock" class="w-3 h-3 text-surface-400" />
                            <span>بانتظار بدء المعلم</span>
                        </span>
                    </div>
                </div>

                <div v-else class="card p-6 text-center text-xs text-surface-400">
                    لا توجد حصص مباشرة مجدولة لليوم. تابع جدول الحصص للمواعيد القادمة.
                </div>
            </section>

            <!-- ── Quick Shortcuts ────────────────────────────────── -->
            <section class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <Link :href="route('student.my-grade')" class="card p-4 hover:border-primary-500/40 transition-all flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-primary-500/10 text-primary-400 flex items-center justify-center font-bold">
                        📖
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs font-bold text-white truncate">مواد صفي</div>
                        <div class="text-[10px] text-surface-400">المنهج الدراسي</div>
                    </div>
                </Link>

                <Link :href="route('student.assessments')" class="card p-4 hover:border-amber-500/40 transition-all flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center font-bold">
                        📝
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs font-bold text-white truncate">الواجبات والتقييمات</div>
                        <div class="text-[10px] text-surface-400">مركز التقييم</div>
                    </div>
                </Link>

                <Link :href="route('student.resources')" class="card p-4 hover:border-accent-500/40 transition-all flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-accent-500/10 text-accent-400 flex items-center justify-center font-bold">
                        📚
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs font-bold text-white truncate">المكتبة والموارد</div>
                        <div class="text-[10px] text-surface-400">ملفات ومذكرات</div>
                    </div>
                </Link>

                <Link :href="route('student.schedule')" class="card p-4 hover:border-purple-500/40 transition-all flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center font-bold">
                        📅
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs font-bold text-white truncate">جدول حصصي</div>
                        <div class="text-[10px] text-surface-400">المواعيد الأسبوعية</div>
                    </div>
                </Link>
            </section>

            <!-- ── Teacher Announcements ──────────────────────────── -->
            <section v-if="announcements.length" class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="flex items-center gap-2 text-base font-black text-surface-900 dark:text-white">
                            <Icon name="bell" class="h-4 w-4 text-primary-500" />
                            <span>إعلانات وتنبيهات المعلمين</span>
                        </h2>
                        <p class="text-xs text-surface-400">آخر الإعلانات والتنبيهات المنشورة من معلميك في مجموعاتك الدراسية.</p>
                    </div>
                </div>

                <div class="space-y-3">
                    <article
                        v-for="announcement in announcements"
                        :key="announcement.id"
                        class="card p-5 border border-surface-200 dark:border-surface-800 hover:border-primary-500/30 transition-all shadow-sm space-y-3"
                    >
                        <!-- Announcement Header with Teacher, Subject & Group badges -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-surface-100 dark:border-surface-800 pb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl bg-primary-500/10 text-primary-600 flex items-center justify-center font-bold text-xs shrink-0 overflow-hidden border border-primary-500/20">
                                    <img v-if="announcement.teacher?.avatar" :src="announcement.teacher.avatar" :alt="announcement.teacher.name" class="w-full h-full object-cover" />
                                    <span v-else>{{ announcement.teacher?.name?.charAt(0) || 'م' }}</span>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h4 class="font-black text-sm text-surface-900 dark:text-white">
                                            المعلم: {{ announcement.teacher?.name || 'معلم المادة' }}
                                        </h4>
                                        <span v-if="announcement.subject?.name" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-primary-500/10 text-primary-700 dark:text-primary-300 border border-primary-500/20">
                                            مادة: {{ announcement.subject.name }}
                                        </span>
                                        <span v-if="announcement.group?.name" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-surface-100 dark:bg-surface-800 text-surface-700 dark:text-surface-300">
                                            المجموعة: {{ announcement.group.name }}
                                        </span>
                                    </div>
                                    <time class="text-[11px] text-surface-400 mt-0.5 block">
                                        {{ formatDateTime(announcement.published_at) }}
                                    </time>
                                </div>
                            </div>

                            <Link
                                v-if="announcement.group?.id"
                                :href="route('student.learn', { groupId: announcement.group.id })"
                                class="btn-ghost btn-xs text-primary-600 dark:text-primary-400 hover:underline flex items-center gap-1 self-start sm:self-center font-bold"
                            >
                                <span>دخول المجموعة</span>
                                <span>←</span>
                            </Link>
                        </div>

                        <!-- Announcement Content -->
                        <div>
                            <h3 class="text-sm sm:text-base font-black text-surface-900 dark:text-white mb-1.5 flex items-center gap-1.5">
                                <span>📢</span>
                                <span>{{ announcement.title }}</span>
                            </h3>
                            <p class="whitespace-pre-line text-xs sm:text-sm leading-relaxed text-surface-600 dark:text-surface-300">
                                {{ announcement.body }}
                            </p>
                        </div>
                    </article>
                </div>
            </section>

        </div>
    </DashboardLayout>
</template>
