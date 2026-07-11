<script setup>
import { ref } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    gradeLevels: { type: Array, required: true },
});

const isModalOpen = ref(false);
const editingGradeLevel = ref(null);

const form = useForm({
    key: '',
    name: '',
    name_en: '',
    is_active: true,
});

function openAddModal() {
    editingGradeLevel.value = null;
    form.reset();
    form.is_active = true;
    isModalOpen.value = true;
}

function openEditModal(gradeLevel) {
    editingGradeLevel.value = gradeLevel;
    form.key = gradeLevel.key;
    form.name = gradeLevel.name;
    form.name_en = gradeLevel.name_en || '';
    form.is_active = gradeLevel.is_active ? true : false;
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

function deleteGradeLevel(id) {
    if (confirm('هل أنت متأكد من حذف هذه المرحلة الدراسية؟')) {
        router.delete(route('admin.grade-levels.destroy', { id }));
    }
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

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="gl in gradeLevels" :key="gl.id" 
                     class="card p-6 flex flex-col justify-between hover:-translate-y-1.5 hover:border-accent-500/40 hover:shadow-glow-accent/15 transition-all duration-300 transform border border-surface-100 dark:border-surface-800"
                >
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <!-- Code badge and Status -->
                            <span class="bg-accent-500/10 text-accent-600 dark:text-accent-400 font-bold px-3 py-1 rounded-xl text-xs">
                                {{ gl.key }}
                            </span>
                            <span :class="gl.is_active ? 'badge-green' : 'badge-gray'">
                                {{ gl.is_active ? 'نشط' : 'غير نشط' }}
                            </span>
                        </div>

                        <h3 class="text-lg font-bold text-surface-900 dark:text-white mb-1">
                             {{ gl.name }}
                        </h3>
                        <p class="text-sm text-surface-400 mb-4">{{ gl.name_en || 'لا يوجد اسم إنجليزي' }}</p>

                        <!-- Stats Counters -->
                        <div class="grid grid-cols-2 gap-2 mb-6">
                            <div class="bg-surface-50 dark:bg-surface-900/50 p-2.5 rounded-xl text-center border border-surface-100 dark:border-surface-800/40">
                                <span class="block text-lg font-black text-accent-600 dark:text-accent-400">{{ gl.subjects_count }}</span>
                                <span class="text-[10px] text-surface-500">المواد الدراسية</span>
                            </div>
                            <div class="bg-surface-50 dark:bg-surface-900/50 p-2.5 rounded-xl text-center border border-surface-100 dark:border-surface-800/40">
                                <span class="block text-lg font-black text-accent-600 dark:text-accent-400">{{ gl.courses_count }}</span>
                                <span class="text-[10px] text-surface-500">الكورسات</span>
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
            <div v-if="isModalOpen" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
                <div class="bg-white dark:bg-surface-900 rounded-3xl w-full max-w-md shadow-2xl overflow-hidden border border-surface-200 dark:border-surface-800">
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
