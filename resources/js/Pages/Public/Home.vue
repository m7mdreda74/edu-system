<script setup>
import { computed, ref, watch } from 'vue';
import { Link, Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CourseCard from '@/Components/CourseCard.vue';
import Icon from '@/Components/Icon.vue';
import WelcomePopup from '@/Components/WelcomePopup.vue';

// Props from HomeController — validated server-side
const props = defineProps({
    featuredCourses: { type: Array, default: () => [] },
    subjects:        { type: Array, default: () => [] },
    teachers:        { type: Array, default: () => [] },
});

const page = usePage();

function getGradeLabel(key) {
    const gl = page.props.grade_levels?.find(item => item.key === key);
    return gl ? gl.name : key;
}

const subjectIcons = {
    calculator: 'calculator',
    atom:       'atom',
    flask:      'flask',
    dna:        'dna',
    book:       'courses',
    language:   'globe',
    landmark:   'landmark',
    globe:      'globe',
};

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
        { q: 'ما هي المراحل الدراسية التي تستهدفها منصة التفوق؟', a: 'تستهدف المنصة بشكل رئيسي طلاب المرحلة الثانوية (الصف العاشر، الحادي عشر، والثاني عشر / التوجيهي) في دولة قطر.' },
        { q: 'هل المناهج المشروحة مطابقة لخطط وزارة التربية والتعليم القطرية؟', a: 'نعم، جميع الكورسات والملازم والشيتات يتم إعدادها وتحديثها بانتظام لتطابق خطط ومعايير وزارة التربية والتعليم والتعليم العالي في قطر بنسبة 100%.' },
        { q: 'كيف يمكنني مشاهدة الدروس من خلال الجوال أو الآيباد؟', a: 'يمكنك الدراسة عبر الموقع مباشرة من أي متصفح، أو تنزيل تطبيق المنصة المخصص للأجهزة الذكية (آيفون، آيباد، أندرويد، وهواوي) لضمان أفضل سرعة تشغيل للفيديوهات.' },
        { q: 'ما هي خطوات الاشتراك وشراء الكورسات؟', a: 'قم بتسجيل حساب مجاني كطالب، ثم اختر المادة أو الكورس المناسب واضغط على زر الاشتراك، حيث يمكنك الدفع بأمان وسهولة عبر بطاقتك الائتمانية أو بطاقة الخصم (Stripe).' },
        { q: 'هل توفر المنصة اختبارات أو كويزات تقييمية؟', a: 'نعم، يحتوي كل كورس على اختبارات قصيرة وواجبات تقييمية (شيتات) يقوم المعلم بتصحيحها ورصد درجاتها لمتابعة مستوى استيعابك بانتظام.' },
        { q: 'كيف يمكنني التواصل مع الدعم الفني في حال واجهتني مشكلة؟', a: 'فريق الدعم متواجد لخدمتك طوال أيام الأسبوع عبر الواتساب على الرقم +974 5555 6666 أو البريد الإلكتروني support@altafawwuq.com.' },
        { q: 'ماذا أفعل إذا نسيت كلمة المرور الخاصة بحسابي؟', a: 'اضغط على زر "نسيت كلمة المرور" في صفحة تسجيل الدخول، وأدخل بريدك الإلكتروني لتصلك رسالة تحتوي على رابط آمن لإعادة تعيين كلمة مرورك الجديدة فوراً.' },
        { q: 'من هم المعلمون في منصة التفوق؟', a: 'تضم المنصة نخبة من أكفأ المعلمين المتخصصين ذوي الخبرة الواسعة في تدريس المناهج القطرية والذين حقق طلابهم أعلى الدرجات في السنوات السابقة.' },
    ];
});

const selectedStageTab = ref('all');
const selectedGradeTab = ref('all');

watch(selectedStageTab, () => {
    if (selectedStageTab.value !== 'all' && selectedGradeTab.value !== 'all') {
        const gl = page.props.grade_levels?.find(g => g.key === selectedGradeTab.value);
        if (!gl || gl.stage !== selectedStageTab.value) {
            selectedGradeTab.value = 'all';
        }
    }
});

const subGrades = computed(() => {
    const gls = page.props.grade_levels || [];
    if (selectedStageTab.value === 'all') {
        return gls.filter(g => g.key !== 'all');
    }
    return gls.filter(g => g.stage === selectedStageTab.value && g.key !== 'all');
});

const filteredSubjects = computed(() => {
    let result = props.subjects;

    if (selectedStageTab.value !== 'all') {
        result = result.filter(s => {
            if (s.grade_level === 'all') return true;
            const gl = page.props.grade_levels?.find(g => g.key === s.grade_level);
            return gl && gl.stage === selectedStageTab.value;
        });
    }

    if (selectedGradeTab.value !== 'all') {
        result = result.filter(s => s.grade_level === selectedGradeTab.value || s.grade_level === 'all');
    }

    return result;
});

function getGradeNumber(key) {
    return key.replace('grade_', '');
}

function selectGrade(glKey) {
    selectedGradeTab.value = glKey;
    if (glKey === 'all') return;
    
    const gl = page.props.grade_levels?.find(g => g.key === glKey);
    if (gl && gl.stage && selectedStageTab.value !== gl.stage) {
        selectedStageTab.value = gl.stage;
    }
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
                    <div class="badge bg-white/20 text-white mb-6 text-sm py-1.5 px-4 flex items-center gap-1.5 w-fit animate-fade-in-up">
                        <Icon name="success" class="w-4 h-4 text-accent-300 animate-float" />
                        <span>منصة التعليم الأولى في قطر</span>
                    </div>

                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white leading-[1.3] md:leading-[1.4] lg:leading-[1.5] mb-6 tracking-tight animate-fade-in-up animation-delay-100">
                        {{ stripEmojis($page.props.settings?.home_hero_title) || 'تفوّق في دراستك' }}
                        <span class="block text-accent-400 mt-3 font-bold">{{ stripEmojis($page.props.settings?.home_hero_subtitle) || 'مع أفضل المدرسين' }}</span>
                    </h1>

                    <p class="text-lg text-white/80 mb-8 leading-relaxed max-w-lg animate-fade-in-up animation-delay-200">
                        {{ stripEmojis($page.props.settings?.home_hero_desc) || 'كورسات متخصصة لمواد المرحلة الثانوية — رياضيات، فيزياء، كيمياء، أحياء، وأكثر. تعلّم بالسرعة التي تناسبك، من أي مكان وبجودة استثنائية.' }}
                    </p>

                    <div class="flex flex-wrap gap-4 items-center animate-fade-in-up animation-delay-300">
                        <Link :href="route('courses.index')" class="btn-accent btn-lg flex items-center gap-2 transform transition-all duration-300 hover:scale-105 hover:shadow-glow-accent">
                            <Icon name="courses" class="w-5 h-5 text-white" />
                            <span>{{ stripEmojis($page.props.settings?.home_hero_btn1) || 'ابدأ التعلم الآن' }}</span>
                        </Link>
                        <Link :href="route('register')" class="btn btn-lg bg-white/10 text-white border border-white/20 hover:bg-white/20 flex items-center gap-2 transition-all duration-300 hover:scale-105">
                            <span>{{ stripEmojis($page.props.settings?.home_hero_btn2) || 'إنشاء حساب مجاني' }}</span>
                        </Link>
                    </div>

                    <!-- Stats -->
                    <div class="flex flex-wrap gap-8 mt-12 pt-8 border-t border-white/20 animate-fade-in-up animation-delay-400">
                        <div v-for="stat in [
                            { value: $page.props.settings?.home_stats_students ?? '+500', label: 'طالب مسجّل' },
                            { value: $page.props.settings?.home_stats_courses ?? '+50',  label: 'كورس متاح' },
                            { value: $page.props.settings?.home_stats_teachers ?? '+20',  label: 'مدرس خبير' },
                        ]" :key="stat.label">
                            <div class="text-3xl font-black text-white hover:text-accent-400 transition-colors duration-300">{{ stat.value }}</div>
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

        <!-- ── Subjects Grid ─────────────────────────────────────── -->
        <section class="section bg-transparent">
            <div class="container-app">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-black text-surface-900 dark:text-white mb-3">
                        تصفّح المواد الدراسية
                    </h2>
                    <p class="text-surface-500 dark:text-surface-400">
                        كل مادة تجدها هنا بشرح مبسط وممتاز
                    </p>
                </div>

                <!-- Grade Filter Tabs -->
                <div class="flex flex-wrap justify-center gap-3 mb-6 animate-fade-in-up animation-delay-100">
                    <button 
                        v-for="tab in [
                            { key: 'all', label: 'كل المراحل' },
                            { key: 'primary', label: 'المرحلة الابتدائية' },
                            { key: 'preparatory', label: 'المرحلة الإعدادية' },
                            { key: 'secondary', label: 'المرحلة الثانوية' }
                        ]"
                        :key="tab.key"
                        @click="selectedStageTab = tab.key" 
                        class="px-5 py-2 rounded-full text-xs font-bold transition-all duration-300 transform active:scale-95 border"
                        :class="selectedStageTab === tab.key 
                            ? 'bg-primary-600 border-primary-600 text-white shadow-glow-primary' 
                            : 'border-surface-200 dark:border-surface-800 text-surface-600 dark:text-surface-300 bg-surface-50 dark:bg-surface-900/50 hover:bg-surface-100 dark:hover:bg-surface-800'"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <!-- Sub-Grades (Grade Levels) Tabs -->
                <div class="flex flex-wrap justify-center items-center gap-2 mb-10 animate-fade-in-up animation-delay-150" dir="rtl">
                    <span class="text-xs font-bold text-surface-450 dark:text-surface-500 ml-2">الصف الدراسي:</span>
                    <button 
                        @click="selectGrade('all')" 
                        class="px-4 py-1.5 rounded-full text-[11px] font-bold transition-all duration-300 transform active:scale-95 border"
                        :class="selectedGradeTab === 'all' 
                            ? 'bg-accent-500 border-accent-500 text-white shadow-glow-accent' 
                            : 'border-surface-200 dark:border-surface-800/60 text-surface-550 dark:text-surface-400 bg-surface-50/50 dark:bg-surface-950/30 hover:bg-surface-100 dark:hover:bg-surface-900'"
                    >
                        كل الصفوف
                    </button>
                    <button 
                        v-for="gl in subGrades" 
                        :key="gl.key"
                        @click="selectGrade(gl.key)" 
                        class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-300 transform active:scale-95 border"
                        :class="selectedGradeTab === gl.key 
                            ? 'bg-accent-500 border-accent-500 text-white shadow-glow-accent' 
                            : 'border-surface-200 dark:border-surface-800/60 text-surface-550 dark:text-surface-400 bg-surface-50/50 dark:bg-surface-950/30 hover:bg-surface-100 dark:hover:bg-surface-900'"
                        :title="gl.name"
                    >
                        {{ getGradeNumber(gl.key) }}
                    </button>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 animate-fade-in-up animation-delay-200">
                    <Link
                        v-for="subject in filteredSubjects"
                        :key="subject.id"
                        :href="route('courses.index', { 
                            subject_id: subject.id,
                            stage: selectedStageTab !== 'all' ? selectedStageTab : undefined,
                            grade_level: selectedGradeTab !== 'all' ? selectedGradeTab : undefined
                        })"
                        class="hover-scale-premium card p-6 text-center group flex flex-col items-center justify-center transition-all duration-300 border border-surface-100 dark:border-surface-800/40 hover:border-accent-500/30"
                    >
                        <div class="p-4 rounded-full bg-accent-50/70 dark:bg-accent-950/40 text-primary-600 dark:text-primary-400 mb-4 group-hover:scale-110 group-hover:bg-accent-100 dark:group-hover:bg-accent-900/50 transition-all duration-300 border border-accent-500/10">
                            <Icon :name="subjectIcons[subject.icon] ?? 'courses'" class="w-8 h-8 group-hover:animate-float" />
                        </div>
                        <div class="font-bold text-surface-800 dark:text-surface-100 text-sm group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                            {{ subject.name }}
                        </div>
                        <div v-if="subject.grade_level && subject.grade_level !== 'all'"
                             class="badge-gray mt-2 text-xs">
                            {{ getGradeLabel(subject.grade_level) }}
                        </div>
                    </Link>
                </div>
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
                        class="card-hover hover-scale-premium p-6 flex flex-col items-center justify-center text-center border border-surface-200 dark:border-surface-800 group"
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

        <!-- ── Featured Courses ──────────────────────────────────── -->
        <section class="section bg-transparent">
            <div class="container-app">
                <div class="flex items-center justify-between mb-10">
                    <div>
                        <h2 class="text-3xl font-black text-surface-900 dark:text-white mb-2">
                            الكورسات الأكثر شعبية
                        </h2>
                        <p class="text-surface-500 dark:text-surface-400">اختارها آلاف الطلاب</p>
                    </div>
                    <Link :href="route('courses.index')" class="btn-outline hidden sm:flex items-center gap-2">
                        <span>عرض الكل</span>
                        <Icon name="arrowLeft" class="w-4 h-4 rtl-flip" />
                    </Link>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <CourseCard
                        v-for="course in featuredCourses"
                        :key="course.id"
                        :course="course"
                        class="animate-fade-up"
                    />
                </div>

                <div class="text-center mt-8 sm:hidden">
                    <Link :href="route('courses.index')" class="btn-outline">عرض كل الكورسات</Link>
                </div>
            </div>
        </section>

        <!-- ── Instructors Section ──────────────────────────────── -->
        <section v-if="teachers && teachers.length > 0" class="section bg-transparent">
            <div class="container-app">
                <div class="text-center mb-12">
                    <span class="badge bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300 mb-3 inline-block">نخبة كادرنا التعليمي</span>
                    <h2 class="text-3xl font-black text-surface-900 dark:text-white mb-3">
                        محترفو التميز
                    </h2>
                    <p class="text-surface-500 dark:text-surface-400 text-sm">أفضل الأساتذة والمعلمين لضمان تفوقك الدراسي</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <Link v-for="teacher in teachers" :key="teacher.id" :href="route('teachers.show', teacher.id)"
                        class="card-hover p-6 flex items-center gap-4 text-start group"
                    >
                        <div class="w-16 h-16 rounded-full overflow-hidden bg-surface-100 border-2 border-primary-200 flex-shrink-0 flex items-center justify-center text-primary-600 font-bold text-xl group-hover:scale-105 group-hover:border-primary-500 transition-all duration-300">
                            <img v-if="teacher.avatar" :src="teacher.avatar" class="w-full h-full object-cover">
                            <span v-else>{{ teacher.name.charAt(0) }}</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-surface-850 dark:text-white text-base mb-1 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">{{ teacher.name }}</h3>
                            <p class="text-xs text-surface-500 dark:text-surface-400 line-clamp-2 leading-relaxed font-semibold">{{ teacher.bio }}</p>
                        </div>
                    </Link>
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
                    <div v-for="(faq, idx) in parsedFaqs" :key="idx" class="border border-surface-200 dark:border-surface-800 rounded-2xl overflow-hidden bg-surface-50/50 dark:bg-surface-955/20">
                        <button @click="activeFaq = (activeFaq === idx ? null : idx)" class="w-full px-6 py-4 flex items-center justify-between text-start font-bold text-sm text-surface-850 dark:text-white">
                            <span>{{ faq.q }}</span>
                            <span class="text-xs transition-transform duration-200" :class="{ 'rotate-180': activeFaq === idx }">▼</span>
                        </button>
                        <div v-if="activeFaq === idx" class="px-6 pb-5 pt-1 text-xs text-surface-500 dark:text-surface-400 leading-relaxed border-t border-surface-150/50 dark:border-surface-800/50">
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
