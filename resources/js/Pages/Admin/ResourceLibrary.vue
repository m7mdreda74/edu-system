<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    resources: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

function moderate(resource, action) {
    router.patch(route(`admin.resource-library.${action}`, resource.id));
}

function statusLabel(status) {
    return {
        pending: 'بانتظار المراجعة',
        approved: 'معتمد',
        rejected: 'مرفوض',
        none: 'خاص',
    }[status] ?? status;
}
</script>

<template>
    <Head title="مراجعة مكتبة الموارد" />
    <DashboardLayout>
        <div class="space-y-6">
            <header>
                <h1 class="text-2xl font-black text-surface-900 dark:text-white">مراجعة مكتبة الموارد</h1>
                <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">اعتمد الموارد المشتركة بعد مراجعة محتواها وصلاحيتها التعليمية.</p>
            </header>

            <div v-if="resources.data?.length" class="card divide-y divide-surface-100 dark:divide-surface-800">
                <article v-for="resource in resources.data" :key="resource.id" class="p-5 flex flex-col lg:flex-row lg:items-center gap-4">
                    <div class="w-11 h-11 rounded-xl bg-primary-50 dark:bg-primary-950 flex items-center justify-center text-primary-600 shrink-0">
                        <Icon name="book" class="w-5 h-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="font-bold text-surface-900 dark:text-white">{{ resource.title }}</h2>
                        <p class="text-xs text-surface-500 mt-1">{{ resource.owner?.name }} · {{ resource.category }} · {{ resource.size_label }}</p>
                        <div class="flex flex-wrap gap-1.5 mt-2">
                            <span v-for="tag in resource.tags" :key="tag" class="badge-accent text-[10px]">#{{ tag }}</span>
                            <span class="badge-primary text-[10px]">{{ statusLabel(resource.approval_status) }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a v-if="resource.file_url" :href="resource.file_url" class="btn-outline btn-sm">فتح الملف</a>
                        <button v-if="resource.approval_status === 'pending'" type="button" class="btn-primary btn-sm" @click="moderate(resource, 'approve')">اعتماد</button>
                        <button v-if="resource.approval_status === 'pending'" type="button" class="btn-ghost btn-sm text-red-500" @click="moderate(resource, 'reject')">رفض</button>
                    </div>
                </article>
            </div>
            <div v-else class="card p-12 text-center text-surface-500">لا توجد موارد تحتاج إلى عرض.</div>

            <div v-if="resources?.links?.length > 3" class="flex justify-center gap-1">
                <Link v-for="link in resources.links" :key="link.label" :href="link.url ?? '#'" :class="['btn-ghost btn-sm', link.active ? 'bg-primary-500/10 text-primary-600' : '']" v-html="link.label" />
            </div>
        </div>
    </DashboardLayout>
</template>
