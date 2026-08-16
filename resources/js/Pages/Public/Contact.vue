<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';
import { ref, computed } from 'vue';

const page = usePage();
const settings = computed(() => page.props.settings || {});

const badge = computed(() => settings.value.contact_badge || 'يسعدنا تواصلكم');
const title = computed(() => settings.value.contact_title || 'تواصل معنا');

const whatsappUrl = computed(() => settings.value.whatsapp_url || 'https://wa.me/97455556666');
const whatsappLabel = computed(() => {
    // Extract phone number from wa.me link
    const url = whatsappUrl.value;
    if (url.includes('wa.me/')) {
        return '+' + url.split('wa.me/')[1].replace(/[^0-9]/g, '');
    }
    return '+974 5555 6666';
});

const contactPhone = computed(() => settings.value.contact_phone || '+974 4444 8888');
const contactEmail = computed(() => settings.value.contact_email || 'support@altafawwuq.com');

const formSubmitted = ref(false);
const form = ref({
    name: '',
    email: '',
    phone: '',
    message: ''
});

function handleSubmit() {
    if (form.value.name && form.value.message) {
        formSubmitted.value = true;
        form.value = { name: '', email: '', phone: '', message: '' };
    }
}
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
                                <a v-if="info.href" :href="info.href" target="_blank" class="font-bold text-surface-800 dark:text-white text-sm hover:text-primary-600 transition-colors">
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
                            <span>شكرًا لتواصلك معنا! تم إرسال رسالتك بنجاح وسيقوم فريق الدعم بالرد عليك قريباً.</span>
                        </div>

                        <form @submit.prevent="handleSubmit" class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-surface-600 dark:text-surface-400 mb-1.5">الاسم الكامل *</label>
                                <input v-model="form.name" required type="text" class="input py-2 text-sm w-full" placeholder="اكتب اسمك هنا">
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-surface-600 dark:text-surface-400 mb-1.5">البريد الإلكتروني</label>
                                    <input v-model="form.email" type="email" class="input py-2 text-sm w-full" placeholder="اكتب بريدك الإلكتروني">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-surface-600 dark:text-surface-400 mb-1.5">رقم الهاتف *</label>
                                    <input v-model="form.phone" required type="tel" class="input py-2 text-sm w-full" placeholder="+974 5555 5555">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-surface-600 dark:text-surface-400 mb-1.5">تفاصيل الاستفسار أو الرسالة *</label>
                                <textarea v-model="form.message" required rows="4" class="input py-2 text-sm w-full resize-none" placeholder="اكتب رسالتك أو مشكلتك الفنية بالتفصيل..."></textarea>
                            </div>

                            <button type="submit" class="btn-primary w-full py-2.5 flex items-center justify-center gap-2">
                                <Icon name="chat" class="w-4 h-4 text-white" />
                                <span>إرسال الرسالة</span>
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </AppLayout>
</template>
