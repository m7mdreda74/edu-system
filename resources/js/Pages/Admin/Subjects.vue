<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    subjects:    { type: Array, default: () => [] },
    gradeLevels: { type: Array, default: () => [] },
});

const ICONS = ['book', 'language', 'globe', 'calculator', 'atom', 'flask', 'dna', 'landmark', 'student', 'users', 'chart', 'settings', 'video'];

const showForm = ref(false);
const editingId = ref(null);

const form = useForm({
    name: '',
    name_en: '',
    icon: 'book',
    is_active: true,
    grade_level_ids: [],
});

// Grades grouped by stage so ticking "the whole preparatory stage" is one move.
const stageGroups = computed(() => {
    const labels = { primary: 'المرحلة الابتدائية', preparatory: 'المرحلة الإعدادية', secondary: 'المرحلة الثانوية' };
    const order = ['primary', 'preparatory', 'secondary'];
    const map = new Map();

    for (const grade of props.gradeLevels) {
        if (!map.has(grade.stage)) {
            map.set(grade.stage, { stage: grade.stage, label: labels[grade.stage] ?? grade.stage, grades: [] });
        }
        map.get(grade.stage).grades.push(grade);
    }

    return [...map.values()].sort((a, b) => order.indexOf(a.stage) - order.indexOf(b.stage));
});

function isStageFullySelected(group) {
    return group.grades.every((g) => form.grade_level_ids.includes(g.id));
}

function toggleStage(group) {
    const ids = group.grades.map((g) => g.id);

    form.grade_level_ids = isStageFullySelected(group)
        ? form.grade_level_ids.filter((id) => !ids.includes(id))
        : [...new Set([...form.grade_level_ids, ...ids])];
}

function startCreate() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function startEdit(subject) {
    editingId.value = subject.id;
    form.clearErrors();
    form.name = subject.name;
    form.name_en = subject.name_en ?? '';
    form.icon = subject.icon ?? 'book';
    form.is_active = subject.is_active;
    form.grade_level_ids = (subject.grade_levels ?? []).map((g) => g.id);
    showForm.value = true;
}

function submit() {
    const options = {
        onSuccess: () => {
            showForm.value = false;
            form.reset();
        },
    };

    editingId.value
        ? form.put(route('admin.subjects.update', { id: editingId.value }), options)
        : form.post(route('admin.subjects.store'), options);
}

function destroy(id) {
    if (confirm('حذف هذه المادة؟')) {
        router.delete(route('admin.subjects.destroy', { id }));
    }
}
</script>

<template>
    <Head title="المواد الدراسية" />

    <DashboardLayout>
        <div class="space-y-6">
            <header class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white">المواد الدراسية</h1>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                        منهج دولة قطر — كل مادة مربوطة بالصفوف التي تُدرّس فيها
                    </p>
                </div>

                <button type="button" class="btn-primary btn-sm" @click="startCreate">
                    <Icon name="plus" class="w-4 h-4" />
                    <span class="ms-1">إضافة مادة</span>
                </button>
            </header>

            <!-- Form -->
            <form v-if="showForm" class="card p-5 space-y-4" @submit.prevent="submit">
                <h2 class="font-bold text-sm text-surface-900 dark:text-white">
                    {{ editingId ? 'تعديل المادة' : 'مادة جديدة' }}
                </h2>

                <div class="grid sm:grid-cols-3 gap-4">
                    <div>
                        <label for="name" class="input-label">الاسم بالعربية</label>
                        <input id="name" v-model="form.name" type="text" class="input" required />
                        <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label for="name_en" class="input-label">الاسم بالإنجليزية</label>
                        <input id="name_en" v-model="form.name_en" type="text" dir="ltr" class="input" />
                    </div>

                    <div>
                        <label for="icon" class="input-label">الأيقونة</label>
                        <select id="icon" v-model="form.icon" class="input">
                            <option v-for="icon in ICONS" :key="icon" :value="icon">{{ icon }}</option>
                        </select>
                    </div>
                </div>

                <!-- Curriculum picker -->
                <div>
                    <span class="input-label">الصفوف التي تُدرّس فيها</span>
                    <p v-if="form.errors.grade_level_ids" class="text-xs text-red-500 mb-2">{{ form.errors.grade_level_ids }}</p>

                    <div class="space-y-3">
                        <div v-for="group in stageGroups" :key="group.stage" class="rounded-xl border border-surface-200 dark:border-surface-700 p-3">
                            <label class="flex items-center gap-2 mb-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    class="rounded"
                                    :checked="isStageFullySelected(group)"
                                    @change="toggleStage(group)"
                                />
                                <span class="text-xs font-bold text-surface-800 dark:text-surface-100">{{ group.label }}</span>
                            </label>

                            <div class="flex flex-wrap gap-2">
                                <label
                                    v-for="grade in group.grades"
                                    :key="grade.id"
                                    class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border text-[11px] cursor-pointer transition-colors"
                                    :class="form.grade_level_ids.includes(grade.id)
                                        ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-300'
                                        : 'border-surface-200 dark:border-surface-700 text-surface-500'"
                                >
                                    <input v-model="form.grade_level_ids" type="checkbox" :value="grade.id" class="hidden" />
                                    <span>{{ grade.name }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-surface-600 dark:text-surface-300">
                    <input v-model="form.is_active" type="checkbox" class="rounded" />
                    <span>مفعّلة</span>
                </label>

                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-ghost btn-sm" @click="showForm = false">إلغاء</button>
                    <button type="submit" class="btn-primary btn-sm" :disabled="form.processing">
                        {{ form.processing ? 'جارٍ الحفظ...' : 'حفظ' }}
                    </button>
                </div>
            </form>

            <!-- List -->
            <div v-if="subjects.length" class="card divide-y divide-surface-100 dark:divide-surface-800">
                <div v-for="subject in subjects" :key="subject.id" class="p-4 flex items-start gap-4">
                    <div class="w-10 h-10 rounded-xl bg-primary-50 dark:bg-primary-950 flex items-center justify-center text-primary-600 shrink-0">
                        <Icon :name="subject.icon || 'book'" class="w-5 h-5" />
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-bold text-sm text-surface-900 dark:text-white">{{ subject.name }}</h3>
                            <span v-if="!subject.is_active" class="badge-gray text-[10px]">معطّلة</span>
                            <span class="badge-primary text-[10px]">{{ subject.teaching_assignments_count }} معلم</span>
                        </div>

                        <p v-if="subject.name_en" class="text-[11px] text-surface-400 font-latin">{{ subject.name_en }}</p>

                        <div class="flex flex-wrap gap-1 mt-2">
                            <span
                                v-for="grade in subject.grade_levels"
                                :key="grade.id"
                                class="badge-gray text-[10px]"
                            >{{ grade.name }}</span>
                            <span v-if="!subject.grade_levels?.length" class="text-[11px] text-red-500">
                                غير مربوطة بأي صف
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-1 shrink-0">
                        <button type="button" class="btn-ghost btn-sm" @click="startEdit(subject)">
                            <Icon name="edit" class="w-4 h-4" />
                        </button>
                        <button type="button" class="btn-ghost btn-sm text-red-500" @click="destroy(subject.id)">
                            <Icon name="trash" class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>

            <div v-else class="card p-12 text-center">
                <Icon name="globe" class="w-10 h-10 text-surface-300 mx-auto mb-3" />
                <h3 class="font-bold text-surface-700 dark:text-surface-200 mb-1">لا توجد مواد بعد</h3>
                <p class="text-sm text-surface-400">ابدأ بإضافة أول مادة دراسية ليتم إسناد المعلمين إليها.</p>
            </div>
        </div>
    </DashboardLayout>
</template>
