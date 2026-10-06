<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import { formatTime12 } from '@/lib/money';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    assignment: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'saved']);

// Default date to tomorrow
const tomorrow = new Date(Date.now() + 24 * 60 * 60 * 1000);
const pad = (n) => String(n).padStart(2, '0');
const defaultDate = `${tomorrow.getFullYear()}-${pad(tomorrow.getMonth() + 1)}-${pad(tomorrow.getDate())}`;

const slotType = ref('private'); // 'private' or 'free_intro'
const slotDate = ref(defaultDate);
const startTime = ref('18:00');
const durationMinutes = ref(60);
const processing = ref(false);

const computedEndTime = computed(() => {
    if (!startTime.value) return '';
    const parts = startTime.value.split(':');
    const h = parseInt(parts[0], 10);
    const m = parseInt(parts[1], 10);
    if (isNaN(h) || isNaN(m)) return '';

    const total = h * 60 + m + Number(durationMinutes.value);
    const endH = Math.floor(total / 60) % 24;
    const endM = total % 60;
    return `${pad(endH)}:${pad(endM)}`;
});

const previewText = computed(() => {
    if (!slotDate.value || !startTime.value) return '';
    const start12 = formatTime12(startTime.value);
    const end12 = formatTime12(computedEndTime.value);
    const typeLabel = slotType.value === 'free_intro' ? 'حصة تجريبية مجانية' : 'موعد برايفيت';
    return `${typeLabel} · تاريخ: ${slotDate.value} · من ${start12} إلى ${end12} (${durationMinutes.value} دقيقة)`;
});

function submitSlot() {
    if (!props.assignment?.id || !slotDate.value || !startTime.value) return;

    processing.value = true;
    const startsAt = `${slotDate.value}T${startTime.value}:00`;
    const endsAt = `${slotDate.value}T${computedEndTime.value}:00`;

    const targetRoute = slotType.value === 'free_intro'
        ? route('teacher.free-intro-sessions.store')
        : route('teacher.private-slots.store');

    router.post(
        targetRoute,
        {
            teaching_assignment_id: props.assignment.id,
            starts_at: startsAt,
            ends_at: endsAt,
            duration_minutes: durationMinutes.value,
            timezone: 'Asia/Qatar',
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                emit('update:modelValue', false);
                emit('saved');
            },
            onFinish: () => {
                processing.value = false;
            },
        }
    );
}

function close() {
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
        <div class="card w-full max-w-lg max-h-[92vh] flex flex-col p-6 sm:p-7 shadow-2xl space-y-5 animate-in fade-in zoom-in-95 duration-200">
            <!-- Header -->
            <div class="flex items-start justify-between gap-4 border-b border-surface-200 dark:border-surface-700 pb-4">
                <div>
                    <h3 class="text-xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-accent-500/10 text-accent-600 flex items-center justify-center">
                            <Icon name="live" class="w-5 h-5" />
                        </span>
                        <span>إضافة موعد برايفيت أو تجريبي</span>
                    </h3>
                    <p class="text-xs text-surface-500 mt-1">
                        {{ assignment.subject?.name }} — {{ assignment.grade?.name }}
                    </p>
                </div>
                <button type="button" class="text-surface-400 hover:text-surface-600 text-xl font-bold p-1" @click="close">✕</button>
            </div>

            <!-- Form -->
            <form @submit.prevent="submitSlot" class="space-y-4">
                <!-- Slot Type Toggle -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-surface-800 dark:text-surface-200">نوع الموعد:</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            @click="slotType = 'private'"
                            :class="[
                                'p-3 rounded-2xl text-xs font-bold transition-all border text-center flex items-center justify-center gap-2',
                                slotType === 'private'
                                    ? 'bg-primary-600 text-white border-primary-600 shadow-sm'
                                    : 'bg-surface-100 dark:bg-surface-800 text-surface-700 dark:text-surface-300 border-transparent hover:border-surface-300'
                            ]"
                        >
                            <Icon name="student" class="w-4 h-4" />
                            <span>حصة برايفيت (طالب فردي)</span>
                        </button>
                        <button
                            type="button"
                            @click="slotType = 'free_intro'"
                            :class="[
                                'p-3 rounded-2xl text-xs font-bold transition-all border text-center flex items-center justify-center gap-2',
                                slotType === 'free_intro'
                                    ? 'bg-accent-500 text-surface-950 border-accent-500 font-black shadow-sm'
                                    : 'bg-surface-100 dark:bg-surface-800 text-surface-700 dark:text-surface-300 border-transparent hover:border-surface-300'
                            ]"
                        >
                            <Icon name="sparkles" class="w-4 h-4" />
                            <span>حصة تجريبية مجانية</span>
                        </button>
                    </div>
                </div>

                <!-- Date & Start Time -->
                <div class="grid sm:grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-surface-800 dark:text-surface-200">اليوم / التاريخ:</label>
                        <input
                            v-model="slotDate"
                            type="date"
                            :min="defaultDate"
                            class="input text-sm font-bold"
                            required
                        />
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-surface-800 dark:text-surface-200">وقت البدء:</label>
                        <input
                            v-model="startTime"
                            type="time"
                            class="input text-sm font-bold"
                            required
                        />
                        <p class="text-[11px] text-primary-600 font-medium">
                            المعاينة: {{ formatTime12(startTime) || '—' }}
                        </p>
                    </div>
                </div>

                <!-- Duration -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-surface-800 dark:text-surface-200">المدة:</label>
                    <div class="grid grid-cols-4 gap-2">
                        <button
                            v-for="d in [
                                { minutes: 30, label: '30 دقيقة' },
                                { minutes: 45, label: '45 دقيقة' },
                                { minutes: 60, label: 'ساعة' },
                                { minutes: 90, label: 'ساعة ونصف' },
                            ]"
                            :key="d.minutes"
                            type="button"
                            @click="durationMinutes = d.minutes"
                            :class="[
                                'py-2 px-2 rounded-xl text-xs font-bold transition-all border text-center',
                                durationMinutes === d.minutes
                                    ? 'bg-primary-600 text-white border-primary-600 shadow-sm'
                                    : 'bg-surface-100 dark:bg-surface-800 text-surface-700 dark:text-surface-300 border-transparent hover:border-surface-300'
                            ]"
                        >
                            {{ d.label }}
                        </button>
                    </div>
                </div>

                <!-- Live Preview -->
                <div v-if="previewText" class="p-3.5 rounded-2xl bg-surface-100 dark:bg-surface-800/80 border border-surface-200 dark:border-surface-700 flex items-start gap-2.5">
                    <Icon name="info" class="w-4 h-4 text-accent-500 shrink-0 mt-0.5" />
                    <p class="text-xs font-bold text-surface-800 dark:text-surface-200 leading-relaxed">
                        {{ previewText }}
                    </p>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" class="btn-ghost" @click="close" :disabled="processing">إلغاء</button>
                    <button type="submit" class="btn-primary flex items-center gap-2" :disabled="processing || !slotDate || !startTime">
                        <Icon name="check" class="w-4 h-4" />
                        <span>{{ processing ? 'جاري النشر...' : 'نشر الموعد للطلاب' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
