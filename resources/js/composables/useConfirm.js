import { ref, shallowRef } from 'vue';

/**
 * Global confirm dialog state.
 * Used by <ConfirmDialog /> component mounted once in DashboardLayout / AppLayout.
 */
export const confirmState = ref({
    show: false,
    title: '',
    message: '',
    confirmLabel: 'تأكيد',
    cancelLabel: 'إلغاء',
    variant: 'danger', // 'danger' | 'warning' | 'info'
    inputLabel: null,  // if set → shows a text input (replaces prompt())
    inputPlaceholder: '',
    inputMaxLength: null,
    inputValue: '',
    resolve: null,
});

/**
 * Show a confirm dialog. Returns a Promise:
 *  - resolves with true  → user clicked confirm
 *  - resolves with false → user clicked cancel / closed
 *  - if inputLabel is set → resolves with { confirmed: true, value: '...' } or false
 *
 * @param {Object} options
 * @param {string} options.title
 * @param {string} options.message
 * @param {'danger'|'warning'|'info'} [options.variant='danger']
 * @param {string} [options.confirmLabel='تأكيد']
 * @param {string} [options.cancelLabel='إلغاء']
 * @param {string|null} [options.inputLabel=null]
 * @param {string} [options.inputPlaceholder='']
 * @param {number|null} [options.inputMaxLength=null]
 */
export function useConfirm() {
    function confirm(options = {}) {
        return new Promise((resolve) => {
            Object.assign(confirmState.value, {
                show: true,
                title: options.title ?? 'تأكيد الإجراء',
                message: options.message ?? '',
                confirmLabel: options.confirmLabel ?? 'تأكيد',
                cancelLabel: options.cancelLabel ?? 'إلغاء',
                variant: options.variant ?? 'danger',
                inputLabel: options.inputLabel ?? null,
                inputPlaceholder: options.inputPlaceholder ?? '',
                inputMaxLength: options.inputMaxLength ?? null,
                inputValue: '',
                resolve,
            });
        });
    }

    return { confirm };
}
