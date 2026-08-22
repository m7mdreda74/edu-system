<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    turnstileSiteKey: { type: String, default: '' },
});

const page = usePage();
const settings = computed(() => page.props.settings || {});

const badge = computed(() => settings.value.contact_badge || 'يسعدنا تواصلكم');
const title = computed(() => settings.value.contact_title || 'تواصل معنا');

const whatsappUrl = computed(() => settings.value.whatsapp_url || 'https://wa.me/97455556666');
const whatsappLabel = computed(() => {
    const url = whatsappUrl.value;

    if (url.includes('wa.me/')) {
        return '+' + url.split('wa.me/')[1].replace(/[^0-9]/g, '');
    }

    return '+974 5555 6666';
});

const contactPhone = computed(() => settings.value.contact_phone || '+974 4444 8888');
const contactEmail = computed(() => settings.value.contact_email || 'support@altafawwuq.com');
const formSubmitted = ref(false);
const captchaContainer = ref(null);
const captchaError = ref('');
const captchaConfigured = computed(() => Boolean(props.turnstileSiteKey));

const form = useForm({
    name: '',
    email: '',
    phone: '',
    message: '',
    captcha_token: '',
});

const nameLength = computed(() => Array.from(form.name).length);
const messageLength = computed(() => Array.from(form.message).length);
const messageWordCount = computed(() => form.message.trim().split(/\s+/u).filter(Boolean).length);

let captchaWidgetId = null;
let captchaScript = null;

function normalizePhone(value) {
    return value
        .replace(/[\u0660-\u0669]/g, (digit) => String(digit.codePointAt(0) - 0x0660))
        .replace(/[\u06F0-\u06F9]/g, (digit) => String(digit.codePointAt(0) - 0x06F0))
        .replace(/[\s().-]+/g, '');
}

function validPhone(value) {
    return /^(?:(?:\+|00)[1-9][0-9]{6,14}|0[1-9][0-9]{6,14}|[1-9][0-9]{6,14})$/.test(normalizePhone(value));
}

function renderTurnstile() {
    if (!captchaConfigured.value || !captchaContainer.value || !window.turnstile || captchaWidgetId !== null) {
        return;
    }

    captchaWidgetId = window.turnstile.render(captchaContainer.value, {
        sitekey: props.turnstileSiteKey,
        theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
        callback: (token) => {
            form.captcha_token = token;
            captchaError.value = '';
        },
        'expired-callback': () => {
            form.captcha_token = '';
            captchaError.value = 'انتهت صلاحية التحقق الأمني. أعد التحقق قبل الإرسال.';
        },
        'error-callback': () => {
            form.captcha_token = '';
            captchaError.value = 'تعذر تحميل التحقق الأمني. حدّث الصفحة وحاول مرة أخرى.';
        },
    });
}

function loadTurnstile() {
    if (!captchaConfigured.value) {
        return;
    }

    if (window.turnstile) {
        renderTurnstile();
        return;
    }

    captchaScript = document.querySelector('script[data-altafawwuq-turnstile]');

    if (!captchaScript) {
        captchaScript = document.createElement('script');
        captchaScript.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        captchaScript.async = true;
        captchaScript.defer = true;
        captchaScript.dataset.altafawwuqTurnstile = 'true';
        document.head.appendChild(captchaScript);
    }

    captchaScript.addEventListener('load', renderTurnstile, { once: true });
    captchaScript.addEventListener('error', () => {
        captchaError.value = 'تعذر تحميل التحقق الأمني. حدّث الصفحة وحاول مرة أخرى.';
    }, { once: true });
}

function resetTurnstile() {
    form.captcha_token = '';

    if (captchaWidgetId !== null && window.turnstile) {
        window.turnstile.reset(captchaWidgetId);
    }
}

function validateBeforeSubmit() {
    form.clearErrors();
    captchaError.value = '';

    if (nameLength.value > 100) {
        form.setError('name', 'الاسم يجب ألا يتجاوز 100 حرف.');
        return false;
    }

    if (!validPhone(form.phone)) {
        form.setError('phone', 'أدخل رقم هاتف صحيحًا مع مفتاح الدولة عند الحاجة.');
        return false;
    }

    if (messageWordCount.value < 2) {
        form.setError('message', 'اكتب الرسالة في كلمتين على الأقل.');
        return false;
    }

    if (messageLength.value > 5000) {
        form.setError('message', 'تفاصيل الاستفسار يجب ألا تتجاوز 5000 حرف.');
        return false;
    }

    if (!captchaConfigured.value) {
        captchaError.value = 'التحقق الأمني غير متاح حاليًا. يرجى المحاولة لاحقًا.';
        return false;
    }

    if (!form.captcha_token) {
        captchaError.value = 'يرجى إكمال التحقق الأمني أولًا.';
        return false;
    }

    return true;
}

function handleSubmit() {
    if (!validateBeforeSubmit()) {
        return;
    }

    form.post(route('contact.store'), {
        preserveScroll: true,
        onSuccess: () => {
            formSubmitted.value = true;
            form.reset();
            resetTurnstile();
        },
        onError: () => {
            resetTurnstile();
        },
    });
}

onMounted(loadTurnstile);

onBeforeUnmount(() => {
    if (captchaWidgetId !== null && window.turnstile?.remove) {
        window.turnstile.remove(captchaWidgetId);
    }
});
</script>

<template>
    <AppLayout>
        <Head title="تواصل معنا" />

        <div class="bg-transparent py-16" dir="rtl">
            <div class="container-app px-4 max-w-4xl">
                <!-- Header -->
                <div class="text-center mb-12">
                    <span class="badge bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300 mb-3 inline-block">{{ badge }}</span>
                    <h1 class="text-3xl md:text-4xl font-black text-surface-900 dark:text-white mb-4">{{ title }}</h1>
                    <p class="text-surface-500 dark:text-surface-400 text-sm leading-relaxed max-w-2xl mx-auto">
                        هل لديك أي استفسار حول الحصص والمعلمين أو تحتاج إلى دعم فني؟ فريق الدعم متواجد لخدمتك طوال أيام الأسبوع.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-start">
                    <!-- Contact Cards (5 cols) -->
                    <div class="md:col-span-5 space-y-4">
                        <div v-for="info in [
                            { title: 'الدعم المالي والفني (واتساب)', value: whatsappLabel, icon: 'chat', href: whatsappUrl },
                            { title: 'الاتصال الهاتفي الساخن', value: contactPhone, icon: 'phone', href: 'tel:' + contactPhone.replace(/\s+/g, '') },
                            { title: 'البريد الإلكتروني الرسمي', value: contactEmail, icon: 'globe', href: 'mailto:' + contactEmail }
                        ]" :key="info.title" class="card p-5 border border-surface-200 dark:border-surface-800 flex items-start gap-4">
                            <div class="p-3 bg-primary-50 dark:bg-primary-950/40 text-primary-600 dark:text-primary-400 rounded-xl flex-shrink-0">
                                <Icon :name="info.icon" class="w-5 h-5" />
                            </div>
                            <div>
                                <h4 class="font-bold text-surface-400 text-xs mb-1">{{ info.title }}</h4>
                                <a v-if="info.href" :href="info.href" target="_blank" rel="noopener noreferrer" class="font-bold text-surface-800 dark:text-white text-sm hover:text-primary-600 transition-colors">
                                    {{ info.value }}
                                </a>
                                <span v-else class="font-bold text-surface-800 dark:text-white text-sm">{{ info.value }}</span>
                            </div>
                        </div>

                        <!-- Working hours -->
                        <div class="card p-5 border border-surface-200 dark:border-surface-800 text-xs text-surface-500 leading-relaxed">
                            <strong class="text-surface-700 dark:text-white block mb-1">أوقات العمل الرسمية:</strong>
                            من السبت إلى الخميس: 9:00 صباحاً حتى 9:00 مساءً<br>
                            يوم الجمعة: 2:00 ظهراً حتى 8:00 مساءً
                        </div>
                    </div>

                    <!-- Contact Form (7 cols) -->
                    <div class="md:col-span-7 card p-6 md:p-8 border border-surface-200 dark:border-surface-800">
                        <h3 class="font-bold text-surface-850 dark:text-white text-lg mb-4">أرسل لنا رسالة مباشرة</h3>

                        <div v-if="formSubmitted" class="alert-success p-4 rounded-xl flex items-center gap-3 mb-4 text-sm">
                            <Icon name="success" class="w-5 h-5 text-green-500 shrink-0" />
                            <span>شكرًا لتواصلك معنا! تم إرسال رسالتك بنجاح وسيقوم فريق الدعم بالرد عليك قريبًا.</span>
                        </div>

                        <div v-if="page.props.flash?.error" class="alert-error p-4 rounded-xl flex items-center gap-3 mb-4 text-sm">
                            <Icon name="error" class="w-5 h-5 text-red-500 shrink-0" />
                            <span>{{ page.props.flash.error }}</span>
                        </div>

                        <form @submit.prevent="handleSubmit" class="space-y-4">
                            <div>
                                <label for="contact-name" class="block text-xs font-semibold text-surface-600 dark:text-surface-400 mb-1.5">الاسم الكامل *</label>
                                <input
                                    id="contact-name"
                                    v-model="form.name"
                                    required
                                    maxlength="100"
                                    type="text"
                                    autocomplete="name"
                                    class="input py-2 text-sm w-full"
                                    :class="{ 'ring-2 ring-red-500': form.errors.name }"
                                    placeholder="اكتب اسمك هنا"
                                >
                                <div class="flex items-center justify-between mt-1">
                                    <p v-if="form.errors.name" class="text-red-500 text-xs">{{ form.errors.name }}</p>
                                    <span class="text-[10px] text-surface-400 mr-auto">{{ nameLength }}/100</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="contact-email" class="block text-xs font-semibold text-surface-600 dark:text-surface-400 mb-1.5">البريد الإلكتروني</label>
                                    <input
                                        id="contact-email"
                                        v-model="form.email"
                                        type="email"
                                        maxlength="255"
                                        autocomplete="email"
                                        class="input py-2 text-sm w-full"
                                        :class="{ 'ring-2 ring-red-500': form.errors.email }"
                                        placeholder="اكتب بريدك الإلكتروني"
                                    >
                                    <p v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</p>
                                </div>
                                <div>
                                    <label for="contact-phone" class="block text-xs font-semibold text-surface-600 dark:text-surface-400 mb-1.5">رقم الهاتف *</label>
                                    <input
                                        id="contact-phone"
                                        v-model="form.phone"
                                        required
                                        maxlength="25"
                                        type="tel"
                                        inputmode="tel"
                                        autocomplete="tel"
                                        dir="ltr"
                                        class="input py-2 text-sm w-full text-left"
                                        :class="{ 'ring-2 ring-red-500': form.errors.phone }"
                                        placeholder="+974 5555 5555"
                                    >
                                    <p v-if="form.errors.phone" class="text-red-500 text-xs mt-1">{{ form.errors.phone }}</p>
                                </div>
                            </div>

                            <div>
                                <label for="contact-message" class="block text-xs font-semibold text-surface-600 dark:text-surface-400 mb-1.5">تفاصيل الاستفسار أو الرسالة *</label>
                                <textarea
                                    id="contact-message"
                                    v-model="form.message"
                                    required
                                    maxlength="5000"
                                    rows="5"
                                    class="input py-2 text-sm w-full resize-none"
                                    :class="{ 'ring-2 ring-red-500': form.errors.message }"
                                    placeholder="اكتب رسالتك أو مشكلتك الفنية بالتفصيل..."
                                ></textarea>
                                <div class="flex items-center justify-between mt-1">
                                    <p v-if="form.errors.message" class="text-red-500 text-xs">{{ form.errors.message }}</p>
                                    <span class="text-[10px] text-surface-400 mr-auto">{{ messageWordCount }} كلمة · {{ messageLength }}/5000</span>
                                </div>
                            </div>

                            <div class="rounded-xl border border-surface-200 dark:border-surface-700 p-3">
                                <div ref="captchaContainer" class="min-h-[65px] flex items-center justify-center" dir="ltr"></div>
                                <p v-if="!captchaConfigured" class="text-center text-xs text-amber-600 dark:text-amber-300">التحقق الأمني غير متاح حاليًا.</p>
                                <p v-if="captchaError || form.errors.captcha_token" class="text-center text-red-500 text-xs mt-1">
                                    {{ captchaError || form.errors.captcha_token }}
                                </p>
                            </div>

                            <button
                                type="submit"
                                :disabled="form.processing || !captchaConfigured"
                                class="btn-primary w-full py-2.5 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <Icon name="chat" class="w-4 h-4 text-white" />
                                <span>{{ form.processing ? 'جارٍ الإرسال...' : 'إرسال الرسالة' }}</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
