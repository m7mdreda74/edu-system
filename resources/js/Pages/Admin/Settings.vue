<script setup>
import { ref, computed } from 'vue';
import { useForm, router, Link, Head } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    dbSettings: { type: Array, required: true },
});

// A comprehensive list of default settings (ensures all keys are initialized even if they don't exist in the database)
const defaultSettings = [
    { key: 'platform_name', value: 'التفوق', type: 'string' },
    { key: 'site_theme', value: 'royal', type: 'string' },
    { key: 'contact_email', value: 'support@altafawwuq.com', type: 'string' },
    { key: 'whatsapp_url', value: 'https://wa.me/97455555555', type: 'string' },
    { key: 'contact_phone', value: '+974 4444 8888', type: 'string' },
    { key: 'contact_badge', value: 'الدعم الفني والاتصال', type: 'string' },
    { key: 'contact_title', value: 'يسعدنا تواصلك معنا في أي وقت', type: 'string' },
    { key: 'commission_percent', value: '20', type: 'integer' },
    { key: 'platform_email', value: 'support@altafawwuq.com', type: 'string' },

    // Welcome Popup
    { key: 'welcome_popup_active', value: 'false', type: 'boolean' },
    { key: 'welcome_popup_title', value: 'أهلاً بك في منصة التفوق التعليمية', type: 'string' },
    { key: 'welcome_popup_bottom_label', value: 'للمزيد الإطلاع على دليل المستخدم', type: 'string' },
    { key: 'welcome_popup_bottom_url', value: 'https://docs.example.com/user-guide', type: 'string' },
    { key: 'welcome_popup_item1_label', value: 'طريقة إنشاء حساب جديد', type: 'string' },
    { key: 'welcome_popup_item1_url', value: 'https://docs.example.com/register', type: 'string' },
    { key: 'welcome_popup_item2_label', value: 'خطوات الدفع الإلكتروني', type: 'string' },
    { key: 'welcome_popup_item2_url', value: 'https://docs.example.com/payment', type: 'string' },
    { key: 'welcome_popup_item3_label', value: 'طريقة إيجاد رقم ID', type: 'string' },
    { key: 'welcome_popup_item3_url', value: 'https://docs.example.com/id', type: 'string' },
    { key: 'welcome_popup_item4_label', value: 'طريقة تنزيل تطبيق المنصة على ويندوز', type: 'string' },
    { key: 'welcome_popup_item4_url', value: 'https://docs.example.com/windows', type: 'string' },
    { key: 'welcome_popup_item5_label', value: 'طريقة تنزيل تطبيق المنصة على آيباد وهاتف', type: 'string' },
    { key: 'welcome_popup_item5_url', value: 'https://docs.example.com/mobile', type: 'string' },
    { key: 'welcome_popup_item6_label', value: 'كيفية حل مشكلة تسجيل الشاشة', type: 'string' },
    { key: 'welcome_popup_item6_url', value: 'https://docs.example.com/screen-record', type: 'string' },

    // Hero / Home Page text fields
    { key: 'home_hero_title', value: 'تفوّق في دراستك الثانوية', type: 'string' },
    { key: 'home_hero_subtitle', value: 'منصة التفوق التعليمية الأولى في قطر', type: 'string' },
    { key: 'home_hero_desc', value: 'نصنع مستقبل التعليم في قطر من خلال تقديم أفضل الشروحات وأقوى المناهج التعليمية المتكاملة لطلاب المرحلة الثانوية.', type: 'string' },
    { key: 'home_hero_btn1', value: 'ابدأ التعلم الآن', type: 'string' },
    { key: 'home_hero_btn2', value: 'إنشاء حساب جديد', type: 'string' },
    { key: 'home_stats_students', value: '+500 طالب', type: 'string' },
    { key: 'home_stats_courses', value: '+50 دورة', type: 'string' },
    { key: 'home_stats_teachers', value: 'أكفأ المعلمين', type: 'string' },
    { key: 'home_cta_title', value: 'ابدأ رحلة تفوقك اليوم معنا', type: 'string' },
    { key: 'home_cta_desc', value: 'سجل الآن في المنصة واحصل على إمكانية الوصول الفوري للدروس والملخصات التفاعلية.', type: 'string' },
    { key: 'home_cta_btn', value: 'سجل مجاناً', type: 'string' },

    // About
    { key: 'about_title', value: 'منصة التفوق التعليمية', type: 'string' },
    { key: 'about_badge', value: 'منصتكم التعليمية الأولى', type: 'string' },
    { key: 'about_desc', value: 'نصنع مستقبل التعليم في قطر من خلال تقديم أفضل الشروحات وأقوى المناهج التعليمية المتكاملة لطلاب المرحلة الثانوية على أيدي نخبة من أكفأ المعلمين.', type: 'string' },
    { key: 'about_values', value: '[]', type: 'string' },
    { key: 'about_pillars', value: '[]', type: 'string' },

    // Footer & Apps
    { key: 'footer_desc', value: 'منصة تعليمية متخصصة في مواد المرحلة الثانوية، نحو مستقبل أفضل لكل طالب.', type: 'string' },
    { key: 'app_title', value: 'حمّل تطبيقات المنصة', type: 'string' },
    { key: 'app_badge', value: 'تطبيقات التفوق للأجهزة الذكية', type: 'string' },
    { key: 'app_desc', value: 'لضمان تجربة تعليمية سلسة وخالية من الانقطاع وبث فيديوهات فائق السرعة، حمّل تطبيقات منصة التفوق المخصصة لأجهزة الكمبيوتر والهواتف الذكية.', type: 'string' },
    { key: 'app_win_url', value: '#', type: 'string' },
    { key: 'app_mac_url', value: '#', type: 'string' },
    { key: 'app_ios_url', value: '#', type: 'string' },
    { key: 'app_android_url', value: '#', type: 'string' },
    { key: 'app_huawei_url', value: '#', type: 'string' },

    // Navigation & social arrays
    { key: 'navbar_links', value: '[]', type: 'string' },
    { key: 'footer_links', value: '[]', type: 'string' },
    { key: 'footer_social_links', value: '[]', type: 'string' },

    // Payment Settings
    { key: 'active_gateway', value: 'fatora', type: 'string' },
    { key: 'tap_publishable_key', value: '', type: 'string' },
    { key: 'tap_secret_key', value: '', type: 'string' },
    { key: 'fatora_api_key', value: '', type: 'string' },
    { key: 'manual_payment_methods', value: '[]', type: 'string' }
];

// Initialize settings by merging defaults with current DB values
const settingsMap = new Map();
defaultSettings.forEach(s => settingsMap.set(s.key, { ...s }));
props.dbSettings.forEach(s => {
    settingsMap.set(s.key, { ...s });
});

const form = useForm({
    settings: Array.from(settingsMap.values())
});

const isDirty = ref(false);
const activeTab = ref('general');

const siteThemes = [
    {
        id: 'royal',
        name: 'العنابي الملكي',
        description: 'هوية فاخرة بالعنابي والذهبي، مناسبة للطابع الرسمي للمنصة.',
        colors: ['#7A1C37', '#C5A039', '#faf8f6'],
    },
    {
        id: 'ocean',
        name: 'المحيط الأكاديمي',
        description: 'أزرق عميق مع سماوي منعش يمنح المحتوى وضوحًا وهدوءًا.',
        colors: ['#2563eb', '#06b6d4', '#f8fafc'],
    },
    {
        id: 'emerald',
        name: 'الزمرد الهادئ',
        description: 'أخضر متزن مع لمسات كهرمانية لتجربة دافئة ومريحة.',
        colors: ['#10b981', '#f59e0b', '#f8faf9'],
    },
    {
        id: 'violet',
        name: 'البنفسجي العصري',
        description: 'بنفسجي أنيق مع وردي هادئ لشكل حديث وحيوي.',
        colors: ['#8b5cf6', '#f43f5e', '#fafafa'],
    },
];

// Helper to locate a setting object by key safely
const getSetting = (key) => form.settings.find(s => s.key === key);

const selectSiteTheme = (themeId) => {
    const setting = getSetting('site_theme');
    if (!setting) return;

    setting.value = themeId;
    document.documentElement.dataset.siteTheme = themeId;
    isDirty.value = true;
};

// Translation helper for friendly labels in Arabic
const getSettingLabel = (key) => {
    if (key === 'platform_name') return 'اسم المنصة';
    if (key === 'contact_email') return 'البريد الإلكتروني للدعم والاتصال';
    if (key === 'whatsapp_url') return 'رابط واتساب الدعم المباشر';
    if (key === 'contact_phone') return 'رقم الهاتف للاتصال المباشر';
    if (key === 'contact_badge') return 'البادج الجانبي لوسائل التواصل';
    if (key === 'contact_title') return 'عنوان صفحة اتصل بنا';
    if (key === 'platform_email') return 'البريد الإلكتروني الأساسي للمنصة (لإرسال الإشعارات)';
    if (key === 'commission_percent') return 'نسبة عمولة المنصة (%)';

    // Payment Settings
    if (key === 'active_gateway') return 'بوابة الدفع النشطة الحالية';
    if (key === 'tap_publishable_key') return 'مفتاح Tap العام (Publishable Key)';
    if (key === 'tap_secret_key') return 'مفتاح Tap السري (Secret Key)';
    if (key === 'fatora_api_key') return 'مفتاح Fatora السري (API Key)';

    // Welcome Popup
    if (key === 'welcome_popup_active') return 'تفعيل النافذة الترحيبية المنبثقة';
    if (key === 'welcome_popup_title') return 'عنوان النافذة الترحيبية الرئيسي';
    if (key === 'welcome_popup_bottom_label') return 'نص الزر/الرابط السفلي للنافذة';
    if (key === 'welcome_popup_bottom_url') return 'رابط الزر/الرابط السفلي للنافذة (URL)';
    if (key.startsWith('welcome_popup_item')) {
        const match = key.match(/welcome_popup_item(\d+)_(label|url)/);
        if (match) {
            const num = match[1];
            const type = match[2] === 'label' ? 'نص الرابط للعنصر' : 'رابط العنصر (URL)';
            return `${type} رقم ${num}`;
        }
    }

    // Hero / CTA
    if (key === 'home_hero_title') return 'العنوان الرئيسي للواجهة (Hero Title)';
    if (key === 'home_hero_subtitle') return 'العنوان الفرعي للواجهة';
    if (key === 'home_hero_desc') return 'وصف الواجهة الترحيبية (Description)';
    if (key === 'home_hero_btn1') return 'نص زر الواجهة الأساسي';
    if (key === 'home_hero_btn2') return 'نص زر الواجهة الفرعي';
    if (key === 'home_stats_students') return 'إحصائية الطلاب المسجلين بالواجهة';
    if (key === 'home_stats_courses') return 'إحصائية الكورسات بالواجهة';
    if (key === 'home_stats_teachers') return 'إحصائية المعلمين بالواجهة';
    if (key === 'home_cta_title') return 'عنوان قسم الدعوة للتسجيل (CTA) بالأسفل';
    if (key === 'home_cta_desc') return 'وصف قسم الدعوة للتسجيل (CTA) بالأسفل';
    if (key === 'home_cta_btn') return 'نص زر قسم الدعوة للتسجيل';

    // About Page
    if (key === 'about_title') return 'العنوان الرئيسي لصفحة من نحن';
    if (key === 'about_badge') return 'البادج الفرعي لصفحة من نحن';
    if (key === 'about_desc') return 'الوصف العام لصفحة من نحن';

    // Footer
    if (key === 'footer_desc') return 'وصف تذييل الموقع (Footer Description)';

    // Apps URLs
    if (key === 'app_title') return 'العنوان الرئيسي لقسم تحميل التطبيقات';
    if (key === 'app_badge') return 'البادج الفرعي لقسم تحميل التطبيقات';
    if (key === 'app_desc') return 'الوصف التعريفي بقسم تحميل التطبيقات';
    if (key === 'app_win_url') return 'رابط تحميل تطبيق ويندوز (Windows Exe)';
    if (key === 'app_mac_url') return 'رابط تحميل تطبيق ماك (Mac OS)';
    if (key === 'app_ios_url') return 'رابط تحميل تطبيق آيفون (iOS Store)';
    if (key === 'app_android_url') return 'رابط تحميل تطبيق أندرويد (Google Play)';
    if (key === 'app_huawei_url') return 'رابط تحميل تطبيق هواوي (Huawei App)';

    return key;
};

// Tabs Definition with SVG Icons
const tabs = [
    { id: 'general',    label: 'الإعدادات العامة',       iconName: 'settings' },
    { id: 'appearance', label: 'ثيم وشكل المنصة',        iconName: 'edit' },
    { id: 'popup',      label: 'النافذة الترحيبية',     iconName: 'bell' },
    { id: 'hero',       label: 'قسم الواجهة والـ Hero',  iconName: 'dashboard' },
    { id: 'navigation', label: 'روابط القوائم والـ Nav',  iconName: 'globe' },
    { id: 'about',      label: 'صفحة من نحن',           iconName: 'info' },
    { id: 'apps',       label: 'تطبيقات الجوال والكمبيوتر',iconName: 'live' },
    { id: 'footer',     label: 'تذييل الصفحة والسوشيال', iconName: 'courses' },
    { id: 'features',   label: 'ميزات المنصة',           iconName: 'progress' },
    { id: 'why_us',     label: 'لماذا نحن؟',             iconName: 'info' },
    { id: 'youtube',    label: 'فيديوهات يوتيوب',        iconName: 'live' },
    { id: 'faqs',       label: 'الأسئلة الشائعة',        iconName: 'chat' },
    { id: 'results',    label: 'نتائج الطلاب',           iconName: 'student' },
    { id: 'payment',    label: 'بوابات الدفع الإلكتروني',iconName: 'payments' },
    { id: 'advanced',   label: 'إعدادات النظام المتقدمة', iconName: 'edit' },
];

// Computed categorizations for inputs
const generalSettings = computed(() => {
    return form.settings.filter(s => s.key === 'platform_name' || s.key === 'contact_email' || s.key === 'whatsapp_url' || s.key === 'contact_phone' || s.key === 'contact_badge' || s.key === 'contact_title');
});

const welcomePopupSettings = computed(() => {
    return form.settings
        .filter(s => s.key.startsWith('welcome_popup_'))
        .sort((a, b) => {
            if (a.key === 'welcome_popup_active') return -1;
            if (b.key === 'welcome_popup_active') return 1;
            if (a.key === 'welcome_popup_title') return -1;
            if (b.key === 'welcome_popup_title') return 1;
            if (a.key === 'welcome_popup_bottom_label') return 1;
            if (b.key === 'welcome_popup_bottom_label') return -1;
            return a.key.localeCompare(b.key, undefined, { numeric: true, sensitivity: 'base' });
        });
});

const heroSettings = computed(() => {
    return form.settings.filter(s => s.key.startsWith('home_hero_') || s.key.startsWith('home_cta_') || s.key.startsWith('home_stats_'));
});

const aboutSettings = computed(() => {
    return form.settings.filter(s => s.key.startsWith('about_') && s.key !== 'about_values' && s.key !== 'about_pillars');
});

const appSettings = computed(() => {
    return form.settings.filter(s => s.key.startsWith('app_'));
});

const footerSettings = computed(() => {
    return form.settings.filter(s => s.key === 'footer_desc');
});

// JSON based settings managed as arrays
const featuresList = ref([]);
const whyChooseUsList = ref([]);
const youtubeList = ref([]);
const faqsList = ref([]);
const resultsList = ref([]);
const aboutValuesList = ref([]);
const aboutPillarsList = ref([]);
const navbarLinksList = ref([]);
const footerLinksList = ref([]);
const socialLinksList = ref([]);
const manualPaymentsList = ref([]);

const initLists = () => {
    const f = getSetting('home_features');
    featuresList.value = f ? JSON.parse(f.value || '[]') : [];
    
    const w = getSetting('home_why_choose_us');
    whyChooseUsList.value = w ? JSON.parse(w.value || '[]') : [];

    const y = getSetting('home_youtube_videos');
    youtubeList.value = y ? JSON.parse(y.value || '[]') : [];

    const q = getSetting('home_faqs');
    faqsList.value = q ? JSON.parse(q.value || '[]') : [];

    const r = getSetting('home_results');
    resultsList.value = r ? JSON.parse(r.value || '[]') : [];

    const av = getSetting('about_values');
    aboutValuesList.value = av ? JSON.parse(av.value || '[]') : [];

    const ap = getSetting('about_pillars');
    aboutPillarsList.value = ap ? JSON.parse(ap.value || '[]') : [];

    const nl = getSetting('navbar_links');
    navbarLinksList.value = nl ? JSON.parse(nl.value || '[]') : [];

    const fl = getSetting('footer_links');
    footerLinksList.value = fl ? JSON.parse(fl.value || '[]') : [];

    const sl = getSetting('footer_social_links');
    socialLinksList.value = sl ? JSON.parse(sl.value || '[]') : [];

    const mp = getSetting('manual_payment_methods');
    manualPaymentsList.value = mp ? JSON.parse(mp.value || '[]') : [];
};

initLists();

// Methods for list management
const addFeature = () => { featuresList.value.push({ title: '', desc: '', icon: 'courses' }); isDirty.value = true; };
const removeFeature = (i) => { featuresList.value.splice(i, 1); isDirty.value = true; };

const addWhyUs = () => { whyChooseUsList.value.push({ title: '', desc: '', icon: 'globe' }); isDirty.value = true; };
const removeWhyUs = (i) => { whyChooseUsList.value.splice(i, 1); isDirty.value = true; };

const addYoutube = () => { youtubeList.value.push({ title: '', url: '', thumbnail: '' }); isDirty.value = true; };
const removeYoutube = (i) => { youtubeList.value.splice(i, 1); isDirty.value = true; };

const addFaq = () => { faqsList.value.push({ q: '', a: '' }); isDirty.value = true; };
const removeFaq = (i) => { faqsList.value.splice(i, 1); isDirty.value = true; };

const addResult = () => { resultsList.value.push({ name: '', title: '', desc: '', school: '' }); isDirty.value = true; };
const removeResult = (i) => { resultsList.value.splice(i, 1); isDirty.value = true; };

const addAboutValue = () => { aboutValuesList.value.push({ title: '', desc: '', icon: 'student' }); isDirty.value = true; };
const removeAboutValue = (i) => { aboutValuesList.value.splice(i, 1); isDirty.value = true; };

const addAboutPillar = () => { aboutPillarsList.value.push({ title: '', desc: '' }); isDirty.value = true; };
const removeAboutPillar = (i) => { aboutPillarsList.value.splice(i, 1); isDirty.value = true; };

const addNavbarLink = () => { navbarLinksList.value.push({ label: '', href: '' }); isDirty.value = true; };
const removeNavbarLink = (i) => { navbarLinksList.value.splice(i, 1); isDirty.value = true; };

const addFooterLink = () => { footerLinksList.value.push({ label: '', href: '' }); isDirty.value = true; };
const removeFooterLink = (i) => { footerLinksList.value.splice(i, 1); isDirty.value = true; };

const addSocialLink = () => { socialLinksList.value.push({ platform: '', url: '', icon: '' }); isDirty.value = true; };
const removeSocialLink = (i) => { socialLinksList.value.splice(i, 1); isDirty.value = true; };

const syncListsBeforeSubmit = () => {
    const f = getSetting('home_features');
    if (f) f.value = JSON.stringify(featuresList.value);

    const w = getSetting('home_why_choose_us');
    if (w) w.value = JSON.stringify(whyChooseUsList.value);

    const y = getSetting('home_youtube_videos');
    if (y) y.value = JSON.stringify(youtubeList.value);

    const q = getSetting('home_faqs');
    if (q) q.value = JSON.stringify(faqsList.value);

    const r = getSetting('home_results');
    if (r) r.value = JSON.stringify(resultsList.value);

    const av = getSetting('about_values');
    if (av) av.value = JSON.stringify(aboutValuesList.value);

    const ap = getSetting('about_pillars');
    if (ap) ap.value = JSON.stringify(aboutPillarsList.value);

    const nl = getSetting('navbar_links');
    if (nl) nl.value = JSON.stringify(navbarLinksList.value);

    const fl = getSetting('footer_links');
    if (fl) fl.value = JSON.stringify(footerLinksList.value);

    const sl = getSetting('footer_social_links');
    if (sl) sl.value = JSON.stringify(socialLinksList.value);

    const mp = getSetting('manual_payment_methods');
    if (mp) mp.value = JSON.stringify(manualPaymentsList.value);
};

const addManualPayment = () => {
    manualPaymentsList.value.push({
        name: '',
        account_name: '',
        account_number: '',
        instructions: ''
    });
    isDirty.value = true;
};

const removeManualPayment = (i) => {
    manualPaymentsList.value.splice(i, 1);
    isDirty.value = true;
};

// Filter out already categorized keys to show under advanced settings
const jsonKeys = ['home_features', 'home_why_choose_us', 'home_youtube_videos', 'home_faqs', 'home_results'];

const paymentSettings = computed(() => {
    return {
        active_gateway: getSetting('active_gateway'),
        tap_publishable_key: getSetting('tap_publishable_key'),
        tap_secret_key: getSetting('tap_secret_key'),
        fatora_api_key: getSetting('fatora_api_key'),
    };
});

const advancedSettings = computed(() => {
    return form.settings.filter(s => {
        const explicitKeys = [
            'platform_name', 'site_theme', 'contact_email', 'whatsapp_url', 'contact_phone', 'contact_badge', 'contact_title',
            'welcome_popup_active', 'welcome_popup_title', 'welcome_popup_bottom_label', 'welcome_popup_bottom_url',
            'welcome_popup_item1_label', 'welcome_popup_item1_url',
            'welcome_popup_item2_label', 'welcome_popup_item2_url',
            'welcome_popup_item3_label', 'welcome_popup_item3_url',
            'welcome_popup_item4_label', 'welcome_popup_item4_url',
            'welcome_popup_item5_label', 'welcome_popup_item5_url',
            'welcome_popup_item6_label', 'welcome_popup_item6_url',
            'home_hero_title', 'home_hero_subtitle', 'home_hero_desc', 'home_hero_btn1', 'home_hero_btn2',
            'home_stats_students', 'home_stats_courses', 'home_stats_teachers',
            'home_cta_title', 'home_cta_desc', 'home_cta_btn',
            'about_title', 'about_badge', 'about_desc', 'about_values', 'about_pillars',
            'footer_desc', 'app_title', 'app_badge', 'app_desc',
            'app_win_url', 'app_mac_url', 'app_ios_url', 'app_android_url', 'app_huawei_url',
            'home_features', 'home_why_choose_us', 'home_youtube_videos', 'home_faqs', 'home_results',
            'navbar_links', 'footer_links', 'footer_social_links',
            'active_gateway', 'tap_publishable_key', 'tap_secret_key', 'fatora_api_key', 'manual_payment_methods'
        ];
        if (explicitKeys.includes(s.key)) return false;
        return true;
    });
});

// Advanced raw tab methods
function addRawSetting() {
    form.settings.push({ key: '', value: '', type: 'string' });
    isDirty.value = true;
}

function removeRawSetting(setting) {
    const actualIndex = form.settings.findIndex(s => s === setting);
    if (actualIndex === -1) return;

    if (setting.id) {
        if (confirm('هل أنت متأكد من حذف هذا الإعداد؟')) {
            router.delete(route('admin.settings.destroy', setting.id), {
                onSuccess: () => { form.settings.splice(actualIndex, 1); }
            });
        }
    } else {
        form.settings.splice(actualIndex, 1);
    }
}

// Global Submit
function saveSettings() {
    syncListsBeforeSubmit();
    form.post(route('admin.settings.update'), {
        onSuccess: () => {
            isDirty.value = false;
        }
    });
}
</script>

<template>
    <DashboardLayout>
        <Head title="إعدادات المنصة والمحتوى" />

        <div class="container-app px-4 py-8">
            <!-- Header section -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
                <div class="flex items-center gap-3">
                    <Link :href="route('admin.dashboard')" class="btn-ghost p-2 rounded-lg shrink-0">
                        <Icon name="arrowRight" class="w-5 h-5 rtl-flip" />
                    </Link>
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <Icon name="settings" class="w-7 h-7 text-primary-500" />
                        <span>إعدادات المنصة والمحتوى</span>
                    </h1>
                </div>
            </div>

            <!-- Main Layout Grid -->
            <div class="flex flex-col lg:flex-row gap-6">
                <!-- Sidebar tabs menu (Internal Sidebar) -->
                <aside class="w-full lg:w-1/4 flex-shrink-0">
                    <div class="card p-3 space-y-1 sticky top-24">
                        <div class="px-3 py-2 text-[10px] font-bold text-surface-400 dark:text-surface-500 uppercase tracking-wider mb-2">أقسام الإعدادات</div>
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            @click="activeTab = tab.id"
                            class="w-full flex items-center text-start gap-2.5 px-4 py-2.5 rounded-xl text-xs font-bold transition-all duration-200 group"
                            :class="activeTab === tab.id
                                ? 'bg-primary-500/10 text-primary-700 dark:bg-accent-500/10 dark:text-accent-300 border-r-4 border-accent-500 pr-3'
                                : 'text-surface-600 hover:bg-surface-100 hover:text-primary-700 hover:pr-5 dark:text-surface-300 dark:hover:bg-surface-800 dark:hover:text-accent-300'"
                        >
                            <Icon :name="tab.iconName" class="w-4 h-4 shrink-0 transition-colors" :class="activeTab === tab.id ? 'text-accent-500' : 'text-surface-400 group-hover:text-primary-700 dark:group-hover:text-accent-400'" />
                            <span class="transition-colors">{{ tab.label }}</span>
                        </button>
                    </div>
                </aside>

                <!-- Forms content (Canvas) -->
                <div class="flex-1 min-w-0 pb-24">
                    <!-- Tab: General Settings -->
                    <div v-if="activeTab === 'general'" class="card p-6 space-y-6 animate-fade-in-up">
                        <h3 class="font-bold text-base text-surface-900 dark:text-white border-b border-surface-100 dark:border-surface-700 pb-3 flex items-center gap-2">
                            <Icon name="settings" class="w-5 h-5 text-primary-500" />
                            <span>الإعدادات العامة للاتصال والمنصة</span>
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div v-for="setting in generalSettings" :key="setting.key" :class="(setting.key === 'whatsapp_url' || setting.key === 'contact_title') ? 'md:col-span-2' : ''" class="space-y-1">
                                <label class="block text-xs font-bold text-surface-700 dark:text-surface-300">{{ getSettingLabel(setting.key) }}</label>
                                <input v-model="setting.value" type="text" class="input w-full text-xs py-2 px-3" @input="isDirty = true" />
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Site Theme -->
                    <div v-if="activeTab === 'appearance'" class="card p-6 space-y-6 animate-fade-in-up">
                        <div class="border-b border-surface-100 dark:border-surface-700 pb-4">
                            <h3 class="font-bold text-base text-surface-900 dark:text-white flex items-center gap-2">
                                <Icon name="edit" class="w-5 h-5 text-primary-500" />
                                <span>اختيار ثيم المنصة</span>
                            </h3>
                            <p class="mt-2 text-xs leading-6 text-surface-500 dark:text-surface-400">
                                الاختيار بيظهر كمعاينة فورًا، وبعد الحفظ بيتطبق على الموقع بالكامل ولوحات التحكم مع الوضع الفاتح والداكن.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <button
                                v-for="theme in siteThemes"
                                :key="theme.id"
                                type="button"
                                class="relative overflow-hidden rounded-2xl border-2 p-4 text-start transition-all duration-300 hover:-translate-y-1"
                                :class="getSetting('site_theme')?.value === theme.id
                                    ? 'border-accent-500 shadow-glow-accent bg-accent-50/40 dark:bg-accent-950/20'
                                    : 'border-surface-200 bg-white hover:border-primary-300 dark:border-surface-700 dark:bg-surface-900/60'"
                                @click="selectSiteTheme(theme.id)"
                            >
                                <span
                                    v-if="getSetting('site_theme')?.value === theme.id"
                                    class="absolute top-3 left-3 rounded-full bg-accent-500 px-2.5 py-1 text-[10px] font-black text-surface-950"
                                >مختار</span>

                                <span class="mb-4 flex h-24 overflow-hidden rounded-xl border border-black/5 shadow-inner" dir="ltr">
                                    <span class="w-2/3 p-3" :style="{ backgroundColor: theme.colors[2] }">
                                        <span class="mb-2 block h-2 w-3/4 rounded-full" :style="{ backgroundColor: theme.colors[0] }"></span>
                                        <span class="mb-1.5 block h-1.5 w-full rounded-full bg-black/10"></span>
                                        <span class="mb-3 block h-1.5 w-2/3 rounded-full bg-black/10"></span>
                                        <span class="inline-block h-6 w-16 rounded-lg" :style="{ backgroundColor: theme.colors[1] }"></span>
                                    </span>
                                    <span class="flex w-1/3 flex-col gap-2 p-3" :style="{ backgroundColor: theme.colors[0] }">
                                        <span class="block h-2 rounded-full bg-white/80"></span>
                                        <span class="block h-2 rounded-full bg-white/40"></span>
                                        <span class="block h-2 w-2/3 rounded-full" :style="{ backgroundColor: theme.colors[1] }"></span>
                                    </span>
                                </span>

                                <span class="flex items-center gap-3">
                                    <span class="flex -space-x-1 space-x-reverse" dir="ltr">
                                        <span v-for="color in theme.colors" :key="color" class="h-5 w-5 rounded-full border-2 border-white shadow-sm dark:border-surface-800" :style="{ backgroundColor: color }"></span>
                                    </span>
                                    <span class="font-black text-surface-900 dark:text-white">{{ theme.name }}</span>
                                </span>
                                <span class="mt-2 block text-xs leading-5 text-surface-500 dark:text-surface-400">{{ theme.description }}</span>
                            </button>
                        </div>

                        <p v-if="form.errors['settings.1.value']" class="error-msg">{{ form.errors['settings.1.value'] }}</p>
                    </div>

                    <!-- Tab: Welcome Popup -->
                    <div v-if="activeTab === 'popup'" class="card p-6 space-y-6 animate-fade-in-up">
                        <h3 class="font-bold text-base text-surface-900 dark:text-white border-b border-surface-100 dark:border-surface-700 pb-3 flex items-center gap-2">
                            <Icon name="bell" class="w-5 h-5 text-primary-500" />
                            <span>النافذة الترحيبية المنبثقة (Welcome Popup)</span>
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div v-for="setting in welcomePopupSettings" :key="setting.key" :class="(setting.key === 'welcome_popup_title' || setting.key === 'welcome_popup_bottom_url') ? 'md:col-span-2' : ''" class="space-y-1">
                                <label class="block text-xs font-bold text-surface-700 dark:text-surface-300">{{ getSettingLabel(setting.key) }}</label>
                                <select v-if="setting.type === 'boolean'" v-model="setting.value" class="input w-full text-xs py-2 px-3" @change="isDirty = true">
                                    <option value="true">نعم / مفعل</option>
                                    <option value="false">لا / معطل</option>
                                </select>
                                <input v-else v-model="setting.value" type="text" class="input w-full text-xs py-2 px-3" @input="isDirty = true" />
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Hero Section -->
                    <div v-if="activeTab === 'hero'" class="card p-6 space-y-6 animate-fade-in-up">
                        <h3 class="font-bold text-base text-surface-900 dark:text-white border-b border-surface-100 dark:border-surface-700 pb-3 flex items-center gap-2">
                            <Icon name="dashboard" class="w-5 h-5 text-primary-500" />
                            <span>قسم الواجهة والـ Hero والـ CTA</span>
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div v-for="setting in heroSettings" :key="setting.key" :class="(setting.key.includes('desc') || setting.key === 'home_hero_title') ? 'md:col-span-2' : ''" class="space-y-1">
                                <label class="block text-xs font-bold text-surface-700 dark:text-surface-300">{{ getSettingLabel(setting.key) }}</label>
                                <textarea v-if="setting.key.includes('desc')" v-model="setting.value" rows="3" class="input w-full text-xs py-2 px-3 resize-y" @input="isDirty = true"></textarea>
                                <input v-else v-model="setting.value" type="text" class="input w-full text-xs py-2 px-3" @input="isDirty = true" />
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Navigation Menu links -->
                    <div v-if="activeTab === 'navigation'" class="space-y-6 animate-fade-in-up">
                        <!-- Navbar Links Card -->
                        <div class="card p-6 space-y-6">
                            <div class="flex justify-between items-center border-b border-surface-100 dark:border-surface-700 pb-3">
                                <h3 class="font-bold text-base text-surface-900 dark:text-white flex items-center gap-2">
                                    <Icon name="globe" class="w-5 h-5 text-primary-500" />
                                    <span>روابط القائمة العلوية (Navbar Links)</span>
                                </h3>
                                <button @click="addNavbarLink" class="btn-outline text-xs py-1.5 px-3">+ إضافة رابط</button>
                            </div>
                            <div class="space-y-4">
                                <div v-for="(link, idx) in navbarLinksList" :key="idx" class="p-4 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-50/40 dark:bg-surface-800/40 space-y-3 relative group">
                                    <button @click="removeNavbarLink(idx)" class="absolute top-4 left-4 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 p-1.5 rounded-lg text-xs font-bold flex items-center gap-1">
                                        <Icon name="trash" class="w-3.5 h-3.5 text-red-500 shrink-0" />
                                        <span>حذف</span>
                                    </button>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="space-y-1">
                                            <label class="block text-[10px] font-bold text-surface-500">نص الرابط (مثال: الرئيسية)</label>
                                            <input v-model="link.label" type="text" class="input w-full text-xs py-1.5 px-3 font-bold" @input="isDirty = true" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="block text-[10px] font-bold text-surface-500">العنوان أو المسار (URL / Path)</label>
                                            <input v-model="link.href" type="text" dir="ltr" class="input w-full text-xs py-1.5 px-3 font-mono" placeholder="/courses, https://..." @input="isDirty = true" />
                                        </div>
                                    </div>
                                </div>
                                <div v-if="navbarLinksList.length === 0" class="text-center py-6 text-surface-400 text-xs">لا توجد روابط مخصصة. سيتم استخدام القائمة الافتراضية.</div>
                            </div>
                        </div>

                        <!-- Footer Links Card -->
                        <div class="card p-6 space-y-6">
                            <div class="flex justify-between items-center border-b border-surface-100 dark:border-surface-700 pb-3">
                                <h3 class="font-bold text-base text-surface-900 dark:text-white flex items-center gap-2">
                                    <Icon name="courses" class="w-5 h-5 text-primary-500" />
                                    <span>روابط تذييل الصفحة (Footer Links)</span>
                                </h3>
                                <button @click="addFooterLink" class="btn-outline text-xs py-1.5 px-3">+ إضافة رابط</button>
                            </div>
                            <div class="space-y-4">
                                <div v-for="(link, idx) in footerLinksList" :key="idx" class="p-4 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-50/40 dark:bg-surface-800/40 space-y-3 relative group">
                                    <button @click="removeFooterLink(idx)" class="absolute top-4 left-4 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 p-1.5 rounded-lg text-xs font-bold flex items-center gap-1">
                                        <Icon name="trash" class="w-3.5 h-3.5 text-red-500 shrink-0" />
                                        <span>حذف</span>
                                    </button>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="space-y-1">
                                            <label class="block text-[10px] font-bold text-surface-500">نص الرابط</label>
                                            <input v-model="link.label" type="text" class="input w-full text-xs py-1.5 px-3 font-bold" @input="isDirty = true" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="block text-[10px] font-bold text-surface-500">العنوان أو المسار (URL / Path)</label>
                                            <input v-model="link.href" type="text" dir="ltr" class="input w-full text-xs py-1.5 px-3 font-mono" placeholder="/courses, https://..." @input="isDirty = true" />
                                        </div>
                                    </div>
                                </div>
                                <div v-if="footerLinksList.length === 0" class="text-center py-6 text-surface-400 text-xs">لا توجد روابط مخصصة للتذييل.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: About Page Settings -->
                    <div v-if="activeTab === 'about'" class="space-y-6 animate-fade-in-up">
                        <div class="card p-6 space-y-6">
                            <h3 class="font-bold text-base text-surface-900 dark:text-white border-b border-surface-100 dark:border-surface-700 pb-3 flex items-center gap-2">
                                <Icon name="info" class="w-5 h-5 text-primary-500" />
                                <span>صفحة من نحن (نصوص عامة)</span>
                            </h3>
                            <div class="grid grid-cols-1 gap-6">
                                <div v-for="setting in aboutSettings" :key="setting.key" class="space-y-1">
                                    <label class="block text-xs font-bold text-surface-700 dark:text-surface-300">{{ getSettingLabel(setting.key) }}</label>
                                    <textarea v-if="setting.key.includes('desc')" v-model="setting.value" rows="3" class="input w-full text-xs py-2 px-3 resize-y" @input="isDirty = true"></textarea>
                                    <input v-else v-model="setting.value" type="text" class="input w-full text-xs py-2 px-3 font-bold" @input="isDirty = true" />
                                </div>
                            </div>
                        </div>

                        <!-- About Values List -->
                        <div class="card p-6 space-y-6">
                            <div class="flex justify-between items-center border-b border-surface-100 dark:border-surface-700 pb-3">
                                <h3 class="font-bold text-base text-surface-900 dark:text-white flex items-center gap-2">
                                    <Icon name="student" class="w-5 h-5 text-primary-500" />
                                    <span>قيم ورسالة المنصة (من نحن)</span>
                                </h3>
                                <button @click="addAboutValue" class="btn-outline text-xs py-1.5 px-3">+ إضافة قيمة</button>
                            </div>
                            <div class="space-y-4">
                                <div v-for="(val, idx) in aboutValuesList" :key="idx" class="p-4 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-50/40 dark:bg-surface-800/40 space-y-3 relative group">
                                    <button @click="removeAboutValue(idx)" class="absolute top-4 left-4 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 p-1.5 rounded-lg text-xs font-bold flex items-center gap-1">
                                        <Icon name="trash" class="w-3.5 h-3.5 text-red-500 shrink-0" />
                                        <span>حذف</span>
                                    </button>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="space-y-1">
                                            <label class="block text-[10px] font-bold text-surface-500">العنوان (مثل: رؤيتنا)</label>
                                            <input v-model="val.title" type="text" class="input w-full text-xs py-1.5 px-3 font-bold" @input="isDirty = true" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="block text-[10px] font-bold text-surface-500">أيقونة العنصر</label>
                                            <input v-model="val.icon" type="text" class="input w-full text-xs py-1.5 px-3 font-mono" placeholder="student, courses, globe" @input="isDirty = true" />
                                        </div>
                                        <div class="space-y-1 md:col-span-2">
                                            <label class="block text-[10px] font-bold text-surface-500">الوصف</label>
                                            <textarea v-model="val.desc" rows="2" class="input w-full text-xs py-1.5 px-3" @input="isDirty = true"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="aboutValuesList.length === 0" class="text-center py-6 text-surface-400 text-xs">لا توجد قيم مضافة بعد.</div>
                            </div>
                        </div>

                        <!-- About Pillars List -->
                        <div class="card p-6 space-y-6">
                            <div class="flex justify-between items-center border-b border-surface-100 dark:border-surface-700 pb-3">
                                <h3 class="font-bold text-base text-surface-900 dark:text-white flex items-center gap-2">
                                    <Icon name="courses" class="w-5 h-5 text-primary-500" />
                                    <span>ركائز التعليم بالمنصة (Pillars)</span>
                                </h3>
                                <button @click="addAboutPillar" class="btn-outline text-xs py-1.5 px-3">+ إضافة ركيزة</button>
                            </div>
                            <div class="space-y-4">
                                <div v-for="(pil, idx) in aboutPillarsList" :key="idx" class="p-4 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-50/40 dark:bg-surface-800/40 space-y-3 relative group">
                                    <button @click="removeAboutPillar(idx)" class="absolute top-4 left-4 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 p-1.5 rounded-lg text-xs font-bold flex items-center gap-1">
                                        <Icon name="trash" class="w-3.5 h-3.5 text-red-500 shrink-0" />
                                        <span>حذف</span>
                                    </button>
                                    <div class="grid grid-cols-1 gap-4">
                                        <div class="space-y-1">
                                            <label class="block text-[10px] font-bold text-surface-500">عنوان الركيزة</label>
                                            <input v-model="pil.title" type="text" class="input w-full text-xs py-1.5 px-3 font-bold" @input="isDirty = true" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="block text-[10px] font-bold text-surface-500">الوصف</label>
                                            <textarea v-model="pil.desc" rows="2" class="input w-full text-xs py-1.5 px-3" @input="isDirty = true"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="aboutPillarsList.length === 0" class="text-center py-6 text-surface-400 text-xs">لا توجد ركائز مضافة بعد.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: App Downloads Settings -->
                    <div v-if="activeTab === 'apps'" class="card p-6 space-y-6 animate-fade-in-up">
                        <h3 class="font-bold text-base text-surface-900 dark:text-white border-b border-surface-100 dark:border-surface-700 pb-3 flex items-center gap-2">
                            <Icon name="live" class="w-5 h-5 text-primary-500" />
                            <span>تطبيقات الجوال والكمبيوتر</span>
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div v-for="setting in appSettings" :key="setting.key" :class="(setting.key === 'app_desc' || setting.key === 'app_title') ? 'md:col-span-2' : ''" class="space-y-1">
                                <label class="block text-xs font-bold text-surface-700 dark:text-surface-300">{{ getSettingLabel(setting.key) }}</label>
                                <textarea v-if="setting.key.includes('desc')" v-model="setting.value" rows="3" class="input w-full text-xs py-2 px-3 resize-y" @input="isDirty = true"></textarea>
                                <input v-else v-model="setting.value" type="text" class="input w-full text-xs py-2 px-3 font-mono" :dir="setting.key.includes('url') ? 'ltr' : 'rtl'" @input="isDirty = true" />
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Footer and Social Settings -->
                    <div v-if="activeTab === 'footer'" class="space-y-6 animate-fade-in-up">
                        <div class="card p-6 space-y-6">
                            <h3 class="font-bold text-base text-surface-900 dark:text-white border-b border-surface-100 dark:border-surface-700 pb-3 flex items-center gap-2">
                                <Icon name="courses" class="w-5 h-5 text-primary-500" />
                                <span>تذييل الصفحة (Footer Description)</span>
                            </h3>
                            <div class="grid grid-cols-1 gap-6">
                                <div v-for="setting in footerSettings" :key="setting.key" class="space-y-1">
                                    <label class="block text-xs font-bold text-surface-700 dark:text-surface-300">{{ getSettingLabel(setting.key) }}</label>
                                    <textarea v-model="setting.value" rows="4" class="input w-full text-xs py-2 px-3 resize-y" @input="isDirty = true"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Footer Social Links Card -->
                        <div class="card p-6 space-y-6">
                            <div class="flex justify-between items-center border-b border-surface-100 dark:border-surface-700 pb-3">
                                <h3 class="font-bold text-base text-surface-900 dark:text-white flex items-center gap-2">
                                    <Icon name="globe" class="w-5 h-5 text-primary-500" />
                                    <span>روابط مواقع التواصل الاجتماعي بالفوتر (Social Links)</span>
                                </h3>
                                <button @click="addSocialLink" class="btn-outline text-xs py-1.5 px-3">+ إضافة رابط تواصل</button>
                            </div>
                            <div class="space-y-4">
                                <div v-for="(social, idx) in socialLinksList" :key="idx" class="p-4 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-50/40 dark:bg-surface-800/40 space-y-3 relative group">
                                    <button @click="removeSocialLink(idx)" class="absolute top-4 left-4 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 p-1.5 rounded-lg text-xs font-bold flex items-center gap-1">
                                        <Icon name="trash" class="w-3.5 h-3.5 text-red-500 shrink-0" />
                                        <span>حذف</span>
                                    </button>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div class="space-y-1">
                                            <label class="block text-[10px] font-bold text-surface-500">اسم الشبكة (مثال: facebook)</label>
                                            <input v-model="social.platform" type="text" class="input w-full text-xs py-1.5 px-3" placeholder="facebook, instagram, telegram..." @input="isDirty = true" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="block text-[10px] font-bold text-surface-500">رمز الأيقونة (من الـ SVG Icons)</label>
                                            <input v-model="social.icon" type="text" class="input w-full text-xs py-1.5 px-3 font-mono" placeholder="facebook, instagram, whatsapp..." @input="isDirty = true" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="block text-[10px] font-bold text-surface-500">رابط الحساب (URL)</label>
                                            <input v-model="social.url" type="text" dir="ltr" class="input w-full text-xs py-1.5 px-3 font-mono" placeholder="https://..." @input="isDirty = true" />
                                        </div>
                                    </div>
                                </div>
                                <div v-if="socialLinksList.length === 0" class="text-center py-6 text-surface-400 text-xs">لا توجد روابط تواصل مضافة.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Features -->
                    <div v-if="activeTab === 'features'" class="card p-6 space-y-6 animate-fade-in-up">
                        <div class="flex justify-between items-center border-b border-surface-100 dark:border-surface-700 pb-3">
                            <h3 class="font-bold text-base text-surface-900 dark:text-white flex items-center gap-2">
                                <Icon name="progress" class="w-5 h-5 text-primary-500" />
                                <span>ميزات المنصة بالصفحة الرئيسية</span>
                            </h3>
                            <button @click="addFeature" class="btn-outline text-xs py-1.5 px-3">+ إضافة ميزة</button>
                        </div>
                        <div class="space-y-4">
                            <div v-for="(feat, idx) in featuresList" :key="idx" class="p-4 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-50/40 dark:bg-surface-800/40 space-y-3 relative group">
                                <button @click="removeFeature(idx)" class="absolute top-4 left-4 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 p-1.5 rounded-lg text-xs font-bold flex items-center gap-1">
                                    <Icon name="trash" class="w-3.5 h-3.5 text-red-500 shrink-0" />
                                    <span>حذف</span>
                                </button>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="space-y-1">
                                        <label class="block text-[10px] font-bold text-surface-500">عنوان الميزة</label>
                                        <input v-model="feat.title" type="text" class="input w-full text-xs py-1.5 px-3" @input="isDirty = true" />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="block text-[10px] font-bold text-surface-500">رمز الأيقونة</label>
                                        <input v-model="feat.icon" type="text" class="input w-full text-xs py-1.5 px-3 font-mono" placeholder="courses, live, chart" @input="isDirty = true" />
                                    </div>
                                    <div class="space-y-1 md:col-span-2">
                                        <label class="block text-[10px] font-bold text-surface-500">الوصف</label>
                                        <textarea v-model="feat.desc" rows="2" class="input w-full text-xs py-1.5 px-3" @input="isDirty = true"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div v-if="featuresList.length === 0" class="text-center py-6 text-surface-400 text-xs">لا توجد ميزات مضافة. اضغط إضافة ميزة للبدء.</div>
                        </div>
                    </div>

                    <!-- Tab: Why Choose Us -->
                    <div v-if="activeTab === 'why_us'" class="card p-6 space-y-6 animate-fade-in-up">
                        <div class="flex justify-between items-center border-b border-surface-100 dark:border-surface-700 pb-3">
                            <h3 class="font-bold text-base text-surface-900 dark:text-white flex items-center gap-2">
                                <Icon name="info" class="w-5 h-5 text-primary-500" />
                                <span>لماذا نحن؟ (صفحة الرئيسية)</span>
                            </h3>
                            <button @click="addWhyUs" class="btn-outline text-xs py-1.5 px-3">+ إضافة بند</button>
                        </div>
                        <div class="space-y-4">
                            <div v-for="(item, idx) in whyChooseUsList" :key="idx" class="p-4 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-50/40 dark:bg-surface-800/40 space-y-3 relative group">
                                <button @click="removeWhyUs(idx)" class="absolute top-4 left-4 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 p-1.5 rounded-lg text-xs font-bold flex items-center gap-1">
                                    <Icon name="trash" class="w-3.5 h-3.5 text-red-500 shrink-0" />
                                    <span>حذف</span>
                                </button>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="space-y-1">
                                        <label class="block text-[10px] font-bold text-surface-500">العنوان</label>
                                        <input v-model="item.title" type="text" class="input w-full text-xs py-1.5 px-3" @input="isDirty = true" />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="block text-[10px] font-bold text-surface-500">رمز الأيقونة</label>
                                        <input v-model="item.icon" type="text" class="input w-full text-xs py-1.5 px-3 font-mono" placeholder="globe, video, success" @input="isDirty = true" />
                                    </div>
                                    <div class="space-y-1 md:col-span-2">
                                        <label class="block text-[10px] font-bold text-surface-500">الوصف</label>
                                        <textarea v-model="item.desc" rows="2" class="input w-full text-xs py-1.5 px-3" @input="isDirty = true"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div v-if="whyChooseUsList.length === 0" class="text-center py-6 text-surface-400 text-xs">لا توجد بنود مضافة.</div>
                        </div>
                    </div>

                    <!-- Tab: YouTube Videos -->
                    <div v-if="activeTab === 'youtube'" class="card p-6 space-y-6 animate-fade-in-up">
                        <div class="flex justify-between items-center border-b border-surface-100 dark:border-surface-700 pb-3">
                            <h3 class="font-bold text-base text-surface-900 dark:text-white flex items-center gap-2">
                                <Icon name="live" class="w-5 h-5 text-primary-500" />
                                <span>فيديوهات يوتيوب بالصفحة الرئيسية</span>
                            </h3>
                            <button @click="addYoutube" class="btn-outline text-xs py-1.5 px-3">+ إضافة فيديو</button>
                        </div>
                        <div class="space-y-4">
                            <div v-for="(vid, idx) in youtubeList" :key="idx" class="p-4 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-50/40 dark:bg-surface-800/40 space-y-3 relative group">
                                <button @click="removeYoutube(idx)" class="absolute top-4 left-4 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 p-1.5 rounded-lg text-xs font-bold flex items-center gap-1">
                                    <Icon name="trash" class="w-3.5 h-3.5 text-red-500 shrink-0" />
                                    <span>حذف</span>
                                </button>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="space-y-1 md:col-span-2">
                                        <label class="block text-[10px] font-bold text-surface-500">عنوان الفيديو</label>
                                        <input v-model="vid.title" type="text" class="input w-full text-xs py-1.5 px-3" @input="isDirty = true" />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="block text-[10px] font-bold text-surface-500">رابط الفيديو (URL)</label>
                                        <input v-model="vid.url" type="text" class="input w-full text-xs py-1.5 px-3 font-mono" placeholder="https://youtube.com/..." @input="isDirty = true" />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="block text-[10px] font-bold text-surface-500">رابط الصورة المصغرة (Thumbnail)</label>
                                        <input v-model="vid.thumbnail" type="text" class="input w-full text-xs py-1.5 px-3 font-mono" placeholder="https://..." @input="isDirty = true" />
                                    </div>
                                </div>
                            </div>
                            <div v-if="youtubeList.length === 0" class="text-center py-6 text-surface-400 text-xs">لا توجد فيديوهات مضافة.</div>
                        </div>
                    </div>

                    <!-- Tab: FAQs -->
                    <div v-if="activeTab === 'faqs'" class="card p-6 space-y-6 animate-fade-in-up">
                        <div class="flex justify-between items-center border-b border-surface-100 dark:border-surface-700 pb-3">
                            <h3 class="font-bold text-base text-surface-900 dark:text-white flex items-center gap-2">
                                <Icon name="chat" class="w-5 h-5 text-primary-500" />
                                <span>الأسئلة الشائعة (FAQs)</span>
                            </h3>
                            <button @click="addFaq" class="btn-outline text-xs py-1.5 px-3">+ إضافة سؤال</button>
                        </div>
                        <div class="space-y-4">
                            <div v-for="(faq, idx) in faqsList" :key="idx" class="p-4 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-50/40 dark:bg-surface-800/40 space-y-3 relative group">
                                <button @click="removeFaq(idx)" class="absolute top-4 left-4 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 p-1.5 rounded-lg text-xs font-bold flex items-center gap-1">
                                    <Icon name="trash" class="w-3.5 h-3.5 text-red-500 shrink-0" />
                                    <span>حذف</span>
                                </button>
                                <div class="grid grid-cols-1 gap-4">
                                    <div class="space-y-1">
                                        <label class="block text-[10px] font-bold text-surface-500">السؤال</label>
                                        <input v-model="faq.q" type="text" class="input w-full text-xs py-1.5 px-3 font-bold" @input="isDirty = true" />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="block text-[10px] font-bold text-surface-500">الإجابة</label>
                                        <textarea v-model="faq.a" rows="3" class="input w-full text-xs py-1.5 px-3" @input="isDirty = true"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div v-if="faqsList.length === 0" class="text-center py-6 text-surface-400 text-xs">لا توجد أسئلة مضافة.</div>
                        </div>
                    </div>

                    <!-- Tab: Student Results -->
                    <div v-if="activeTab === 'results'" class="card p-6 space-y-6 animate-fade-in-up">
                        <div class="flex justify-between items-center border-b border-surface-100 dark:border-surface-700 pb-3">
                            <h3 class="font-bold text-base text-surface-900 dark:text-white flex items-center gap-2">
                                <Icon name="student" class="w-5 h-5 text-primary-500" />
                                <span>نتائج وآراء الطلاب المتفوقين</span>
                            </h3>
                            <button @click="addResult" class="btn-outline text-xs py-1.5 px-3">+ إضافة طالب</button>
                        </div>
                        <div class="space-y-4">
                            <div v-for="(res, idx) in resultsList" :key="idx" class="p-4 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-50/40 dark:bg-surface-800/40 space-y-3 relative group">
                                <button @click="removeResult(idx)" class="absolute top-4 left-4 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 p-1.5 rounded-lg text-xs font-bold flex items-center gap-1">
                                    <Icon name="trash" class="w-3.5 h-3.5 text-red-500 shrink-0" />
                                    <span>حذف</span>
                                </button>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="space-y-1">
                                        <label class="block text-[10px] font-bold text-surface-500">اسم الطالب</label>
                                        <input v-model="res.name" type="text" class="input w-full text-xs py-1.5 px-3 font-bold" @input="isDirty = true" />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="block text-[10px] font-bold text-surface-500">العنوان / الدفعة (مثال: دفعة 2026)</label>
                                        <input v-model="res.title" type="text" class="input w-full text-xs py-1.5 px-3" @input="isDirty = true" />
                                    </div>
                                    <div class="space-y-1 md:col-span-2">
                                        <label class="block text-[10px] font-bold text-surface-500">المدرسة</label>
                                        <input v-model="res.school" type="text" class="input w-full text-xs py-1.5 px-3" @input="isDirty = true" />
                                    </div>
                                    <div class="space-y-1 md:col-span-2">
                                        <label class="block text-[10px] font-bold text-surface-500">الوصف / الإنجاز</label>
                                        <textarea v-model="res.desc" rows="2" class="input w-full text-xs py-1.5 px-3" @input="isDirty = true"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div v-if="resultsList.length === 0" class="text-center py-6 text-surface-400 text-xs">لا توجد نتائج مضافة.</div>
                        </div>
                    </div>

                    <!-- Tab: Payment Gateways -->
                    <div v-if="activeTab === 'payment'" class="card p-6 space-y-6 animate-fade-in-up">
                        <div class="border-b border-surface-100 dark:border-surface-700 pb-3">
                            <h3 class="font-bold text-base text-surface-900 dark:text-white flex items-center gap-2">
                                <Icon name="payments" class="w-5 h-5 text-primary-500" />
                                <span>إعدادات بوابات الدفع الإلكتروني</span>
                            </h3>
                            <p class="text-xs text-surface-500 mt-1">تحديد بوابة الدفع النشطة وإدخال مفاتيح الربط لتوجيه المدفوعات إلى حسابك مباشرة.</p>
                        </div>

                        <div class="space-y-6">
                            <!-- Active Gateway Select -->
                            <div v-if="paymentSettings.active_gateway" class="space-y-2">
                                <label class="block text-xs font-bold text-surface-700 dark:text-surface-300">
                                    {{ getSettingLabel('active_gateway') }}
                                </label>
                                <select v-model="paymentSettings.active_gateway.value" class="input w-full" @change="isDirty = true">
                                    <option value="fatora">فاتورة Fatora (قطر)</option>
                                    <option value="tap">Tap Payments (الخليج والشرق الأوسط)</option>
                                    <option value="stripe">Stripe</option>
                                </select>
                            </div>

                            <!-- Fatora Settings Group -->
                            <div v-if="paymentSettings.active_gateway?.value === 'fatora'" class="p-5 rounded-2xl border border-surface-200 dark:border-surface-800 bg-surface-50/20 dark:bg-surface-900/10 space-y-4">
                                <div class="flex items-center gap-2 text-sm font-bold text-surface-800 dark:text-surface-200">
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                    إعدادات بوابة Fatora
                                </div>
                                <div v-if="paymentSettings.fatora_api_key" class="space-y-1.5">
                                    <label class="block text-[11px] font-bold text-surface-500">
                                        {{ getSettingLabel('fatora_api_key') }}
                                    </label>
                                    <input v-model="paymentSettings.fatora_api_key.value" type="text" dir="ltr" class="input w-full text-xs font-mono" placeholder="E4B73..." @input="isDirty = true" />
                                </div>
                            </div>

                            <!-- Tap Settings Group -->
                            <div v-if="paymentSettings.active_gateway?.value === 'tap'" class="p-5 rounded-2xl border border-surface-200 dark:border-surface-800 bg-surface-50/20 dark:bg-surface-900/10 space-y-4">
                                <div class="flex items-center gap-2 text-sm font-bold text-surface-800 dark:text-surface-200">
                                    <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                                    إعدادات بوابة Tap Payments
                                </div>
                                <div v-if="paymentSettings.tap_publishable_key" class="space-y-1.5">
                                    <label class="block text-[11px] font-bold text-surface-500">
                                        {{ getSettingLabel('tap_publishable_key') }}
                                    </label>
                                    <input v-model="paymentSettings.tap_publishable_key.value" type="text" dir="ltr" class="input w-full text-xs font-mono" placeholder="pk_test_..." @input="isDirty = true" />
                                </div>
                                <div v-if="paymentSettings.tap_secret_key" class="space-y-1.5">
                                    <label class="block text-[11px] font-bold text-surface-500">
                                        {{ getSettingLabel('tap_secret_key') }}
                                    </label>
                                    <input v-model="paymentSettings.tap_secret_key.value" type="text" dir="ltr" class="input w-full text-xs font-mono" placeholder="sk_test_..." @input="isDirty = true" />
                                </div>
                            </div>

                            <!-- Stripe Info Group -->
                            <div v-if="paymentSettings.active_gateway?.value === 'stripe'" class="p-5 rounded-2xl border border-surface-200 dark:border-surface-800 bg-surface-50/20 dark:bg-surface-900/10 space-y-2 text-xs text-surface-500">
                                يتم قراءة بيانات Stripe مباشرة من ملف الإعدادات البيئية الخاص بالسيرفر.
                            </div>

                            <!-- Manual Payment Methods Group -->
                            <div class="border-t border-surface-150 dark:border-surface-800 pt-6 mt-6">
                                <div class="flex justify-between items-center mb-4">
                                    <div>
                                        <h4 class="font-bold text-sm text-surface-900 dark:text-white">وسائل الدفع اليدوية (الحسابات البنكية والمحافظ)</h4>
                                        <p class="text-xs text-surface-400 mt-1">أدخل أرقام الحسابات البنكية أو أرقام الهواتف للمحافظ الإلكترونية ليقوم الطلاب بالتحويل يدوياً ورفع صورة الإيصال.</p>
                                    </div>
                                    <button type="button" @click="addManualPayment" class="btn-outline text-xs py-1.5 px-3 flex items-center gap-1">
                                        <Icon name="plus" class="w-3.5 h-3.5" />
                                        <span>إضافة وسيلة دفع</span>
                                    </button>
                                </div>

                                <div class="space-y-4">
                                    <div v-for="(method, idx) in manualPaymentsList" :key="idx" class="p-4 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-50/20 dark:bg-surface-800/10 relative group space-y-3">
                                        <button type="button" @click="removeManualPayment(idx)" class="absolute top-4 left-4 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 p-1.5 rounded-lg text-xs font-bold flex items-center gap-1">
                                            <Icon name="trash" class="w-3.5 h-3.5 text-red-500 shrink-0" />
                                            <span>حذف</span>
                                        </button>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div class="space-y-1">
                                                <label class="block text-[10px] font-bold text-surface-500">اسم وسيلة الدفع</label>
                                                <input v-model="method.name" type="text" class="input w-full text-xs" placeholder="مثال: فودافون كاش / بنك قطر الوطني" @input="isDirty = true" />
                                            </div>
                                            <div class="space-y-1">
                                                <label class="block text-[10px] font-bold text-surface-500">اسم صاحب الحساب / المستفيد</label>
                                                <input v-model="method.account_name" type="text" class="input w-full text-xs" placeholder="مثال: منصة التفوق التعليمية" @input="isDirty = true" />
                                            </div>
                                            <div class="space-y-1">
                                                <label class="block text-[10px] font-bold text-surface-500">رقم الحساب / المحفظة / الـ IBAN</label>
                                                <input v-model="method.account_number" type="text" class="input w-full text-xs font-mono" placeholder="مثال: 01012345678 أو IBAN..." @input="isDirty = true" />
                                            </div>
                                            <div class="space-y-1 md:col-span-3">
                                                <label class="block text-[10px] font-bold text-surface-500">خطوات وتعليمات التحويل للطالب</label>
                                                <textarea v-model="method.instructions" rows="2" class="input w-full text-xs" placeholder="اكتب الخطوات للطالب لإتمام التحويل ورفع إيصال الدفع..." @input="isDirty = true"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="manualPaymentsList.length === 0" class="text-center py-6 text-surface-400 text-xs">لا توجد وسائل دفع يدوية مضافة حالياً.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Advanced Settings -->
                    <div v-if="activeTab === 'advanced'" class="card p-6 space-y-6 animate-fade-in-up">
                        <div class="flex justify-between items-center border-b border-surface-100 dark:border-surface-700 pb-3">
                            <h3 class="font-bold text-base text-surface-900 dark:text-white flex items-center gap-2">
                                <Icon name="edit" class="w-5 h-5 text-primary-500" />
                                <span>إعدادات النظام المتقدمة</span>
                            </h3>
                            <button @click="addRawSetting" class="btn-outline text-xs py-1.5 px-3 flex items-center gap-1">
                                <Icon name="plus" class="w-3.5 h-3.5" />
                                <span>إضافة متغير جديد</span>
                            </button>
                        </div>
                        
                        <div class="space-y-6">
                            <div v-for="(setting, index) in advancedSettings" :key="setting.key || index" class="p-4 rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50/40 dark:bg-surface-800/40 space-y-3 relative">
                                <button v-if="setting.key !== 'commission_percent' && setting.key !== 'platform_email'" @click="removeRawSetting(setting)" class="absolute top-4 left-4 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 p-1.5 rounded-lg text-xs font-bold flex items-center gap-1">
                                    <Icon name="trash" class="w-3.5 h-3.5 text-red-500 shrink-0" />
                                    <span>حذف</span>
                                </button>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="space-y-1">
                                        <label class="block text-[10px] font-bold text-surface-500">اسم المتغير (Key)</label>
                                        <input v-model="setting.key" type="text" dir="ltr" :disabled="setting.key === 'commission_percent' || setting.key === 'platform_email'" class="input w-full text-xs py-1.5 px-3 font-mono" placeholder="custom_key_name" @input="isDirty = true" />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="block text-[10px] font-bold text-surface-500">النوع (Type)</label>
                                        <select v-model="setting.type" :disabled="setting.key === 'commission_percent' || setting.key === 'platform_email'" class="input w-full text-xs py-1.5 px-3" @change="isDirty = true">
                                            <option value="string">نص (String)</option>
                                            <option value="integer">رقم (Integer)</option>
                                            <option value="boolean">منطقي (Boolean)</option>
                                        </select>
                                    </div>
                                    <div class="space-y-1 md:col-span-2">
                                        <label class="block text-xs font-bold text-surface-700 dark:text-surface-300">{{ getSettingLabel(setting.key) }}</label>
                                        <textarea v-if="setting.type === 'string'" v-model="setting.value" rows="2" class="input w-full text-xs py-1.5 px-3" placeholder="القيمة..." @input="isDirty = true"></textarea>
                                        <input v-else-if="setting.type === 'integer'" v-model="setting.value" type="number" class="input w-full text-xs py-1.5 px-3" @input="isDirty = true">
                                        <select v-else-if="setting.type === 'boolean'" v-model="setting.value" class="input w-full text-xs py-1.5 px-3" @change="isDirty = true">
                                            <option value="true">نعم / مفعل</option>
                                            <option value="false">لا / معطل</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div v-if="advancedSettings.length === 0" class="text-center py-6 text-surface-400 text-xs">لا توجد إعدادات متقدمة مضافة حالياً.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Floating Save Button -->
            <div class="fixed bottom-6 z-40 p-3.5 rounded-2xl shadow-glow-primary flex items-center gap-3"
                 style="left: 50% !important; transform: translateX(-50%) !important; background-color: rgba(28, 20, 22, 0.65) !important; backdrop-filter: blur(20px) !important; -webkit-backdrop-filter: blur(20px) !important; border: 1px solid rgba(255, 255, 255, 0.1) !important;">
                <Link :href="route('admin.dashboard')" class="btn-ghost text-xs px-4 py-2 text-white/80 hover:text-white rounded-xl">إلغاء</Link>
                <button @click="saveSettings" :disabled="form.processing" class="btn-primary py-2.5 px-6 text-xs flex items-center gap-2 transition-transform hover:scale-102">
                    <Icon v-if="form.processing" name="clock" class="w-4 h-4 text-white animate-spin shrink-0" />
                    <Icon v-else name="success" class="w-4 h-4 text-white shrink-0" />
                    <span>{{ form.processing ? 'جاري الحفظ...' : 'حفظ التغييرات' }}</span>
                </button>
            </div>
        </div>
    </DashboardLayout>
</template>
