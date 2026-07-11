<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    subjects: { type: Array, required: true },
});

const isModalOpen = ref(false);
const editingSubject = ref(null);

const form = useForm({
    name: '',
    name_en: '',
    grade_level: 'all',
    icon: 'book',
    is_active: true,
});

import { usePage } from '@inertiajs/vue3';

const page = usePage();

function getGradeLabel(key) {
    const gl = page.props.grade_levels?.find(item => item.key === key);
    return gl ? gl.name : key;
}

const iconOptions = [
    { value: 'calculator', label: 'رياضيات 📐' },
    { value: 'atom', label: 'فيزياء ⚛️' },
    { value: 'flask', label: 'كيمياء 🧪' },
    { value: 'dna', label: 'أحياء 🧬' },
    { value: 'landmark', label: 'تاريخ/اجتماعيات 🏛️' },
    { value: 'globe', label: 'جغرافيا 🌍' },
    { value: 'book', label: 'كتاب عام 📖' },
    { value: 'language', label: 'لغات 🗣️' },
];

function openAddModal() {
    editingSubject.value = null;
    form.reset();
    form.is_active = true;
    isModalOpen.value = true;
}

function openEditModal(subject) {
    editingSubject.value = subject;
    form.name = subject.name;
    form.name_en = subject.name_en || '';
    form.grade_level = subject.grade_level;
    form.icon = subject.icon || 'book';
    form.is_active = subject.is_active ? true : false;
    isModalOpen.value = true;
}

function submitForm() {
    if (editingSubject.value) {
        form.put(route('admin.subjects.update', { id: editingSubject.value.id }), {
            onSuccess: () => {
                isModalOpen.value = false;
                form.reset();
            }
        });
    } else {
        form.post(route('admin.subjects.store'), {
            onSuccess: () => {
                isModalOpen.value = false;
                form.reset();
            }
        });
    }
}

function deleteSubject(id) {
    if (confirm('هل أنت متأكد من حذف هذه المادة الدراسية؟')) {
        router.delete(route('admin.subjects.destroy', { id }));
    }
}
</script>

<template>
    <DashboardLayout>
        <Head title="إدارة المواد الدراسية" />

        <div class="container-app px-4 py-10">
            <!-- Header -->
            <div class="flex items-center justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <Icon name="courses" class="w-8 h-8 text-primary-500" />
                        <span>المواد الدراسية</span>
                    </h1>
                    <p class="text-surface-500 mt-1">تحديد وتنظيم المواد الدراسية والصفوف المتاحة</p>
                </div>
                <button @click="openAddModal" class="btn-primary flex items-center gap-2">
                    <Icon name="plus" class="w-4 h-4" />
                    <span>إضافة مادة جديدة</span>
                </button>
            </div>

            <!-- Subject Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="subj in subjects" :key="subj.id" 
                     class="card p-6 flex flex-col justify-between hover:shadow-lg transition-all duration-300"
                >
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <!-- Icon and Status -->
                            <div class="w-12 h-12 rounded-2xl bg-primary-500/10 dark:bg-primary-950/20 flex items-center justify-center">
                                <Icon :name="subj.icon || 'book'" class="w-6 h-6 text-primary-500" />
                            </div>
                            <span :class="subj.is_active ? 'badge-green' : 'badge-gray'">
                                {{ subj.is_active ? 'نشط' : 'غير نشط' }}
                            </span>
                        </div>

                        <h3 class="text-lg font-bold text-surface-900 dark:text-white mb-1">
                            {{ subj.name }}
                        </h3>
                        <p class="text-sm text-surface-400 mb-4">{{ subj.name_en || 'لا يوجد اسم إنجليزي' }}</p>

                        <div class="flex flex-wrap gap-2 text-xs text-surface-500 mb-6">
                            <span class="bg-surface-100 dark:bg-surface-800 px-2.5 py-1 rounded-lg">
                                {{ getGradeLabel(subj.grade_level) }}
                            </span>
                            <span class="bg-surface-100 dark:bg-surface-800 px-2.5 py-1 rounded-lg">
                                {{ subj.courses_count }} كورس
                            </span>
                        </div>
                    </div>

                    <div class="flex gap-2 border-t border-surface-100 dark:border-surface-800 pt-4">
                        <button @click="openEditModal(subj)" class="btn-outline btn-sm flex-1 flex items-center justify-center gap-1">
                            <Icon name="edit" class="w-4 h-4" />
                            <span>تعديل</span>
                        </button>
                        <button @click="deleteSubject(subj.id)" class="btn-ghost btn-sm text-red-500 hover:bg-red-500/10 flex-1 flex items-center justify-center gap-1">
                            <Icon name="close" class="w-4 h-4" />
                            <span>حذف</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="subjects.length === 0" class="card p-16 text-center text-surface-400">
                <Icon name="courses" class="w-16 h-16 mx-auto text-surface-300 dark:text-surface-700 mb-4" />
                <h3 class="text-lg font-bold text-surface-800 dark:text-surface-200 mb-2">لا توجد مواد دراسية</h3>
                <p class="text-sm mb-6">ابدأ بإضافة أول مادة دراسية لتنظيم الكورسات فيها</p>
                <button @click="openAddModal" class="btn-primary">إضافة مادة</button>
            </div>

            <!-- Modal for Add/Edit -->
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
                            <h3 class="text-xl font-black text-surface-900 dark:text-white">
                                {{ editingSubject ? 'تعديل المادة الدراسية' : 'إضافة مادة دراسية جديدة' }}
                            </h3>
                            <button @click="isModalOpen = false" class="btn-ghost p-1 rounded-full">
                                <Icon name="close" class="w-5 h-5 text-surface-500" />
                            </button>
                        </div>

                        <form @submit.prevent="submitForm" class="space-y-4">
                            <div>
                                <label class="label mb-1">اسم المادة (عربي)</label>
                                <input v-model="form.name" type="text" required class="input" placeholder="مثال: فيزياء" />
                            </div>

                            <div>
                                <label class="label mb-1">اسم المادة (إنجليزي)</label>
                                <input v-model="form.name_en" type="text" class="input" placeholder="مثال: Physics" />
                            </div>

                            <div>
                                <label class="label mb-1">الصف الدراسي</label>
                                <select v-model="form.grade_level" required class="input">
                                    <option v-for="gl in $page.props.grade_levels" :key="gl.key" :value="gl.key">
                                        {{ gl.name }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="label mb-1">الأيقونة المناسبة</label>
                                <select v-model="form.icon" required class="input">
                                    <option v-for="opt in iconOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </div>

                            <div v-if="editingSubject" class="flex items-center gap-2 pt-2">
                                <input v-model="form.is_active" type="checkbox" id="subject-active" class="rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                                <label for="subject-active" class="text-sm font-semibold text-surface-700 dark:text-surface-300">مادة نشطة ومتاحة للاختيار</label>
                            </div>

                            <div class="flex gap-3 pt-4">
                                <button type="submit" :disabled="form.processing" class="btn-primary flex-1">
                                    {{ form.processing ? 'جاري الحفظ...' : 'حفظ' }}
                                </button>
                                <button type="button" @click="isModalOpen = false" class="btn-ghost flex-1">
                                    إلغاء
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </div>
    </DashboardLayout>
</template>
