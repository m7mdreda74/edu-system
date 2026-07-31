<script setup>
import { computed, ref } from 'vue';
import { Link, Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TeacherCard from '@/Components/TeacherCard.vue';
import Icon from '@/Components/Icon.vue';
import WelcomePopup from '@/Components/WelcomePopup.vue';

// Props from HomeController — validated server-side
const props = defineProps({
    grades:           { type: Array, default: () => [] },
    featuredTeachers: { type: Array, default: () => [] },
    term:             { type: Object, default: null },
    stats:            {
        type: Object,
        default: () => ({
            registered_students: 0,
            available_courses: 0,
            active_teachers: 0,
        }),
    },
});

const termNotice = computed(() => {
    if (!props.term) return null;

    return props.term.is_current
        ? `${props.term.name} — جارٍ حتى ${props.term.ends_on}`
        : `${props.term.name} — يبدأ ${props.term.starts_on}`;
});

const page = usePage();

// Helper to remove any emojis from settings texts
function stripEmojis(text) {
    if (!text) return '';
    return text.replace(/[\u{1F600}-\u{1F64F}\u{1F300}-\u{1F5FF}\u{1F680}-\u{1F6FF}\u{1F1E0}-\u{1F1FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}\u{1F900}-\u{1F9FF}\u{1F100}-\u{1F1FF}\u{1F200}-\u{1F2FF}\u{1F300}-\u{1F5FF}\u{1F600}-\u{1F64F}\u{1F680}-\u{1F6FF}\u{1F900}-\u{1F9FF}\u{24C2}-\u{1F251}]/gu, '').trim();
}

const activeFaq = ref(null);

const settings = computed(() => page.props.settings || {});

const features = computed(() => {
    try {
        const raw = settings.value.home_features;
        if (raw) return typeof raw === 'string' ? JSON.parse(raw) : raw;
    } catch (e) {
        console.warn('Failed to parse home_features settings JSON:', e);
    }
    return [
        { title: 'حصص شرح', desc: 'تحتوي على شرح المادة بكل جزئياتها وفق خطة الوزارة المعتمدة نبسط فيها المعقد ونحث الفهم الكامل.', icon: 'courses' },
        { title: 'حصص الزووم', desc: 'تأتي لتكمل عملية التعليم بعد حصص الشرح لتوضيح أي معلومة وللإجابة على أي استفسار.', icon: 'live' },
        { title: 'الملازم والشيتات', desc: 'نلتزم بخطة وتعليمات وزارة التربية والتعليم ضمن ملازم خاصة تحتوي كل ما تحتاجه.', icon: 'courses' },
        { title: 'متابعة مستمرة', desc: 'لنتأكد من تقدم الطالب بشكل دقيق مع تحليل نقاط القوة والضعف وتقديم خطة فعالة.', icon: 'chart' },
        { title: 'معلمون خبراء', desc: 'نضم نخبة من المعلمين يقدمون شروحات دقيقة ومبسطة وإجابات فورية وإرشادات مهنية.', icon: 'teacher' }
    ];
});

const studentResults = computed(() => {
    try {
        const raw = settings.value.home_results;
        if (raw) return typeof raw === 'string' ? JSON.parse(raw) : raw;
    } catch (e) {
        console.warn('Failed to parse home_results settings JSON:', e);
    }
    return [
        { name: 'محمد الكواري', title: 'دفعة 2024', desc: 'الحصول على الدرجة الكاملة في الرياضيات والفيزياء', school: 'عمر بن الخطاب الثانوية للبنين' },
        { name: 'سارة المهندي', title: 'دفعة 2025 الفصل الثاني', desc: 'المركز الأول على المدرسة بمعدل 99.8%', school: 'آمنة بنت وهب الثانوية للبنات' },
        { name: 'خالد آل ثاني', title: 'دفعة 2025 الفصل الأول', desc: 'تفوق استثنائي في الكيمياء والأحياء بـ 100%', school: 'الدوحة الثانوية للبنين' },
        { name: 'مريم الباكر', title: 'دفعة 2026 الفصل الأول', desc: 'الدرجة الكاملة في اللغة العربية والإنجليزية', school: 'البيان الثانوية للبنات' }
    ];
});

const whyChooseUs = computed(() => {
    try {
        const raw = settings.value.home_why_choose_us;
        if (raw) return typeof raw === 'string' ? JSON.parse(raw) : raw;
    } catch (e) {
        console.warn('Failed to parse home_why_choose_us settings JSON:', e);
    }
    return [
        { icon: 'globe', title: 'تعليم مرن', desc: 'نتيح للطلبة سهولة الوصول للمعلومة في أي وقت ومن أي جهاز لتحقيق الاستفادة الكاملة.' },
        { icon: 'video', title: 'جودة عالية', desc: 'شروحات وفيديوهات بجودة عالية وتواصل مباشر مع الأساتذة لتحقيق الفهم الكامل.' },
        { icon: 'info', title: 'صوت وصورة نقية', desc: 'دروس مسجلة ومباشرة بأعلى دقة ونقاء صوتي لتسهيل استيعاب المعلومة بدون أي تشتيت.' },
        { icon: 'success', title: 'تبسيط المعلومة', desc: 'نسعى لتبسيط كل ما هو معقد بأساليب تعليمية حديثة ومبتكرة تناسب كافة الطلاب.' },
        { icon: 'chart', title: 'التزام كامل بالخطة', desc: 'متابعة دورية وجداول زمنية محددة تضمن تغطية المناهج وحل الاختبارات بالشكل الأمثل.' },
        { icon: 'settings', title: 'تقنية متطورة', desc: 'منصة آمنة وتفاعلية وسلسة خالية تماماً من التعقيدات وتدعم كافة الأجهزة الذكية.' }
    ];
});

const youtubeVideos = computed(() => {
    try {
        const raw = settings.value.home_youtube_videos;
        if (raw) return typeof raw === 'string' ? JSON.parse(raw) : raw;
    } catch (e) {
        console.warn('Failed to parse home_youtube_videos settings JSON:', e);
    }
    return [
        { title: 'طريقة التحضير لامتحانات الثانوية العامة في قطر', url: 'https://youtube.com', thumbnail: 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=500&q=80' },
        { title: 'مراجعة شاملة لمادة الفيزياء — الفصل الدراسي الثاني', url: 'https://youtube.com', thumbnail: 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?w=500&q=80' },
        { title: 'أسهل طريقة لفهم الكيمياء العضوية بالتفصيل', url: 'https://youtube.com', thumbnail: 'https://images.unsplash.com/photo-1532094349884-543bc11b234d?w=500&q=80' }
    ];
});

const parsedFaqs = computed(() => {
    try {
        const raw = settings.value.home_faqs;
        if (raw) return typeof raw === 'string' ? JSON.parse(raw) : raw;
    } catch (e) {
        console.warn('Failed to parse home_faqs settings JSON:', e);
    }
    return [
        { q: 'ما هي المراحل الدراسية التي تستهدفها منصة التفوق؟', a: 'تغطي المنصة منهج دولة قطر كاملاً من الصف الأول الابتدائي حتى الثاني عشر، وتشمل مسارات المرحلة الثانوية الثلاثة: العلمي، والآداب والإنسانيات، والتكنولوجي.' },
        { q: 'هل المناهج المشروحة مطابقة لخطط وزارة التربية والتعليم القطرية؟', a: 'نعم، جميع الشروحات والملازم والشيتات يتم إعدادها وتحديثها بانتظام لتطابق خطط ومعايير وزارة التربية والتعليم والتعليم العالي في قطر بنسبة 100%.' },
        { q: 'كيف يمكنني مشاهدة الدروس من خلال الجوال أو الآيباد؟', a: 'يمكنك الدراسة عبر الموقع مباشرة من أي متصفح، أو تنزيل تطبيق المنصة المخصص للأجهزة الذكية (آيفون، آيباد، أندرويد، وهواوي) لضمان أفضل سرعة تشغيل للفيديوهات.' },
        { q: 'ما هي خطوات الاشتراك مع معلم؟', a: 'قم بتسجيل حساب مجاني كطالب، ثم اختر صفك فالمادة، شاهد الفيديو التعريفي للمعلمين، واضغط اشتراك مع من يناسبك، حيث يمكنك الدفع بأمان وسهولة عبر بطاقتك الائتمانية أو بطاقة الخصم (Stripe).' },
        { q: 'هل توفر المنصة اختبارات أو كويزات تقييمية؟', a: 'نعم، تحتوي كل مجموعة على اختبارات قصيرة وواجبات تقييمية (شيتات) يقوم المعلم بتصحيحها ورصد درجاتها لمتابعة مستوى استيعابك بانتظام.' },
        { q: 'كيف يمكنني التواصل مع الدعم الفني في حال واجهتني مشكلة؟', a: 'فريق الدعم متواجد لخدمتك طوال أيام الأسبوع عبر الواتساب على الرقم +974 5555 6666 أو البريد الإلكتروني support@altafawwuq.com.' },
        { q: 'ماذا أفعل إذا نسيت كلمة المرور الخاصة بحسابي؟', a: 'اضغط على زر "نسيت كلمة المرور" في صفحة تسجيل الدخول، وأدخل بريدك الإلكتروني لتصلك رسالة تحتوي على رابط آمن لإعادة تعيين كلمة مرورك الجديدة فوراً.' },
        { q: 'من هم المعلمون في منصة التفوق؟', a: 'تضم المنصة نخبة من أكفأ المعلمين المتخصصين ذوي الخبرة الواسعة في تدريس المناهج القطرية والذين حقق طلابهم أعلى الدرجات في السنوات السابقة.' },
    ];
});

const selectedStageTab = ref('all');
const selectedTrackTab = ref('all');

const TRACK_LABELS = {
    science:    '🔬 العلمي',
    arts:       '📚 الآداب والإنسانيات',
    technology: '💻 التكنولوجي',
};

const filteredGrades = computed(() => {
    let list = props.grades;
    if (selectedStageTab.value !== 'all') {
        list = list.filter(g => g.stage === selectedStageTab.value);
    }
    if (selectedStageTab.value === 'secondary' && selectedTrackTab.value !== 'all') {
        // null track = grade 10 (common), show when 'all' is selected only
        list = list.filter(g => g.track === selectedTrackTab.value);
    }
    return list;
});

function selectStage(key) {
    selectedStageTab.value = key;
    selectedTrackTab.value = 'all';
}

// Grouped by stage so the secondary tracks sit under one heading instead of
// looking like extra, unrelated grades.
const STAGE_ORDER = ['primary', 'preparatory', 'secondary'];

const gradeGroups = computed(() => {
    const byStage = new Map();

    for (const grade of filteredGrades.value) {
        if (!byStage.has(grade.stage)) {
            byStage.set(grade.stage, { stage: grade.stage, label: grade.stage_label, grades: [] });
        }
        byStage.get(grade.stage).grades.push(grade);
    }

    return [...byStage.values()].sort(
        (a, b) => STAGE_ORDER.indexOf(a.stage) - STAGE_ORDER.indexOf(b.stage),
    );
});

function formatNumber(value) {
    return new Intl.NumberFormat('en-US').format(Number(value ?? 0));
}

</script>


<template>
    <AppLayout>
        <Head title="الرئيسية" />
        <WelcomePopup />

        <!-- ── Hero Section ─────────────────────────────────────── -->
        <section class="hero-gradient relative overflow-hidden">
            <!-- Background decoration -->
            <div class="absolute inset-0 opacity-10">
                <div class="absolute top-10 start-10 w-64 h-64 rounded-full bg-white/20 blur-3xl"></div>
                <div class="absolute bottom-10 end-10 w-96 h-96 rounded-full bg-accent-400/30 blur-3xl"></div>
            </div>

            <div class="container-app px-4 py-20 md:py-28 relative">
                <div class="max-w-2xl">
                    <div v-if="stripEmojis($page.props.settings?.home_hero_badge)" class="badge bg-white/20 text-white mb-6 text-sm py-1.5 px-4 flex items-center gap-1.5 w-fit animate-fade-in-up">
                        <Icon name="success" class="w-4 h-4 text-accent-300 animate-float" />
                        <span>{{ stripEmojis($page.props.settings?.home_hero_badge) }}</span>
                    </div>

                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white leading-[1.3] md:leading-[1.4] lg:leading-[1.5] mb-6 tracking-tight animate-fade-in-up animation-delay-100">
                        {{ stripEmojis($page.props.settings?.home_hero_title) }}
                        <span class="block text-accent-400 mt-3 font-bold">{{ stripEmojis($page.props.settings?.home_hero_subtitle) }}</span>
                    </h1>

                    <p class="text-lg text-white/80 mb-8 leading-relaxed max-w-lg animate-fade-in-up animation-delay-200">
                        {{ stripEmojis($page.props.settings?.home_hero_desc) }}
                    </p>

                    <div class="flex flex-wrap gap-4 items-center animate-fade-in-up animation-delay-300">
                        <a href="#grades" class="btn-accent btn-lg flex items-center gap-2 transform transition-all duration-300 hover:scale-105 hover:shadow-glow-accent">
                            <Icon name="courses" class="w-5 h-5 text-white" />
                            <span>{{ stripEmojis($page.props.settings?.home_hero_btn1) }}</span>
                        </a>
                        <Link :href="route('register')" class="btn btn-lg bg-white/10 text-white border border-white/20 hover:bg-white/20 flex items-center gap-2 transition-all duration-300 hover:scale-105">
                            <span>{{ stripEmojis($page.props.settings?.home_hero_btn2) }}</span>
                        </Link>
                    </div>

                    <!-- Stats -->
                    <div class="flex flex-wrap gap-8 mt-12 pt-8 border-t border-white/20 animate-fade-in-up animation-delay-400">
                        <div v-for="stat in [
                            { value: props.stats.registered_students, label: 'طالب مسجّل' },
                            { value: props.stats.available_courses, label: 'دورة متاحة' },
                            { value: props.stats.active_teachers, label: 'مدرس خبير' },
                        ]" :key="stat.label">
                            <div class="text-3xl font-black text-white hover:text-accent-400 transition-colors duration-300">{{ formatNumber(stat.value) }}</div>
                            <div class="text-sm text-white/70">{{ stat.label }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Features Bar ─────────────────────────────────────── -->
        <section class="bg-primary-950 text-white py-12 border-t border-primary-900/50">
            <div class="container-app px-4">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-6 divide-y md:divide-y-0 md:divide-x md:divide-x-reverse divide-primary-900/60 text-center md:text-start">
                    <div v-for="feat in features" :key="feat.title" class="pt-6 md:pt-0 md:px-4 flex flex-col gap-2 items-center md:items-start">
                        <div class="p-2.5 bg-primary-900 rounded-xl text-accent-400 w-fit">
                            <Icon :name="feat.icon" class="w-5 h-5" />
                        </div>
                        <h3 class="font-bold text-sm text-white mt-1">{{ feat.title }}</h3>
                        <p class="text-xs text-white/70 leading-relaxed">{{ feat.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Grades Grid — step one of the browse flow ─────────── -->
        <section id="grades" class="section bg-transparent scroll-mt-20">
            <div class="container-app">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-black text-surface-900 dark:text-white mb-3">
                        اختر صفك الدراسي
                    </h2>
                    <p class="text-surface-500 dark:text-surface-400">
                        الصف يفتح لك المواد، والمادة تفتح لك المعلمين
                    </p>

                    <p v-if="termNotice" class="mt-3 inline-flex items-center gap-1.5 badge-accent text-xs">
                        <Icon name="calendar" class="w-3.5 h-3.5" />
                        {{ termNotice }}
                    </p>
                </div>

                <!-- Grade Filter Tabs -->
                <div class="mb-6 animate-fade-in-up animation-delay-100">
                    <div class="flex flex-wrap justify-center gap-3 mb-3">
                        <button 
                            v-for="tab in [
                                { key: 'all', label: 'كل المراحل' },
                                { key: 'primary', label: 'المرحلة الابتدائية' },
                                { key: 'preparatory', label: 'المرحلة الإعدادية' },
                                { key: 'secondary', label: 'المرحلة الثانوية' }
                            ]"
                            :key="tab.key"
                            @click="selectStage(tab.key)" 
                            class="px-5 py-2 rounded-full text-xs font-bold transition-all duration-300 transform active:scale-95 border"
                            :class="selectedStageTab === tab.key 
                                ? 'bg-primary-600 border-primary-600 text-white shadow-glow-primary' 
                                : 'border-surface-200 dark:border-surface-800 text-surface-600 dark:text-surface-300 bg-surface-50 dark:bg-surface-900/50 hover:bg-surface-100 dark:hover:bg-surface-800'"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <!-- Track sub-filter for secondary -->
                    <Transition
                        enter-active-class="transition-all duration-300 ease-out"
                        enter-from-class="opacity-0 -translate-y-2"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition-all duration-200"
                        leave-from-class="opacity-100"
                        leave-to-class="opacity-0"
                    >
                        <div v-if="selectedStageTab === 'secondary'" class="flex flex-wrap justify-center gap-2 pt-3 border-t border-surface-200 dark:border-surface-800">
                            <span class="text-xs text-surface-400 font-semibold self-center">المسار:</span>
                            <button
                                v-for="track in [
                                    { key: 'all', label: 'كل المسارات' },
                                    { key: 'science',    label: TRACK_LABELS.science },
                                    { key: 'arts',       label: TRACK_LABELS.arts },
                                    { key: 'technology', label: TRACK_LABELS.technology },
                                ]"
                                :key="track.key"
                                @click="selectedTrackTab = track.key"
                                class="px-4 py-1.5 rounded-full text-xs font-bold transition-all duration-300 border"
                                :class="selectedTrackTab === track.key
                                    ? 'bg-accent-500 border-accent-500 text-white shadow-glow-accent'
                                    : 'border-surface-200 dark:border-surface-700 text-surface-600 dark:text-surface-300 bg-white dark:bg-surface-900/50 hover:bg-surface-100 dark:hover:bg-surface-800'"
                            >
                                {{ track.label }}
                            </button>
                        </div>
                    </Transition>
                </div>

                <!-- One block per stage, so the secondary tracks read clearly -->
                <div v-for="group in gradeGroups" :key="group.stage" class="mb-10 last:mb-0 animate-fade-in-up animation-delay-200">
                    <h3 v-if="gradeGroups.length > 1" class="text-sm font-black text-surface-700 dark:text-surface-300 mb-4 text-center">
                        {{ group.label }}
                    </h3>

                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                        <Link
                            v-for="grade in group.grades"
                            :key="grade.key"
                            :href="route('grades.show', { key: grade.key })"
                            class="hover-scale-premium card p-6 text-center group flex flex-col items-center justify-center transition-all duration-300"
                        >
                            <div class="p-4 rounded-full bg-accent-50/70 dark:bg-accent-950/40 text-primary-600 dark:text-primary-400 mb-4 group-hover:scale-110 group-hover:bg-accent-100 dark:group-hover:bg-accent-900/50 transition-all duration-300 border border-accent-500/10">
                                <Icon name="student" class="w-8 h-8 group-hover:animate-float" />
                            </div>

                            <div class="font-bold text-surface-800 dark:text-surface-100 text-sm group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                {{ grade.name }}
                            </div>

                            <span v-if="grade.track_label" class="badge-primary mt-2 text-[10px]">
                                {{ grade.track_label }}
                            </span>

                            <div class="flex items-center gap-1.5 mt-2 flex-wrap justify-center">
                                <span class="badge-gray text-[10px]">{{ grade.subjects_count }} مادة</span>
                                <span v-if="grade.teachers_count" class="badge-green text-[10px]">
                                    {{ grade.teachers_count }} معلم
                                </span>
                            </div>
                        </Link>
                    </div>
                </div>

                <p v-if="!filteredGrades.length" class="text-center text-sm text-surface-400 py-8">
                    لا توجد صفوف متاحة في هذه المرحلة حالياً.
                </p>
            </div>
        </section>

        <!-- ── Student Results Section ───────────────────────────── -->
        <section class="section bg-transparent">
            <div class="container-app">
                <div class="text-center mb-12 max-w-2xl mx-auto">
                    <span class="badge bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300 mb-3 inline-block">تميز ونتائج استثنائية</span>
                    <h2 class="text-3xl font-black text-surface-900 dark:text-white mb-4">
                        نتائج وآراء طلابنا المتفوقين
                    </h2>
                    <p class="text-surface-500 dark:text-surface-400 text-sm leading-relaxed">
                        تأسست منصتنا لتكون الخيار الأول للتعليم في قطر، وقد حصل طلابنا على أكثر من 100 درجة كاملة في مختلف المواد للمراحل الثانوية.
                    </p>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div v-for="result in studentResults" :key="result.name"
                        class="card-hover hover-scale-premium p-6 flex flex-col items-center justify-center text-center group"
                    >
                        <div class="w-16 h-16 rounded-full overflow-hidden bg-primary-50 dark:bg-primary-950/50 flex items-center justify-center text-primary-600 dark:text-primary-400 mb-4 border-2 border-primary-100 dark:border-primary-900 group-hover:scale-110 group-hover:border-primary-500 transition-all duration-300">
                            <span class="text-lg font-black group-hover:animate-float">{{ result.name.charAt(0) }}</span>
                        </div>
                        <h3 class="font-bold text-surface-800 dark:text-white text-sm mb-1 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">{{ result.name }}</h3>
                        <div class="badge bg-accent-50 text-accent-700 dark:bg-accent-950 dark:text-accent-400 text-[10px] mb-2 font-bold">{{ result.title }}</div>
                        <p class="text-xs text-surface-500 dark:text-surface-400 leading-relaxed">{{ result.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Teachers — each card carries their intro video ────── -->
        <section v-if="featuredTeachers.length" class="section bg-transparent">
            <div class="container-app">
                <div class="text-center mb-12">
                    <span class="badge bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300 mb-3 inline-block">نخبة كادرنا التعليمي</span>
                    <h2 class="text-3xl font-black text-surface-900 dark:text-white mb-3">
                        تعرّف على معلمينا
                    </h2>
                    <p class="text-surface-500 dark:text-surface-400 text-sm">
                        شاهد فيديو تعريفي لكل معلم واحكم بنفسك على طريقة الشرح قبل ما تحجز
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <TeacherCard
                        v-for="teacher in featuredTeachers"
                        :key="teacher.id"
                        :teacher="teacher"
                        class="animate-fade-up"
                    />
                </div>
            </div>
        </section>

        <!-- ── YouTube Videos Section ────────────────────────────── -->
        <section class="section bg-transparent">
            <div class="container-app">
                <div class="text-center mb-12">
                    <span class="badge bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-400 mb-3 inline-block font-bold">التفوق على يوتيوب</span>
                    <h2 class="text-3xl font-black text-surface-900 dark:text-white mb-3">
                        شروحات ومراجعات مجانية
                    </h2>
                    <p class="text-surface-500 dark:text-surface-400 text-sm">تابع شروحات إضافية ومراجعات مميزة على قناتنا الرسمية</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <a v-for="video in youtubeVideos" :key="video.title" :href="video.url" target="_blank"
                       class="card-hover group flex flex-col justify-between"
                    >
                        <div class="relative aspect-video overflow-hidden bg-surface-100 flex-shrink-0">
                            <img :src="video.thumbnail" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                            <!-- Overlay Play Button -->
                            <div class="absolute inset-0 bg-black/35 flex items-center justify-center group-hover:bg-black/50 transition-colors">
                                <div class="w-12 h-12 rounded-full bg-red-600 text-white flex items-center justify-center shadow-lg transform group-hover:scale-110 transition-transform duration-200">
                                    <Icon name="live" class="w-5 h-5 text-white" />
                                </div>
                            </div>
                        </div>
                        <div class="p-5 text-start">
                            <h4 class="font-bold text-surface-850 dark:text-white text-sm line-clamp-2 leading-relaxed mb-2 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                {{ video.title }}
                            </h4>
                            <span class="text-[10px] text-red-600 dark:text-red-400 font-bold flex items-center gap-1 group-hover:translate-x-[-4px] transition-transform">
                                <span>شاهد الآن على يوتيوب</span>
                                <span>🔗</span>
                            </span>
                        </div>
                    </a>
                </div>
            </div>
        </section>

        <!-- ── Why Choose Us ────────────────────────────────────── -->
        <section class="section bg-transparent relative overflow-hidden">
            <div class="container-app">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-black text-surface-900 dark:text-white mb-3">
                        لماذا التفوق خيارك الأول؟
                    </h2>
                    <p class="text-surface-500 dark:text-surface-400 text-sm">نصنع تجربة تعليمية فريدة تضمن لك الريادة</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="item in whyChooseUs" :key="item.title"
                        class="card-hover hover-scale-premium p-6 flex items-start gap-4 text-start group"
                    >
                        <div class="p-3 bg-primary-50 dark:bg-primary-950/40 text-primary-600 dark:text-primary-400 rounded-2xl flex-shrink-0 group-hover:scale-110 group-hover:bg-primary-100 dark:group-hover:bg-primary-900/50 transition-all duration-300">
                            <Icon :name="item.icon" class="w-6 h-6 group-hover:animate-float" />
                        </div>
                        <div>
                            <h3 class="font-bold text-surface-800 dark:text-white text-base mb-1.5 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">{{ item.title }}</h3>
                            <p class="text-xs text-surface-500 dark:text-surface-400 leading-relaxed">{{ item.desc }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── FAQ Section ──────────────────────────────────────── -->
        <section class="section bg-transparent">
            <div class="container-app max-w-3xl">
                <div class="text-center mb-12">
                    <span class="badge bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300 mb-3 inline-block">الأسئلة الشائعة</span>
                    <h2 class="text-3xl font-black text-surface-900 dark:text-white mb-3">
                        لديك استفسار؟ لدينا إجابة
                    </h2>
                </div>

                <div class="space-y-4">
                    <div 
                        v-for="(faq, idx) in parsedFaqs" 
                        :key="idx" 
                        class="border rounded-2xl overflow-hidden transition-all duration-300"
                        :class="activeFaq === idx 
                            ? 'border-primary-500/40 dark:border-primary-500/30 bg-surface-100/50 dark:bg-surface-900/50 shadow-sm' 
                            : 'border-surface-200 dark:border-surface-800/80 bg-surface-50/50 dark:bg-surface-900/20'"
                    >
                        <button 
                            @click="activeFaq = (activeFaq === idx ? null : idx)" 
                            class="w-full px-6 py-4 flex items-center justify-between text-start font-bold text-sm text-surface-850 dark:text-surface-100 transition-colors"
                            :class="{ 'text-primary-600 dark:text-primary-450': activeFaq === idx }"
                        >
                            <span>{{ faq.q }}</span>
                            <span class="text-xs transition-transform duration-300" :class="{ 'rotate-180 text-primary-500': activeFaq === idx }">▼</span>
                        </button>
                        <div 
                            v-if="activeFaq === idx" 
                            class="px-6 pb-5 pt-1 text-xs text-surface-600 dark:text-surface-300 leading-relaxed border-t border-surface-150/50 dark:border-surface-800/40"
                        >
                            {{ faq.a }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── CTA Section ────────────────────────────────────────── -->
        <section class="hero-gradient py-16">
            <div class="container-app px-4 text-center">
                <h2 class="text-3xl font-black text-white mb-4">{{ stripEmojis($page.props.settings?.home_cta_title) || 'ابدأ رحلتك نحو التفوق اليوم' }}</h2>
                <p class="text-white/80 mb-8 max-w-md mx-auto">
                    {{ stripEmojis($page.props.settings?.home_cta_desc) || 'انضم لآلاف الطلاب الذين حققوا نتائج متميزة مع منصة التفوق' }}
                </p>
                <Link :href="route('register')" class="btn-accent btn-lg transform transition-all duration-300 hover:scale-105 hover:shadow-glow-accent">
                    {{ stripEmojis($page.props.settings?.home_cta_btn) || 'إنشاء حساب مجاني — الآن' }}
                </Link>
            </div>
        </section>
    </AppLayout>
</template>
