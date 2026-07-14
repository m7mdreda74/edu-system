<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';

const page = usePage();
const settings = computed(() => page.props.settings || {});

const badge = computed(() => settings.value.app_badge || 'تطبيقات التفوق للأجهزة الذكية');
const title = computed(() => settings.value.app_title || 'حمّل تطبيقات المنصة');
const desc = computed(() => settings.value.app_desc || 'لضمان تجربة تعليمية سلسة وخالية من الانقطاع وبث فيديوهات فائق السرعة، حمّل تطبيقات منصة التفوق المخصصة لأجهزة الكمبيوتر والهواتف الذكية.');

const winUrl = computed(() => settings.value.app_win_url || '#');
const macUrl = computed(() => settings.value.app_mac_url || '#');
const iosUrl = computed(() => settings.value.app_ios_url || '#');
const androidUrl = computed(() => settings.value.app_android_url || '#');
const huaweiUrl = computed(() => settings.value.app_huawei_url || '#');
</script>

<template>
    <AppLayout>
        <Head title="تطبيقاتنا" />

        <div class="bg-transparent py-16" dir="rtl">
            <div class="container-app px-4 max-w-4xl text-center">
                <!-- Header -->
                <div class="text-center mb-12">
                    <span class="badge bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300 mb-3 inline-block">{{ badge }}</span>
                    <h1 class="text-3xl md:text-4xl font-black text-surface-900 dark:text-white mb-4">{{ title }}</h1>
                    <p class="text-surface-500 dark:text-surface-400 text-sm leading-relaxed max-w-2xl mx-auto">
                        {{ desc }}
                    </p>
                </div>

                <!-- Apps Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12 text-start">
                    
                    <!-- Desktop Apps Card -->
                    <div class="card p-8 border border-surface-200 dark:border-surface-800 flex flex-col justify-between">
                        <div>
                            <div class="p-3 bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400 rounded-xl w-fit mb-4">
                                <Icon name="globe" class="w-7 h-7" />
                            </div>
                            <h3 class="text-lg font-black text-surface-900 dark:text-white mb-2">تطبيق الكمبيوتر واللابتوب</h3>
                            <p class="text-xs text-surface-500 dark:text-surface-400 leading-relaxed mb-6">
                                يوفر التطبيق أسرع استجابة لتشغيل الفيديوهات وحل الامتحانات والشيتات بملء الشاشة مع حماية الخصوصية ومكافحة برامج الغش وتسجيل الشاشة.
                            </p>
                        </div>
                        <div class="space-y-3">
                            <a :href="winUrl" class="btn-primary w-full py-2.5 flex items-center justify-center gap-2 transition-transform duration-200 hover:scale-102">
                                <Icon name="globe" class="w-4 h-4 text-white" />
                                <span>تنزيل لنظام ويندوز (Windows App)</span>
                            </a>
                            <a :href="macUrl" class="btn-outline w-full py-2.5 flex items-center justify-center gap-2 transition-transform duration-200 hover:scale-102">
                                <Icon name="globe" class="w-4 h-4" />
                                <span>تنزيل لنظام ماك (macOS App)</span>
                            </a>
                        </div>
                    </div>

                    <!-- Mobile Apps Card -->
                    <div class="card p-8 border border-surface-200 dark:border-surface-800 flex flex-col justify-between">
                        <div>
                            <div class="p-3 bg-accent-50 dark:bg-accent-950 text-accent-600 dark:text-accent-400 rounded-xl w-fit mb-4">
                                <Icon name="globe" class="w-7 h-7" />
                            </div>
                            <h3 class="text-lg font-black text-surface-900 dark:text-white mb-2">تطبيقات الهواتف الذكية والآيباد</h3>
                            <p class="text-xs text-surface-550 leading-relaxed mb-6">
                                تابع شروحات معلميك وحل واجباتك وحصص الزووم التفاعلية أثناء التنقل بكل سهولة من جهاز الآيباد أو الهاتف الذكي.
                            </p>
                        </div>
                        <div class="grid grid-cols-1 gap-2.5">
                            <a :href="iosUrl" class="btn-outline w-full py-2 flex items-center justify-center gap-2 text-xs">
                                <Icon name="globe" class="w-4 h-4" />
                                <span>متوفر على Apple App Store</span>
                            </a>
                            <a :href="androidUrl" class="btn-outline w-full py-2 flex items-center justify-center gap-2 text-xs">
                                <Icon name="globe" class="w-4 h-4" />
                                <span>متوفر على Google Play Store</span>
                            </a>
                            <a :href="huaweiUrl" class="btn-outline w-full py-2 flex items-center justify-center gap-2 text-xs">
                                <Icon name="globe" class="w-4 h-4" />
                                <span>متوفر على Huawei AppGallery</span>
                            </a>
                        </div>
                    </div>

                </div>

                <!-- Access Guide -->
                <div class="card p-8 border border-surface-200 dark:border-surface-800 text-start max-w-2xl mx-auto">
                    <h3 class="font-bold text-surface-900 dark:text-white text-base mb-4 text-center">خطوات تسجيل الدخول في التطبيق</h3>
                    <div class="space-y-4 text-xs text-surface-550 leading-relaxed">
                        <div class="flex gap-3 items-start">
                            <div class="w-5 h-5 bg-primary-100 dark:bg-primary-950 text-primary-650 dark:text-primary-400 rounded-full font-bold flex items-center justify-center flex-shrink-0 text-[10px]">١</div>
                            <p>قم بتسجيل الدخول إلى حسابك على موقع منصة التفوق من خلال المتصفح بشكل اعتيادي.</p>
                        </div>
                        <div class="flex gap-3 items-start">
                            <div class="w-5 h-5 bg-primary-100 dark:bg-primary-950 text-primary-650 dark:text-primary-400 rounded-full font-bold flex items-center justify-center flex-shrink-0 text-[10px]">٢</div>
                            <p>توجه إلى حسابك الشخصي (الملف التعريفي) لتوليد رمز الاستجابة السريعة (QR Code) أو رقم الوصول الحصري.</p>
                        </div>
                        <div class="flex gap-3 items-start">
                            <div class="w-5 h-5 bg-primary-100 dark:bg-primary-950 text-primary-650 dark:text-primary-400 rounded-full font-bold flex items-center justify-center flex-shrink-0 text-[10px]">٣</div>
                            <p>افتح التطبيق المنزّل على جهازك، واضغط على "تسجيل عبر الباركود"، ووجه كاميرا الهاتف نحو الشاشة ليتم الدخول فوراً وبأمان.</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
