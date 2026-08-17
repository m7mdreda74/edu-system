<script setup>
import { computed, ref } from 'vue';
import { Head, router, Link, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { useConfirm } from '@/composables/useConfirm';
import { useClientPagination } from '@/composables/useClientPagination';
import { formatQAR as formatMoney } from '@/lib/money';

const props = defineProps({
    links:           { type: Array, required: true },
    selectedStudent: { type: Object, default: null },
    pendingRequests: { type: Array, default: () => [] },
});

const {
    data: paginatedAttendance,
    pagination: attendancePagination,
    setPage: setAttendancePage,
} = useClientPagination(computed(() => props.selectedStudent?.attendance ?? []));
const {
    data: paginatedQuizAttempts,
    pagination: quizPagination,
    setPage: setQuizPage,
} = useClientPagination(computed(() => props.selectedStudent?.quizAttempts ?? []));
const {
    data: paginatedPayments,
    pagination: paymentsPagination,
    setPage: setPaymentsPage,
} = useClientPagination(computed(() => props.selectedStudent?.payments ?? []));

const { confirm } = useConfirm();

const linkForm = useForm({
    student_phone: '',
    relationship: 'guardian',
});

function linkStudent() {
    linkForm.post(route('parent.link-student'), {
        preserveScroll: true,
        onSuccess: () => linkForm.reset('student_phone'),
    });
}

async function unlinkStudent(id) {
    const ok = await confirm({
        title: 'إلغاء ربط الحساب',
        message: 'هل أنت متأكد من إلغاء ربط هذا الحساب؟',
        confirmLabel: 'إلغاء الربط',
        variant: 'danger',
    });
    if (ok) router.delete(route('parent.unlink-student', { id }));
}

function selectStudent(studentId) {
    router.get(route('parent.dashboard'), { student_id: studentId }, { preserveState: true });
}

function formatQAR(halala) {
    return formatMoney(halala ?? 0);
}

function subscriptionStatusLabel(subscription) {
    if (subscription.is_active) return 'نشط';
    if (subscription.status === 'pending') return 'بانتظار الدفع';
    if (subscription.status === 'cancelled') return 'ملغي';
    return 'منتهي';
}

const days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

function studentId() {
    return props.selectedStudent?.student?.id;
}

function subscribeToGroup(groupId) {
    router.post(route('parent.groups.subscribe', { groupId }), { student_id: studentId() });
}

function bookFreeIntro(slotId) {
    router.post(route('parent.free-intro-sessions.book', { slotId }), { student_id: studentId() });
}

function subscribeToPrivate(assignmentId) {
    router.post(route('parent.private.subscribe', { assignmentId }), { student_id: studentId() });
}

function bookPrivateSlot(slotId) {
    router.post(route('parent.private-slots.book', { slotId }), { student_id: studentId() });
}

function contactTeacher(assignmentId) {
    router.post(route('chat.start'), {
        kind: 'academic',
        teaching_assignment_id: assignmentId,
        student_id: studentId(),
    });
}

function contactAdmin() {
    router.post(route('chat.start'), {
        kind: 'support',
        student_id: studentId(),
    });
}

const rejectNotes = ref('');
const activeRejectRequestId = ref(null);
const isRejectModalOpen = ref(false);
const rejectForm = useForm({ notes: '' });
const payingRequestId = ref(null);

function openRejectModal(requestId) {
    activeRejectRequestId.value = requestId;
    rejectNotes.value = '';
    rejectForm.reset();
    rejectForm.clearErrors();
    isRejectModalOpen.value = true;
}

function submitReject() {
    rejectForm.notes = rejectNotes.value;
    rejectForm.post(route('parent.purchase-requests.reject', { id: activeRejectRequestId.value }), {
        preserveScroll: true,
        onSuccess: () => {
            isRejectModalOpen.value = false;
            activeRejectRequestId.value = null;
            rejectNotes.value = '';
            rejectForm.reset();
        }
    });
}

function payForRequest(requestId) {
    payingRequestId.value = requestId;
    router.post(route('parent.purchase-requests.pay', { id: requestId }), {}, {
        preserveScroll: true,
        onFinish: () => { payingRequestId.value = null; },
    });
}
</script>

<template>
    <DashboardLayout>
        <Head title="بوابة أولياء الأمور" />

        <div class="dashboard-data-page">
            <!-- Header -->
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <Icon name="users" class="w-8 h-8 text-primary-500" />
                        <span>متابعة أداء الأبناء (أولياء الأمور)</span>
                    </h1>
                    <p class="text-surface-500 mt-1">عرض ومتابعة التقدم الدراسي ونتائج اختبارات الطلاب المرتبطين بك</p>
                </div>
            </div>

            <!-- Dashboard Layout Grid -->
            <div class="grid flex-1 grid-cols-1 gap-4 lg:grid-cols-4">
                <!-- Sidebar: Linked Students -->
                <div class="lg:col-span-1 space-y-3">
                    <div class="card p-4 border-primary-200 dark:border-primary-900/60 bg-primary-50/40 dark:bg-primary-950/20">
                        <h3 class="font-bold text-sm text-surface-900 dark:text-white">ربط طالب بحسابك</h3>
                        <p class="text-xs text-surface-500 mt-1 mb-3">اكتب رقم الجوال المسجل في حساب الطالب لربطه بك.</p>
                        <form class="space-y-2" @submit.prevent="linkStudent">
                            <input
                                v-model="linkForm.student_phone"
                                type="tel"
                                inputmode="tel"
                                autocomplete="tel"
                                class="input w-full text-sm"
                                placeholder="رقم جوال الطالب"
                                required
                            />
                            <p v-if="linkForm.errors.student_phone" class="text-xs text-red-500">{{ linkForm.errors.student_phone }}</p>
                            <select v-model="linkForm.relationship" class="input w-full text-sm" required>
                                <option value="father">أب</option>
                                <option value="mother">أم</option>
                                <option value="guardian">ولي أمر</option>
                            </select>
                            <button type="submit" class="btn-primary btn-sm w-full" :disabled="linkForm.processing">
                                {{ linkForm.processing ? 'جارٍ الربط...' : 'ربط الطالب' }}
                            </button>
                        </form>
                    </div>
                    <h3 class="font-bold text-xs uppercase tracking-wider text-surface-400 mb-2">الأبناء المرتبطون</h3>
                    
                    <button v-for="link in links" :key="link.id" type="button"
                            @click="selectStudent(link.student_user_id)"
                            class="w-full card p-4 text-start hover:shadow-md transition-all border flex flex-col justify-between"
                            :class="selectedStudent && selectedStudent.student.id === link.student_user_id ? 'border-primary-500 bg-primary-50/10' : 'border-transparent'"
                    >
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-950/20 text-primary-600 font-bold flex items-center justify-center">
                                {{ link.student?.name?.charAt(0) }}
                            </div>
                            <div>
                                <div class="font-bold text-sm text-surface-900 dark:text-white">{{ link.student?.name }}</div>
                                <div class="text-xs text-surface-400">{{ link.relationship === 'father' ? 'أب' : (link.relationship === 'mother' ? 'أم' : 'ولي أمر') }}</div>
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center border-t border-surface-100 dark:border-surface-800 pt-2 mt-2">
                            <span class="text-[10px] text-surface-400">ID: {{ link.student_user_id }}</span>
                    <button type="button" @click.stop="unlinkStudent(link.id)" class="text-[10px] text-red-500 hover:underline">إلغاء الربط</button>
                        </div>
                    </button>

                    <div v-if="links.length === 0" class="text-center py-10 text-surface-400">
                        استخدم نموذج الربط بالأعلى لإضافة حساب الطالب إلى حسابك.
                    </div>
                </div>

                <!-- Main Section: Selected Student Reports -->
                <div class="lg:col-span-3 space-y-6">
                    <!-- Pending Purchase Requests -->
                    <div v-if="pendingRequests.length > 0" class="card p-6 border-amber-300 dark:border-amber-900 bg-amber-500/5">
                        <h3 class="text-lg font-bold text-surface-900 dark:text-white mb-4 flex items-center gap-2">
                            <Icon name="certificate" class="w-5 h-5 text-amber-500" />
                            <span>طلبات الدفع المعلقة للأبناء 💳</span>
                        </h3>
                        <div class="space-y-4">
                            <div v-for="req in pendingRequests" :key="req.id"
                                 class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-2xl bg-white dark:bg-surface-850 border border-surface-200 dark:border-surface-700 shadow-sm"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-950/20 text-amber-600 font-bold flex items-center justify-center">
                                        {{ req.student?.name?.charAt(0) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-sm text-surface-900 dark:text-white">
                                            {{ req.student?.name }}
                                        </div>
                                        <div class="text-xs text-surface-500 dark:text-surface-400 mt-0.5">
                                            يطلب الاشتراك في: <span class="font-semibold text-primary-600 dark:text-primary-400">{{ req.group?.assignment?.subject?.name }} — {{ req.group?.name }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between sm:justify-end gap-4">
                                    <div class="text-start sm:text-end">
                                        <div class="text-xs text-surface-400">القيمة المطلوبة</div>
                                        <div class="text-base font-black text-surface-900 dark:text-white">
                                            {{ req.group?.monthly_price === 0 ? 'مجاني' : formatQAR(req.group?.monthly_price) }}
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button"
                                                class="btn-primary btn-sm flex items-center gap-1.5"
                                                :disabled="payingRequestId === req.id"
                                                @click="payForRequest(req.id)"
                                        >
                                            <span>{{ payingRequestId === req.id ? 'جارٍ التحضير...' : 'دفع الآن' }}</span>
                                        </button>
                                        <button type="button" @click="openRejectModal(req.id)"
                                                class="btn-outline btn-sm text-xs text-red-500 border-red-200 dark:border-red-900 hover:bg-red-50 dark:hover:bg-red-950/40"
                                        >
                                            رفض
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <template v-if="selectedStudent">
                        <!-- Student Profile Header card -->
                        <div class="card p-6 bg-gradient-to-br from-primary-900 to-primary-950 text-white border-none relative overflow-hidden">
                            <div class="relative z-10">
                                <h2 class="text-2xl font-black mb-1">التقرير الدراسي لـ: {{ selectedStudent.student.name }}</h2>
                                <p class="text-sm opacity-85">{{ selectedStudent.student.email }}</p>
                            </div>
                        </div>

                        <div class="grid md:grid-cols-3 gap-4">
                            <div class="card p-5">
                                <p class="text-xs text-surface-500">نسبة الحضور</p>
                                <p class="text-3xl font-black text-primary-600 mt-2">{{ selectedStudent.attendanceSummary.rate }}%</p>
                                <p class="text-xs text-surface-400 mt-1">{{ selectedStudent.attendanceSummary.present }} من {{ selectedStudent.attendanceSummary.total }} حصة</p>
                            </div>
                            <div class="card p-5">
                                <p class="text-xs text-surface-500">الاشتراكات</p>
                                <p class="text-3xl font-black text-green-600 mt-2">{{ selectedStudent.subscriptions.length }}</p>
                                <p class="text-xs text-surface-400 mt-1">اشتراك للطالب</p>
                            </div>
                            <div class="card p-5">
                                <p class="text-xs text-surface-500">الاختبارات</p>
                                <p class="text-3xl font-black text-accent-600 mt-2">{{ selectedStudent.quizAttempts.length }}</p>
                                <p class="text-xs text-surface-400 mt-1">محاولة مسجلة</p>
                            </div>
                        </div>

                        <div class="card p-6">
                            <div class="flex items-center justify-between gap-3 mb-4">
                                <h3 class="text-lg font-bold text-surface-900 dark:text-white flex items-center gap-2">
                                    <Icon name="courses" class="w-5 h-5 text-primary-500" />
                                    الاشتراكات والحصص
                                </h3>
                                <button type="button" class="btn-ghost btn-sm" @click="contactAdmin">تواصل مع الإدارة</button>
                            </div>
                            <div class="space-y-3">
                                <div v-for="subscription in selectedStudent.subscriptions" :key="subscription.id" class="rounded-xl border border-surface-200 dark:border-surface-700 p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p class="font-bold">{{ subscription.subject?.name }} — {{ subscription.group?.name ?? 'حصص برايفت' }}</p>
                                            <p class="text-xs text-surface-500 mt-1">المدرس: {{ subscription.teacher?.name }} · {{ subscriptionStatusLabel(subscription) }}</p>
                                        </div>
                                        <div class="text-end">
                                            <p class="font-black">{{ formatQAR(subscription.monthly_price) }}</p>
                                            <p class="text-xs text-surface-400">حتى {{ subscription.period_end ?? '—' }}</p>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2 mt-3">
                                        <button type="button" class="btn-outline btn-sm" @click="contactTeacher(subscription.assignment_id)">مراسلة المدرس</button>
                                        <Link v-if="subscription.status === 'pending'" :href="route('checkout.show', subscription.id)" class="btn-primary btn-sm">إكمال الدفع</Link>
                                        <Link v-else-if="subscription.is_active || subscription.status === 'expired'" :href="route('subscriptions.renewal.show', subscription.id)" class="btn-accent btn-sm">تجديد الاشتراك</Link>
                                    </div>
                                </div>
                                <p v-if="!selectedStudent.subscriptions.length" class="text-sm text-surface-500">لا توجد اشتراكات للطالب حتى الآن.</p>
                            </div>
                        </div>

                        <div v-if="selectedStudent.eligibleGroups.length" class="card p-6">
                            <h3 class="text-lg font-bold mb-4">احجز مجموعة للطالب وادفع نيابةً عنه</h3>
                            <div class="grid md:grid-cols-2 gap-3">
                                <div v-for="group in selectedStudent.eligibleGroups" :key="group.id" class="rounded-xl border border-surface-200 dark:border-surface-700 p-4">
                                    <p class="font-bold">{{ group.subject?.name }} — {{ group.name }}</p>
                                    <p class="text-xs text-surface-500 mt-1">المدرس: {{ group.teacher?.name }} · المقاعد المتاحة: {{ group.seats_left }}</p>
                                    <p v-if="group.schedules.length" class="text-xs text-surface-500 mt-1">
                                        {{ group.schedules.map(schedule => `${days[schedule.day]} ${schedule.start}-${schedule.end}`).join('، ') }}
                                    </p>
                                    <div class="flex items-center justify-between mt-3">
                                        <b>{{ formatQAR(group.monthly_price) }}</b>
                                        <button type="button" class="btn-primary btn-sm" :disabled="group.already_subscribed || !group.seats_left" @click="subscribeToGroup(group.id)">
                                            {{ group.already_subscribed ? 'مشترك بالفعل' : (!group.seats_left ? 'مكتملة' : 'احجز وادفع') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedStudent.freeIntroSlots.length" class="card p-6">
                            <h3 class="text-lg font-bold mb-1">حصة مجانية للطالب</h3>
                            <p class="text-xs text-surface-500 mb-4">اختر موعدًا منشورًا من المدرس، بدون رسوم أو اشتراك.</p>
                            <div class="grid md:grid-cols-2 gap-3">
                                <div v-for="slot in selectedStudent.freeIntroSlots" :key="slot.id" class="rounded-xl border border-accent-500/30 bg-accent-500/5 p-4 flex items-center justify-between gap-3">
                                    <div>
                                        <p class="font-bold">{{ slot.teacher?.name }}</p>
                                        <p class="text-xs text-surface-500">{{ slot.subject }} · {{ new Date(slot.starts_at).toLocaleString('ar-EG') }}</p>
                                    </div>
                                    <button type="button" class="btn-accent btn-sm" @click="bookFreeIntro(slot.id)">احجز مجانًا</button>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedStudent.privateAssignments.length" class="card p-6">
                            <h3 class="text-lg font-bold mb-1">الحصص البرايفت</h3>
                            <p class="text-xs text-surface-500 mb-4">المواعيد التي نشرها المدرس تظهر هنا، وكل موعد متاح لطالب واحد فقط. سعر البرايفت أعلى من المجموعة.</p>
                            <div class="grid md:grid-cols-2 gap-3">
                                <div v-for="assignment in selectedStudent.privateAssignments" :key="assignment.id" class="rounded-xl border border-surface-200 dark:border-surface-700 p-4">
                                    <p class="font-bold">{{ assignment.subject }} — {{ assignment.teacher?.name }}</p>
                                    <p class="font-black text-primary-600 mt-2">{{ formatQAR(assignment.monthly_price) }} شهريًا</p>
                                    <div v-if="assignment.private_slots?.length" class="space-y-2 mt-3">
                                        <div v-for="slot in assignment.private_slots" :key="slot.id" class="flex items-center justify-between gap-2 rounded-lg bg-surface-50 dark:bg-surface-900/50 px-3 py-2">
                                            <span class="text-xs">{{ new Date(slot.starts_at).toLocaleString('ar-EG') }}</span>
                                            <button v-if="assignment.has_active_subscription" type="button" class="btn-outline btn-sm" @click="bookPrivateSlot(slot.id)">احجز الموعد</button>
                                            <span v-else class="text-[11px] text-surface-400">بعد الاشتراك</span>
                                        </div>
                                    </div>
                                    <p v-else class="text-xs text-surface-400 mt-3">لا توجد مواعيد برايفيت منشورة حاليًا.</p>
                                    <Link v-if="assignment.private_subscription?.status === 'pending'" :href="route('checkout.show', assignment.private_subscription.id)" class="btn-primary btn-sm mt-3 w-full justify-center">إكمال دفع الاشتراك</Link>
                                    <button v-else-if="!assignment.has_active_subscription" type="button" class="btn-accent btn-sm mt-3 w-full" @click="subscribeToPrivate(assignment.id)">اشترك برايفيت للطالب</button>
                                    <p v-else class="text-xs text-green-600 mt-3">اشتراك برايفيت نشط — اختر موعدًا منشورًا.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Attendance -->
                        <div class="card p-6">
                            <h3 class="text-lg font-bold mb-4">سجل الحضور</h3>
                            <div class="data-table-scroll">
                                <table class="data-table">
                                    <thead><tr class="text-surface-500 border-b border-surface-200 dark:border-surface-700"><th class="p-3 text-start">الحصة</th><th class="p-3 text-start">المادة</th><th class="p-3 text-start">الموعد</th><th class="p-3 text-start">دخل</th><th class="p-3 text-start">خرج</th><th class="p-3 text-start">الحالة</th><th class="p-3 text-start">المدة</th></tr></thead>
                                    <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                                        <tr v-for="session in paginatedAttendance" :key="session.id">
                                            <td class="p-3 font-bold">{{ session.title }}</td><td class="p-3">{{ session.subject }}</td><td class="p-3 text-xs">{{ new Date(session.scheduled_at).toLocaleString('ar-EG') }}</td>
                                            <td class="p-3 text-xs">{{ session.joined_at ? new Date(session.joined_at).toLocaleTimeString('ar-EG') : '—' }}</td><td class="p-3 text-xs">{{ session.left_at ? new Date(session.left_at).toLocaleTimeString('ar-EG') : '—' }}</td>
                                            <td class="p-3"><span :class="session.attended ? 'badge-green' : 'badge-gray'">{{ session.attended ? 'حاضر' : 'غائب' }}</span></td><td class="p-3">{{ session.minutes }} دقيقة</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-if="!selectedStudent.attendance.length" class="text-sm text-surface-500 text-center py-4">لا يوجد سجل حصص منتهية حتى الآن.</p>
                            <DataTablePagination :paginator="attendancePagination" item-label="حصة" @page-change="setAttendancePage" />
                        </div>

                        <!-- Quiz results -->
                        <div class="card p-6">
                            <h3 class="text-lg font-bold text-surface-900 dark:text-white mb-4 flex items-center gap-2">
                                <Icon name="settings" class="w-5 h-5 text-primary-500" />
                                <span>نتائج الاختبارات والتقييمات</span>
                            </h3>

                            <div class="data-table-scroll no-scrollbar">
                                <table class="data-table">
                                    <thead class="bg-surface-50 dark:bg-surface-800 border-b border-surface-200 dark:border-surface-700">
                                        <tr>
                                            <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">الاختبار</th>
                                            <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">درجة النجاح المطلوب</th>
                                            <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">درجة الطالب</th>
                                            <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">النتيجة</th>
                                            <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">التاريخ</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                                        <tr v-for="att in paginatedQuizAttempts" :key="att.id">
                                            <td class="px-6 py-4 font-bold text-surface-900 dark:text-white">{{ att.title }}</td>
                                            <td class="px-6 py-4 text-surface-500">{{ att.passing_score }}%</td>
                                            <td class="px-6 py-4 font-semibold">{{ att.earned_points }}/{{ att.total_points }} ({{ att.score }}%)</td>
                                            <td class="px-6 py-4">
                                                <span :class="att.passed ? 'badge-green' : 'badge-gray'">
                                                    {{ att.passed ? 'ناجح' : 'لم ينجح' }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-xs text-surface-400">
                                                {{ att.submitted_at ? new Date(att.submitted_at).toLocaleDateString('ar') : 'لم يسلّم' }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div v-if="selectedStudent.quizAttempts.length === 0" class="text-center py-6 text-surface-400">
                                لا توجد محاولات اختبار مسجلة حتى الآن.
                            </div>
                            <DataTablePagination :paginator="quizPagination" item-label="محاولة" @page-change="setQuizPage" />
                        </div>

                        <!-- Homework and paper exam grades -->
                        <div class="card p-6">
                            <h3 class="text-lg font-bold mb-4">الواجبات وتقييم الاختبار الورقي</h3>
                            <div class="space-y-3">
                                <div v-for="submission in selectedStudent.submissions" :key="submission.id" class="rounded-xl border border-surface-200 dark:border-surface-700 p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div><p class="font-bold">{{ submission.title }}</p><p class="text-xs text-surface-500 mt-1">{{ submission.subject }} · {{ submission.type === 'paper_exam' ? 'اختبار ورقي' : 'واجب' }}</p></div>
                                        <b v-if="submission.score !== null">{{ submission.score }}/{{ submission.max_score }}</b><span v-else class="text-xs text-surface-400">بانتظار التقييم</span>
                                    </div>
                                    <p v-if="submission.teacher_feedback" class="text-sm text-surface-600 dark:text-surface-300 mt-3">ملاحظات المدرس: {{ submission.teacher_feedback }}</p>
                                </div>
                            </div>
                            <p v-if="!selectedStudent.submissions.length" class="text-sm text-surface-500 text-center py-4">لا توجد واجبات أو نماذج ورقية مسلّمة.</p>
                        </div>

                        <!-- Payments/Transactions -->
                        <div class="card p-6">
                            <h3 class="text-lg font-bold text-surface-900 dark:text-white mb-4 flex items-center gap-2">
                                <Icon name="payments" class="w-5 h-5 text-primary-500" />
                                <span>المدفوعات والفواتير</span>
                            </h3>

                            <div class="data-table-scroll no-scrollbar">
                                <table class="data-table">
                                    <thead class="bg-surface-50 dark:bg-surface-800 border-b border-surface-200 dark:border-surface-700">
                                        <tr>
                                            <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">الاشتراك</th>
                                            <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">المبلغ</th>
                                            <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">طريقة التحويل</th>
                                            <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">التاريخ</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                                        <tr v-for="pay in paginatedPayments" :key="pay.id">
                                            <td class="px-6 py-4 font-bold text-surface-900 dark:text-white">{{ pay.subscription?.assignment?.subject?.name ?? "اشتراك" }}</td>
                                            <td class="px-6 py-4 font-semibold text-green-600 dark:text-green-400">{{ formatQAR(pay.amount) }}</td>
                                            <td class="px-6 py-4 text-surface-500">
                                                <span v-if="pay.gateway === 'vodafone_cash'">
                                                    فودافون كاش<span v-if="pay.sender_phone" dir="ltr"> — {{ pay.sender_phone }}</span>
                                                </span>
                                                <span v-else>تحويل سابق</span>
                                            </td>
                                            <td class="px-6 py-4 text-xs text-surface-400">{{ new Date(pay.created_at).toLocaleDateString('ar') }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div v-if="selectedStudent.payments.length === 0" class="text-center py-6 text-surface-400">
                                لا توجد عمليات دفع مسجلة.
                            </div>
                            <DataTablePagination :paginator="paymentsPagination" item-label="عملية دفع" @page-change="setPaymentsPage" />
                        </div>
                    </template>

                    <div v-else class="card p-16 text-center text-surface-400">
                        <Icon name="users" class="w-16 h-16 mx-auto text-surface-300 dark:text-surface-700 mb-4" />
                        <h3 class="text-lg font-bold text-surface-800 dark:text-surface-200 mb-2">اختر أحد الأبناء للمتابعة</h3>
                        <p class="text-sm">اضغط على بطاقة أحد الأبناء من القائمة الجانبية لعرض التقدم والدراسة.</p>
                    </div>
                </div>
            </div>

            <!-- Modal for rejecting -->
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div v-if="isRejectModalOpen" class="modal-overlay z-50 bg-black/55 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label="رفض طلب الشراء">
                    <div class="modal-panel-compact bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-3xl w-full max-w-lg p-6 shadow-2xl relative" dir="rtl">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-black text-surface-900 dark:text-white">رفض طلب الشراء</h3>
                            <button type="button" @click="isRejectModalOpen = false" class="btn-ghost p-1 rounded-full" aria-label="إغلاق النافذة">
                                <Icon name="close" class="w-5 h-5 text-surface-500" />
                            </button>
                        </div>

                        <form @submit.prevent="submitReject" class="space-y-4">
                            <div>
                                <label class="label mb-1">سبب الرفض (اختياري)</label>
                                <textarea v-model="rejectNotes" class="input h-24 p-3" placeholder="اكتب سبب الرفض هنا..."></textarea>
                                <p v-if="rejectForm.errors.notes" class="error-msg mt-1">{{ rejectForm.errors.notes }}</p>
                            </div>

                            <div class="flex gap-3 pt-4">
                                <button type="submit" :disabled="rejectForm.processing" class="btn-primary flex-1 bg-red-600 hover:bg-red-700 focus:ring-red-500/40">{{ rejectForm.processing ? 'جارٍ الرفض...' : 'تأكيد الرفض' }}</button>
                                <button type="button" @click="isRejectModalOpen = false" class="btn-ghost flex-1">إلغاء</button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </div>
    </DashboardLayout>
</template>
