<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    paginator: { type: Object, required: true },
    itemLabel: { type: String, default: 'نتيجة' },
});

const emit = defineEmits(['page-change']);

const currentPage = computed(() => Number(props.paginator?.current_page ?? 1));
const lastPage = computed(() => Math.max(1, Number(props.paginator?.last_page ?? 1)));
const total = computed(() => Number(props.paginator?.total ?? 0));
const hasServerLinks = computed(() => Array.isArray(props.paginator?.links));

const clientPages = computed(() => {
    const pages = [];
    const start = Math.max(1, currentPage.value - 2);
    const end = Math.min(lastPage.value, currentPage.value + 2);

    if (start > 1) pages.push(1);
    if (start > 2) pages.push('start-gap');
    for (let page = start; page <= end; page += 1) pages.push(page);
    if (end < lastPage.value - 1) pages.push('end-gap');
    if (end < lastPage.value) pages.push(lastPage.value);

    return pages;
});

function serverLabel(label) {
    const value = String(label ?? '');

    if (/previous|pagination\.previous|السابق/i.test(value)) return 'السابق';
    if (/next|pagination\.next|التالي/i.test(value)) return 'التالي';

    return value.replace(/&laquo;|&raquo;|«|»/g, '').trim();
}

function goTo(page) {
    const target = Math.min(lastPage.value, Math.max(1, Number(page)));
    if (target !== currentPage.value) emit('page-change', target);
}
</script>

<template>
    <nav
        v-if="total > 0"
        class="data-table-footer flex flex-col gap-3 border-t border-surface-100 px-4 py-3 dark:border-surface-800 sm:flex-row sm:items-center sm:justify-between"
        aria-label="التنقل بين صفحات الجدول"
    >
        <p class="text-xs text-surface-500 dark:text-surface-400">
            عرض
            <b class="text-surface-800 dark:text-surface-200">{{ paginator.from ?? 1 }}</b>
            إلى
            <b class="text-surface-800 dark:text-surface-200">{{ paginator.to ?? total }}</b>
            من
            <b class="text-surface-800 dark:text-surface-200">{{ total }}</b>
            {{ itemLabel }}
        </p>

        <div v-if="lastPage > 1" class="flex flex-wrap items-center gap-1.5" dir="rtl">
            <template v-if="hasServerLinks">
                <template v-for="link in paginator.links" :key="`${link.label}-${link.url}`">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        preserve-state
                        class="data-table-page-link"
                        :class="link.active ? 'data-table-page-link-active' : ''"
                        v-html="serverLabel(link.label)"
                    />
                    <span
                        v-else
                        class="data-table-page-link cursor-not-allowed opacity-35"
                        v-html="serverLabel(link.label)"
                    />
                </template>
            </template>

            <template v-else>
                <button
                    type="button"
                    class="data-table-page-link"
                    :disabled="currentPage === 1"
                    @click="goTo(currentPage - 1)"
                >
                    السابق
                </button>

                <template v-for="page in clientPages" :key="page">
                    <span v-if="typeof page === 'string'" class="px-1 text-surface-400">…</span>
                    <button
                        v-else
                        type="button"
                        class="data-table-page-link"
                        :class="page === currentPage ? 'data-table-page-link-active' : ''"
                        :aria-current="page === currentPage ? 'page' : undefined"
                        @click="goTo(page)"
                    >
                        {{ page }}
                    </button>
                </template>

                <button
                    type="button"
                    class="data-table-page-link"
                    :disabled="currentPage === lastPage"
                    @click="goTo(currentPage + 1)"
                >
                    التالي
                </button>
            </template>
        </div>
    </nav>
</template>
