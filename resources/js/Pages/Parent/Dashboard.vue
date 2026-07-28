<script setup>
import { ref } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    links:           { type: Array, required: true },
    selectedStudent: { type: Object, default: null },
    pendingRequests: { type: Array, default: () => [] },
});

const isModalOpen = ref(false);
const { confirm } = useConfirm();

const form = useForm({
    email: '',
    relationship: 'father',
});

function openAddModal() {
    form.reset();
    isModalOpen.value = true;
}

function submitLink() {
    form.post(route('parent.link-student'), {
        onSuccess: () => {
            isModalOpen.value = false;
            form.reset();
        }
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
    const formatted = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format((halala ?? 0) / 100);
    return `${formatted} ر.ق.`;
}

const rejectNotes = ref('');
const activeRejectRequestId = ref(null);
const isRejectModalOpen = ref(false);

function openRejectModal(requestId) {
    activeRejectRequestId.value = requestId;
    rejectNotes.value = '';
    isRejectModalOpen.value = true;
}

function submitReject() {
    router.post(route('parent.purchase-requests.reject', { id: activeRejectRequestId.value }), {
        notes: rejectNotes.value
    }, {
        onSuccess: () => {
            isRejectModalOpen.value = false;
            activeRejectRequestId.value = null;
            rejectNotes.value = '';
        }
    });
}
</script>

<template>
    <DashboardLayout>
        <Head title="بوابة أولياء الأمور" />

        <div class="container-app px-4 py-10">
            <!-- Header -->
            <div class="flex items-center justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <Icon name="users" class="w-8 h-8 text-primary-500" />
                        <span>متابعة أداء الأبناء (أولياء الأمور)</span>
                    </h1>
                    <p class="text-surface-500 mt-1">عرض ومتابعة التقدم الدراسي ونتائج اختبارات الطلاب المرتبطين بك</p>
                </div>
                <button @click="openAddModal" class="btn-primary flex items-center gap-2">
                    <Icon name="plus" class="w-4 h-4" />
                    <span>ربط حساب ابن/ابنة</span>
                </button>
            </div>

            <!-- Dashboard Layout Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                <!-- Sidebar: Linked Students -->
                <div class="lg:col-span-1 space-y-3">
                    <h3 class="font-bold text-xs uppercase tracking-wider text-surface-400 mb-2">الأبناء المرتبطون</h3>
                    
                    <button v-for="link in links" :key="link.id" 
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
                            <button @click.stop="unlinkStudent(link.id)" class="text-[10px] text-red-500 hover:underline">إلغاء الربط</button>
                        </div>
                    </button>

                    <div v-if="links.length === 0" class="text-center py-10 text-surface-400">
                        لم تقم بربط أي حساب طالب حتى الآن.
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
                                        <Link :href="route('checkout.show', { slug: req.course?.slug })"
                                              :data="{ purchase_request_id: req.id }"
                                              class="btn-primary btn-sm flex items-center gap-1.5"
                                        >
                                            <span>دفع الآن</span>
                                        </Link>
                                        <button @click="openRejectModal(req.id)"
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

                        <!-- Progress in Courses -->
                        <div class="card p-6">
                            <h3 class="text-lg font-bold text-surface-900 dark:text-white mb-4 flex items-center gap-2">
                                <Icon name="courses" class="w-5 h-5 text-primary-500" />
                                <span>الاشتراكات الحالية</span>
                            </h3>

                            <div class="space-y-4">
                                <div v-for="enroll in selectedStudent.enrollments" :key="enroll.id" class="border border-surface-100 dark:border-surface-800 p-4 rounded-2xl">
                                    <div class="flex items-center justify-between gap-4 mb-2">
                                        <div>
                                            <h4 class="font-bold text-surface-900 dark:text-white text-base">{{ enroll.subject?.name }}</h4>
                                            <p class="text-xs text-surface-400">المدرس: {{ enroll.teacher?.name }}</p>
                                        </div>
                                        <div class="text-sm font-bold text-primary-500">{{ enroll.progress_percent }}%</div>
                                    </div>
                                    <div class="progress-bar w-full">
                                        <div class="progress-bar-fill" :style="{ width: enroll.progress_percent + '%' }"></div>
                                    </div>
                                </div>

                                <div v-if="selectedStudent.enrollments.length === 0" class="text-center py-6 text-surface-400">
                                    الطالب غير مشترك مع أي معلم بعد.
                                </div>
                            </div>
                        </div>

                        <!-- Quiz results -->
                        <div class="card p-6">
                            <h3 class="text-lg font-bold text-surface-900 dark:text-white mb-4 flex items-center gap-2">
                                <Icon name="settings" class="w-5 h-5 text-primary-500" />
                                <span>نتائج الاختبارات والتقييمات</span>
                            </h3>

                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
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
                                        <tr v-for="att in selectedStudent.quizAttempts" :key="att.id">
                                            <td class="px-6 py-4 font-bold text-surface-900 dark:text-white">{{ att.quiz?.title }}</td>
                                            <td class="px-6 py-4 text-surface-500">{{ att.quiz?.passing_score }}%</td>
                                            <td class="px-6 py-4 font-semibold">{{ att.score }}%</td>
                                            <td class="px-6 py-4">
                                                <span :class="att.passed ? 'badge-green' : 'badge-gray'">
                                                    {{ att.passed ? 'ناجح' : 'لم ينجح' }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-xs text-surface-400">
                                                {{ new Date(att.created_at).toLocaleDateString('ar') }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div v-if="selectedStudent.quizAttempts.length === 0" class="text-center py-6 text-surface-400">
                                لا توجد محاولات اختبار مسجلة حتى الآن.
                            </div>
                        </div>

                        <!-- Payments/Transactions -->
                        <div class="card p-6">
                            <h3 class="text-lg font-bold text-surface-900 dark:text-white mb-4 flex items-center gap-2">
                                <Icon name="payments" class="w-5 h-5 text-primary-500" />
                                <span>المدفوعات والفواتير</span>
                            </h3>

                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-surface-50 dark:bg-surface-800 border-b border-surface-200 dark:border-surface-700">
                                        <tr>
                                            <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">الاشتراك</th>
                                            <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">المبلغ</th>
                                            <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">بوابة الدفع</th>
                                            <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">التاريخ</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                                        <tr v-for="pay in selectedStudent.payments" :key="pay.id">
                                            <td class="px-6 py-4 font-bold text-surface-900 dark:text-white">{{ pay.subscription?.assignment?.subject?.name ?? "اشتراك" }}</td>
                                            <td class="px-6 py-4 font-semibold text-green-600 dark:text-green-400">{{ formatQAR(pay.amount) }}</td>
                                            <td class="px-6 py-4 text-surface-500 uppercase">{{ pay.gateway }}</td>
                                            <td class="px-6 py-4 text-xs text-surface-400">{{ new Date(pay.created_at).toLocaleDateString('ar') }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div v-if="selectedStudent.payments.length === 0" class="text-center py-6 text-surface-400">
                                لا توجد عمليات دفع مسجلة.
                            </div>
                        </div>
                    </template>

                    <div v-else class="card p-16 text-center text-surface-400">
                        <Icon name="users" class="w-16 h-16 mx-auto text-surface-300 dark:text-surface-700 mb-4" />
                        <h3 class="text-lg font-bold text-surface-800 dark:text-surface-200 mb-2">اختر أحد الأبناء للمتابعة</h3>
                        <p class="text-sm">اضغط على بطاقة أحد الأبناء من القائمة الجانبية لعرض التقدم والدراسة.</p>
                    </div>
                </div>
            </div>

            <!-- Modal for linking -->
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/55 backdrop-blur-sm">
                    <div class="bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-3xl w-full max-w-lg p-6 overflow-hidden shadow-2xl relative" dir="rtl">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-black text-surface-900 dark:text-white">ربط حساب ابن/ابنة جديد</h3>
                            <button @click="isModalOpen = false" class="btn-ghost p-1 rounded-full">
                                <Icon name="close" class="w-5 h-5 text-surface-500" />
                            </button>
                        </div>

                        <form @submit.prevent="submitLink" class="space-y-4">
                            <div>
                                <label class="label mb-1">البريد الإلكتروني للطالب</label>
                                <input v-model="form.email" type="email" required class="input text-start" placeholder="student@example.com" />
                            </div>

                            <div>
                                <label class="label mb-1">صلة القرابة</label>
                                <select v-model="form.relationship" required class="input">
                                    <option value="father">أب (Father)</option>
                                    <option value="mother">أم (Mother)</option>
                                    <option value="guardian">ولي أمر (Guardian)</option>
                                </select>
                            </div>

                            <div class="flex gap-3 pt-4">
                                <button type="submit" :disabled="form.processing" class="btn-primary flex-1">ربط الحساب</button>
                                <button type="button" @click="isModalOpen = false" class="btn-ghost flex-1">إلغاء</button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>

            <!-- Modal for rejecting -->
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div v-if="isRejectModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/55 backdrop-blur-sm">
                    <div class="bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-3xl w-full max-w-lg p-6 overflow-hidden shadow-2xl relative" dir="rtl">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-black text-surface-900 dark:text-white">رفض طلب الشراء</h3>
                            <button @click="isRejectModalOpen = false" class="btn-ghost p-1 rounded-full">
                                <Icon name="close" class="w-5 h-5 text-surface-500" />
                            </button>
                        </div>

                        <form @submit.prevent="submitReject" class="space-y-4">
                            <div>
                                <label class="label mb-1">سبب الرفض (اختياري)</label>
                                <textarea v-model="rejectNotes" class="input h-24 p-3" placeholder="اكتب سبب الرفض هنا..."></textarea>
                            </div>

                            <div class="flex gap-3 pt-4">
                                <button type="submit" class="btn-primary flex-1 bg-red-600 hover:bg-red-700 focus:ring-red-500/40">تأكيد الرفض</button>
                                <button type="button" @click="isRejectModalOpen = false" class="btn-ghost flex-1">إلغاء</button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </div>
    </DashboardLayout>
</template>
