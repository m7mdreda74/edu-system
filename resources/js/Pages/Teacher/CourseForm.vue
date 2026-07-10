<script setup>
import { useForm } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

const props = defineProps({
    course:   { type: Object, default: null },
    subjects: { type: Array,  required: true },
});

const isEdit = !!props.course;

const form = useForm({
    title:         props.course?.title         ?? '',
    description:   props.course?.description   ?? '',
    subject_id:    props.course?.subject_id    ?? '',
    price:         props.course ? props.course.price / 100 : 0,       // Display in QAR
    discount_price:props.course?.discount_price ? props.course.discount_price / 100 : '',
    grade_level:   props.course?.grade_level   ?? 'grade_12',
    level:         props.course?.level         ?? 'beginner',
    is_published:  props.course?.is_published  ?? false,
});

// Price must be stored as halala (×100) before submitting
function submit() {
    const submitData = {
        ...form.data(),
        price:         Math.round(form.price * 100),
        discount_price:form.discount_price ? Math.round(form.discount_price * 100) : null,
    };

    if (isEdit) {
        form.transform(() => submitData).put(route('teacher.courses.update', { id: props.course.id }));
    } else {
        form.transform(() => submitData).post(route('teacher.courses.store'));
    }
}
</script>

<template>
    <DashboardLayout>
        <Head :title="isEdit ? 'تعديل الكورس' : 'كورس جديد'" />

        <div class="container-app px-4 py-10 max-w-2xl">

            <!-- Header -->
            <div class="flex items-center gap-3 mb-8">
                <Link :href="route('teacher.courses')" class="btn-ghost p-2">←</Link>
                <div>
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white">
                        {{ isEdit ? 'تعديل الكورس' : 'إنشاء كورس جديد' }}
                    </h1>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-5">

                <!-- Title -->
                <div>
                    <label class="input-label" for="course-title">عنوان الكورس *</label>
                    <input
                        id="course-title"
                        v-model="form.title"
                        type="text"
                        class="input"
                        :class="{ 'border-red-500': form.errors.title }"
                        placeholder="مثال: رياضيات الصف الثاني عشر — المشتقات والتكامل"
                        required
                    />
                    <p v-if="form.errors.title" class="text-red-500 text-xs mt-1">{{ form.errors.title }}</p>
                </div>

                <!-- Description -->
                <div>
                    <label class="input-label" for="course-desc">وصف الكورس *</label>
                    <textarea
                        id="course-desc"
                        v-model="form.description"
                        class="input min-h-[120px]"
                        :class="{ 'border-red-500': form.errors.description }"
                        placeholder="وصف مفصّل يساعد الطالب على فهم ما سيتعلمه..."
                        required
                    ></textarea>
                    <p v-if="form.errors.description" class="text-red-500 text-xs mt-1">{{ form.errors.description }}</p>
                </div>

                <!-- Subject + Grade + Level -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="input-label" for="subject">المادة *</label>
                        <select id="subject" v-model="form.subject_id" class="input" required>
                            <option value="" disabled>اختر المادة</option>
                            <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <p v-if="form.errors.subject_id" class="text-red-500 text-xs mt-1">{{ form.errors.subject_id }}</p>
                    </div>

                    <div>
                        <label class="input-label" for="grade">الصف الدراسي *</label>
                        <select id="grade" v-model="form.grade_level" class="input" required>
                            <option value="grade_10">الصف العاشر</option>
                            <option value="grade_11">الصف الحادي عشر</option>
                            <option value="grade_12">الصف الثاني عشر</option>
                            <option value="all">كل الصفوف</option>
                        </select>
                    </div>

                    <div>
                        <label class="input-label" for="level">المستوى *</label>
                        <select id="level" v-model="form.level" class="input" required>
                            <option value="beginner">مبتدئ</option>
                            <option value="intermediate">متوسط</option>
                            <option value="advanced">متقدم</option>
                        </select>
                    </div>
                </div>

                <!-- Pricing -->
                <div class="card p-5">
                    <h3 class="font-semibold text-surface-800 dark:text-white mb-4">التسعير (بالريال القطري)</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="input-label" for="price">السعر الأصلي</label>
                            <div class="relative">
                                <input
                                    id="price"
                                    v-model="form.price"
                                    type="number" min="0" step="0.01"
                                    class="input ps-10"
                                    placeholder="0"
                                />
                                <span class="absolute start-3 top-1/2 -translate-y-1/2 text-surface-400 text-sm">ر.ق</span>
                            </div>
                            <p class="text-xs text-surface-400 mt-1">0 = مجاني</p>
                        </div>
                        <div>
                            <label class="input-label" for="discount">سعر الخصم (اختياري)</label>
                            <div class="relative">
                                <input
                                    id="discount"
                                    v-model="form.discount_price"
                                    type="number" min="0" step="0.01"
                                    class="input ps-10"
                                    placeholder="اتركه فارغاً إن لم يكن هناك خصم"
                                />
                                <span class="absolute start-3 top-1/2 -translate-y-1/2 text-surface-400 text-sm">ر.ق</span>
                            </div>
                        </div>
                    </div>
                    <p v-if="form.errors.discount_price" class="text-red-500 text-xs mt-2">{{ form.errors.discount_price }}</p>
                </div>

                <!-- Publish toggle -->
                <div class="flex items-center gap-3 p-4 card">
                    <input
                        id="is-published"
                        v-model="form.is_published"
                        type="checkbox"
                        class="w-4 h-4 text-primary-600 rounded"
                    />
                    <label for="is-published" class="cursor-pointer">
                        <div class="font-medium text-surface-800 dark:text-white">نشر الكورس</div>
                        <div class="text-xs text-surface-400">الكورس سيظهر للطلاب فور النشر</div>
                    </label>
                </div>

                <!-- Submit -->
                <div class="flex gap-3">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="btn-primary btn-lg"
                        :class="{ 'opacity-60': form.processing }"
                        id="save-course-btn"
                    >
                        <span v-if="form.processing">⏳ جاري الحفظ...</span>
                        <span v-else>{{ isEdit ? '💾 حفظ التغييرات' : '✨ إنشاء الكورس' }}</span>
                    </button>
                    <Link :href="route('teacher.courses')" class="btn-ghost">إلغاء</Link>
                </div>
            </form>
        </div>
    </DashboardLayout>
</template>
