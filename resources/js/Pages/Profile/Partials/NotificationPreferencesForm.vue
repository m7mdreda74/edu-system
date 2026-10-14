<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    preferences: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    preferences: props.preferences.flatMap((type) => type.channels.map((channel) => ({
        type: type.key,
        channel: channel.key,
        enabled: channel.enabled,
    }))),
});

function preference(typeKey, channelKey) {
    return form.preferences.find((item) => item.type === typeKey && item.channel === channelKey);
}

function submit() {
    form.put(route('profile.notification-preferences.update'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-bold text-surface-900 dark:text-white">تفضيلات الإشعارات</h2>
            <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">
                اختر التنبيهات التي تريد استقبالها. الإعدادات الافتراضية مفعلة تلقائيًا.
            </p>
        </header>

        <form @submit.prevent="submit" class="mt-6 space-y-5">
            <div
                v-for="type in preferences"
                :key="type.key"
                class="rounded-xl border border-surface-200 p-4 dark:border-surface-700"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="font-bold text-surface-900 dark:text-white">{{ type.label }}</h3>
                        <p class="mt-1 text-xs leading-5 text-surface-500 dark:text-surface-400">{{ type.description }}</p>
                    </div>

                    <div class="flex flex-wrap gap-4 text-sm">
                        <label
                            v-for="channel in type.channels"
                            :key="channel.key"
                            class="inline-flex cursor-pointer items-center gap-2 text-surface-700 dark:text-surface-300"
                        >
                            <input
                                v-model="preference(type.key, channel.key).enabled"
                                type="checkbox"
                                class="rounded border-surface-300 text-primary-600 focus:ring-primary-500"
                            />
                            {{ channel.label }}
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? 'جارٍ الحفظ...' : 'حفظ التفضيلات' }}
                </button>
                <span v-if="form.recentlySuccessful" class="text-sm font-semibold text-green-600">تم الحفظ.</span>
            </div>
        </form>
    </section>
</template>
