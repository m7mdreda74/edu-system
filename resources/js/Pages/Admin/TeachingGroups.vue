<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import StatCard from '@/Components/StatCard.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { formatQAR } from '@/lib/money';
import { debounce } from '@/lib/debounce';
import { useConfirm } from '@/composables/useConfirm';
import { useClientPagination } from '@/composables/useClientPagination';

const props = defineProps({
    groups:       { type: Object, required: true },
    assignments:  { type: Array, default: () => [] },
    teachers:     { type: Array, default: () => [] },
    subjects:     { type: Array, default: () => [] },
    gradeLevels:  { type: Array, default: () => [] },
    filters:      { type: Object, default: () => ({}) },
    terms:        { type: Array, default: () => [] },
    stats:        { type: Object, default: () => ({}) },
});

const {
    data: paginatedAssignments,
    pagination: assignmentsPagination,
    setPage: setAssignmentsPage,
} = useClientPagination(computed(() => props.assignments));

const { confirm } = useConfirm();

const managementTab = ref('assignments');
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const term = ref(props.filters.term ?? '');

const assignmentForm = useForm({
    teacher_id: '',
    subject_id: '',
    grade_level_id: '',
    accepts_private: false,
    private_monthly_price_qar: 0,
});

const assignmentEdit = useForm({
    id: null,
    accepts_private: false,
    private_monthly_price_qar: 0,
    is_active: true,
});

const groupForm = useForm({
    teaching_assignment_id: '',
    academic_term_id: '',
    name: '',
    capacity: 5,
    monthly_price_qar: 0,
    timezone: 'Asia/Qatar',
});

const groupEdit = useForm({
    id: null,
    name: '',
    capacity: 1,
    monthly_price_qar: 0,
    academic_term_id: '',
    is_active: true,
});

const apply = debounce(() => {
    router.get(route('admin.teaching-groups'), {
        search: search.value || undefined,
        status: status.value || undefined,
        term: term.value || undefined,
    }, { preserveState: true, replace: true });
}, 300);

watch([search, status, term], apply);

function storeAssignment() {
    assignmentForm.post(route('admin.teaching-assignments.store'), {
        preserveScroll: true,
        onSuccess: () => assignmentForm.reset(),
    });
}

function editAssignment(assignment) {
    assignmentEdit.id = assignment.id;
    assignmentEdit.accepts_private = Boolean(assignment.accepts_private);
    assignmentEdit.private_monthly_price_qar = (assignment.private_monthly_price ?? 0) / 100;
    assignmentEdit.is_active = Boolean(assignment.is_active);
}

function updateAssignment() {
    assignmentEdit.patch(route('admin.teaching-assignments.update', assignmentEdit.id), {
        preserveScroll: true,
        onSuccess: () => assignmentEdit.reset(),
    });
}

function storeGroup() {
    groupForm.post(route('admin.teaching-groups.store'), {
        preserveScroll: true,
        onSuccess: () => {
            groupForm.reset();
            groupForm.capacity = 5;
            groupForm.monthly_price_qar = 0;
            groupForm.timezone = 'Asia/Qatar';
        },
    });
}

function editGroup(group) {
    groupEdit.id = group.id;
    groupEdit.name = group.name;
    groupEdit.capacity = group.capacity;
    groupEdit.monthly_price_qar = group.monthly_price / 100;
    groupEdit.academic_term_id = group.academic_term_id ?? '';
    groupEdit.is_active = Boolean(group.is_active);
}

function updateGroup() {
    groupEdit.put(route('admin.teaching-groups.update', groupEdit.id), {
        preserveScroll: true,
        onSuccess: () => groupEdit.reset(),
    });
}

async function toggle(group) {
    const message = group.is_active
        ? `إيقاف "${group.name}"؟ لن تظهر للطلاب الجدد.`
        : `تفعيل "${group.name}"؟`;

    const ok = await confirm({
        title: group.is_active ? 'إيقاف المجموعة' : 'تفعيل المجموعة',
        message,
        confirmLabel: group.is_active ? 'إيقاف' : 'تفعيل',
        variant: group.is_active ? 'warning' : 'info',
    });
    if (ok) router.patch(route('admin.teaching-groups.toggle', { id: group.id }), {}, { preserveScroll: true });
}

async function destroyGroup(group) {
    const ok = await confirm({
        title: `حذف "${group.name}"`,
        message: 'سيتم حذف هذه المجموعة نهائياً.',
        confirmLabel: 'حذف',
        variant: 'danger',
    });
    if (ok) router.delete(route('admin.teaching-groups.destroy', group.id), { preserveScroll: true });
}

</script>

<template>
    <Head title="إدارة التدريس والتسعير" />

    <DashboardLayout>
        <div class="dashboard-data-page">
            <header>
                <h1 class="text-2xl font-black text-surface-900 dark:text-white">إدارة التدريس والتسعير</h1>
                <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                    الإدارة تنشئ الإسنادات والمجموعات وتحدد السعة والأسعار، والمدرس يحدد المواعيد وينشر الحصص والمحتوى والاختبارات.
                </p>
            </header>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <StatCard label="إجمالي المجموعات" :value="stats.total" icon="courses" tone="primary" />
                <StatCard label="مفعّلة" :value="stats.active" icon="success" tone="green" />
                <StatCard label="متوقفة" :value="stats.inactive" icon="lock" tone="slate" />
                <StatCard label="بلا طلاب" :value="stats.empty" icon="info" tone="accent" />
            </div>

            <section class="card overflow-hidden">
                <div class="flex flex-wrap border-b border-surface-100 dark:border-surface-800">
                    <button
                        v-for="item in [
                            { key: 'assignments', label: 'إسناد المواد وتسعير البرايفيت' },
                            { key: 'groups', label: 'إنشاء مجموعة وتسعيرها' },
                        ]"
                        :key="item.key"
                        type="button"
                        class="px-5 py-3 text-xs font-bold border-b-2 transition-colors"
                        :class="managementTab === item.key ? 'border-primary-600 text-primary-600' : 'border-transparent text-surface-500'"
                        @click="managementTab = item.key"
                    >
                        {{ item.label }}
                    </button>
                </div>

                <div v-if="managementTab === 'assignments'" class="p-5 space-y-6">
                    <form class="grid md:grid-cols-3 gap-3" @submit.prevent="storeAssignment">
                        <select v-model="assignmentForm.teacher_id" class="input" required>
                            <option value="">اختر المعلم</option>
                            <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">{{ teacher.name }}</option>
                        </select>
                        <select v-model="assignmentForm.subject_id" class="input" required>
                            <option value="">اختر المادة</option>
                            <option v-for="subject in subjects" :key="subject.id" :value="subject.id">{{ subject.name }}</option>
                        </select>
                        <select v-model="assignmentForm.grade_level_id" class="input" required>
                            <option value="">اختر الصف</option>
                            <option v-for="grade in gradeLevels" :key="grade.id" :value="grade.id">{{ grade.name }}</option>
                        </select>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="assignmentForm.accepts_private" type="checkbox" />
                            إتاحة حصص برايفيت
                        </label>
                        <div>
                            <label class="text-[11px] text-surface-500">سعر البرايفيت الشهري (ر.ق)</label>
                            <input v-model.number="assignmentForm.private_monthly_price_qar" type="number" min="0" step="0.01" class="input w-full" :disabled="!assignmentForm.accepts_private" />
                        </div>
                        <button type="submit" class="btn-primary" :disabled="assignmentForm.processing">إسناد بواسطة الإدارة</button>
                    </form>

                    <p v-if="Object.keys(assignmentForm.errors).length" class="text-xs text-red-500">
                        {{ Object.values(assignmentForm.errors)[0] }}
                    </p>

                    <div class="overflow-x-auto no-scrollbar">
                        <table class="data-table">
                            <thead class="text-xs text-surface-500">
                                <tr>
                                    <th class="p-3 text-start">المعلم</th>
                                    <th class="p-3 text-start">المادة والصف</th>
                                    <th class="p-3 text-start">البرايفيت</th>
                                    <th class="p-3 text-start">الحالة</th>
                                    <th class="data-table-actions p-3 text-start">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                                <tr v-for="assignment in paginatedAssignments" :key="assignment.id">
                                    <td class="p-3 font-bold">{{ assignment.teacher?.name }}</td>
                                    <td class="p-3 text-xs">{{ assignment.subject?.name }} — {{ assignment.grade?.name }}</td>
                                    <td class="p-3 text-xs">
                                        {{ assignment.accepts_private ? formatQAR(assignment.private_monthly_price) : 'غير متاح' }}
                                    </td>
                                    <td class="p-3"><span :class="assignment.is_active ? 'badge-green' : 'badge-gray'">{{ assignment.is_active ? 'نشط' : 'متوقف' }}</span></td>
                                    <td class="data-table-actions p-3"><button type="button" class="btn-ghost btn-sm" @click="editAssignment(assignment)">ضبط</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <DataTablePagination :paginator="assignmentsPagination" item-label="إسناد" @page-change="setAssignmentsPage" />

                    <form v-if="assignmentEdit.id" class="rounded-xl border border-primary-500/20 p-4 grid md:grid-cols-4 gap-3 items-end" @submit.prevent="updateAssignment">
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="assignmentEdit.accepts_private" type="checkbox" />
                            البرايفيت متاح
                        </label>
                        <div>
                            <label class="text-[11px] text-surface-500">السعر الشهري (ر.ق)</label>
                            <input v-model.number="assignmentEdit.private_monthly_price_qar" type="number" min="0" step="0.01" class="input w-full" />
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="assignmentEdit.is_active" type="checkbox" />
                            الإسناد نشط
                        </label>
                        <button type="submit" class="btn-primary">حفظ إعدادات الإسناد</button>
                    </form>
                </div>

                <div v-else-if="managementTab === 'groups'" class="p-5 space-y-6">
                    <form class="space-y-4" @submit.prevent="storeGroup">
                        <div class="grid md:grid-cols-3 gap-3">
                            <select v-model="groupForm.teaching_assignment_id" class="input" required>
                                <option value="">اختر المعلم والمادة والصف</option>
                                <option v-for="assignment in assignments.filter((item) => item.is_active)" :key="assignment.id" :value="assignment.id">
                                    {{ assignment.teacher?.name }} — {{ assignment.subject?.name }} — {{ assignment.grade?.name }}
                                </option>
                            </select>
                            <select v-model="groupForm.academic_term_id" class="input">
                                <option value="">بدون فصل محدد</option>
                                <option v-for="item in terms" :key="item.id" :value="item.id">{{ item.name }} {{ item.year_label }}</option>
                            </select>
                            <input v-model="groupForm.name" class="input" placeholder="اسم المجموعة" required />
                            <input v-model.number="groupForm.capacity" type="number" min="1" max="1000" class="input" placeholder="السعة (الافتراضي 5)" />
                            <input v-model.number="groupForm.monthly_price_qar" type="number" min="0" step="0.01" class="input" placeholder="السعر الشهري ر.ق" required />
                            <button type="submit" class="btn-primary" :disabled="groupForm.processing">إنشاء وتسعير المجموعة</button>
                        </div>
                        <p class="text-xs text-surface-500">السعة الافتراضية للمجموعة 5 طلاب، ويمكن للإدارة تعديلها لكل مجموعة.</p>
                        <p v-if="Object.keys(groupForm.errors).length" class="text-xs text-red-500">{{ Object.values(groupForm.errors)[0] }}</p>
                    </form>

                    <form v-if="groupEdit.id" class="rounded-xl border border-primary-500/20 p-4 grid md:grid-cols-3 gap-3 items-end" @submit.prevent="updateGroup">
                        <input v-model="groupEdit.name" class="input" placeholder="اسم المجموعة" required />
                        <input v-model.number="groupEdit.capacity" type="number" min="1" class="input" placeholder="السعة" required />
                        <input v-model.number="groupEdit.monthly_price_qar" type="number" min="0" step="0.01" class="input" placeholder="السعر الشهري ر.ق" required />
                        <select v-model="groupEdit.academic_term_id" class="input">
                            <option value="">بدون فصل محدد</option>
                            <option v-for="item in terms" :key="item.id" :value="item.id">{{ item.name }} {{ item.year_label }}</option>
                        </select>
                        <label class="flex items-center gap-2 text-sm"><input v-model="groupEdit.is_active" type="checkbox" /> المجموعة مفعّلة</label>
                        <button type="submit" class="btn-primary">حفظ تعديلات الإدارة</button>
                    </form>
                </div>

            </section>

            <div class="card p-4 flex flex-wrap gap-3">
                <input v-model="search" type="text" class="input flex-1 min-w-[200px]" placeholder="ابحث باسم المجموعة أو المعلم..." />
                <select v-model="status" class="input w-auto">
                    <option value="">كل الحالات</option>
                    <option value="active">مفعّلة</option>
                    <option value="inactive">متوقفة</option>
                    <option value="full">مكتملة العدد</option>
                </select>
                <select v-model="term" class="input w-auto">
                    <option value="">كل الفصول</option>
                    <option v-for="item in terms" :key="item.id" :value="item.id">{{ item.name }} {{ item.year_label }}</option>
                </select>
            </div>

            <div class="card data-table-card">
                <div class="data-table-scroll no-scrollbar">
                    <table class="data-table">
                        <thead class="bg-surface-50 dark:bg-surface-900 text-xs text-surface-500">
                            <tr>
                                <th class="px-4 py-3 text-start">المجموعة</th>
                                <th class="px-4 py-3 text-start">المعلم</th>
                                <th class="px-4 py-3 text-start">الموعد</th>
                                <th class="px-4 py-3 text-start">السعر</th>
                                <th class="px-4 py-3 text-start">الطلاب</th>
                                <th class="px-4 py-3 text-start">الحالة</th>
                                <th class="data-table-actions px-4 py-3 text-start">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                            <tr v-for="group in groups.data" :key="group.id">
                                <td class="px-4 py-3">
                                    <Link :href="route('admin.teaching-groups.show', group.id)" class="font-bold text-xs hover:text-primary-600">{{ group.name }}</Link>
                                    <div class="text-[10px] text-surface-400">{{ group.subject }} · {{ group.grade }}</div>
                                </td>
                                <td class="px-4 py-3 text-xs">{{ group.teacher?.name }}</td>
                                <td class="px-4 py-3 text-[11px] text-surface-500">{{ group.schedule || '—' }}</td>
                                <td class="px-4 py-3 font-bold text-xs">{{ formatQAR(group.monthly_price) }}</td>
                                <td class="px-4 py-3 text-xs">{{ group.students_count }} / {{ group.capacity }}</td>
                                <td class="px-4 py-3">
                                    <span v-if="!group.is_active" class="badge-gray">متوقفة</span>
                                    <span v-else-if="group.is_full" class="badge-accent">مكتملة</span>
                                    <span v-else class="badge-green">مفعّلة</span>
                                </td>
                                <td class="data-table-actions px-4 py-3">
                                    <div class="flex gap-1">
                                        <button type="button" class="btn-ghost btn-sm" @click="editGroup(group); managementTab = 'groups'">تعديل</button>
                                        <button type="button" class="btn-ghost btn-sm" @click="toggle(group)">{{ group.is_active ? 'إيقاف' : 'تفعيل' }}</button>
                                        <button type="button" class="text-red-500 text-xs px-2" @click="destroyGroup(group)">حذف</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!groups.data?.length">
                                <td colspan="7" class="px-4 py-12 text-center text-surface-400">لا توجد مجموعات مطابقة.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <DataTablePagination :paginator="groups" item-label="مجموعة" />
            </div>
        </div>
    </DashboardLayout>
</template>

