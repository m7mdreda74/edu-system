<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

defineProps({
    terms:   { type: Array, default: () => [] },
    current: { type: String, default: null },
});

const showForm = ref(false);
const editingId = ref(null);

const form = useForm({
    year_label: '',
    term_number: 1,
    name: 'الفصل الدراسي الأول',
    starts_on: '',
    ends_on: '',
    is_provisional: false,
});

const TERM_NAMES = { 1: 'الفصل الدراسي الأول', 2: 'الفصل الدراسي الثاني', 3: 'الفصل الصيفي' };

function onTermNumberChange() {
    form.name = TERM_NAMES[form.term_number] ?? form.name;
}

function startCreate() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function startEdit(term) {
    editingId.value = term.id;
    form.clearErrors();
    form.year_label = term.year_label;
    form.term_number = term.term_number;
    form.name = term.name;
    form.starts_on = term.starts_on;
    form.ends_on = term.ends_on;
    form.is_provisional = term.is_provisional;
    showForm.value = true;
}

function submit() {
    const options = { onSuccess: () => { showForm.value = false; form.reset(); } };

    editingId.value
        ? form.put(route('admin.academic-terms.update', { id: editingId.value }), options)
        : form.post(route('admin.academic-terms.store'), options);
}

function destroy(id) {
    if (confirm('حذف هذا الفصل الدراسي؟')) {
        router.delete(route('admin.academic-terms.destroy', { id }));
    }
}
</script>

<template>
    <Head title="الفصول الدراسية" />

    <DashboardLayout>
        <div class="space-y-6">
            <header class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white">الفصول الدراسية</h1>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                        التقويم الأكاديمي — العام في قطر فصلان دراسيان
                    </p>
                    <p v-if="current" class="badge-primary text-[10px] mt-2 inline-flex">
                        الفصل الجاري/القادم: {{ current }}
                    </p>
                </div>

                <button type="button" class="btn-primary btn-sm" @click="startCreate">
                    <Icon name="plus" class="w-4 h-4" />
                    <span class="ms-1">إضافة فصل</span>
                </button>
            </header>

            <!-- Form -->
            <form v-if="showForm" class="card p-5 space-y-4" @submit.prevent="submit">
                <h2 class="font-bold text-sm text-surface-900 dark:text-white">
                    {{ editingId ? 'تعديل الفصل الدراسي' : 'فصل دراسي جديد' }}
                </h2>

                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label for="year_label" class="input-label">العام الدراسي</label>
                        <input id="year_label" v-model="form.year_label" type="text" dir="ltr" class="input" placeholder="2026/2027" required />
                        <p v-if="form.errors.year_label" class="text-xs text-red-500 mt-1">{{ form.errors.year_label }}</p>
                    </div>

                    <div>
                        <label for="term_number" class="input-label">رقم الفصل</label>
                        <select id="term_number" v-model.number="form.term_number" class="input" @change="onTermNumberChange">
                            <option :value="1">الأول</option>
                            <option :value="2">الثاني</option>
                            <option :value="3">صيفي</option>
                        </select>
                        <p v-if="form.errors.term_number" class="text-xs text-red-500 mt-1">{{ form.errors.term_number }}</p>
                    </div>

                    <div>
                        <label for="name" class="input-label">الاسم المعروض</label>
                        <input id="name" v-model="form.name" type="text" class="input" required />
                    </div>

                    <div>
                        <label for="starts_on" class="input-label">تاريخ البداية</label>
                        <input id="starts_on" v-model="form.starts_on" type="date" class="input" required />
                        <p v-if="form.errors.starts_on" class="text-xs text-red-500 mt-1">{{ form.errors.starts_on }}</p>
                    </div>

                    <div>
                        <label for="ends_on" class="input-label">تاريخ النهاية</label>
                        <input id="ends_on" v-model="form.ends_on" type="date" class="input" required />
                        <p v-if="form.errors.ends_on" class="text-xs text-red-500 mt-1">{{ form.errors.ends_on }}</p>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-surface-600 dark:text-surface-300">
                    <input v-model="form.is_provisional" type="checkbox" class="rounded" />
                    <span>تواريخ مبدئية (لم يصدر التقويم الرسمي بعد)</span>
                </label>

                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-ghost btn-sm" @click="showForm = false">إلغاء</button>
                    <button type="submit" class="btn-primary btn-sm" :disabled="form.processing">
                        {{ form.processing ? 'جارٍ الحفظ...' : 'حفظ' }}
                    </button>
                </div>
            </form>

            <!-- List -->
            <div v-if="terms.length" class="space-y-3">
                <article
                    v-for="term in terms"
                    :key="term.id"
                    class="card p-5 flex items-center gap-4 flex-wrap"
                    :class="term.is_current ? 'border-s-4 border-primary-500' : ''"
                >
                    <div class="w-11 h-11 rounded-xl bg-primary-50 dark:bg-primary-950 flex items-center justify-center text-primary-600 font-black shrink-0">
                        {{ term.term_number }}
                    </div>

                    <div class="flex-1 min-w-[200px]">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-bold text-sm text-surface-900 dark:text-white">{{ term.full_name }}</h3>
                            <span v-if="term.is_current" class="badge-green text-[10px]">جارٍ الآن</span>
                            <span v-if="term.is_provisional" class="badge-accent text-[10px]">مبدئي</span>
                        </div>

                        <p class="text-xs text-surface-500 dark:text-surface-400 mt-1 flex items-center gap-1.5">
                            <Icon name="calendar" class="w-3.5 h-3.5" />
                            {{ term.starts_on }} → {{ term.ends_on }}
                            <span v-if="term.is_current" class="text-primary-600 dark:text-primary-400 font-bold">
                                · متبقٍ {{ term.weeks_remaining }} أسبوع
                            </span>
                        </p>
                    </div>

                    <span class="badge-gray text-[10px] shrink-0">{{ term.groups_count }} مجموعة</span>

                    <div class="flex items-center gap-1 shrink-0">
                        <button type="button" class="btn-ghost btn-sm" @click="startEdit(term)">
                            <Icon name="edit" class="w-4 h-4" />
                        </button>
                        <button
                            type="button"
                            class="btn-ghost btn-sm text-red-500"
                            :disabled="term.groups_count > 0"
                            :title="term.groups_count > 0 ? 'مرتبط بمجموعات تدريس' : 'حذف'"
                            @click="destroy(term.id)"
                        >
                            <Icon name="trash" class="w-4 h-4" />
                        </button>
                    </div>
                </article>
            </div>

            <div v-else class="card p-12 text-center">
                <Icon name="calendar" class="w-10 h-10 text-surface-300 mx-auto mb-3" />
                <h3 class="font-bold text-surface-700 dark:text-surface-200 mb-1">لا توجد فصول دراسية</h3>
                <p class="text-sm text-surface-400">أضف الفصل الأول لتبدأ المجموعات في الارتباط به.</p>
            </div>
        </div>
    </DashboardLayout>
</template>
