<script setup>
import { onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    group: { type: Object, required: true },
    studentsCount: { type: Number, default: 0 },
});

const search = ref('');
const students = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: props.studentsCount });
const loading = ref(false);
const error = ref('');

async function fetchRoster(page = 1) {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get(route('teacher.groups.roster', {
            groupId: props.group.id,
            page,
            search: search.value || undefined,
        }));

        students.value = data.data ?? [];
        meta.value = data.meta ?? meta.value;
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'تعذر تحميل قائمة الطلاب.';
    } finally {
        loading.value = false;
    }
}

onMounted(() => fetchRoster());
</script>

<template>
    <DashboardLayout>
        <Head :title="`طلاب ${group.name}`" />

        <div class="container-app space-y-6" dir="rtl">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <Link :href="route('teacher.groups.workspace', { groupId: group.id })" class="mb-3 inline-flex items-center gap-1 text-sm font-bold text-primary-600 hover:underline">
                        <Icon name="arrow-right" class="h-4 w-4" />
                        العودة إلى مساحة المجموعة
                    </Link>
                    <h1 class="flex items-center gap-3 text-3xl font-black text-surface-900 dark:text-white">
                        <Icon name="student" class="h-8 w-8 text-primary-500" />
                        طلاب {{ group.name }}
                    </h1>
                    <p class="mt-1 text-sm text-surface-500">{{ group.subject?.name }} · {{ group.grade?.name }} · {{ studentsCount }} طالبًا مسجلًا.</p>
                </div>
                <span class="badge-primary">السعة {{ group.capacity }}</span>
            </header>

            <section class="card space-y-4 p-5">
                <form class="flex flex-col gap-3 sm:flex-row" @submit.prevent="fetchRoster(1)">
                    <label class="sr-only" for="student-roster-search">البحث في الطلاب</label>
                    <input id="student-roster-search" v-model="search" type="search" class="input flex-1" placeholder="ابحث بالاسم أو البريد الإلكتروني..." />
                    <button type="submit" class="btn-primary" :disabled="loading">{{ loading ? 'جارٍ البحث...' : 'بحث' }}</button>
                </form>

                <div v-if="loading" class="py-12 text-center text-sm text-surface-400">جارٍ تحميل قائمة الطلاب...</div>
                <div v-else-if="error" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/20 dark:text-red-300">{{ error }}</div>
                <div v-else-if="!students.length" class="py-12 text-center text-sm text-surface-400">لا يوجد طلاب مطابقون للبحث.</div>
                <div v-else class="divide-y divide-surface-100 rounded-xl border border-surface-200 dark:divide-surface-700 dark:border-surface-700">
                    <div v-for="student in students" :key="student.id" class="flex items-center justify-between gap-3 p-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-500/10 text-sm font-bold text-primary-600">
                                <img v-if="student.avatar" :src="student.avatar" :alt="student.name" class="h-full w-full object-cover" />
                                <span v-else>{{ student.name?.charAt(0) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate font-bold text-surface-900 dark:text-white">{{ student.name }}</p>
                                <p class="truncate text-xs text-surface-500">{{ student.email }}</p>
                            </div>
                        </div>
                        <Link :href="route('chat.index')" class="btn-ghost btn-sm shrink-0 text-primary-600">رسالة</Link>
                    </div>
                </div>

                <footer class="flex items-center justify-between gap-3 border-t border-surface-200 pt-4 dark:border-surface-700">
                    <span class="text-xs text-surface-500">{{ meta.total }} طالبًا · صفحة {{ meta.current_page }} من {{ meta.last_page }}</span>
                    <div class="flex gap-2">
                        <button type="button" class="btn-ghost btn-sm" :disabled="loading || meta.current_page <= 1" @click="fetchRoster(meta.current_page - 1)">السابق</button>
                        <button type="button" class="btn-ghost btn-sm" :disabled="loading || meta.current_page >= meta.last_page" @click="fetchRoster(meta.current_page + 1)">التالي</button>
                    </div>
                </footer>
            </section>
        </div>
    </DashboardLayout>
</template>
