<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    courses: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search ?? '');

function applyFilters() {
    router.get(route('admin.courses'), { search: search.value || undefined },
        { preserveState: true, replace: true });
}

function togglePublish(courseId) {
    router.patch(route('admin.courses.toggle', { id: courseId }), {}, { preserveScroll: true });
}

function formatQAR(halala) {
    if (!halala) return 'مجاني';
    return new Intl.NumberFormat('ar-QA', { style: 'currency', currency: 'QAR', minimumFractionDigits: 0 })
        .format(halala / 100);
}
</script>

<template>
    <DashboardLayout>
        <Head title="إدارة الكورسات" />

        <div class="container-app px-4 py-10">
            <div class="flex items-center justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <Icon name="courses" class="w-8 h-8 text-primary-500" />
                        <span>الكورسات</span>
                    </h1>
                    <p class="text-surface-500 mt-1">{{ courses.total }} كورس مسجّل</p>
                </div>
                <Link :href="route('admin.dashboard')" class="btn-ghost">← الداشبورد</Link>
            </div>

            <!-- Search -->
            <div class="card p-4 mb-6">
                <input
                    v-model="search"
                    @input="applyFilters"
                    type="text"
                    placeholder="بحث في الكورسات..."
                    class="input w-full md:w-80"
                    id="courses-search"
                />
            </div>

            <!-- Table -->
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-surface-50 dark:bg-surface-800 border-b border-surface-200 dark:border-surface-700">
                            <tr>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">الكورس</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">المدرس</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">السعر</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">الطلاب</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">الحالة</th>
                                <th class="text-start p-4 font-semibold text-surface-600 dark:text-surface-300">إجراء</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-700">
                            <tr v-for="course in courses.data" :key="course.id"
                                class="hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-10 rounded-lg overflow-hidden bg-surface-200 dark:bg-surface-700 flex-shrink-0">
                                            <img v-if="course.thumbnail"
                                                 :src="course.thumbnail" :alt="course.title"
                                                 class="w-full h-full object-cover" />
                                            <div v-else class="w-full h-full flex items-center justify-center text-surface-400 bg-surface-100 dark:bg-surface-800">
                                                 <Icon name="courses" class="w-5 h-5" />
                                             </div>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-surface-800 dark:text-white line-clamp-1">
                                                {{ course.title }}
                                            </div>
                                            <div class="text-xs text-surface-400">{{ course.subject?.name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 text-surface-600 dark:text-surface-300">{{ course.teacher?.name }}</td>
                                <td class="p-4 font-medium text-primary-700 dark:text-primary-400">
                                    {{ formatQAR(course.discount_price ?? course.price) }}
                                </td>
                                <td class="p-4 text-surface-600 dark:text-surface-300">
                                    {{ course.enrollments_count }}
                                </td>
                                <td class="p-4">
                                    <span :class="course.is_published ? 'badge-green' : 'badge-gray'">
                                        {{ course.is_published ? 'منشور' : 'مسودة' }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    <div class="flex gap-2">
                                        <button
                                            @click="togglePublish(course.id)"
                                            class="text-xs px-3 py-1.5 rounded-lg font-medium transition-colors"
                                            :class="course.is_published
                                                ? 'bg-orange-50 text-orange-600 hover:bg-orange-100 dark:bg-orange-950/50 dark:text-orange-400'
                                                : 'bg-green-50 text-green-600 hover:bg-green-100 dark:bg-green-950/50 dark:text-green-400'"
                                            :id="`toggle-course-${course.id}`"
                                        >
                                            {{ course.is_published ? 'إخفاء' : 'نشر' }}
                                        </button>
                                        <Link :href="route('courses.show', { slug: course.slug })"
                                              class="text-xs px-3 py-1.5 rounded-lg font-medium bg-surface-100 text-surface-600 hover:bg-surface-200 dark:bg-surface-700 dark:text-surface-300"
                                              target="_blank"
                                        >
                                            عرض
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="!courses.data?.length" class="text-center text-surface-400 py-10 flex flex-col items-center justify-center gap-2">
                    <Icon name="courses" class="w-12 h-12 text-surface-400" />
                    <p>لا توجد كورسات مطابقة للبحث</p>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
