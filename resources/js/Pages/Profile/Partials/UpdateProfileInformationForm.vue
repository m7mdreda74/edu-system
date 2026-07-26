<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;
const isTeacher = (user.roles ?? []).includes('teacher');

const form = useForm({
    name: user.name,
    email: user.email,
    avatar: null,
    // Teacher-only public profile fields; the server ignores them otherwise.
    headline: user.headline ?? '',
    bio: user.bio ?? '',
    intro_video_url: user.intro_video_url ?? '',
    intro_video_thumbnail: user.intro_video_thumbnail ?? '',
    years_experience: user.years_experience ?? null,
    _method: 'PATCH',
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-bold text-surface-900 dark:text-white">
                معلومات الحساب
            </h2>

            <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">
                تحديث معلومات حسابك الشخصي والبريد الإلكتروني والصورة الشخصية.
            </p>
        </header>

        <form
            @submit.prevent="form.post(route('profile.update'), { forceFormData: true })"
            class="mt-6 space-y-6"
        >
            <!-- Avatar -->
            <div>
                <InputLabel for="avatar" value="الصورة الشخصية" />
                <div class="mt-2 flex items-center gap-4">
                    <img v-if="user.avatar" :src="user.avatar" class="w-16 h-16 rounded-full object-cover border" />
                    <div v-else class="w-16 h-16 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center text-primary-750 font-bold border">
                        {{ user.name.charAt(0) }}
                    </div>
                    <input
                        id="avatar"
                        type="file"
                        accept="image/*"
                        class="text-sm text-surface-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100"
                        @input="form.avatar = $event.target.files[0]"
                    />
                </div>
                <InputError class="mt-2" :message="form.errors.avatar" />
            </div>

            <div>
                <InputLabel for="name" value="Name" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <!-- ── Teacher public profile ─────────────────────────── -->
            <template v-if="isTeacher">
                <div class="pt-6 border-t border-surface-200 dark:border-surface-700">
                    <h3 class="font-bold text-surface-900 dark:text-white text-sm mb-1">ملفك العام للطلاب</h3>
                    <p class="text-xs text-surface-500 dark:text-surface-400">
                        الطالب يشوف الفيديو التعريفي ونبذتك قبل ما يقرر يحجز معك — كمّلهم عشان تظهر بشكل أفضل.
                    </p>
                </div>

                <div>
                    <InputLabel for="headline" value="العنوان المختصر" />
                    <TextInput
                        id="headline"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.headline"
                        placeholder="معلم رياضيات — 12 سنة خبرة"
                    />
                    <InputError class="mt-2" :message="form.errors.headline" />
                </div>

                <div>
                    <InputLabel for="bio" value="نبذة تعريفية" />
                    <textarea
                        id="bio"
                        v-model="form.bio"
                        rows="4"
                        class="input mt-1 block w-full"
                        placeholder="اكتب عن أسلوبك في الشرح وخبرتك مع المنهج."
                    ></textarea>
                    <InputError class="mt-2" :message="form.errors.bio" />
                </div>

                <div>
                    <InputLabel for="intro_video_url" value="رابط الفيديو التعريفي" />
                    <TextInput
                        id="intro_video_url"
                        type="url"
                        dir="ltr"
                        class="mt-1 block w-full"
                        v-model="form.intro_video_url"
                        placeholder="https://www.youtube.com/watch?v=..."
                    />
                    <p class="text-xs text-surface-400 mt-1">يدعم روابط يوتيوب وفيميو.</p>
                    <InputError class="mt-2" :message="form.errors.intro_video_url" />
                </div>

                <div>
                    <InputLabel for="intro_video_thumbnail" value="صورة مصغّرة للفيديو" />
                    <TextInput
                        id="intro_video_thumbnail"
                        type="url"
                        dir="ltr"
                        class="mt-1 block w-full"
                        v-model="form.intro_video_thumbnail"
                        placeholder="https://..."
                    />
                    <InputError class="mt-2" :message="form.errors.intro_video_thumbnail" />
                </div>

                <div>
                    <InputLabel for="years_experience" value="سنوات الخبرة" />
                    <TextInput
                        id="years_experience"
                        type="number"
                        min="0"
                        max="70"
                        class="mt-1 block w-full"
                        v-model="form.years_experience"
                    />
                    <InputError class="mt-2" :message="form.errors.years_experience" />
                </div>
            </template>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800">
                    Your email address is unverified.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Save</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-gray-600"
                    >
                        Saved.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
