<script setup>
import { ref, computed } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import axios from 'axios';

const props = defineProps({
    dbSettings: { type: Object, required: true },
});

// Current active editor page: 'home', 'about', 'apps', 'contact', 'popup' or null (for dashboard cards view)
const activePage = ref(null);
const isSaving = ref(false);
const wasSaved = ref(false);

// Parse JSON fields safely helper
function parseJson(str, defaultValue) {
    if (!str) return defaultValue;
    try {
        return typeof str === 'string' ? JSON.parse(str) : str;
    } catch (e) {
        return defaultValue;
    }
}

function parseBooleanSetting(value, defaultValue = false) {
    if (value === undefined || value === null || value === '') return defaultValue;

    return value === true || value === 1 || value === '1' || value === 'true';
}

// Convert all settings into localized edit form
const form = useForm({
    // Home Page Settings
    home_hero_badge: props.dbSettings.home_hero_badge || '',
    home_hero_title: props.dbSettings.home_hero_title || '',
    home_hero_subtitle: props.dbSettings.home_hero_subtitle || '',
    home_hero_desc: props.dbSettings.home_hero_desc || '',
    home_hero_btn1: props.dbSettings.home_hero_btn1 || '',
    home_hero_btn2: props.dbSettings.home_hero_btn2 || '',
    home_cta_title: props.dbSettings.home_cta_title || '',
    home_cta_desc: props.dbSettings.home_cta_desc || '',
    home_cta_btn: props.dbSettings.home_cta_btn || '',
    home_features: parseJson(props.dbSettings.home_features, []),
    home_results: parseJson(props.dbSettings.home_results, []),
    home_why_choose_us: parseJson(props.dbSettings.home_why_choose_us, []),
    home_youtube_videos: parseJson(props.dbSettings.home_youtube_videos, []),
    home_youtube_visible: parseBooleanSetting(props.dbSettings.home_youtube_visible, true),
    home_faqs: parseJson(props.dbSettings.home_faqs, []),

    // About Us Page Settings
    about_title: props.dbSettings.about_title || '',
    about_badge: props.dbSettings.about_badge || '',
    about_desc: props.dbSettings.about_desc || '',
    about_values: parseJson(props.dbSettings.about_values, []),
    about_pillars: parseJson(props.dbSettings.about_pillars, []),

    // Our Apps Page Settings
    app_title: props.dbSettings.app_title || '',
    app_badge: props.dbSettings.app_badge || '',
    app_desc: props.dbSettings.app_desc || '',
    app_win_url: props.dbSettings.app_win_url || '',
    app_mac_url: props.dbSettings.app_mac_url || '',
    app_ios_url: props.dbSettings.app_ios_url || '',
    app_android_url: props.dbSettings.app_android_url || '',
    app_huawei_url: props.dbSettings.app_huawei_url || '',

    // Contact Us Page Settings
    contact_title: props.dbSettings.contact_title || '',
    contact_badge: props.dbSettings.contact_badge || '',
    contact_email: props.dbSettings.contact_email || '',
    contact_phone: props.dbSettings.contact_phone || '',
    whatsapp_url: props.dbSettings.whatsapp_url || '',

    // Welcome Popup Settings
    welcome_popup_active: props.dbSettings.welcome_popup_active === 'true' || props.dbSettings.welcome_popup_active === true,
    welcome_popup_title: props.dbSettings.welcome_popup_title || '',
    welcome_popup_bottom_label: props.dbSettings.welcome_popup_bottom_label || '',
    welcome_popup_bottom_url: props.dbSettings.welcome_popup_bottom_url || '',
    
    welcome_popup_item1_label: props.dbSettings.welcome_popup_item1_label || '',
    welcome_popup_item1_url: props.dbSettings.welcome_popup_item1_url || '',
    
    welcome_popup_item2_label: props.dbSettings.welcome_popup_item2_label || '',
    welcome_popup_item2_url: props.dbSettings.welcome_popup_item2_url || '',
    
    welcome_popup_item3_label: props.dbSettings.welcome_popup_item3_label || '',
    welcome_popup_item3_url: props.dbSettings.welcome_popup_item3_url || '',
    
    welcome_popup_item4_label: props.dbSettings.welcome_popup_item4_label || '',
    welcome_popup_item4_url: props.dbSettings.welcome_popup_item4_url || '',
    
    welcome_popup_item5_label: props.dbSettings.welcome_popup_item5_label || '',
    welcome_popup_item5_url: props.dbSettings.welcome_popup_item5_url || '',
    
    welcome_popup_item6_label: props.dbSettings.welcome_popup_item6_label || '',
    welcome_popup_item6_url: props.dbSettings.welcome_popup_item6_url || '',
});

// Save settings for a specific page
async function submitPageSettings(pageKey) {
    const payload = {};
    
    // Filter payload keys depending on the current active page
    let fields = [];
    if (pageKey === 'home') {
        fields = [
            'home_hero_badge', 'home_hero_title', 'home_hero_subtitle', 'home_hero_desc',
            'home_hero_btn1', 'home_hero_btn2',
            'home_cta_title', 'home_cta_desc', 'home_cta_btn',
            'home_features', 'home_results', 'home_why_choose_us',
            'home_youtube_videos', 'home_youtube_visible', 'home_faqs'
        ];
    } else if (pageKey === 'about') {
        fields = ['about_title', 'about_badge', 'about_desc', 'about_values', 'about_pillars'];
    } else if (pageKey === 'apps') {
        fields = ['app_title', 'app_badge', 'app_desc', 'app_win_url', 'app_mac_url', 'app_ios_url', 'app_android_url', 'app_huawei_url'];
    } else if (pageKey === 'contact') {
        fields = ['contact_title', 'contact_badge', 'contact_email', 'contact_phone', 'whatsapp_url'];
    } else if (pageKey === 'popup') {
        fields = [
            'welcome_popup_active', 'welcome_popup_title', 'welcome_popup_bottom_label', 'welcome_popup_bottom_url',
            'welcome_popup_item1_label', 'welcome_popup_item1_url',
            'welcome_popup_item2_label', 'welcome_popup_item2_url',
            'welcome_popup_item3_label', 'welcome_popup_item3_url',
            'welcome_popup_item4_label', 'welcome_popup_item4_url',
            'welcome_popup_item5_label', 'welcome_popup_item5_url',
            'welcome_popup_item6_label', 'welcome_popup_item6_url'
        ];
    }

    fields.forEach(f => {
        payload[f] = form[f];
    });

    if (pageKey === 'home') {
        payload.home_results = (form.home_results || []).map(({ school: _school, ...result }) => ({
            ...result,
            grade: result.grade || '',
        }));
    }

    // Special type cast for active status boolean
    if (pageKey === 'popup') {
        payload['welcome_popup_active'] = form.welcome_popup_active ? 'true' : 'false';
    }

    if (pageKey === 'home') {
        payload.home_youtube_visible = form.home_youtube_visible ? 'true' : 'false';
    }

    isSaving.value = true;
    wasSaved.value = false;
    form.clearErrors();

    try {
        await axios.post(route('admin.site-pages.update'), {
            settings: payload,
        }, {
            headers: { Accept: 'application/json' },
        });
        form.defaults();
        form.reset();
        wasSaved.value = true;
        window.setTimeout(() => {
            wasSaved.value = false;
        }, 2500);
    } catch (error) {
        if (error.response?.status === 422) {
            Object.entries(error.response.data.errors ?? {}).forEach(([key, messages]) => {
                form.setError(key, Array.isArray(messages) ? messages[0] : messages);
            });
        }
    } finally {
        isSaving.value = false;
    }
}

// ── Array Item Managers helpers ───────────────────────────────────────
function addFeature() {
    form.home_features.push({ title: 'ميزة جديدة', desc: 'تفاصيل الميزة والوصف القصير...', icon: 'courses' });
}
function addResult() {
    form.home_results.push({ name: 'اسم الطالب', title: 'دفعة ٢٠٢٦', grade: 'الصف الثاني عشر', desc: 'تفاصيل النتيجة والمعدل...' });
}
function addWhyUs() {
    form.home_why_choose_us.push({ icon: 'globe', title: 'العنوان التوضيحي', desc: 'وصف الميزة التنافسية بالتفصيل...' });
}
function addYoutube() {
    form.home_youtube_videos.push({ title: 'عنوان الفيديو التوضيحي المالي أو التقني', url: 'https://youtube.com/watch?v=...', thumbnail: 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=500&q=80' });
}
function addFaq() {
    form.home_faqs.push({ q: 'السؤال الشائع؟', a: 'الإجابة الشاملة والدقيقة...' });
}
function addAboutValue() {
    form.about_values.push({ title: 'رؤية/رسالة جديدة', desc: 'الوصف والتفاصيل هنا...', icon: 'student' });
}
function addAboutPillar() {
    form.about_pillars.push({ title: 'ركيزة أساسية جديدة', desc: 'شرح وتفاصيل الركيزة للتفوق والتدريس...' });
}
</script>

<template>
    <DashboardLayout>
        <Head title="إدارة صفحات ومحتوى الموقع" />

        <div class="container-app px-4 py-8 max-w-5xl" dir="rtl">
            
            <!-- Header bar -->
            <div class="flex items-center gap-3 mb-8">
                <button v-if="activePage" type="button" @click="activePage = null" class="btn-ghost p-2 rounded-lg" title="رجوع للصفحات" aria-label="العودة إلى صفحات الموقع">
                    <Icon name="arrowRight" class="w-5 h-5" />
                </button>
                <div>
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <Icon name="courses" class="w-7 h-7 text-primary-500" />
                        <span v-if="!activePage">إدارة صفحات ومحتوى الموقع</span>
                        <span v-else-if="activePage === 'home'">تعديل الصفحة الرئيسية</span>
                        <span v-else-if="activePage === 'about'">تعديل صفحة من نحن</span>
                        <span v-else-if="activePage === 'apps'">تعديل صفحة تطبيقاتنا</span>
                        <span v-else-if="activePage === 'contact'">تعديل صفحة تواصل معنا</span>
                        <span v-else-if="activePage === 'popup'">تعديل النافذة الترحيبية</span>
                    </h1>
                    <p class="text-xs text-surface-500 mt-1">
                        <span v-if="!activePage">تعديل محتويات صفحات المنصة العامة والواجهات التفاعلية دون لمس الأكواد.</span>
                        <span v-else>قم بتعديل الحقول المطلوبة بالأسفل ثم اضغط على حفظ التغييرات.</span>
                    </p>
                </div>
            </div>

            <!-- ── Cards grid (Main View) ────────────────────────────────── -->
            <div v-if="!activePage" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Home Page Card -->
                <button type="button" @click="activePage = 'home'" class="card p-6 text-start flex items-start gap-4 hover:shadow-card-hover hover:border-primary-400 transition-all duration-300 transform hover:-translate-y-1 group">
                    <div class="p-3.5 bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400 rounded-2xl group-hover:scale-105 transition-transform">
                        <Icon name="dashboard" class="w-7 h-7" />
                    </div>
                    <div>
                        <h3 class="font-bold text-surface-900 dark:text-white text-base mb-1">الصفحة الرئيسية</h3>
                        <p class="text-xs text-surface-500 leading-relaxed">تحرير واجهة الهيرو، الأرقام والإحصائيات، كروت الشرح، المتفوقين، يوتيوب والأسئلة الشائعة.</p>
                    </div>
                </button>

                <!-- About Us Card -->
                <button type="button" @click="activePage = 'about'" class="card p-6 text-start flex items-start gap-4 hover:shadow-card-hover hover:border-primary-400 transition-all duration-300 transform hover:-translate-y-1 group">
                    <div class="p-3.5 bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400 rounded-2xl group-hover:scale-105 transition-transform">
                        <Icon name="student" class="w-7 h-7" />
                    </div>
                    <div>
                        <h3 class="font-bold text-surface-900 dark:text-white text-base mb-1">صفحة من نحن</h3>
                        <p class="text-xs text-surface-500 leading-relaxed">تحرير نصوص رؤية ورسالة المنصة، والركائز الأساسية لعملية التدريس والتقويم التعليمي.</p>
                    </div>
                </button>

                <!-- Our Apps Card -->
                <button type="button" @click="activePage = 'apps'" class="card p-6 text-start flex items-start gap-4 hover:shadow-card-hover hover:border-primary-400 transition-all duration-300 transform hover:-translate-y-1 group">
                    <div class="p-3.5 bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400 rounded-2xl group-hover:scale-105 transition-transform">
                        <Icon name="globe" class="w-7 h-7" />
                    </div>
                    <div>
                        <h3 class="font-bold text-surface-900 dark:text-white text-base mb-1">صفحة تطبيقاتنا</h3>
                        <p class="text-xs text-surface-500 leading-relaxed">تحرير روابط تحميل تطبيقات الكمبيوتر (ويندوز، ماك) وتطبيقات الأجهزة الذكية والآيباد.</p>
                    </div>
                </button>

                <!-- Contact Us Card -->
                <button type="button" @click="activePage = 'contact'" class="card p-6 text-start flex items-start gap-4 hover:shadow-card-hover hover:border-primary-400 transition-all duration-300 transform hover:-translate-y-1 group">
                    <div class="p-3.5 bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400 rounded-2xl group-hover:scale-105 transition-transform">
                        <Icon name="chat" class="w-7 h-7" />
                    </div>
                    <div>
                        <h3 class="font-bold text-surface-900 dark:text-white text-base mb-1">صفحة تواصل معنا</h3>
                        <p class="text-xs text-surface-500 leading-relaxed">إدارة أرقام الهواتف القطرية، البريد الإلكتروني الساخن، ورابط الدعم المباشر على واتساب.</p>
                    </div>
                </button>

                <!-- Welcome Popup Card -->
                <button type="button" @click="activePage = 'popup'" class="card p-6 text-start flex items-start gap-4 hover:shadow-card-hover hover:border-primary-400 transition-all duration-300 transform hover:-translate-y-1 group">
                    <div class="p-3.5 bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400 rounded-2xl group-hover:scale-105 transition-transform">
                        <Icon name="live" class="w-7 h-7" />
                    </div>
                    <div>
                        <h3 class="font-bold text-surface-900 dark:text-white text-base mb-1">النافذة الترحيبية</h3>
                        <p class="text-xs text-surface-500 leading-relaxed">تفعيل بوب اب الإرشادات العام للطلاب عند الدخول، وإدارة الكروت الستة التعليمية بفيديوهاتها.</p>
                    </div>
                </button>
            </div>

            <!-- ── Editor Views ──────────────────────────────────────────── -->
            <div v-else class="space-y-6 pb-36">

                <!-- 1. Home Page Editor -->
                <div v-if="activePage === 'home'" class="space-y-6">
                    <!-- Hero Section -->
                    <div class="card p-6">
                        <h3 class="font-bold text-sm text-surface-800 dark:text-white border-b border-surface-200 dark:border-surface-800 pb-3 mb-4">قسم الهيرو الرئيسي (Hero Section)</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="label mb-1 block">الشارة أعلى العنوان الرئيسي</label>
                                <input v-model="form.home_hero_badge" type="text" class="input text-xs w-full">
                            </div>
                            <div>
                                <label class="label mb-1 block">العنوان العريض الأول</label>
                                <input v-model="form.home_hero_title" type="text" class="input text-xs w-full">
                            </div>
                            <div>
                                <label class="label mb-1 block">العنوان الفرعي الثاني</label>
                                <input v-model="form.home_hero_subtitle" type="text" class="input text-xs w-full">
                            </div>
                            <div class="md:col-span-2">
                                <label class="label mb-1 block">نص الوصف والتحفيز</label>
                                <textarea v-model="form.home_hero_desc" rows="3" class="input text-xs w-full"></textarea>
                            </div>
                            <div>
                                <label class="label mb-1 block">نص الزر الأول (ابدأ الآن)</label>
                                <input v-model="form.home_hero_btn1" type="text" class="input text-xs w-full">
                            </div>
                            <div>
                                <label class="label mb-1 block">نص الزر الثاني (إنشاء حساب)</label>
                                <input v-model="form.home_hero_btn2" type="text" class="input text-xs w-full">
                            </div>
                        </div>
                    </div>

                    <!-- Features list manager -->
                    <div class="card p-6">
                        <div class="flex justify-between items-center border-b border-surface-200 dark:border-surface-800 pb-3 mb-4">
                            <h3 class="font-bold text-sm text-surface-800 dark:text-white">مميزات المنصة (شريط المميزات)</h3>
                            <button type="button" @click="addFeature" class="btn-outline text-[10px] py-1 px-3.5">+ إضافة ميزة</button>
                        </div>
                        <div class="space-y-4">
                            <div v-for="(feat, idx) in form.home_features" :key="idx" class="border border-surface-200 dark:border-surface-800 rounded-2xl p-4 bg-surface-50/40 dark:bg-surface-800/40 space-y-3 relative animate-fade-up">
                                <div class="flex justify-between items-center border-b border-surface-100 dark:border-surface-700 pb-2 mb-2">
                                    <span class="text-[10px] font-bold text-surface-500">البند #{{ idx + 1 }}</span>
                                    <button type="button" @click="form.home_features.splice(idx, 1)" class="text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 py-1 px-2.5 rounded-lg text-[10px] font-black flex items-center gap-1.5 transition-colors">
                                        <Icon name="close" class="w-3 h-3 text-red-500 shrink-0" />
                                        <span>حذف البند</span>
                                    </button>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="md:col-span-2">
                                        <label class="label mb-1 block text-[10px]">العنوان</label>
                                        <input v-model="feat.title" type="text" class="input text-xs w-full">
                                    </div>
                                    <div>
                                        <label class="label mb-1 block text-[10px]">الأيقونة</label>
                                        <select v-model="feat.icon" class="input text-xs w-full">
                                            <option value="courses">مقررات (courses)</option>
                                            <option value="live">بث مباشر (live)</option>
                                            <option value="chart">أداء (chart)</option>
                                            <option value="teacher">معلم (teacher)</option>
                                        </select>
                                    </div>
                                    <div class="md:col-span-3">
                                        <label class="label mb-1 block text-[10px]">الوصف</label>
                                        <textarea v-model="feat.desc" rows="2" class="input text-xs w-full"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Student results manager -->
                    <div class="card p-6">
                        <div class="flex justify-between items-center border-b border-surface-200 dark:border-surface-800 pb-3 mb-4">
                            <h3 class="font-bold text-sm text-surface-800 dark:text-white">لوحة شرف متفوقي قطر بالرئيسية</h3>
                            <button type="button" @click="addResult" class="btn-outline text-[10px] py-1 px-3.5">+ إضافة طالب متفوق</button>
                        </div>
                        <div class="space-y-4">
                            <div v-for="(res, idx) in form.home_results" :key="idx" class="border border-surface-200 dark:border-surface-800 rounded-2xl p-4 bg-surface-50/40 dark:bg-surface-800/40 space-y-3 relative animate-fade-up">
                                <div class="flex justify-between items-center border-b border-surface-100 dark:border-surface-700 pb-2 mb-2">
                                    <span class="text-[10px] font-bold text-surface-500">الطالب المتفوق #{{ idx + 1 }}</span>
                                    <button type="button" @click="form.home_results.splice(idx, 1)" class="text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 py-1 px-2.5 rounded-lg text-[10px] font-black flex items-center gap-1.5 transition-colors">
                                        <Icon name="close" class="w-3 h-3 text-red-500 shrink-0" />
                                        <span>حذف الطالب</span>
                                    </button>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="label mb-1 block text-[10px]">اسم الطالب</label>
                                        <input v-model="res.name" type="text" class="input text-xs w-full">
                                    </div>
                                    <div>
                                        <label class="label mb-1 block text-[10px]">الدفعة والفصل</label>
                                        <input v-model="res.title" type="text" class="input text-xs w-full" placeholder="دفعة ٢٠٢٦">
                                    </div>
                                    <div>
                                        <label class="label mb-1 block text-[10px]">الصف الدراسي</label>
                                        <input v-model="res.grade" type="text" class="input text-xs w-full" placeholder="الصف الثاني عشر">
                                    </div>
                                    <div class="md:col-span-3">
                                        <label class="label mb-1 block text-[10px]">نص الإشادة والتفوق</label>
                                        <textarea v-model="res.desc" rows="2" class="input text-xs w-full"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Why choose us manager -->
                    <div class="card p-6">
                        <div class="flex justify-between items-center border-b border-surface-200 dark:border-surface-800 pb-3 mb-4">
                            <h3 class="font-bold text-sm text-surface-800 dark:text-white">لماذا التفوق خيارك الأول (الميزات الستة)</h3>
                            <button type="button" @click="addWhyUs" class="btn-outline text-[10px] py-1 px-3.5">+ إضافة عنصر</button>
                        </div>
                        <div class="space-y-4">
                            <div v-for="(item, idx) in form.home_why_choose_us" :key="idx" class="border border-surface-200 dark:border-surface-800 rounded-2xl p-4 bg-surface-50/40 dark:bg-surface-800/40 space-y-3 relative animate-fade-up">
                                <div class="flex justify-between items-center border-b border-surface-100 dark:border-surface-700 pb-2 mb-2">
                                    <span class="text-[10px] font-bold text-surface-500">العنصر #{{ idx + 1 }}</span>
                                    <button type="button" @click="form.home_why_choose_us.splice(idx, 1)" class="text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 py-1 px-2.5 rounded-lg text-[10px] font-black flex items-center gap-1.5 transition-colors">
                                        <Icon name="close" class="w-3 h-3 text-red-500 shrink-0" />
                                        <span>حذف العنصر</span>
                                    </button>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="md:col-span-2">
                                        <label class="label mb-1 block text-[10px]">العنوان</label>
                                        <input v-model="item.title" type="text" class="input text-xs w-full">
                                    </div>
                                    <div>
                                        <label class="label mb-1 block text-[10px]">الأيقونة</label>
                                        <select v-model="item.icon" class="input text-xs w-full">
                                            <option value="globe">مرن (globe)</option>
                                            <option value="video">فيديو (video)</option>
                                            <option value="info">صورة وصوت (info)</option>
                                            <option value="success">تبسيط (success)</option>
                                            <option value="chart">التزام بالخطة (chart)</option>
                                            <option value="settings">تقنية متطورة (settings)</option>
                                        </select>
                                    </div>
                                    <div class="md:col-span-3">
                                        <label class="label mb-1 block text-[10px]">الوصف</label>
                                        <textarea v-model="item.desc" rows="2" class="input text-xs w-full"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- YouTube Videos -->
                    <div class="card p-6">
                        <div class="flex justify-between items-center border-b border-surface-200 dark:border-surface-800 pb-3 mb-4">
                            <h3 class="font-bold text-sm text-surface-800 dark:text-white">شروحات ومراجعات يوتيوب التفوق</h3>
                            <button type="button" @click="addYoutube" class="btn-outline text-[10px] py-1 px-3.5">+ إضافة فيديو</button>
                        </div>
                        <label class="flex items-start gap-3 rounded-xl border border-primary-100 dark:border-primary-900/50 bg-primary-50/50 dark:bg-primary-950/20 p-3 mb-4 cursor-pointer">
                            <input v-model="form.home_youtube_visible" type="checkbox" class="mt-0.5 rounded border-surface-300 text-primary-600 focus:ring-primary-500">
                            <span>
                                <span class="block text-xs font-bold text-surface-800 dark:text-white">إظهار قسم الشروحات والمراجعات المجانية</span>
                                <span class="block text-[11px] text-surface-500 dark:text-surface-400 mt-1">يظهر القسم فقط عند تفعيله ووجود فيديوهات مضافة.</span>
                            </span>
                        </label>
                        <div class="space-y-4">
                            <div v-for="(vid, idx) in form.home_youtube_videos" :key="idx" class="border border-surface-200 dark:border-surface-800 rounded-2xl p-4 bg-surface-50/40 dark:bg-surface-800/40 space-y-3 relative animate-fade-up">
                                <div class="flex justify-between items-center border-b border-surface-100 dark:border-surface-700 pb-2 mb-2">
                                    <span class="text-[10px] font-bold text-surface-500">الفيديو #{{ idx + 1 }}</span>
                                    <button type="button" @click="form.home_youtube_videos.splice(idx, 1)" class="text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 py-1 px-2.5 rounded-lg text-[10px] font-black flex items-center gap-1.5 transition-colors">
                                        <Icon name="close" class="w-3 h-3 text-red-500 shrink-0" />
                                        <span>حذف الفيديو</span>
                                    </button>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="md:col-span-2">
                                        <label class="label mb-1 block text-[10px]">عنوان الفيديو الرئيسي</label>
                                        <input v-model="vid.title" type="text" class="input text-xs w-full">
                                    </div>
                                    <div>
                                        <label class="label mb-1 block text-[10px]">رابط الفيديو (Link)</label>
                                        <input v-model="vid.url" type="text" dir="ltr" class="input text-xs w-full">
                                    </div>
                                    <div>
                                        <label class="label mb-1 block text-[10px]">رابط الصورة المصغرة (Thumbnail Image)</label>
                                        <input v-model="vid.thumbnail" type="text" dir="ltr" class="input text-xs w-full">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FAQs Dynamic Manager -->
                    <div class="card p-6">
                        <div class="flex justify-between items-center border-b border-surface-200 dark:border-surface-800 pb-3 mb-4">
                            <h3 class="font-bold text-sm text-surface-800 dark:text-white">الأسئلة الشائعة (FAQ Accordion)</h3>
                            <button type="button" @click="addFaq" class="btn-outline text-[10px] py-1 px-3.5">+ إضافة سؤال</button>
                        </div>
                        <div class="space-y-4">
                            <div v-for="(faq, idx) in form.home_faqs" :key="idx" class="border border-surface-200 dark:border-surface-800 rounded-2xl p-4 bg-surface-50/40 dark:bg-surface-800/40 space-y-3 relative animate-fade-up">
                                <div class="flex justify-between items-center border-b border-surface-100 dark:border-surface-700 pb-2 mb-2">
                                    <span class="text-[10px] font-bold text-surface-500">السؤال #{{ idx + 1 }}</span>
                                    <button type="button" @click="form.home_faqs.splice(idx, 1)" class="text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 py-1 px-2.5 rounded-lg text-[10px] font-black flex items-center gap-1.5 transition-colors">
                                        <Icon name="close" class="w-3 h-3 text-red-500 shrink-0" />
                                        <span>حذف السؤال</span>
                                    </button>
                                </div>
                                <div class="space-y-3">
                                    <div>
                                        <label class="label mb-1 block text-[10px]">السؤال</label>
                                        <input v-model="faq.q" type="text" class="input text-xs w-full">
                                    </div>
                                    <div>
                                        <label class="label mb-1 block text-[10px]">الإجابة</label>
                                        <textarea v-model="faq.a" rows="3" class="input text-xs w-full"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CTA Section -->
                    <div class="card p-6">
                        <h3 class="font-bold text-sm text-surface-800 dark:text-white border-b border-surface-200 dark:border-surface-800 pb-3 mb-4">بانر التسجيل والاتصال السفلي (CTA Banner)</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="label mb-1 block">العنوان العريض للبانر</label>
                                <input v-model="form.home_cta_title" type="text" class="input text-xs w-full">
                            </div>
                            <div class="md:col-span-2">
                                <label class="label mb-1 block">نص الوصف المصاحب</label>
                                <textarea v-model="form.home_cta_desc" rows="2" class="input text-xs w-full"></textarea>
                            </div>
                            <div class="md:col-span-2">
                                <label class="label mb-1 block">نص زر الإجراء (إنشاء حساب)</label>
                                <input v-model="form.home_cta_btn" type="text" class="input text-xs w-full">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. About Us Page Editor -->
                <div v-if="activePage === 'about'" class="space-y-6">
                    <div class="card p-6">
                        <h3 class="font-bold text-sm text-surface-800 dark:text-white border-b border-surface-200 dark:border-surface-800 pb-3 mb-4">نصوص وعناوين صفحة من نحن</h3>
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label class="label mb-1 block">شارة الصفحة العلوية (Badge)</label>
                                <input v-model="form.about_badge" type="text" class="input text-xs w-full" placeholder="منصتكم التعليمية الأولى">
                            </div>
                            <div>
                                <label class="label mb-1 block">عنوان الصفحة الرئيسي</label>
                                <input v-model="form.about_title" type="text" class="input text-xs w-full">
                            </div>
                            <div>
                                <label class="label mb-1 block">نص المقدمة والتعريف</label>
                                <textarea v-model="form.about_desc" rows="4" class="input text-xs w-full"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Vision & Mission Values -->
                    <div class="card p-6">
                        <div class="flex justify-between items-center border-b border-surface-200 dark:border-surface-800 pb-3 mb-4">
                            <h3 class="font-bold text-sm text-surface-800 dark:text-white">رؤيتنا ورسالتنا (القيم الأساسية)</h3>
                            <button type="button" @click="addAboutValue" class="btn-outline text-[10px] py-1 px-3.5">+ إضافة قيمة</button>
                        </div>
                        <div class="space-y-4">
                            <div v-for="(val, idx) in form.about_values" :key="idx" class="border border-surface-200 dark:border-surface-800 rounded-2xl p-4 bg-surface-50/40 dark:bg-surface-800/40 space-y-3 relative animate-fade-up">
                                <div class="flex justify-between items-center border-b border-surface-100 dark:border-surface-700 pb-2 mb-2">
                                    <span class="text-[10px] font-bold text-surface-500">القيمة #{{ idx + 1 }}</span>
                                    <button type="button" @click="form.about_values.splice(idx, 1)" class="text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 py-1 px-2.5 rounded-lg text-[10px] font-black flex items-center gap-1.5 transition-colors">
                                        <Icon name="close" class="w-3 h-3 text-red-500 shrink-0" />
                                        <span>حذف القيمة</span>
                                    </button>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="md:col-span-2">
                                        <label class="label mb-1 block text-[10px]">العنوان</label>
                                        <input v-model="val.title" type="text" class="input text-xs w-full">
                                    </div>
                                    <div>
                                        <label class="label mb-1 block text-[10px]">الأيقونة</label>
                                        <select v-model="val.icon" class="input text-xs w-full">
                                            <option value="student">طالب (student)</option>
                                            <option value="courses">مناهج (courses)</option>
                                        </select>
                                    </div>
                                    <div class="md:col-span-3">
                                        <label class="label mb-1 block text-[10px]">الوصف والتفاصيل</label>
                                        <textarea v-model="val.desc" rows="2" class="input text-xs w-full"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pillars list -->
                    <div class="card p-6">
                        <div class="flex justify-between items-center border-b border-surface-200 dark:border-surface-800 pb-3 mb-4">
                            <h3 class="font-bold text-sm text-surface-800 dark:text-white">ركائز المنصة ومحاور التفوق الدراسي</h3>
                            <button type="button" @click="addAboutPillar" class="btn-outline text-[10px] py-1 px-3.5">+ إضافة ركيزة</button>
                        </div>
                        <div class="space-y-4">
                            <div v-for="(pillar, idx) in form.about_pillars" :key="idx" class="border border-surface-200 dark:border-surface-800 rounded-2xl p-4 bg-surface-50/40 dark:bg-surface-800/40 space-y-3 relative animate-fade-up">
                                <div class="flex justify-between items-center border-b border-surface-100 dark:border-surface-700 pb-2 mb-2">
                                    <span class="text-[10px] font-bold text-surface-500">الركيزة #{{ idx + 1 }}</span>
                                    <button type="button" @click="form.about_pillars.splice(idx, 1)" class="text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 py-1 px-2.5 rounded-lg text-[10px] font-black flex items-center gap-1.5 transition-colors">
                                        <Icon name="close" class="w-3 h-3 text-red-500 shrink-0" />
                                        <span>حذف الركيزة</span>
                                    </button>
                                </div>
                                <div class="space-y-3">
                                    <div>
                                        <label class="label mb-1 block text-[10px]">العنوان الرئيسي</label>
                                        <input v-model="pillar.title" type="text" class="input text-xs w-full">
                                    </div>
                                    <div>
                                        <label class="label mb-1 block text-[10px]">الوصف التوضيحي</label>
                                        <textarea v-model="pillar.desc" rows="2" class="input text-xs w-full"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Our Apps Page Editor -->
                <div  v-if="activePage === 'apps'" class="space-y-6">
                    <div class="card p-6">
                        <h3 class="font-bold text-sm text-surface-800 dark:text-white border-b border-surface-200 dark:border-surface-800 pb-3 mb-4">محتوى صفحة تطبيقاتنا</h3>
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label class="label mb-1 block">شارة التطبيقات العلوية (Badge)</label>
                                <input v-model="form.app_badge" type="text" class="input text-xs w-full">
                            </div>
                            <div>
                                <label class="label mb-1 block">عنوان الصفحة الرئيسي</label>
                                <input v-model="form.app_title" type="text" class="input text-xs w-full">
                            </div>
                            <div>
                                <label class="label mb-1 block">الوصف والنص الإرشادي</label>
                                <textarea v-model="form.app_desc" rows="3" class="input text-xs w-full"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Download Links -->
                    <div class="card p-6">
                        <h3 class="font-bold text-sm text-surface-800 dark:text-white border-b border-surface-200 dark:border-surface-800 pb-3 mb-4">روابط التحميل المباشرة للتطبيقات</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="label mb-1 block">رابط تطبيق ويندوز (Windows)</label>
                                <input v-model="form.app_win_url" type="text" dir="ltr" class="input text-xs w-full">
                            </div>
                            <div>
                                <label class="label mb-1 block">رابط تطبيق ماك (macOS)</label>
                                <input v-model="form.app_mac_url" type="text" dir="ltr" class="input text-xs w-full">
                            </div>
                            <div>
                                <label class="label mb-1 block">رابط تطبيق آيفون/آيباد (iOS)</label>
                                <input v-model="form.app_ios_url" type="text" dir="ltr" class="input text-xs w-full">
                            </div>
                            <div>
                                <label class="label mb-1 block">رابط تطبيق أندرويد (Google Play)</label>
                                <input v-model="form.app_android_url" type="text" dir="ltr" class="input text-xs w-full">
                            </div>
                            <div class="md:col-span-2">
                                <label class="label mb-1 block">رابط تطبيق هواوي (AppGallery)</label>
                                <input v-model="form.app_huawei_url" type="text" dir="ltr" class="input text-xs w-full">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Contact Us Page Editor -->
                <div v-if="activePage === 'contact'" class="space-y-6">
                    <div class="card p-6">
                        <h3 class="font-bold text-sm text-surface-800 dark:text-white border-b border-surface-200 dark:border-surface-800 pb-3 mb-4">إعدادات الاتصال والدعم الفني</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="label mb-1 block">شارة الاتصال العلوية (Badge)</label>
                                <input v-model="form.contact_badge" type="text" class="input text-xs w-full">
                            </div>
                            <div>
                                <label class="label mb-1 block">العنوان الرئيسي للصفحة</label>
                                <input v-model="form.contact_title" type="text" class="input text-xs w-full">
                            </div>
                            <div>
                                <label class="label mb-1 block">البريد الإلكتروني للدعم</label>
                                <input v-model="form.contact_email" type="email" dir="ltr" class="input text-xs w-full">
                            </div>
                            <div>
                                <label class="label mb-1 block">رقم هاتف الاتصال المباشر</label>
                                <input v-model="form.contact_phone" type="text" dir="ltr" class="input text-xs w-full">
                            </div>
                            <div class="md:col-span-2">
                                <label class="label mb-1 block">رابط المحادثة المباشرة على واتساب (WhatsApp URL)</label>
                                <input v-model="form.whatsapp_url" type="text" dir="ltr" class="input text-xs w-full" placeholder="https://wa.me/...">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. Welcome Popup Editor -->
                <div  v-if="activePage === 'popup'" class="space-y-6">
                    <!-- Status and header -->
                    <div class="card p-6">
                        <h3 class="font-bold text-sm text-surface-800 dark:text-white border-b border-surface-200 dark:border-surface-800 pb-3 mb-4">حالة النافذة وعنوانها</h3>
                        <div class="grid grid-cols-1 gap-4">
                            <div class="flex items-center gap-3 bg-surface-50 dark:bg-surface-900/50 p-4 rounded-2xl border border-surface-200 dark:border-surface-800">
                                <input v-model="form.welcome_popup_active" id="popup_status" type="checkbox" class="w-5 h-5 accent-primary-600 rounded">
                                <label for="popup_status" class="font-bold text-xs cursor-pointer select-none">تفعيل وعرض النافذة الترحيبية للطلاب عند الدخول</label>
                            </div>
                            <div>
                                <label class="label mb-1 block">عنوان النافذة الترحيبية العريض</label>
                                <input v-model="form.welcome_popup_title" type="text" class="input text-xs w-full">
                            </div>
                            <div>
                                <label class="label mb-1 block">اسم رابط الدليل السفلي</label>
                                <input v-model="form.welcome_popup_bottom_label" type="text" class="input text-xs w-full" placeholder="للمزيد الإطلاع على دليل المستخدم">
                            </div>
                            <div>
                                <label class="label mb-1 block">عنوان رابط الدليل السفلي (URL)</label>
                                <input v-model="form.welcome_popup_bottom_url" type="text" dir="ltr" class="input text-xs w-full">
                            </div>
                        </div>
                    </div>

                    <!-- Cards links / Videos -->
                    <div class="card p-6">
                        <h3 class="font-bold text-sm text-surface-800 dark:text-white border-b border-surface-200 dark:border-surface-800 pb-3 mb-4">إدارة الكروت الستة التعليمية بفيديوهاتها</h3>
                        <div class="space-y-6 divide-y divide-surface-100 dark:divide-surface-800">
                            <!-- Card 1 -->
                            <div class="pt-4 first:pt-0">
                                <h4 class="font-bold text-xs text-primary-600 mb-2">الكارت الأول (افتراضياً: إنشاء حساب)</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="label text-[10px] mb-1 block">اسم البطاقة</label>
                                        <input v-model="form.welcome_popup_item1_label" type="text" class="input text-xs w-full">
                                    </div>
                                    <div>
                                        <label class="label text-[10px] mb-1 block">رابط التوجيه أو رابط الفيديو التوضيحي</label>
                                        <input v-model="form.welcome_popup_item1_url" type="text" dir="ltr" class="input text-xs w-full">
                                    </div>
                                </div>
                            </div>
                            <!-- Card 2 -->
                            <div class="pt-4">
                                <h4 class="font-bold text-xs text-primary-600 mb-2">الكارت الثاني (افتراضياً: الدفع الإلكتروني)</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="label text-[10px] mb-1 block">اسم البطاقة</label>
                                        <input v-model="form.welcome_popup_item2_label" type="text" class="input text-xs w-full">
                                    </div>
                                    <div>
                                        <label class="label text-[10px] mb-1 block">رابط التوجيه أو رابط الفيديو التوضيحي</label>
                                        <input v-model="form.welcome_popup_item2_url" type="text" dir="ltr" class="input text-xs w-full">
                                    </div>
                                </div>
                            </div>
                            <!-- Card 3 -->
                            <div class="pt-4">
                                <h4 class="font-bold text-xs text-primary-600 mb-2">الكارت الثالث (افتراضياً: إيجاد رقم ID)</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="label text-[10px] mb-1 block">اسم البطاقة</label>
                                        <input v-model="form.welcome_popup_item3_label" type="text" class="input text-xs w-full">
                                    </div>
                                    <div>
                                        <label class="label text-[10px] mb-1 block">رابط التوجيه أو رابط الفيديو التوضيحي</label>
                                        <input v-model="form.welcome_popup_item3_url" type="text" dir="ltr" class="input text-xs w-full">
                                    </div>
                                </div>
                            </div>
                            <!-- Card 4 -->
                            <div class="pt-4">
                                <h4 class="font-bold text-xs text-primary-600 mb-2">الكارت الرابع (افتراضياً: تنزيل ويندوز)</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="label text-[10px] mb-1 block">اسم البطاقة</label>
                                        <input v-model="form.welcome_popup_item4_label" type="text" class="input text-xs w-full">
                                    </div>
                                    <div>
                                        <label class="label text-[10px] mb-1 block">رابط التوجيه أو رابط الفيديو التوضيحي</label>
                                        <input v-model="form.welcome_popup_item4_url" type="text" dir="ltr" class="input text-xs w-full">
                                    </div>
                                </div>
                            </div>
                            <!-- Card 5 -->
                            <div class="pt-4">
                                <h4 class="font-bold text-xs text-primary-600 mb-2">الكارت الخامس (افتراضياً: تنزيل آيباد وهاتف)</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="label text-[10px] mb-1 block">اسم البطاقة</label>
                                        <input v-model="form.welcome_popup_item5_label" type="text" class="input text-xs w-full">
                                    </div>
                                    <div>
                                        <label class="label text-[10px] mb-1 block">رابط التوجيه أو رابط الفيديو التوضيحي</label>
                                        <input v-model="form.welcome_popup_item5_url" type="text" dir="ltr" class="input text-xs w-full">
                                    </div>
                                </div>
                            </div>
                            <!-- Card 6 -->
                            <div class="pt-4">
                                <h4 class="font-bold text-xs text-primary-600 mb-2">الكارت السادس (افتراضياً: مشكلة تسجيل الشاشة)</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="label text-[10px] mb-1 block">اسم البطاقة</label>
                                        <input v-model="form.welcome_popup_item6_label" type="text" class="input text-xs w-full">
                                    </div>
                                    <div>
                                        <label class="label text-[10px] mb-1 block">رابط التوجيه أو رابط الفيديو التوضيحي</label>
                                        <input v-model="form.welcome_popup_item6_url" type="text" dir="ltr" class="input text-xs w-full">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Floating Save Button for Editor page -->
                <div class="fixed bottom-6 z-40 p-3.5 rounded-2xl shadow-glow-primary flex items-center gap-3"
                     style="left: 50% !important; transform: translateX(-50%) !important; background-color: rgba(15, 23, 42, 0.4) !important; backdrop-filter: blur(20px) !important; -webkit-backdrop-filter: blur(20px) !important; border: 1px solid rgba(255, 255, 255, 0.15) !important;">
                    <button type="button" @click="activePage = null" class="btn-ghost text-xs px-4 py-2 text-white/80 hover:text-white rounded-xl">إلغاء</button>
                    <button type="button" @click="submitPageSettings(activePage)" :disabled="isSaving" class="btn-primary py-2.5 px-6 text-xs flex items-center gap-2 transition-transform hover:scale-[1.02]">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.75V16.5L12 14.25 7.5 16.5V3.75m9 0H18A2.25 2.25 0 0120.25 6v12A2.25 2.25 0 0118 20.25H6A2.25 2.25 0 013.75 18V6A2.25 2.25 0 016 3.75h1.5m9 0h-9" />
                        </svg>
                        <span>{{ isSaving ? 'جاري الحفظ...' : (wasSaved ? 'تم الحفظ' : 'حفظ التغييرات') }}</span>
                    </button>
                </div>
            </div>

        </div>

    </DashboardLayout>
</template>
