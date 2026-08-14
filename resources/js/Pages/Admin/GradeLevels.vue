<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    gradeLevels: { type: Array, required: true },
});

const isModalOpen = ref(false);
const editingGradeLevel = ref(null);
const selectedStage = ref('all_stages');
const selectedTrack = ref('all_tracks');
const { confirm } = useConfirm();

const TRACK_LABELS = {
    science:    'المسار العلمي',
    arts:       'مسار الآداب والإنسانيات',
    technology: 'المسار التكنولوجي',
};

const TRACK_COLORS = {
    science:    'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    arts:       'bg-purple-500/10 text-purple-600 dark:text-purple-400',
    technology: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
};

const form = useForm({
    key: '',
    name: '',
    name_en: '',
    stage: 'secondary',
    track: '',
    is_active: true,
    vodafone_cash_number: '',
});

const showTrackFilter = computed(() => selectedStage.value === 'secondary');

const filteredGradeLevels = computed(() => {
    let list = props.gradeLevels;
    if (selectedStage.value !== 'all_stages') {
        list = list.filter(gl => gl.stage === selectedStage.value);
    }
    if (selectedStage.value === 'secondary' && selectedTrack.value !== 'all_tracks') {
        list = list.filter(gl => gl.track === selectedTrack.value);
    }
    return list;
});

function getStageLabel(stage) {
    const labels = {
        primary: 'ابتدائية',
        preparatory: 'إعدادية',
        secondary: 'ثانوية',
        all: 'عام',
    };
    return labels[stage] || stage;
}

function openAddModal() {
    editingGradeLevel.value = null;
    form.reset();
    form.stage = 'secondary';
    form.track = '';
    form.is_active = true;
    isModalOpen.value = true;
}

function openEditModal(gradeLevel) {
    editingGradeLevel.value = gradeLevel;
    form.key = gradeLevel.key;
    form.name = gradeLevel.name;
    form.name_en = gradeLevel.name_en || '';
    form.stage = gradeLevel.stage || 'secondary';
    form.track = gradeLevel.track || '';
    form.is_active = gradeLevel.is_active ? true : false;
    form.vodafone_cash_number = gradeLevel.vodafone_cash_number || '';
    isModalOpen.value = true;
}

function submitForm() {
    if (editingGradeLevel.value) {
        form.put(route('admin.grade-levels.update', { id: editingGradeLevel.value.id }), {
            onSuccess: () => {
                isModalOpen.value = false;
                form.reset();
            }
        });
    } else {
        form.post(route('admin.grade-levels.store'), {
            onSuccess: () => {
                isModalOpen.value = false;
                form.reset();
            }
        });
    }
}

async function deleteGradeLevel(id) {
    const ok = await confirm({
        title: 'حذف المرحلة الدراسية',
        message: 'سيتم حذف هذه المرحلة الدراسية نهائياً.',
        confirmLabel: 'حذف',
        variant: 'danger',
    });
    if (ok) router.delete(route('admin.grade-levels.destroy', { id }));
}
</script>

<template>
    <DashboardLayout>
        <Head title="إدارة المراحل الدراسية" />

        <div class="container-app px-4 py-10">
            <!-- Header -->
            <div class="flex items-center justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <Icon name="courses" class="w-8 h-8 text-accent-500" />
                        <span>المراحل الدراسية</span>
                    </h1>
                    <p class="text-surface-500 mt-1">تحديد وتنظيم المراحل والصفوف الدراسية وربطها بالطلاب والمعلمين والمواد</p>
                </div>
                <button @click="openAddModal" class="btn-primary flex items-center gap-2">
                    <Icon name="plus" class="w-4 h-4" />
                    <span>إضافة مرحلة جديدة</span>
                </button>
            </div>

            <!-- Filter Tabs -->
            <div class="mb-6">
                <div class="flex flex-wrap gap-2 mb-3">
                    <button 
                        v-for="stage in [
                            { key: 'all_stages', label: 'كل المراحل' },
                            { key: 'primary', label: 'المرحلة الابتدائية' },
                            { key: 'preparatory', label: 'المرحلة الإعدادية' },
                            { key: 'secondary', label: 'المرحلة الثانوية' },
                            { key: 'all', label: 'عام / غير مصنف' }
                        ]"
                        :key="stage.key"
                        @click="selectedStage = stage.key; selectedTrack = 'all_tracks'"
                        class="btn btn-sm px-4 py-2 border transition-all"
                        :class="selectedStage === stage.key 
                            ? 'bg-accent-500 text-white border-accent-500 hover:bg-accent-600 shadow-glow-accent/25 font-bold' 
                            : 'bg-white dark:bg-surface-800 text-surface-600 dark:text-surface-300 border-surface-200 dark:border-surface-700/80 hover:bg-surface-50 dark:hover:bg-surface-750'"
                    >
                        {{ stage.label }}
                    </button>
                </div>

                <!-- Track sub-filter (only for secondary) -->
                <Transition
                    enter-active-class="transition-all duration-300 ease-out"
                    enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition-all duration-200"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div v-if="showTrackFilter" class="flex flex-wrap gap-2 pt-2 border-t border-surface-100 dark:border-surface-800">
                        <span class="text-xs text-surface-400 font-semibold self-center ms-1">المسار:</span>
                        <button
                            v-for="track in [
                                { key: 'all_tracks', label: 'كل المسارات', color: '' },
                                { key: 'science',    label: '🔬 العلمي',           color: 'text-blue-600 dark:text-blue-400' },
                                { key: 'arts',       label: '📚 الآداب والإنسانيات', color: 'text-purple-600 dark:text-purple-400' },
                                { key: 'technology', label: '💻 التكنولوجي',       color: 'text-emerald-600 dark:text-emerald-400' },
                            ]"
                            :key="track.key"
                            @click="selectedTrack = track.key"
                            class="btn btn-sm px-3 py-1.5 border text-xs transition-all"
                            :class="selectedTrack === track.key
                                ? 'bg-primary-600 text-white border-primary-600 font-bold'
                                : `bg-white dark:bg-surface-800 border-surface-200 dark:border-surface-700 hover:bg-surface-50 ${track.color}`"
                        >
                            {{ track.label }}
                        </button>
                    </div>
                </Transition>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="gl in filteredGradeLevels" :key="gl.id" 
                     class="card p-6 flex flex-col justify-between hover:-translate-y-1.5 hover:border-accent-500/40 hover:shadow-glow-accent/15 transition-all duration-300 transform border border-surface-100 dark:border-surface-800"
                >
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-4">
                            <!-- Code badge, Stage, Track -->
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="bg-accent-500/10 text-accent-600 dark:text-accent-400 font-bold px-2.5 py-0.5 rounded-lg text-xs">
                                    {{ gl.key }}
                                </span>
                                <span class="bg-primary-500/10 text-primary-600 dark:text-primary-400 font-semibold px-2.5 py-0.5 rounded-lg text-xs">
                                    {{ getStageLabel(gl.stage) }}
                                </span>
                                <span v-if="gl.track" :class="TRACK_COLORS[gl.track]" class="font-semibold px-2.5 py-0.5 rounded-lg text-xs">
                                    {{ TRACK_LABELS[gl.track] }}
                                </span>
                            </div>
                            <span :class="gl.is_active ? 'badge-green' : 'badge-gray'" class="ms-auto">
                                {{ gl.is_active ? 'نشط' : 'غير نشط' }}
                            </span>
                        </div>

                        <h3 class="text-lg font-bold text-surface-900 dark:text-white mb-1">
                             {{ gl.name }}
                        </h3>
                        <p class="text-sm text-surface-400 mb-4">{{ gl.name_en || 'لا يوجد اسم إنجليزي' }}</p>

                        <div class="mb-4 rounded-xl border border-primary-100 bg-primary-50/60 px-3 py-2 dark:border-primary-900/60 dark:bg-primary-950/25">
                            <div class="flex items-center gap-1.5 text-[11px] font-bold text-primary-700 dark:text-primary-300">
                                <Icon name="payments" class="h-3.5 w-3.5" />
                                <span>فودافون كاش</span>
                            </div>
                            <p class="mt-1 font-mono text-xs text-surface-700 dark:text-surface-200" dir="ltr">
                                {{ gl.vodafone_cash_number || 'لم يتم ضبط رقم التحويل' }}
                            </p>
                        </div>

                        <!-- Stats Counters -->
                        <div class="grid grid-cols-2 gap-2 mb-6">
                            <div class="bg-surface-50 dark:bg-surface-900/50 p-2.5 rounded-xl text-center border border-surface-100 dark:border-surface-800/40">
                                <span class="block text-lg font-black text-accent-600 dark:text-accent-400">{{ gl.subjects_count }}</span>
                                <span class="text-[10px] text-surface-500">المواد الدراسية</span>
                            </div>
                            <div class="bg-surface-50 dark:bg-surface-900/50 p-2.5 rounded-xl text-center border border-surface-100 dark:border-surface-800/40">
                                <span class="block text-lg font-black text-accent-600 dark:text-accent-400">{{ gl.groups_count }}</span>
                                <span class="text-[10px] text-surface-500">المجموعات</span>
                            </div>
                            <div class="bg-surface-50 dark:bg-surface-900/50 p-2.5 rounded-xl text-center border border-surface-100 dark:border-surface-800/40">
                                <span class="block text-lg font-black text-accent-600 dark:text-accent-400">{{ gl.students_count }}</span>
                                <span class="text-[10px] text-surface-500">الطلاب</span>
                            </div>
                            <div class="bg-surface-50 dark:bg-surface-900/50 p-2.5 rounded-xl text-center border border-surface-100 dark:border-surface-800/40">
                                <span class="block text-lg font-black text-accent-600 dark:text-accent-400">{{ gl.teachers_count }}</span>
                                <span class="text-[10px] text-surface-500">المعلمون</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 border-t border-surface-100 dark:border-surface-800 pt-4">
                        <Link :href="route('admin.grade-levels.show', { id: gl.id })" 
                              class="btn-outline btn-sm w-full flex items-center justify-center gap-1.5 bg-accent-500/5 border-accent-500/20 text-accent-600 dark:text-accent-400 hover:bg-accent-500/10"
                        >
                            <Icon name="live" class="w-4 h-4" />
                            <span>عرض الإحصائيات والتفاصيل المربوطة</span>
                        </Link>
                        <div class="flex gap-2">
                            <button @click="openEditModal(gl)" class="btn-outline btn-sm flex-1 flex items-center justify-center gap-1">
                                <Icon name="edit" class="w-4 h-4" />
                                <span>تعديل</span>
                            </button>
                            <button @click="deleteGradeLevel(gl.id)" class="btn-ghost btn-sm text-red-500 hover:bg-red-500/10 flex-1 flex items-center justify-center gap-1">
                                <Icon name="close" class="w-4 h-4" />
                                <span>حذف</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="gradeLevels.length === 0" class="card p-12 text-center max-w-lg mx-auto">
                <Icon name="courses" class="w-16 h-16 text-surface-300 mx-auto mb-4" />
                <h3 class="text-xl font-bold text-surface-800 dark:text-white mb-2">لا توجد مراحل دراسية بعد</h3>
                <p class="text-surface-500 mb-6">ابدأ بإضافة أول مرحلة دراسية للمنصة لربط المواد والطلاب بها.</p>
                <button @click="openAddModal" class="btn-primary">إضافة مرحلة دراسية</button>
            </div>

            <!-- Modal Add/Edit -->
            <div v-if="isModalOpen" class="modal-overlay bg-black/50 z-50 backdrop-blur-sm">
                <div class="modal-panel-compact bg-white dark:bg-surface-900 rounded-3xl w-full max-w-md shadow-2xl border border-surface-200 dark:border-surface-800">
                    <div class="p-6 flex items-center justify-between border-b border-surface-100 dark:border-surface-800">
                        <h2 class="text-xl font-black text-surface-900 dark:text-white">
                            {{ editingGradeLevel ? 'تعديل المرحلة الدراسية' : 'إضافة مرحلة جديدة' }}
                        </h2>
                        <button @click="isModalOpen = false" class="btn-ghost p-1 rounded-full text-surface-500 hover:bg-surface-100 dark:hover:bg-surface-800">
                            <Icon name="close" class="w-5 h-5" />
                        </button>
                    </div>

                    <form @submit.prevent="submitForm" class="p-6 space-y-4">
                        <div>
                            <label class="input-label mb-1">رمز المرحلة (بالإنجليزية - بدون مسافات)</label>
                            <input v-model="form.key" type="text" required class="input" placeholder="مثال: grade_10" :disabled="!!editingGradeLevel" />
                            <p class="text-[10px] text-surface-400 mt-1">يجب أن يكون فريداً مثل grade_10 ويستخدم كرمز بروتوكولي للمرحلة.</p>
                            <p v-if="form.errors.key" class="text-red-500 text-xs mt-1">{{ form.errors.key }}</p>
                        </div>

                        <div>
                            <label class="input-label mb-1">اسم المرحلة (بالعربية)</label>
                            <input v-model="form.name" type="text" required class="input" placeholder="مثال: الصف العاشر" />
                            <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="input-label mb-1">اسم المرحلة (بالإنجليزية - اختياري)</label>
                            <input v-model="form.name_en" type="text" class="input" placeholder="مثال: Grade 10" />
                            <p v-if="form.errors.name_en" class="text-red-500 text-xs mt-1">{{ form.errors.name_en }}</p>
                        </div>

                        <div>
                            <label class="input-label mb-1">رقم فودافون كاش لاستلام تحويلات هذه المرحلة</label>
                            <input v-model="form.vodafone_cash_number" type="tel" inputmode="tel" class="input font-mono" dir="ltr" placeholder="01012345678" />
                            <p class="text-[10px] text-surface-400 mt-1">يظهر هذا الرقم فقط للطلاب أو أولياء الأمور المشتركين في هذه المرحلة.</p>
                            <p v-if="form.errors.vodafone_cash_number" class="text-red-500 text-xs mt-1">{{ form.errors.vodafone_cash_number }}</p>
                        </div>

                        <div>
                            <label class="input-label mb-1">المرحلة التعليمية العامة</label>
                            <select v-model="form.stage" required class="input">
                                <option value="primary">المرحلة الابتدائية (الصفوف 1 - 6)</option>
                                <option value="preparatory">المرحلة الإعدادية (الصفوف 7 - 9)</option>
                                <option value="secondary">المرحلة الثانوية (الصفوف 10 - 12)</option>
                                <option value="all">عام / كل المراحل</option>
                            </select>
                            <p v-if="form.errors.stage" class="text-red-500 text-xs mt-1">{{ form.errors.stage }}</p>
                        </div>

                        <!-- Track field (secondary only) -->
                        <Transition
                            enter-active-class="transition-all duration-300 ease-out"
                            enter-from-class="opacity-0 -translate-y-2"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="transition-all duration-150"
                            leave-from-class="opacity-100"
                            leave-to-class="opacity-0"
                        >
                            <div v-if="form.stage === 'secondary'">
                                <label class="input-label mb-1">المسار الدراسي (للمرحلة الثانوية)</label>
                                <select v-model="form.track" class="input">
                                    <option value="">بدون مسار (الصف العاشر المشترك)</option>
                                    <option value="science">المسار العلمي</option>
                                    <option value="arts">مسار الآداب والإنسانيات</option>
                                    <option value="technology">المسار التكنولوجي</option>
                                </select>
                                <p v-if="form.errors.track" class="text-red-500 text-xs mt-1">{{ form.errors.track }}</p>
                            </div>
                        </Transition>

                        <div v-if="editingGradeLevel" class="flex items-center gap-2 pt-2">
                            <input v-model="form.is_active" type="checkbox" id="gl-active" class="rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                            <label for="gl-active" class="text-sm font-semibold text-surface-700 dark:text-surface-300">مرحلة نشطة ومتاحة للاختيار</label>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="button" @click="isModalOpen = false" class="btn-outline flex-1">
                                إلغاء
                            </button>
                            <button type="submit" :disabled="form.processing" class="btn-primary flex-1">
                                {{ editingGradeLevel ? 'حفظ التعديلات' : 'إضافة المرحلة' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
