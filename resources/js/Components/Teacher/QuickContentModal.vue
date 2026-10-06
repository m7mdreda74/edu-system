<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    assignment: { type: Object, default: null },
    terms:      { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const activeTab = ref('video'); // 'video' | 'booklet' | 'homework'
const isNewUnit = ref(false);

const form = useForm({
    unit_id: null,
    new_unit_title: '',
    title: '',
    video_url: '',
    description: '',
    is_free_preview: false,
    booklet: null,
    homework: null,
    academic_term_id: null,
});

function handleBookletUpload(e) {
    form.booklet = e.target.files[0] || null;
}

function handleHomeworkUpload(e) {
    form.homework = e.target.files[0] || null;
}

function submit() {
    if (!props.assignment?.id) return;

    form.post(route('teacher.curriculum.quick-lesson', props.assignment.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            isNewUnit.value = false;
            emit('update:modelValue', false);
            emit('saved');
        },
    });
}

function close() {
    form.reset();
    isNewUnit.value = false;
    emit('update:modelValue', false);
}
</script>

<template>
    <div
        v-if="modelValue && assignment"
        class="modal-overlay z-[80] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
        role="dialog"
        aria-modal="true"
        @click.self="close"
    >
        <div class="card w-full max-w-xl max-h-[92vh] flex flex-col p-6 sm:p-7 shadow-2xl space-y-5 animate-in fade-in zoom-in-95 duration-200">
            <!-- Header -->
            <div class="flex items-start justify-between gap-4 border-b border-surface-200 dark:border-surface-700 pb-4">
                <div>
                    <h3 class="text-xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center">
                            <Icon name="book" class="w-5 h-5" />
                        </span>
                        <span>إضافة درس ومحتوى سريع</span>
                    </h3>
                    <p class="text-xs text-surface-500 mt-1">
                        {{ assignment.subject?.name }} — {{ assignment.stage?.name }} · {{ assignment.grade?.name }}
                    </p>
                </div>
                <button type="button" class="text-surface-400 hover:text-surface-600 text-xl font-bold p-1" @click="close">✕</button>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="space-y-4 overflow-y-auto no-scrollbar">
                <!-- Unit Selection -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between text-xs">
                        <label class="font-bold text-surface-800 dark:text-surface-200">الوحدة الدراسية:</label>
                        <button
                            type="button"
                            @click="isNewUnit = !isNewUnit"
                            class="text-primary-600 dark:text-primary-400 font-bold hover:underline"
                        >
                            {{ isNewUnit ? 'اختر من الوحدات الحالية' : '+ إنشاء وحدة جديدة' }}
                        </button>
                    </div>

                    <div v-if="isNewUnit">
                        <input
                            v-model="form.new_unit_title"
                            type="text"
                            class="input text-sm"
                            placeholder="مثال: الوحدة الأولى: النهايات والاتصال"
                            required
                        />
                    </div>
                    <div v-else>
                        <select v-model="form.unit_id" class="input text-sm">
                            <option :value="null">-- اختر الوحدة (أو سيتم وضعها في الوحدة الأولى تلقائياً) --</option>
                            <option v-for="unit in assignment.units || []" :key="unit.id" :value="unit.id">
                                {{ unit.title }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Lesson Title -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-surface-800 dark:text-surface-200">عنوان الدرس:</label>
                    <input
                        v-model="form.title"
                        type="text"
                        class="input text-sm font-bold"
                        placeholder="مثال: الدرس الأول: حساب النهايات جبرياً"
                        required
                    />
                    <div v-if="form.errors.title" class="text-xs text-red-500">{{ form.errors.title }}</div>
                </div>

                <!-- Content Tabs: Video / Booklet / Homework -->
                <div class="space-y-2 pt-1">
                    <label class="block text-xs font-bold text-surface-800 dark:text-surface-200">مرفقات الدرس:</label>
                    <div class="flex rounded-xl bg-surface-100 dark:bg-surface-800 p-1 gap-1 text-xs font-bold">
                        <button
                            type="button"
                            @click="activeTab = 'video'"
                            :class="['flex-1 py-2 rounded-lg transition-all flex items-center justify-center gap-1.5', activeTab === 'video' ? 'bg-white dark:bg-surface-900 text-primary-600 shadow-sm' : 'text-surface-600 dark:text-surface-400']"
                        >
                            <Icon name="video" class="w-4 h-4" />
                            <span>فيديو الشرح</span>
                        </button>
                        <button
                            type="button"
                            @click="activeTab = 'booklet'"
                            :class="['flex-1 py-2 rounded-lg transition-all flex items-center justify-center gap-1.5', activeTab === 'booklet' ? 'bg-white dark:bg-surface-900 text-primary-600 shadow-sm' : 'text-surface-600 dark:text-surface-400']"
                        >
                            <Icon name="book" class="w-4 h-4" />
                            <span>المذكرة / الملزمة (PDF)</span>
                        </button>
                        <button
                            type="button"
                            @click="activeTab = 'homework'"
                            :class="['flex-1 py-2 rounded-lg transition-all flex items-center justify-center gap-1.5', activeTab === 'homework' ? 'bg-white dark:bg-surface-900 text-primary-600 shadow-sm' : 'text-surface-600 dark:text-surface-400']"
                        >
                            <Icon name="check" class="w-4 h-4" />
                            <span>واجب منزلي</span>
                        </button>
                    </div>

                    <!-- Video Tab -->
                    <div v-show="activeTab === 'video'" class="p-3.5 rounded-2xl bg-surface-50 dark:bg-surface-900/60 border border-surface-200 dark:border-surface-800 space-y-2">
                        <label class="block text-xs font-bold text-surface-700 dark:text-surface-300">رابط الفيديو (يوتيوب أو رابط مباشر):</label>
                        <input
                            v-model="form.video_url"
                            type="url"
                            class="input text-xs"
                            placeholder="https://www.youtube.com/watch?v=..."
                        />
                        <p class="text-[11px] text-surface-400">يمكنك وضع رابط الشرح الآن أو إضافته لاحقاً في أي وقت.</p>
                    </div>

                    <!-- Booklet Tab -->
                    <div v-show="activeTab === 'booklet'" class="p-3.5 rounded-2xl bg-surface-50 dark:bg-surface-900/60 border border-surface-200 dark:border-surface-800 space-y-2">
                        <label class="block text-xs font-bold text-surface-700 dark:text-surface-300">ملف ملزمة الدرس (PDF):</label>
                        <input
                            type="file"
                            accept=".pdf"
                            @change="handleBookletUpload"
                            class="input text-xs file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-primary-50 file:text-primary-700"
                        />
                    </div>

                    <!-- Homework Tab -->
                    <div v-show="activeTab === 'homework'" class="p-3.5 rounded-2xl bg-surface-50 dark:bg-surface-900/60 border border-surface-200 dark:border-surface-800 space-y-2">
                        <label class="block text-xs font-bold text-surface-700 dark:text-surface-300">ملف ورقة الواجب (PDF أو صورة):</label>
                        <input
                            type="file"
                            accept=".pdf,.doc,.docx,.png,.jpg,.jpeg"
                            @change="handleHomeworkUpload"
                            class="input text-xs file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-primary-50 file:text-primary-700"
                        />
                    </div>
                </div>

                <!-- Description / Summary -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-surface-800 dark:text-surface-200">وصف أو تعليمات للطالب (اختياري):</label>
                    <textarea
                        v-model="form.description"
                        rows="2"
                        class="input text-xs resize-none"
                        placeholder="أهم النقاط التي يشملها هذا الدرس..."
                    ></textarea>
                </div>

                <!-- Free Preview Checkbox -->
                <label class="flex items-center gap-2 text-xs text-surface-700 dark:text-surface-300 cursor-pointer">
                    <input v-model="form.is_free_preview" type="checkbox" class="w-4 h-4 rounded text-primary-600 focus:ring-primary-500" />
                    <span>متاح كمعاينة مجانية للطلاب غير المشتركين</span>
                </label>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" class="btn-ghost" @click="close" :disabled="form.processing">إلغاء</button>
                    <button type="submit" class="btn-primary flex items-center gap-2" :disabled="form.processing || !form.title">
                        <Icon name="check" class="w-4 h-4" />
                        <span>{{ form.processing ? 'جاري النشر...' : 'نشر الدرس والمحتوى فوراً' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
