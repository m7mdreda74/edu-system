<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="تفعيل البريد الإلكتروني" />

        <div class="mb-4 text-sm text-surface-600 dark:text-surface-300">
            شكرًا لتسجيلك معنا. قبل البدء، يرجى تفعيل بريدك الإلكتروني من خلال
            الضغط على الرابط الذي أرسلناه إليك. إذا لم يصلك، يمكننا إرسال رابط جديد.
        </div>

        <div
            class="mb-4 text-sm font-medium text-green-600"
            v-if="verificationLinkSent"
        >
            تم إرسال رابط تفعيل جديد إلى البريد الإلكتروني الذي سجلت به.
        </div>

        <form @submit.prevent="submit">
            <div class="mt-4 flex items-center justify-between">
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    إعادة إرسال رسالة التفعيل
                </PrimaryButton>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="text-sm text-surface-600 underline hover:text-primary-700 dark:text-surface-300 dark:hover:text-white focus:outline-none focus:ring-2 focus:ring-accent-500 focus:ring-offset-2"
                    >تسجيل الخروج</Link
                >
            </div>
        </form>
    </GuestLayout>
</template>
