<script setup>
import { ref } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    course:      { type: Object, required: true },
    worksheets:  { type: Array, required: true },
    lessons:     { type: Array, required: true },
    submissions: { type: Array, required: true },
});

const isModalOpen = ref(false);

const form = useForm({
    title: '',
    lesson_id: '',
    file: null,
    type: 'study', // study | homework
    requires_submission: false,
    due_date: '',
    max_score: '100',
});

const gradeForm = useForm({
    score: '',
    teacher_feedback: '',
});

const activeTab = ref('sheets'); // sheets | submissions

function openAddModal() {
    form.reset();
    isModalOpen.value = true;
}

function handleFileUpload(e) {
    form.file = e.target.files[0];
}

function submitWorksheet() {
    form.post(route('teacher.worksheets.store', { id: props.course.id }), {
        onSuccess: () => {
            isModalOpen.value = false;
            form.reset();
        }
    });
}

function deleteWorksheet(id) {
    if (confirm('هل أنت متأكد من حذف هذا الشيت؟')) {
        router.delete(route('teacher.worksheets.destroy', { id }));
    }
}

function submitGrade(subId, maxScore) {
    const scoreStr = prompt(`أدخل الدرجة (من 0 إلى ${maxScore}):`);
    if (scoreStr === null) return;
    
    const score = parseInt(scoreStr);
    if (isNaN(score) || score < 0 || score > maxScore) {
        alert('الرجاء إدخال درجة صالحة ضمن النطاق المسموح.');
        return;
    }
    
    const feedback = prompt('أضف تعليقاً للمعلم (اختياري):');
    
    gradeForm.score = score;
    gradeForm.teacher_feedback = feedback || '';
    gradeForm.post(route('teacher.worksheets.grade', { submissionId: subId }), {
        preserveScroll: true
    });
}
</script>

<template>
    <DashboardLayout>
        <Head :title="'إدارة أوراق العمل - ' + course.title" />

        <div class="container-app px-4 py-10">
            <!-- Header -->
            <div class="flex items-center justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <Icon name="courses" class="w-8 h-8 text-primary-500" />
                        <span>أوراق العمل والواجبات</span>
                    </h1>
                    <p class="text-surface-500 mt-1">كورس: {{ course.title }}</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('teacher.courses')" class="btn-ghost">← العودة للكورسات</Link>
                    <button @click="openAddModal" class="btn-primary flex items-center gap-2">
                        <Icon name="plus" class="w-4 h-4" />
                        <span>رفع شيت/واجب جديد</span>
                    </button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-surface-200 dark:border-surface-800 mb-6">
                <button @click="activeTab = 'sheets'"
                        class="px-6 py-3 font-bold text-sm border-b-2 transition-all"
                        :class="activeTab === 'sheets' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-surface-500 hover:text-surface-700'"
                >
                    الملفات المرفوعة ({{ worksheets.length }})
                </button>
                <button @click="activeTab = 'submissions'"
                        class="px-6 py-3 font-bold text-sm border-b-2 transition-all"
                        :class="activeTab === 'submissions' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-surface-500 hover:text-surface-700'"
                >
                    تسليمات الواجبات من الطلاب ({{ submissions.length }})
                </button>
            </div>

            <!-- Tab Content: Sheets List -->
            <div v-if="activeTab === 'sheets'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="sheet in worksheets" :key="sheet.id" class="card p-6 flex flex-col justify-between hover:shadow-lg transition-all duration-300">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <span :class="sheet.type === 'homework' ? 'badge-accent' : 'badge-primary'">
                                {{ sheet.type === 'homework' ? 'واجب منزلي' : 'شيت دراسي' }}
                            </span>
                            <span v-if="sheet.requires_submission" class="text-xs text-amber-600 bg-amber-50 dark:bg-amber-950/20 px-2 py-1 rounded">
                                يتطلب تسليم
                            </span>
                        </div>

                        <h3 class="text-lg font-bold text-surface-900 dark:text-white mb-2">{{ sheet.title }}</h3>
                        <p class="text-xs text-surface-400 mb-4">
                            الدرس المرتبط: {{ sheet.lesson ? sheet.lesson.title : 'عام للكورس' }}
                        </p>

                        <div class="text-xs text-surface-500 space-y-1 mb-6">
                            <div v-if="sheet.due_date">تاريخ التسليم: {{ new Date(sheet.due_date).toLocaleDateString('ar') }}</div>
                            <div v-if="sheet.max_score">الدرجة الكبرى: {{ sheet.max_score }}</div>
                        </div>
                    </div>

                    <div class="flex gap-2 border-t border-surface-100 dark:border-surface-800 pt-4">
                        <a :href="sheet.file_path" target="_blank" class="btn-outline btn-sm flex-1 flex items-center justify-center gap-1">
                            <span>تحميل الملف</span>
                        </a>
                        <button @click="deleteWorksheet(sheet.id)" class="btn-ghost btn-sm text-red-500 hover:bg-red-500/10 flex-1 flex items-center justify-center gap-1">
                            <Icon name="close" class="w-4 h-4" />
                            <span>حذف</span>
                        </button>
                    </div>
                </div>

                <div v-if="worksheets.length === 0" class="col-span-full card p-16 text-center text-surface-400">
                    <Icon name="courses" class="w-16 h-16 mx-auto text-surface-300 dark:text-surface-700 mb-4" />
                    <h3 class="text-lg font-bold text-surface-800 dark:text-surface-200 mb-2">لا توجد أوراق عمل</h3>
                    <p class="text-sm">ابدأ برفع أول شيت دراسي أو واجب منزلي لطلابك</p>
                </div>
            </div>

            <!-- Tab Content: Submissions List -->
            <div v-if="activeTab === 'submissions'" class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-surface-50 dark:bg-surface-800 border-b border-surface-200 dark:border-surface-700">
                            <tr>
                                <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">الطالب</th>
                                <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">الواجب</th>
                                <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">تاريخ التسليم</th>
                                <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">الدرجة</th>
                                <th class="text-start px-6 py-4 font-bold text-surface-700 dark:text-surface-300">التعليق والتقييم</th>
                                <th class="text-center px-6 py-4 font-bold text-surface-700 dark:text-surface-300">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                            <tr v-for="sub in submissions" :key="sub.id" class="hover:bg-surface-50/50 dark:hover:bg-surface-800/20">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-surface-900 dark:text-white">{{ sub.student?.name }}</div>
                                    <div class="text-xs text-surface-400">{{ sub.student?.email }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-surface-800 dark:text-surface-200">{{ sub.worksheet?.title }}</div>
                                    <a :href="sub.submitted_file_path" target="_blank" class="text-xs text-primary-500 hover:underline">عرض حل الطالب 🔗</a>
                                </td>
                                <td class="px-6 py-4 text-xs text-surface-500">
                                    {{ new Date(sub.submitted_at).toLocaleString('en-US') }}
                                </td>
                                <td class="px-6 py-4 font-semibold">
                                    <span v-if="sub.score !== null" class="text-green-600 dark:text-green-400">
                                        {{ sub.score }} / {{ sub.worksheet?.max_score }}
                                    </span>
                                    <span v-else class="text-amber-500">لم يصحح بعد</span>
                                </td>
                                <td class="px-6 py-4 text-xs text-surface-500 max-w-xs truncate" :title="sub.teacher_feedback">
                                    {{ sub.teacher_feedback || '—' }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <button @click="submitGrade(sub.id, sub.worksheet?.max_score)" 
                                            class="btn-primary btn-xs py-1.5 px-3 rounded-lg"
                                    >
                                        {{ sub.score !== null ? 'تعديل الدرجة' : 'رصد الدرجة' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="submissions.length === 0" class="p-16 text-center text-surface-400">
                    <Icon name="courses" class="w-16 h-16 mx-auto text-surface-300 dark:text-surface-700 mb-4" />
                    <h3 class="text-lg font-bold text-surface-800 dark:text-surface-200 mb-2">لا توجد تسليمات</h3>
                    <p class="text-sm">لم يقم الطلاب برفع أي حلول بعد</p>
                </div>
            </div>

            <!-- Upload Modal -->
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
                            <h3 class="text-xl font-black text-surface-900 dark:text-white">رفع شيت أو واجب جديد</h3>
                            <button @click="isModalOpen = false" class="btn-ghost p-1 rounded-full">
                                <Icon name="close" class="w-5 h-5 text-surface-500" />
                            </button>
                        </div>

                        <form @submit.prevent="submitWorksheet" class="space-y-4">
                            <div>
                                <label class="label mb-1">العنوان</label>
                                <input v-model="form.title" type="text" required class="input" placeholder="مثال: واجب الدرس الأول - المصفوفات" />
                            </div>

                            <div>
                                <label class="label mb-1">الدرس المرتبط (اختياري)</label>
                                <select v-model="form.lesson_id" class="input">
                                    <option value="">عام للكورس كامل</option>
                                    <option v-for="les in lessons" :key="les.id" :value="les.id">{{ les.title }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="label mb-1">نوع أوراق العمل</label>
                                <select v-model="form.type" required class="input">
                                    <option value="study">شيت دراسي/ملخص (للقراءة فقط)</option>
                                    <option value="homework">واجب منزلي (يتطلب حل من الطالب)</option>
                                </select>
                            </div>

                            <div v-if="form.type === 'homework'" class="flex items-center gap-2 pt-2">
                                <input v-model="form.requires_submission" type="checkbox" id="homework-require" class="rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                                <label for="homework-require" class="text-sm font-semibold text-surface-700 dark:text-surface-300">يتطلب من الطالب رفع ملف الحل</label>
                            </div>

                            <div v-if="form.type === 'homework' && form.requires_submission" class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="label mb-1">آخر موعد للتسليم</label>
                                    <input v-model="form.due_date" type="date" class="input" />
                                </div>
                                <div>
                                    <label class="label mb-1">الدرجة الكبرى</label>
                                    <input v-model="form.max_score" type="number" required class="input" />
                                </div>
                            </div>

                            <div>
                                <label class="label mb-1">ملف الشيت (PDF/Word/صورة)</label>
                                <input type="file" required @change="handleFileUpload" class="input py-2" />
                            </div>

                            <div class="flex gap-3 pt-4">
                                <button type="submit" :disabled="form.processing" class="btn-primary flex-1">رفع الملف</button>
                                <button type="button" @click="isModalOpen = false" class="btn-ghost flex-1">إلغاء</button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </div>
    </DashboardLayout>
</template>
