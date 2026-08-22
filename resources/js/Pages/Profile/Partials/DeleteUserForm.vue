<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-lg font-bold text-surface-900 dark:text-white">
                حذف الحساب
            </h2>

            <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">
                بمجرد حذف حسابك، سيتم حذف جميع موارده وبياناته نهائيًا. قبل حذف حسابك، يرجى تنزيل أي بيانات أو معلومات ترغب في الاحتفاظ بها.
            </p>
        </header>

        <DangerButton @click="confirmUserDeletion">حذف الحساب</DangerButton>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6 bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-2xl">
                <h2
                    class="text-lg font-bold text-surface-900 dark:text-white"
                >
                    هل أنت متأكد من رغبتك في حذف حسابك؟
                </h2>

                <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">
                    بمجرد حذف حسابك، سيتم حذف جميع بياناتك ومواردك نهائيًا. يرجى إدخال كلمة المرور لتأكيد رغبتك في حذف الحساب نهائيًا.
                </p>

                <div class="mt-6">
                    <InputLabel
                        for="password"
                        value="كلمة المرور"
                        class="sr-only"
                    />

                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-3/4"
                        maxlength="255"
                        required
                        placeholder="كلمة المرور"
                        @keyup.enter="deleteUser"
                    />

                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeModal">
                        إلغاء
                    </SecondaryButton>

                    <DangerButton
                        class="ms-3"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        حذف الحساب نهائياً
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
