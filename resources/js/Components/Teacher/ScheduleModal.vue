<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import { formatTime12 } from '@/lib/money';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    group:      { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const daysList = [
    { value: 6, label: 'السبت' },
    { value: 0, label: 'الأحد' },
    { value: 1, label: 'الإثنين' },
    { value: 2, label: 'الثلاثاء' },
    { value: 3, label: 'الأربعاء' },
    { value: 4, label: 'الخميس' },
    { value: 5, label: 'الجمعة' },
];

const selectedDays = ref([6, 2]); // default: Saturday & Tuesday
const startTime = ref('19:00'); // default 7:00 PM
const durationMinutes = ref(60); // default 1 hour
const autoSchedule = ref(true);
const processing = ref(false);

watch(() => props.group, (newGroup) => {
    if (newGroup?.schedules?.length) {
        selectedDays.value = newGroup.schedules.map(s => Number(s.day_of_week));
        const first = newGroup.schedules[0];
        if (first?.start_time) {
            startTime.value = first.start_time.slice(0, 5);
        }
        if (first?.duration_minutes) {
            durationMinutes.value = Number(first.duration_minutes);
        }
    } else {
        selectedDays.value = [6, 2];
        startTime.value = '19:00';
        durationMinutes.value = 60;
    }
}, { immediate: true });

function toggleDay(dayVal) {
    const idx = selectedDays.value.indexOf(dayVal);
    if (idx > -1) {
        if (selectedDays.value.length > 1) {
            selectedDays.value.splice(idx, 1);
        }
    } else {
        selectedDays.value.push(dayVal);
    }
}

// Calculate end time
const computedEndTime = computed(() => {
    if (!startTime.value) return '';
    const parts = startTime.value.split(':');
    const h = parseInt(parts[0], 10);
    const m = parseInt(parts[1], 10);
    if (isNaN(h) || isNaN(m)) return '';

    const totalMinutes = h * 60 + m + Number(durationMinutes.value);
    const endH = Math.floor(totalMinutes / 60) % 24;
    const endM = totalMinutes % 60;
    return `${String(endH).padStart(2, '0')}:${String(endM).padStart(2, '0')}`;
});

const previewText = computed(() => {
    if (!selectedDays.value.length || !startTime.value) return '';
    const dayNames = selectedDays.value
        .map(d => daysList.find(item => item.value === d)?.label)
        .filter(Boolean)
        .join(' و ');

    const start12 = formatTime12(startTime.value);
    const end12 = formatTime12(computedEndTime.value);

    let durationLabel = `${durationMinutes.value} دقيقة`;
    if (durationMinutes.value === 60) durationLabel = 'ساعة واحدة';
    if (durationMinutes.value === 90) durationLabel = 'ساعة ونصف';
    if (durationMinutes.value === 120) durationLabel = 'ساعتان';

    return `تنعقد المجموعة يومي (${dayNames}) · من ${start12} إلى ${end12} (${durationLabel})`;
});

function submitSchedule() {
    if (!props.group?.id || !selectedDays.value.length) return;

    processing.value = true;
    router.post(
        route('teacher.teaching-schedule.groups.schedules.store', props.group.id),
        {
            days: selectedDays.value,
            start_time: startTime.value,
            duration_minutes: durationMinutes.value,
            auto_schedule: autoSchedule.value,
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

function deleteSchedule(scheduleId) {
    if (!confirm('هل أنت متأكد من حذف هذا الموعد؟')) return;
    router.delete(route('teacher.teaching-schedule.group-schedules.destroy', scheduleId), {
        preserveScroll: true,
    });
}

function triggerAutoSchedule() {
    if (!props.group?.id) return;
    processing.value = true;
    router.post(
        route('teacher.teaching-schedule.groups.auto-schedule', props.group.id),
        {},
        {
            preserveScroll: true,
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
        v-if="modelValue && group"
        class="modal-overlay z-[80] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
        role="dialog"
        aria-modal="true"
        @click.self="close"
    >
        <div class="card w-full max-w-xl max-h-[92vh] flex flex-col p-6 sm:p-7 shadow-2xl space-y-5 animate-in fade-in zoom-in-95 duration-200">
            <!-- Header -->
            <div class="flex items-start justify-between gap-4 border-b border-surface-200 dark:border-surface-700 pb-4">
                <div>
                    <h3 class="text-xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-primary-500/10 text-primary-600 flex items-center justify-center">
                            <Icon name="calendar" class="w-5 h-5" />
                        </span>
                        <span>مواعيد وجدولة المجموعة</span>
                    </h3>
                    <p class="text-xs text-surface-500 mt-1">
                        مجموعة: <strong class="text-primary-600 font-bold">{{ group.name }}</strong>
                    </p>
                </div>
                <button type="button" class="text-surface-400 hover:text-surface-600 text-xl font-bold p-1" @click="close">✕</button>
            </div>

            <!-- Existing Schedules (if any) -->
            <div v-if="group.schedules?.length" class="bg-surface-50 dark:bg-surface-900/60 p-3.5 rounded-2xl border border-surface-200 dark:border-surface-800 space-y-2">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-bold text-surface-700 dark:text-surface-300">المواعيد المحددة حالياً:</span>
                    <button
                        v-if="group.pending_lessons_count > 0"
                        type="button"
                        class="text-primary-600 dark:text-primary-400 hover:underline font-bold flex items-center gap-1"
                        :disabled="processing"
                        @click="triggerAutoSchedule"
                    >
                        <span>⚡ جدولة {{ group.pending_lessons_count }} حصة متبقية تلقائياً</span>
                    </button>
                </div>
                <div class="flex flex-wrap gap-2 pt-1">
                    <span
                        v-for="s in group.schedules"
                        :key="s.id"
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-primary-500/10 text-primary-800 dark:text-primary-200 text-xs font-bold border border-primary-500/20"
                    >
                        <span>{{ s.day_name }} · {{ s.start_time_12 }} إلى {{ s.end_time_12 }}</span>
                        <button
                            type="button"
                            class="text-red-500 hover:text-red-700 font-black text-sm"
                            title="حذف هذا الموعد"
                            @click="deleteSchedule(s.id)"
                        >
                            ×
                        </button>
                    </span>
                </div>
            </div>

            <!-- Form -->
            <form @submit.prevent="submitSchedule" class="space-y-5 overflow-y-auto no-scrollbar">
                <!-- Days Selector (Multi-select) -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-surface-800 dark:text-surface-200">
                        أيام انعقاد المجموعة (اختر يوماً أو أكثر):
                    </label>
                    <div class="grid grid-cols-4 sm:grid-cols-7 gap-2">
                        <button
                            v-for="day in daysList"
                            :key="day.value"
                            type="button"
                            @click="toggleDay(day.value)"
                            :class="[
                                'py-2 px-2 rounded-xl text-xs font-bold transition-all text-center border',
                                selectedDays.includes(day.value)
                                    ? 'bg-primary-600 text-white border-primary-600 shadow-sm scale-102'
                                    : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-400 border-transparent hover:border-surface-300'
                            ]"
                        >
                            {{ day.label }}
                        </button>
                    </div>
                </div>

                <!-- Time and Duration -->
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-surface-800 dark:text-surface-200">
                            وقت البدء:
                        </label>
                        <div class="relative">
                            <input
                                v-model="startTime"
                                type="time"
                                class="input text-base font-bold"
                                required
                            />
                        </div>
                        <p class="text-[11px] text-primary-600 font-medium">
                            المعاينة: {{ formatTime12(startTime) || '—' }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-surface-800 dark:text-surface-200">
                            مدة الحصة:
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                v-for="d in [
                                    { minutes: 45, label: '45 دقيقة' },
                                    { minutes: 60, label: 'ساعة واحدة' },
                                    { minutes: 90, label: 'ساعة ونصف' },
                                    { minutes: 120, label: 'ساعتان' },
                                ]"
                                :key="d.minutes"
                                type="button"
                                @click="durationMinutes = d.minutes"
                                :class="[
                                    'py-2 px-2 rounded-xl text-xs font-bold transition-all border text-center',
                                    durationMinutes === d.minutes
                                        ? 'bg-accent-500 text-surface-950 border-accent-500 font-black shadow-sm'
                                        : 'bg-surface-100 dark:bg-surface-800 text-surface-700 dark:text-surface-300 border-transparent hover:border-surface-300'
                                ]"
                            >
                                {{ d.label }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Live Schedule Preview Card -->
                <div v-if="previewText" class="p-4 rounded-2xl bg-primary-500/10 border border-primary-500/25 flex items-start gap-3">
                    <Icon name="clock" class="w-5 h-5 text-primary-600 shrink-0 mt-0.5" />
                    <div>
                        <div class="text-xs font-bold text-primary-900 dark:text-primary-100">ملخص المواعيد:</div>
                        <div class="text-sm font-black text-primary-700 dark:text-primary-300 mt-0.5 leading-relaxed">
                            {{ previewText }}
                        </div>
                    </div>
                </div>

                <!-- Auto-schedule checkbox -->
                <label class="flex items-center gap-2.5 text-xs text-surface-700 dark:text-surface-300 cursor-pointer p-2 rounded-xl hover:bg-surface-50 dark:hover:bg-surface-900">
                    <input
                        v-model="autoSchedule"
                        type="checkbox"
                        class="w-4 h-4 rounded text-primary-600 focus:ring-primary-500"
                    />
                    <span class="font-medium">جدولة وتوزيع حصص المجموعة تلقائياً على هذه المواعيد القادمة</span>
                </label>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" class="btn-ghost" @click="close" :disabled="processing">إلغاء</button>
                    <button type="submit" class="btn-primary flex items-center gap-2" :disabled="processing || !selectedDays.length">
                        <Icon name="check" class="w-4 h-4" />
                        <span>{{ processing ? 'جاري الحفظ والجدولة...' : 'حفظ المواعيد وتطبيقها' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
