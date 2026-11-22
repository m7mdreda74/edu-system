<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import StatCard from '@/Components/StatCard.vue';
import { formatTime12 } from '@/lib/money';
import { filterTeacherGroups } from '@/lib/teacherGroupFilters';

const props = defineProps({
    groups: { type: Array, default: () => [] },
    privateSlots: { type: Array, default: () => [] },
    assignedGrades: { type: Array, default: () => [] },
    assignedSubjects: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
});

const searchQuery = ref('');
const selectedStage = ref('');
const selectedType = ref('');
const selectedGrade = ref('');
const selectedTrack = ref('');
const selectedSubject = ref('');

const appliedFilters = ref({
    search: '',
    stage: '',
    type: '',
    grade: '',
    track: '',
    subject: '',
});

const selectedGroupForStudents = ref(null);
const studentSearchQuery = ref('');
const rosterStudents = ref([]);
const rosterMeta = ref({ current_page: 1, last_page: 1, total: 0 });
const rosterLoading = ref(false);
const rosterError = ref('');
let rosterRequestId = 0;

const days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
const stageOrder = ['primary', 'preparatory', 'secondary'];
const stageLabels = { primary: 'ابتدائي', preparatory: 'إعدادي', secondary: 'ثانوي' };
const trackLabels = { science: 'العلمي', arts: 'الآداب والإنسانيات', technology: 'التكنولوجي' };
const allGroups = computed(() => [...props.groups, ...props.privateSlots]);

function formatSchedule(schedule) {
    const date = schedule.date ? new Date(`${schedule.date.slice(0, 10)}T00:00:00`).toLocaleDateString('ar-EG-u-nu-latn', { dateStyle: 'medium' }) : null;
    const endDate = schedule.end_date && schedule.end_date !== schedule.date
        ? new Date(`${schedule.end_date.slice(0, 10)}T00:00:00`).toLocaleDateString('ar-EG-u-nu-latn', { dateStyle: 'medium' })
        : null;
    return `${days[schedule.day_of_week]}${date ? ` ${date}` : ''} · ${formatTime12(schedule.start_time)}${schedule.end_time ? ` إلى ${formatTime12(schedule.end_time)}${endDate ? ` ${endDate}` : ''}` : ''}${schedule.timezone ? ` (${schedule.timezone})` : ''}`;
}

function uniqueOptions(values, key = 'id') {
    return [...new Map(values.filter(Boolean).map(value => [value[key], value])).values()];
}

const grades = computed(() => uniqueOptions([
    ...props.assignedGrades,
    ...allGroups.value.map(group => group.grade),
]));

const availableGrades = computed(() => {
    if (!selectedStage.value) return grades.value;
    return grades.value.filter(grade => grade.stage === selectedStage.value);
});

watch(selectedStage, () => {
    if (selectedGrade.value && !availableGrades.value.some(g => String(g.id) === String(selectedGrade.value))) {
        selectedGrade.value = '';
    }
});

const subjects = computed(() => uniqueOptions([
    ...props.assignedSubjects,
    ...allGroups.value.map(group => group.subject),
]));

const stages = computed(() => {
    const present = new Set(grades.value.map(grade => grade.stage).filter(Boolean));
    const ordered = stageOrder.filter(stage => present.has(stage));
    return ordered.length ? ordered : stageOrder;
});

function applyFilters() {
    appliedFilters.value = {
        search: searchQuery.value,
        stage: selectedStage.value,
        type: selectedType.value,
        grade: selectedGrade.value,
        track: selectedTrack.value,
        subject: selectedSubject.value,
    };
}

function resetFilters() {
    searchQuery.value = '';
    selectedStage.value = '';
    selectedType.value = '';
    selectedGrade.value = '';
    selectedTrack.value = '';
    selectedSubject.value = '';
    applyFilters();
}

const hasActiveFilter = computed(() => Boolean(
    searchQuery.value || selectedStage.value || selectedType.value || selectedGrade.value ||
    appliedFilters.value.search || appliedFilters.value.stage || appliedFilters.value.type || appliedFilters.value.grade
));

const filteredGroups = computed(() => filterTeacherGroups(allGroups.value, appliedFilters.value));

async function openStudentsModal(group) {
    selectedGroupForStudents.value = group;
    studentSearchQuery.value = '';
    rosterStudents.value = [];
    rosterMeta.value = { current_page: 1, last_page: 1, total: 0 };
    await fetchRoster();
}

function closeStudentsModal() {
    rosterRequestId++;
    selectedGroupForStudents.value = null;
    studentSearchQuery.value = '';
    rosterStudents.value = [];
    rosterError.value = '';
    rosterLoading.value = false;
}

async function fetchRoster(page = 1) {
    if (!selectedGroupForStudents.value) return;
    const requestId = ++rosterRequestId;

    if (selectedGroupForStudents.value.source === 'private_slot') {
        const q = studentSearchQuery.value.trim().toLowerCase();
        rosterStudents.value = (selectedGroupForStudents.value.students ?? []).filter(student =>
            !q || student.name?.toLowerCase().includes(q) || student.email?.toLowerCase().includes(q)
        );
        rosterMeta.value = { current_page: 1, last_page: 1, total: rosterStudents.value.length };
        rosterError.value = '';
        rosterLoading.value = false;
        return;
    }

    rosterLoading.value = true;
    rosterError.value = '';

    try {
        const { data } = await axios.get(route('teacher.groups.roster', {
            groupId: selectedGroupForStudents.value.id,
            page,
            search: studentSearchQuery.value || undefined,
        }));
        if (requestId !== rosterRequestId) return;
        rosterStudents.value = data.data ?? [];
        rosterMeta.value = data.meta ?? rosterMeta.value;
    } catch (error) {
        if (requestId !== rosterRequestId) return;
        rosterError.value = error.response?.data?.message || 'تعذر تحميل قائمة الطلاب.';
    } finally {
        if (requestId === rosterRequestId) rosterLoading.value = false;
    }
}
</script>

<template>
    <DashboardLayout>
        <Head title="مجموعاتي التعليمية" />

        <div class="container-app px-4 py-8 space-y-7">
            <header class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white flex items-center gap-3">
                        <Icon name="courses" class="w-8 h-8 text-primary-500" />
                        <span>مجموعاتي التعليمية</span>
                    </h1>
                    <p class="text-surface-500 dark:text-surface-400 mt-1">
                        إدارة مجموعاتك الدراسية، ومتابعة الطلاب المسجلين بكل مجموعة ومواعيدها الأسبوعية.
                    </p>
                </div>

            </header>

            <!-- Stats Grid -->
            <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <StatCard label="إجمالي المجموعات" :value="stats.total_groups || groups.length" icon="courses" tone="primary" />
                <StatCard label="المجموعات النشطة" :value="stats.active_groups || groups.length" icon="success" tone="green" />
                <StatCard label="الطلاب المسجلون في المجموعات" :value="stats.total_students || 0" icon="student" tone="accent" />
                <StatCard label="السعة الإجمالية" :value="stats.total_capacity || 0" icon="users" tone="primary" />
            </section>

            <!-- Filters -->
            <div class="card p-4 space-y-3">
                <div class="flex flex-wrap items-center gap-3">
                    <!-- 1. جميع المراحل (ابتدائي / إعدادي / ثانوي) -->
                    <select v-model="selectedStage" aria-label="المرحلة الدراسية" class="input text-sm w-auto min-w-[170px]">
                        <option value="">جميع المراحل</option>
                        <option v-for="stage in stages" :key="stage" :value="stage">{{ stageLabels[stage] || stage }}</option>
                    </select>

                    <!-- 2. جميع المجموعات (مجموعة / برايفيت) -->
                    <select v-model="selectedType" aria-label="نوع المجموعة" class="input text-sm w-auto min-w-[170px]">
                        <option value="">جميع المجموعات</option>
                        <option value="group">مجموعة</option>
                        <option value="private">برايفيت</option>
                    </select>

                    <!-- 3. جميع الصفوف (حسب المرحلة المسندة للمعلم) -->
                    <select v-model="selectedGrade" aria-label="الصف الدراسي" class="input text-sm w-auto min-w-[190px]">
                        <option value="">جميع الصفوف</option>
                        <option v-for="grade in availableGrades" :key="grade.id" :value="String(grade.id)">
                            {{ grade.name }}{{ grade.track ? ` - ${trackLabels[grade.track] || grade.track}` : '' }}
                        </option>
                    </select>

                    <!-- 4. زر تطبيق الفلتر -->
                    <button type="button" class="btn-primary btn-sm px-5" @click="applyFilters">
                        تطبيق الفلتر
                    </button>

                    <button v-if="hasActiveFilter" type="button" class="btn-ghost btn-sm" @click="resetFilters">
                        إلغاء الفلتر
                    </button>

                    <span class="ms-auto text-xs text-surface-500 font-bold">
                        عرض {{ filteredGroups.length }} من {{ allGroups.length }} نتيجة
                    </span>
                </div>
            </div>

            <!-- Groups Grid -->
            <section v-if="filteredGroups.length" class="grid md:grid-cols-2 gap-6">
                <article
                    v-for="group in filteredGroups"
                    :key="`${group.source}-${group.id}`"
                    class="card p-6 flex flex-col justify-between space-y-5 hover:border-primary-500/40 transition-colors"
                >
                    <div class="space-y-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-xl font-black text-surface-900 dark:text-white">{{ group.name }}</h2>
                                <div class="flex flex-wrap items-center gap-2 mt-1 text-xs text-surface-500">
                                    <span>{{ group.is_private ? 'برايفت' : 'مجموعة' }}</span>
                                    <span>·</span>
                                    <span class="font-bold text-primary-600 dark:text-primary-400">{{ group.subject?.name }}</span>
                                    <span>·</span>
                                    <span>{{ group.grade?.name }}</span>
                                    <span v-if="group.term">· {{ group.term }}</span>
                                </div>
                            </div>
                            <span :class="group.is_active ? 'badge-green' : 'badge-gray'" class="text-xs">
                                {{ group.source === 'private_slot' ? 'محجوزة' : (group.is_active ? 'نشطة' : 'متوقفة') }}
                            </span>
                        </div>

                    </div>

                    <!-- Actions -->
                    <div class="pt-3 border-t border-surface-100 dark:border-surface-700/60 flex flex-wrap items-center justify-between gap-2">
                        <button
                            type="button"
                            class="btn-primary btn-sm flex items-center gap-1.5"
                            @click="openStudentsModal(group)"
                        >
                            <Icon name="users" class="w-4 h-4" />
                            <span>عرض تفاصيل المجموعة</span>
                        </button>

                        <div v-if="group.source === 'group'" class="flex items-center gap-1.5">
                            <Link :href="route('teacher.groups.workspace', { groupId: group.id })" class="btn-primary btn-sm">مساحة المجموعة</Link>
                            <Link :href="route('teacher.materials', { groupId: group.id })" class="btn-outline btn-sm">المواد</Link>
                            <Link :href="route('teacher.worksheets.index', { groupId: group.id })" class="btn-ghost btn-sm">الواجبات</Link>
                        </div>
                    </div>
                </article>
            </section>

            <div v-else class="card p-12 text-center text-surface-400">
                <Icon name="courses" class="w-12 h-12 mx-auto mb-3 opacity-50" />
                <p class="text-base font-bold text-surface-700 dark:text-surface-300">لا توجد نتائج تطابق البحث والفلاتر.</p>
            </div>
        </div>

        <!-- Students Modal -->
        <div
            v-if="selectedGroupForStudents"
            class="modal-overlay z-[70] bg-black/60 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            :aria-label="`طلاب ${selectedGroupForStudents.name}`"
            @click.self="closeStudentsModal"
        >
            <div class="card w-full max-w-2xl max-h-[85vh] flex flex-col p-6 space-y-4 shadow-2xl">
                <div class="flex items-start justify-between gap-3 border-b border-surface-200 dark:border-surface-700 pb-4">
                    <div>
                        <h2 class="text-xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                            <Icon name="users" class="w-6 h-6 text-primary-500" />
                            <span>تفاصيل المجموعة: {{ selectedGroupForStudents.name }}</span>
                        </h2>
                        <p class="text-xs text-surface-500 mt-1">
                            {{ selectedGroupForStudents.students_count ?? 0 }} طالب مسجل · السعة {{ selectedGroupForStudents.capacity }} مقعد
                        </p>
                    </div>
                    <button type="button" class="text-surface-400 hover:text-surface-600 text-lg font-bold" @click="closeStudentsModal">✕</button>
                </div>

                <div class="text-sm text-surface-700 dark:text-surface-300">
                    <span class="font-bold">{{ selectedGroupForStudents.source === 'private_slot' ? 'موعد الحصة:' : 'المواعيد الأسبوعية:' }} </span>
                    <span v-if="selectedGroupForStudents.schedules?.length">{{ selectedGroupForStudents.schedules.map(formatSchedule).join('، ') }}</span>
                    <span v-else>لم يتم تحديد مواعيد بعد.</span>
                </div>

                <div class="space-y-2">
                    <input
                        v-model="studentSearchQuery"
                        type="text"
                        class="input text-xs"
                        placeholder="ابحث بالاسم أو البريد الإلكتروني..."
                        @keyup.enter="fetchRoster()"
                    />
                    <button type="button" class="btn-outline btn-sm" :disabled="rosterLoading" @click="fetchRoster()">
                        {{ rosterLoading ? 'جارٍ البحث...' : 'بحث' }}
                    </button>
                </div>

                <div class="overflow-y-auto no-scrollbar flex-1 space-y-2.5 py-1">
                    <div v-if="rosterLoading" class="py-12 text-center text-sm text-surface-400">جارٍ تحميل قائمة الطلاب...</div>
                    <div v-else-if="rosterError" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/20 dark:text-red-300">
                        {{ rosterError }}
                    </div>
                    <template v-else>
                        <div
                            v-for="(student, sIdx) in rosterStudents"
                            :key="student.id"
                            class="flex items-center justify-between gap-3 p-3.5 rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-900/60 hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                        >
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="text-xs font-mono text-surface-400 w-5">{{ sIdx + 1 }}.</span>
                                <div class="w-10 h-10 rounded-full bg-primary-500/10 text-primary-600 flex items-center justify-center font-bold text-sm overflow-hidden shrink-0">
                                    <img v-if="student.avatar" :src="student.avatar" :alt="student.name" class="w-full h-full object-cover" />
                                    <span v-else>{{ student.name?.charAt(0) }}</span>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-bold text-sm text-surface-900 dark:text-white truncate">{{ student.name }}</p>
                                    <p class="text-xs text-surface-500 dark:text-surface-400 font-mono truncate">{{ student.email }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <span class="badge-green text-xs">مشترك</span>
                                <Link :href="route('chat.index')" class="btn-ghost btn-sm text-primary-600">
                                    رسالة
                                </Link>
                            </div>
                        </div>
                    </template>

                    <div v-if="!rosterLoading && !rosterError && !rosterStudents.length" class="py-12 text-center text-surface-400 text-sm">
                        لا يوجد طلاب مسجلون في هذه المجموعة بعد.
                    </div>
                </div>

                <div class="pt-3 border-t border-surface-200 dark:border-surface-700 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <button type="button" class="btn-ghost btn-sm" :disabled="rosterLoading || rosterMeta.current_page <= 1" @click="fetchRoster(rosterMeta.current_page - 1)">السابق</button>
                        <span class="text-xs text-surface-500">{{ rosterMeta.current_page }} / {{ rosterMeta.last_page }}</span>
                        <button type="button" class="btn-ghost btn-sm" :disabled="rosterLoading || rosterMeta.current_page >= rosterMeta.last_page" @click="fetchRoster(rosterMeta.current_page + 1)">التالي</button>
                    </div>
                    <button type="button" class="btn-outline btn-sm" @click="closeStudentsModal">إغلاق</button>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
