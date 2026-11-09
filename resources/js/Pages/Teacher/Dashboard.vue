<script setup>
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

defineProps({
    stats:                { type: Object, default: () => ({}) },
    assignments:          { type: Array,  default: () => [] },
    groups:               { type: Array,  default: () => [] },
    recent_enrollments:   { type: Array,  default: () => [] },
    recent_announcements: { type: Array,  default: () => [] },
    upcoming_sessions:    { type: Array,  default: () => [] },
    pending_submissions:  { type: Array,  default: () => [] },
    at_risk_students:     { type: Array,  default: () => [] },
    terms:                { type: Array,  default: () => [] },
});
</script>

<template>
    <Head title="لوحة المعلم" />

    <DashboardLayout>
        <div class="space-y-8 pb-12">
            <!-- ── Top Header ──────────────────────────────────────── -->
            <header>
                <h1 class="text-3xl font-black text-surface-900 dark:text-white tracking-tight">
                    مرحباً، أستاذ {{ $page.props.auth.user?.name }} 👋
                </h1>
                <p class="text-sm text-surface-500 dark:text-surface-400 mt-1.5 leading-relaxed">
                    مساحتك لإدارة المواد الدراسية، مواعيد المجموعات والبرايفت، وجدولة الحصص ونشر المحتوى بكل سلاسة.
                </p>
            </header>

            <!-- ── Upcoming Sessions Bar ────────────────────────────── -->
            <section v-if="upcoming_sessions.length" class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-extrabold text-sm text-surface-800 dark:text-surface-200 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-red-500 animate-ping"></span>
                        <span>أقرب الحصص المباشرة المجدولة:</span>
                    </h2>
                    <Link :href="route('teacher.live-sessions')" class="text-xs font-bold text-primary-600 hover:underline">
                        عرض جميع الحصص ←
                    </Link>
                </div>

                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                    <div
                        v-for="session in upcoming_sessions"
                        :key="session.id"
                        class="card p-4 flex items-center justify-between gap-3 border-l-4 border-l-primary-500 hover:border-primary-600 transition-all"
                    >
                        <div class="min-w-0">
                            <div class="text-xs font-bold text-surface-400">
                                {{ session.subject_name }} · {{ session.group_name }}
                            </div>
                            <h3 class="font-black text-sm text-surface-900 dark:text-white truncate mt-0.5">
                                {{ session.title }}
                            </h3>
                            <div class="text-xs font-semibold text-primary-600 dark:text-primary-400 mt-1 flex items-center gap-1.5">
                                <Icon name="clock" class="w-3.5 h-3.5" />
                                <span>{{ session.scheduled_at_formatted }}</span>
                            </div>
                        </div>

                        <a
                            :href="session.room_url"
                            class="btn-primary btn-sm shrink-0 flex items-center gap-1.5 shadow-sm"
                        >
                            <Icon name="live" class="w-3.5 h-3.5" />
                            <span>دخول</span>
                        </a>
                    </div>
                </div>
            </section>

            <!-- ── Student Subscriptions & Bookings (الاشتراكات والحجوزات لطلابك) ── -->
            <section class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="font-extrabold text-base text-surface-900 dark:text-white flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>الاشتراكات والحجوزات لطلابك</span>
                    </h2>
                    <Link :href="route('teacher.students.index')" class="text-xs font-bold text-primary-600 dark:text-primary-400 hover:underline">
                        عرض قائمة جميع الطلاب ←
                    </Link>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <!-- 1. Groups Card -->
                    <Link
                        :href="route('teacher.students.index')"
                        class="card p-6 border border-surface-200 dark:border-surface-800 hover:border-primary-500/60 dark:hover:border-primary-500/60 hover:shadow-lg transition-all flex items-center justify-between gap-4 group"
                    >
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 rounded-2xl bg-primary-500/15 text-primary-600 dark:text-primary-400 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                                <Icon name="users" class="w-7 h-7" />
                            </div>
                            <div>
                                <h3 class="text-base font-black text-surface-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                    المجموعات الدراسية
                                </h3>
                                <p class="text-xs text-surface-500 dark:text-surface-400 mt-1">
                                    إجمالي الطلاب المشتركين في المجموعات
                                </p>
                            </div>
                        </div>

                        <div class="text-end shrink-0">
                            <div class="text-3xl font-black text-primary-600 dark:text-primary-400">
                                {{ stats.group_students_count ?? 0 }}
                            </div>
                            <span class="text-[11px] font-bold text-surface-400 group-hover:text-primary-500 transition-colors">
                                طالب مشترك ←
                            </span>
                        </div>
                    </Link>

                    <!-- 2. Private Card -->
                    <Link
                        :href="route('teacher.students.index')"
                        class="card p-6 border border-surface-200 dark:border-surface-800 hover:border-accent-500/60 dark:hover:border-accent-500/60 hover:shadow-lg transition-all flex items-center justify-between gap-4 group"
                    >
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 rounded-2xl bg-accent-500/15 text-accent-600 dark:text-accent-400 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                                <Icon name="student" class="w-7 h-7" />
                            </div>
                            <div>
                                <h3 class="text-base font-black text-surface-900 dark:text-white group-hover:text-accent-600 dark:group-hover:text-accent-400 transition-colors">
                                    الحصص الخاصة (البرايفت)
                                </h3>
                                <p class="text-xs text-surface-500 dark:text-surface-400 mt-1">
                                    إجمالي الطلاب المشتركين في البرايفت
                                </p>
                            </div>
                        </div>

                        <div class="text-end shrink-0">
                            <div class="text-3xl font-black text-accent-600 dark:text-accent-400">
                                {{ stats.private_students_count ?? 0 }}
                            </div>
                            <span class="text-[11px] font-bold text-surface-400 group-hover:text-accent-500 transition-colors">
                                طالب مشترك ←
                            </span>
                        </div>
                    </Link>
                </div>
            </section>

            <!-- ── Urgent Actions & Follow-up (Grading Queue & At-risk Students) ── -->
            <section v-if="pending_submissions.length || at_risk_students.length" class="grid lg:grid-cols-2 gap-5">
                <!-- Submissions Awaiting Grading -->
                <div v-if="pending_submissions.length" class="card p-5 space-y-4 border-r-4 border-r-accent-500 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="p-2 rounded-xl bg-accent-500/10 text-accent-600 dark:text-accent-400">
                                <Icon name="edit" class="w-4 h-4" />
                            </span>
                            <h2 class="font-black text-sm text-surface-900 dark:text-white">
                                تسليمات بانتظار تصحيحك ({{ stats.pending_grading }})
                            </h2>
                        </div>
                        <span class="text-xs text-surface-400 font-bold">الأحدث</span>
                    </div>

                    <div class="space-y-2.5">
                        <div
                            v-for="sub in pending_submissions"
                            :key="sub.id"
                            class="flex items-center justify-between gap-3 p-3 rounded-xl bg-surface-50 dark:bg-surface-850 border border-surface-200/70 dark:border-surface-800"
                        >
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 rounded-full bg-primary-500/10 text-primary-600 flex items-center justify-center font-bold text-xs shrink-0 overflow-hidden">
                                    <img v-if="sub.student_avatar" :src="sub.student_avatar" :alt="sub.student_name" class="w-full h-full object-cover" />
                                    <span v-else>{{ sub.student_name?.charAt(0) }}</span>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="font-bold text-xs text-surface-900 dark:text-white truncate">{{ sub.student_name }}</p>
                                        <span v-if="sub.is_late" class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-red-500/10 text-red-500 border border-red-500/20">
                                            متأخر
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-surface-500 truncate">{{ sub.subject_name }} · {{ sub.worksheet_title }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-[11px] text-surface-400 font-mono">{{ sub.submitted_at_formatted }}</span>
                                <Link
                                    v-if="sub.group_id"
                                    :href="route('teacher.worksheets.index', { groupId: sub.group_id })"
                                    class="btn-accent btn-xs px-2.5 py-1 text-[11px] font-bold"
                                >
                                    تصحيح
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Students Needing Follow-up (Quiz Score < 50%) -->
                <div v-if="at_risk_students.length" class="card p-5 space-y-4 border-r-4 border-r-amber-500 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="p-2 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                                <Icon name="chart" class="w-4 h-4" />
                            </span>
                            <h2 class="font-black text-sm text-surface-900 dark:text-white">
                                طلاب بحاجة إلى متابعة ودعم تعليمي
                            </h2>
                        </div>
                        <span class="text-xs text-amber-600 dark:text-amber-400 font-bold">تنبيه أداء</span>
                    </div>

                    <div class="space-y-2.5">
                        <div
                            v-for="st in at_risk_students"
                            :key="st.id"
                            class="flex items-center justify-between gap-3 p-3 rounded-xl bg-surface-50 dark:bg-surface-850 border border-surface-200/70 dark:border-surface-800"
                        >
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 rounded-full bg-amber-500/10 text-amber-600 flex items-center justify-center font-bold text-xs shrink-0 overflow-hidden">
                                    <img v-if="st.student_avatar" :src="st.student_avatar" :alt="st.student_name" class="w-full h-full object-cover" />
                                    <span v-else>{{ st.student_name?.charAt(0) }}</span>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-bold text-xs text-surface-900 dark:text-white truncate">{{ st.student_name }}</p>
                                    <p class="text-[11px] text-surface-500 truncate">{{ st.subject_name }} · {{ st.quiz_title }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2.5 shrink-0">
                                <span class="px-2 py-0.5 rounded-full text-xs font-black bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20">
                                    {{ st.score }}%
                                </span>
                                <Link
                                    :href="route('teacher.gradebook')"
                                    class="text-xs font-bold text-primary-600 hover:underline"
                                >
                                    سجل الدرجات ←
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── Assigned Subjects & Stages (موادك والمراحل الدراسية المسندة إليك — Display Cards Only -> مجموعاتي) ── -->
            <section class="space-y-4">
                <div class="flex items-center justify-between border-b border-surface-200 dark:border-surface-800 pb-3">
                    <div>
                        <h2 class="text-xl font-black text-surface-900 dark:text-white">
                            موادك والمراحل الدراسية المسندة إليك
                        </h2>
                        <p class="text-xs text-surface-400 mt-0.5">
                            اضغط على أي بطاقة للانتقال إلى تبويب مجموعاتي
                        </p>
                    </div>
                    <Link :href="route('teacher.groups.index')" class="text-xs font-bold text-primary-600 dark:text-primary-400 hover:underline">
                        إدارة مجموعاتي ←
                    </Link>
                </div>

                <div v-if="assignments.length" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <Link
                        v-for="assignment in assignments"
                        :key="assignment.id"
                        :href="route('teacher.groups.index')"
                        class="card p-6 border border-surface-200 dark:border-surface-800 hover:border-primary-500/60 dark:hover:border-primary-500/60 hover:shadow-lg transition-all flex items-start justify-between gap-4 group"
                    >
                        <div class="flex-1 min-w-0 space-y-2.5 text-sm">
                            <div class="flex items-center gap-2">
                                <span class="text-surface-500 dark:text-surface-400 font-bold shrink-0">المرحلة :</span>
                                <span class="font-black text-surface-900 dark:text-white truncate">{{ assignment.stage.name }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-surface-500 dark:text-surface-400 font-bold shrink-0">الصف :</span>
                                <span class="font-black text-surface-900 dark:text-white truncate">{{ assignment.grade.name }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-surface-500 dark:text-surface-400 font-bold shrink-0">المادة :</span>
                                <span class="font-black text-primary-600 dark:text-primary-400 truncate">{{ assignment.subject.name }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-surface-500 dark:text-surface-400 font-bold shrink-0">عدد المجموعات :</span>
                                <span class="font-black text-surface-900 dark:text-white">{{ assignment.groups_count ?? 0 }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-surface-500 dark:text-surface-400 font-bold shrink-0">البرايفت :</span>
                                <span class="font-black text-accent-600 dark:text-accent-400">{{ assignment.private_count ?? 0 }}</span>
                            </div>
                        </div>

                        <div class="flex flex-col items-end justify-between self-stretch shrink-0">
                            <div class="w-12 h-12 rounded-2xl bg-primary-500/15 text-primary-600 dark:text-primary-400 flex items-center justify-center font-black text-lg group-hover:scale-105 transition-transform">
                                <Icon name="courses" class="w-6 h-6" />
                            </div>
                            <span class="text-[11px] font-bold text-surface-400 group-hover:text-primary-500 transition-colors mt-4">
                                مجموعاتي ←
                            </span>
                        </div>
                    </Link>
                </div>

                <div v-else class="card p-12 text-center space-y-3">
                    <Icon name="courses" class="w-12 h-12 text-surface-300 mx-auto" />
                    <h3 class="text-lg font-bold text-surface-800 dark:text-white">لا توجد مواد مسندة إليك حالياً</h3>
                    <p class="text-xs text-surface-400 max-w-md mx-auto">
                        تتولى إدارة المنصة إسناد المواد والصفوف الدراسية إليك. بمجرد الإسناد ستظهر هنا فوراً.
                    </p>
                </div>
            </section>

            <!-- ── Latest 5 Teacher Announcements (أحدث 5 إعلانات قام المدرس بإعلانها) ── -->
            <section class="space-y-4">
                <div class="flex items-center justify-between border-b border-surface-200 dark:border-surface-800 pb-3">
                    <div>
                        <h2 class="text-xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                            <Icon name="chat" class="w-5 h-5 text-accent-500" />
                            <span>أحدث الإعلانات المنشورة</span>
                        </h2>
                        <p class="text-xs text-surface-400 mt-0.5">
                            أحدث 5 إعلانات قمت بنشرها لمجموعاتك الدراسية
                        </p>
                    </div>
                    <Link :href="route('teacher.announcements')" class="btn-outline btn-sm flex items-center gap-1.5 font-bold">
                        <span>إدارة ونشر الإعلانات</span>
                        <span>←</span>
                    </Link>
                </div>

                <div v-if="recent_announcements.length" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <article
                        v-for="announcement in recent_announcements"
                        :key="announcement.id"
                        class="card p-5 border border-surface-200 dark:border-surface-800 flex flex-col justify-between space-y-3"
                    >
                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <span
                                    v-if="announcement.group_name"
                                    class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-primary-500/10 text-primary-700 dark:text-primary-300"
                                >
                                    {{ announcement.group_name }}
                                </span>
                                <span class="text-[11px] text-surface-400 font-mono">
                                    {{ announcement.published_at_formatted }}
                                </span>
                            </div>

                            <h3 class="font-black text-sm text-surface-900 dark:text-white leading-snug">
                                {{ announcement.title }}
                            </h3>
                            <p class="text-xs text-surface-500 dark:text-surface-400 line-clamp-3 leading-relaxed">
                                {{ announcement.body }}
                            </p>
                        </div>

                        <div
                            v-if="announcement.subject_name || announcement.grade_name"
                            class="pt-2.5 border-t border-surface-100 dark:border-surface-800 text-[11px] text-surface-400 flex items-center gap-1.5"
                        >
                            <span>{{ announcement.subject_name }}</span>
                            <span v-if="announcement.subject_name && announcement.grade_name">·</span>
                            <span>{{ announcement.grade_name }}</span>
                        </div>
                    </article>
                </div>

                <div v-else class="card p-8 text-center space-y-2 border border-surface-200 dark:border-surface-800">
                    <p class="text-sm font-bold text-surface-700 dark:text-surface-200">لم تقم بنشر أي إعلانات بعد</p>
                    <p class="text-xs text-surface-400">يمكنك نشر تنبيهات وإعلانات لطلاب مجموعاتك من صفحة الإعلانات.</p>
                </div>
            </section>
        </div>
    </DashboardLayout>
</template>
