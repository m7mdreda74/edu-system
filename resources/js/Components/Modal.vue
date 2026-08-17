<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: '2xl',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close']);
const dialog = ref();
const showSlot = ref(props.show);
let previousBodyOverflow = '';
let bodyOverflowCaptured = false;

const openDialog = () => {
    if (!dialog.value) return;

    if (!bodyOverflowCaptured) {
        previousBodyOverflow = document.body.style.overflow;
        bodyOverflowCaptured = true;
    }

    showSlot.value = true;
    document.body.style.overflow = 'hidden';

    if (!dialog.value.open) {
        dialog.value.showModal();
    }
};

const closeDialog = () => {
    document.body.style.overflow = previousBodyOverflow;
    previousBodyOverflow = '';
    bodyOverflowCaptured = false;

    window.setTimeout(() => {
        if (!props.show) {
            dialog.value?.close();
            showSlot.value = false;
        }
    }, 200);
};

watch(
    () => props.show,
    (show) => show ? openDialog() : closeDialog(),
);

const close = () => {
    if (props.closeable) {
        emit('close');
    }
};

const closeOnEscape = (e) => {
    if (e.key === 'Escape') {
        e.preventDefault();

        if (props.show) {
            close();
        }
    }
};

onMounted(() => {
    document.addEventListener('keydown', closeOnEscape);

    if (props.show) {
        openDialog();
    }
});

onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);

    document.body.style.overflow = previousBodyOverflow;
    previousBodyOverflow = '';
    bodyOverflowCaptured = false;
});

const maxWidthClass = computed(() => {
    return {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
    }[props.maxWidth];
});
</script>

<template>
    <dialog
        class="z-[60] m-0 min-h-full min-w-full overflow-y-auto bg-transparent p-0 backdrop:bg-transparent"
        ref="dialog"
        aria-label="نافذة حوار"
        @cancel.prevent="close"
    >
        <div
            class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
            scroll-region
        >
            <Transition
                enter-active-class="ease-out duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="ease-in duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-show="show"
                    class="fixed inset-0 transform transition-all"
                    @click="close"
                >
                    <div
                        class="absolute inset-0 bg-surface-950/75"
                    />
                </div>
            </Transition>

            <Transition
                enter-active-class="ease-out duration-300"
                enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                leave-active-class="ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            >
                <div
                    v-show="show"
                    class="modal-panel-compact mb-6 transform rounded-2xl border border-surface-200 bg-white text-surface-900 shadow-xl transition-all dark:border-surface-800 dark:bg-surface-900 dark:text-white sm:mx-auto sm:w-full"
                    :class="maxWidthClass"
                    @click.stop
                >
                    <slot v-if="showSlot" />
                </div>
            </Transition>
        </div>
    </dialog>
</template>
