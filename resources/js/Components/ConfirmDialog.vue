<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { confirmState } from '@/composables/useConfirm';

const inputRef = ref(null);

// Focus input when it appears
watch(() => confirmState.value.show, (show) => {
    if (show && confirmState.value.inputLabel) {
        nextTick(() => inputRef.value?.focus());
    }
});

const variantConfig = computed(() => {
    const v = confirmState.value.variant;
    return {
        danger: {
            iconBg: 'bg-red-100 dark:bg-red-950/40',
            iconColor: 'text-red-600 dark:text-red-400',
            btnClass: 'bg-red-600 hover:bg-red-700 focus:ring-red-500 text-white',
            icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />`,
        },
        warning: {
            iconBg: 'bg-amber-100 dark:bg-amber-950/40',
            iconColor: 'text-amber-600 dark:text-amber-400',
            btnClass: 'bg-amber-500 hover:bg-amber-600 focus:ring-amber-400 text-white',
            icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />`,
        },
        info: {
            iconBg: 'bg-primary-100 dark:bg-primary-950/40',
            iconColor: 'text-primary-600 dark:text-primary-400',
            btnClass: 'bg-primary-600 hover:bg-primary-700 focus:ring-primary-500 text-white',
            icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />`,
        },
    }[v] ?? {};
});

function onConfirm() {
    const s = confirmState.value;
    if (s.inputLabel) {
        if (!s.inputValue?.trim()) return; // require input
        s.resolve({ confirmed: true, value: s.inputValue.trim() });
    } else {
        s.resolve(true);
    }
    close();
}

function onCancel() {
    confirmState.value.resolve(false);
    close();
}

function close() {
    confirmState.value.show = false;
}

function onKeydown(e) {
    if (e.key === 'Escape') onCancel();
}
</script>

<template>
    <!-- Backdrop -->
    <Teleport to="body">
        <Transition
            enter-active-class="duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="confirmState.show"
                class="fixed inset-0 z-[9998] bg-black/60 backdrop-blur-sm"
                @click="onCancel"
            />
        </Transition>

        <!-- Dialog -->
        <Transition
            enter-active-class="duration-200 ease-out"
            enter-from-class="opacity-0 scale-95 translate-y-2"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="duration-150 ease-in"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 translate-y-2"
        >
            <div
                v-if="confirmState.show"
                class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
                @keydown="onKeydown"
            >
                <div
                    class="w-full max-w-md rounded-2xl border border-surface-200 bg-white shadow-2xl dark:border-surface-700 dark:bg-surface-900"
                    dir="rtl"
                    role="alertdialog"
                    aria-modal="true"
                    :aria-labelledby="'confirm-title'"
                >
                    <!-- Header -->
                    <div class="flex items-start gap-4 p-6">
                        <!-- Icon -->
                        <div :class="['flex h-11 w-11 shrink-0 items-center justify-center rounded-full', variantConfig.iconBg]">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                                class="h-6 w-6"
                                :class="variantConfig.iconColor"
                                v-html="variantConfig.icon"
                            />
                        </div>

                        <!-- Text -->
                        <div class="flex-1 min-w-0">
                            <h3
                                id="confirm-title"
                                class="text-base font-bold text-surface-900 dark:text-white"
                            >
                                {{ confirmState.title }}
                            </h3>
                            <p
                                v-if="confirmState.message"
                                class="mt-1.5 text-sm leading-relaxed text-surface-500 dark:text-surface-400"
                            >
                                {{ confirmState.message }}
                            </p>
                        </div>
                    </div>

                    <!-- Optional input (replaces prompt()) -->
                    <div v-if="confirmState.inputLabel" class="px-6 pb-2">
                        <label class="mb-1.5 block text-sm font-semibold text-surface-700 dark:text-surface-300">
                            {{ confirmState.inputLabel }}
                        </label>
                        <textarea
                            ref="inputRef"
                            v-model="confirmState.inputValue"
                            rows="3"
                            :placeholder="confirmState.inputPlaceholder"
                            class="input w-full resize-none"
                            @keydown.enter.ctrl="onConfirm"
                        />
                    </div>

                    <!-- Divider -->
                    <div class="h-px bg-surface-100 dark:bg-surface-800 mx-6" />

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3 p-4">
                        <button
                            type="button"
                            class="rounded-xl border border-surface-200 bg-white px-4 py-2 text-sm font-semibold text-surface-700 transition hover:bg-surface-50 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-300 dark:hover:bg-surface-700"
                            @click="onCancel"
                        >
                            {{ confirmState.cancelLabel }}
                        </button>
                        <button
                            type="button"
                            class="rounded-xl px-4 py-2 text-sm font-semibold shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2"
                            :class="variantConfig.btnClass"
                            :disabled="confirmState.inputLabel && !confirmState.inputValue?.trim()"
                            @click="onConfirm"
                        >
                            {{ confirmState.confirmLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
