<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="الملف الشخصي" />

    <DashboardLayout>
        <div class="py-6">
            <div class="mx-auto max-w-3xl space-y-6">
                
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white">
                        الملف الشخصي
                    </h1>
                    <p class="text-sm text-surface-500 mt-1">إدارة معلومات الحساب الشخصي وتعديل كلمة المرور والصورة الشخصية.</p>
                </div>

                <div class="card p-6 sm:p-8 border border-surface-200 dark:border-surface-800">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <div class="card p-6 sm:p-8 border border-surface-200 dark:border-surface-800">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <div class="card p-6 sm:p-8 border border-surface-200 dark:border-surface-800">
                    <DeleteUserForm class="max-w-xl" />
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
