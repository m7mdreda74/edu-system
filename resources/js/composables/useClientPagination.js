import { computed, ref, unref, watch } from 'vue';

export function useClientPagination(source, perPage = 10) {
    const currentPage = ref(1);
    const items = computed(() => {
        const value = unref(source);
        return Array.isArray(value) ? value : [];
    });
    const pageSize = computed(() => Math.max(1, Number(unref(perPage) ?? 10)));
    const total = computed(() => items.value.length);
    const lastPage = computed(() => Math.max(1, Math.ceil(total.value / pageSize.value)));

    function setPage(page) {
        currentPage.value = Math.min(lastPage.value, Math.max(1, Number(page) || 1));
    }

    watch(lastPage, () => setPage(currentPage.value));
    watch(() => unref(source), () => setPage(1));

    const data = computed(() => {
        const start = (currentPage.value - 1) * pageSize.value;
        return items.value.slice(start, start + pageSize.value);
    });

    const pagination = computed(() => {
        const from = total.value ? ((currentPage.value - 1) * pageSize.value) + 1 : null;

        return {
            current_page: currentPage.value,
            last_page: lastPage.value,
            per_page: pageSize.value,
            from,
            to: total.value ? Math.min(total.value, from + pageSize.value - 1) : null,
            total: total.value,
        };
    });

    return { data, pagination, setPage };
}
