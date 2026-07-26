<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';

defineProps({
    subscription: { type: Object, required: true },
});
</script>

<template>
    <Head title="اشتراك فعّال" />

    <AppLayout>
        <div class="container-app px-4 py-20">
            <div class="card p-10 max-w-md mx-auto text-center">
                <div class="w-16 h-16 rounded-full bg-green-50 dark:bg-green-950 flex items-center justify-center mx-auto mb-5">
                    <Icon name="success" class="w-8 h-8 text-green-500" />
                </div>

                <h1 class="text-xl font-black text-surface-900 dark:text-white mb-2">
                    اشتراكك فعّال بالفعل
                </h1>

                <p class="text-sm text-surface-500 dark:text-surface-400 mb-1">
                    {{ subscription.label }}
                </p>

                <p v-if="subscription.period_end" class="text-xs text-surface-400 mb-6">
                    ساري حتى {{ subscription.period_end }}
                </p>

                <div class="flex flex-col sm:flex-row gap-2 justify-center">
                    <Link
                        v-if="subscription.group"
                        :href="route('student.learn', { groupId: subscription.group.id })"
                        class="btn-primary btn-sm"
                    >
                        ادخل الحصة
                    </Link>
                    <Link :href="route('student.my-classes')" class="btn-outline btn-sm">حصصي</Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
