<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import GradeCard from '@/Components/GradeCard.vue';
import TeacherCard from '@/Components/TeacherCard.vue';
import Icon from '@/Components/Icon.vue';
import WelcomePopup from '@/Components/WelcomePopup.vue';

// Props from HomeController — validated server-side
const props = defineProps({
    grades:           { type: Array, default: () => [] },
    featuredTeachers: { type: Array, default: () => [] },
    term:             { type: Object, default: null },
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
const teacherScroller = ref(null);
const canScrollTeacherLeft = ref(false);
const canScrollTeacherRight = ref(false);

function updateTeacherScrollState() {
    const row = teacherScroller.value;
    if (!row) return;

    const cards = [...row.querySelectorAll('.teacher-card')];
    if (!cards.length) {
        canScrollTeacherLeft.value = false;
        canScrollTeacherRight.value = false;
        return;
    }

    const viewport = row.getBoundingClientRect();
    const leftEdge = Math.min(...cards.map((card) => card.getBoundingClientRect().left));
    const rightEdge = Math.max(...cards.map((card) => card.getBoundingClientRect().right));
    const tolerance = 2;

    canScrollTeacherLeft.value = leftEdge < viewport.left - tolerance;
    canScrollTeacherRight.value = rightEdge > viewport.right + tolerance;
}

function scrollTeachers(direction) {
    const row = teacherScroller.value;
    if (!row) return;

    const card = row.querySelector('.teacher-card');
    const styles = getComputedStyle(row);
    const gap = parseFloat(styles.columnGap || styles.gap || '0');
    const step = (card?.getBoundingClientRect().width || row.clientWidth * 0.8) + gap;
    const isRtl = styles.direction === 'rtl';
    const delta = direction === 'left'
        ? (isRtl ? -step : step)
        : (isRtl ? step : -step);

    row.scrollBy({ left: delta, behavior: 'smooth' });
    window.setTimeout(updateTeacherScrollState, 350);
}

onMounted(() => {
    nextTick(updateTeacherScrollState);
    window.addEventListener('resize', updateTeacherScrollState);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateTeacherScrollState);
});

const settings = computed(() => page.props.settings || {});

const heroContent = computed(() => ({
    badge: stripEmojis(settings.value.home_hero_badge) || 'منصة التعليم الأولى في قطر',
    title: stripEmojis(settings.value.home_hero_title) || 'تفوّق في دراستك الثانوية',
    subtitle: stripEmojis(settings.value.home_hero_subtitle) || 'منصة التفوق التعليمية الأولى في قطر',
    description: stripEmojis(settings.value.home_hero_desc) || 'نصنع مستقبل التعليم في قطر من خلال أفضل الشروحات والمناهج التعليمية المتكاملة.',
    primaryButton: stripEmojis(settings.value.home_hero_btn1) || 'ابدأ التعلم الآن',
    secondaryButton: stripEmojis(settings.value.home_hero_btn2) || 'إنشاء حساب جديد',
}));

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
        { name: 'محمد الكواري', title: 'دفعة 2024', desc: 'الحصول على الدرجة الكاملة في الرياضيات والفيزياء', grade: 'الصف الثاني عشر' },
        { name: 'سارة المهندي', title: 'دفعة 2025 الفصل الثاني', desc: 'المركز الأول على المدرسة بمعدل 99.8%', grade: 'الصف الثاني عشر' },
        { name: 'خالد آل ثاني', title: 'دفعة 2025 الفصل الأول', desc: 'تفوق استثنائي في الكيمياء والأحياء بـ 100%', grade: 'الصف الثاني عشر' },
        { name: 'مريم الباكر', title: 'دفعة 2026 الفصل الأول', desc: 'الدرجة الكاملة في اللغة العربية والإنجليزية', grade: 'الصف الثاني عشر' }
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
        const videos = raw ? (typeof raw === 'string' ? JSON.parse(raw) : raw) : [];

        return Array.isArray(videos)
            ? videos.filter((video) => typeof video?.url === 'string' && video.url.trim() !== '')
            : [];
    } catch (e) {
        console.warn('Failed to parse home_youtube_videos settings JSON:', e);
    }
    return [];
});

const youtubeSectionVisible = computed(() => {
    const visibility = settings.value.home_youtube_visible;
    const isEnabled = visibility === undefined
        || visibility === null
        || visibility === ''
        || visibility === true
        || visibility === 1
        || visibility === '1'
        || visibility === 'true';

    return isEnabled && youtubeVideos.value.length > 0;
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
const allStagePreview = ref([]);

const TRACK_LABELS = {
    science:    '🔬 العلمي',
    arts:       '📚 الآداب والإنسانيات',
    technology: '💻 التكنولوجي',
};

const STAGE_ORDER = ['primary', 'preparatory', 'secondary'];
const ALL_STAGE_PREVIEW_COUNT = 4;

function shuffled(items) {
    const copy = [...items];

    for (let index = copy.length - 1; index > 0; index -= 1) {
        const randomIndex = Math.floor(Math.random() * (index + 1));
        [copy[index], copy[randomIndex]] = [copy[randomIndex], copy[index]];
    }

    return copy;
}

function refreshAllStagePreview() {
    const queues = shuffled(STAGE_ORDER)
        .map((stage) => shuffled(props.grades.filter((grade) => grade.stage === stage)));
    const preview = [];

    while (preview.length < Math.min(ALL_STAGE_PREVIEW_COUNT, props.grades.length) && queues.some((queue) => queue.length)) {
        for (const queue of queues) {
            if (!queue.length) continue;

            preview.push(queue.shift());
            if (preview.length === ALL_STAGE_PREVIEW_COUNT) break;
        }
    }

    allStagePreview.value = preview;
}

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

    if (key === 'all') refreshAllStagePreview();
}

const visibleGrades = computed(() => {
    return selectedStageTab.value === 'all' ? allStagePreview.value : filteredGrades.value;
});

refreshAllStagePreview();

</script>


<template>
    <AppLayout>
        <Head title="الرئيسية" />
        <WelcomePopup />

        <!-- ── Hero Section ─────────────────────────────────────── -->
        <section class="hero-image relative overflow-hidden">
            <div class="container-app px-4 py-20 md:py-28 relative">
                <div class="max-w-2xl">
                    <div v-if="heroContent.badge" class="badge bg-white/20 text-white mb-6 text-sm py-1.5 px-4 flex items-center gap-1.5 w-fit">
                        <Icon name="success" class="w-4 h-4 text-accent-300 animate-float" />
                        <span>{{ heroContent.badge }}</span>
                    </div>

                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white leading-[1.3] md:leading-[1.4] lg:leading-[1.5] mb-6 tracking-tight">
                        {{ heroContent.title }}
                        <span class="block text-accent-400 mt-3 text-2xl font-bold md:text-3xl lg:text-4xl">{{ heroContent.subtitle }}</span>
                    </h1>

                    <p class="text-lg text-white/80 mb-8 leading-relaxed max-w-lg">
                        {{ heroContent.description }}
                    </p>

                    <div class="flex flex-wrap gap-4 items-center">
                        <a href="#grades" class="btn-accent btn-lg flex items-center gap-2 transform transition-all duration-300 hover:scale-105 hover:shadow-glow-accent">
                            <Icon name="courses" class="w-5 h-5 text-white" />
                            <span>{{ heroContent.primaryButton }}</span>
                        </a>
                        <Link :href="route('register')" class="btn btn-lg bg-white/10 text-white border border-white/20 hover:bg-white/20 flex items-center gap-2 transition-all duration-300 hover:scale-105">
                            <span>{{ heroContent.secondaryButton }}</span>
                        </Link>
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
                        <button type="button"
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
                            <button type="button"
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

                <!-- Keep the preview to one row; stage-specific rows can scroll horizontally. -->
                <div v-if="visibleGrades.length" class="animate-fade-in-up animation-delay-200">
                    <h3 v-if="selectedStageTab !== 'all'" class="text-sm font-black text-surface-700 dark:text-surface-300 mb-4 text-center">
                        {{ filteredGrades[0]?.stage_label }}
                    </h3>

                    <div
                        class="grade-card-row no-scrollbar"
                        :class="{ 'sm:justify-center': selectedStageTab === 'all' }"
                        aria-label="الصفوف الدراسية"
                    >
                        <GradeCard
                            v-for="grade in visibleGrades"
                            :key="grade.key"
                            :grade="grade"
                        />
                    </div>

                    <div class="mt-5 flex justify-center">
                        <Link :href="route('grades.index')" class="btn-outline btn-sm inline-flex items-center gap-2">
                            <span>عرض المزيد</span>
                            <Icon name="arrowLeft" class="w-4 h-4" />
                        </Link>
                    </div>
                </div>

                <p v-if="!filteredGrades.length" class="text-center text-sm text-surface-400 py-8">
                    لا توجد صفوف متاحة في هذه المرحلة حالياً.
                </p>
            </div>
        </section>

        <!-- ── Student Results Section ───────────────────────────── -->
        <section v-if="studentResults.length" class="section bg-transparent">
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
                        class="card-hover hover-scale-premium p-6 flex flex-col items-center justify-between text-center group"
                    >
                        <div class="flex flex-col items-center w-full gap-4">
                            <div class="flex items-center justify-center gap-2 w-full text-center">
                                <h3 class="font-bold text-surface-800 dark:text-white text-sm group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                    {{ result.name }}
                                </h3>
                                <span
                                    class="inline-flex shrink-0 text-accent-500"
                                    title="نتيجة مميزة"
                                    aria-label="نتيجة مميزة"
                                >
                                    <Icon name="success" class="w-6 h-6 text-accent-500" />
                                </span>
                            </div>
                            <p class="text-xs text-surface-500 dark:text-surface-400 leading-relaxed">{{ result.desc }}</p>
                        </div>

                        <div class="w-full mt-4 pt-3 border-t border-surface-100 dark:border-surface-800 text-[10px] text-surface-400 flex items-center justify-between">
                            <span class="badge-gray px-2 py-0.5 text-[9px] rounded font-bold">
                                {{ result.grade || 'الصف الدراسي غير محدد' }}
                            </span>
                            <span class="badge-gray px-2 py-0.5 text-[9px] rounded font-bold">
                                {{ result.title }}
                            </span>
                        </div>
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

                <div v-if="featuredTeachers.length > 1" class="teacher-carousel relative">
                    <button
                        type="button"
                        class="teacher-carousel-control teacher-carousel-control--left"
                        :disabled="!canScrollTeacherLeft"
                        aria-label="تحريك كروت المعلمين إلى اليسار"
                        title="تحريك إلى اليسار"
                        @click="scrollTeachers('left')"
                    >
                        <Icon name="arrowLeft" class="teacher-carousel-control__arrow w-7 h-7" />
                    </button>

                    <div
                        ref="teacherScroller"
                        class="teacher-card-row no-scrollbar"
                        aria-label="كروت المعلمين"
                        @scroll="updateTeacherScrollState"
                    >
                        <TeacherCard
                            v-for="teacher in featuredTeachers"
                            :key="teacher.id"
                            :teacher="teacher"
                            class="animate-fade-up"
                        />
                    </div>

                    <button
                        type="button"
                        class="teacher-carousel-control teacher-carousel-control--right"
                        :disabled="!canScrollTeacherRight"
                        aria-label="تحريك كروت المعلمين إلى اليمين"
                        title="تحريك إلى اليمين"
                        @click="scrollTeachers('right')"
                    >
                        <Icon name="arrowRight" class="teacher-carousel-control__arrow w-7 h-7" />
                    </button>
                </div>
            </div>
        </section>

        <!-- ── YouTube Videos Section ────────────────────────────── -->
        <section v-if="youtubeSectionVisible" class="section bg-transparent">
            <div class="container-app">
                <div class="text-center mb-12">
                    <span class="badge bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-400 mb-3 inline-block font-bold">التفوق على يوتيوب</span>
                    <h2 class="text-3xl font-black text-surface-900 dark:text-white mb-3">
                        شروحات ومراجعات مجانية
                    </h2>
                    <p class="text-surface-500 dark:text-surface-400 text-sm">تابع شروحات إضافية ومراجعات مميزة على قناتنا الرسمية</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <a v-for="video in youtubeVideos" :key="video.title" :href="video.url" target="_blank" rel="noopener noreferrer"
                       class="card-hover group flex flex-col justify-between"
                    >
                        <div class="relative aspect-video overflow-hidden bg-surface-100 flex-shrink-0">
                            <img :src="video.thumbnail" :alt="video.title || 'فيديو تعليمي'" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
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
                            type="button"
                            @click="activeFaq = (activeFaq === idx ? null : idx)" 
                            :aria-expanded="activeFaq === idx"
                            :aria-controls="`faq-answer-${idx}`"
                            class="w-full px-6 py-4 flex items-center justify-between text-start font-bold text-sm text-surface-850 dark:text-surface-100 transition-colors"
                            :class="{ 'text-primary-600 dark:text-primary-500': activeFaq === idx }"
                        >
                            <span>{{ faq.q }}</span>
                            <span aria-hidden="true" class="text-xs transition-transform duration-300" :class="{ 'rotate-180 text-primary-500': activeFaq === idx }">▼</span>
                        </button>
                        <div 
                            v-if="activeFaq === idx" 
                            :id="`faq-answer-${idx}`"
                            class="px-6 pb-5 pt-1 text-xs text-surface-600 dark:text-surface-300 leading-relaxed border-t border-surface-200/50 dark:border-surface-800/40"
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
                <Link :href="route('register')" class="btn btn-lg bg-white text-primary-800 border border-white/80 shadow-xl transform transition-all duration-300 hover:scale-105 hover:bg-surface-50 hover:shadow-glow-accent">
                    {{ stripEmojis($page.props.settings?.home_cta_btn) || 'إنشاء حساب مجاني — الآن' }}
                </Link>
            </div>
        </section>
    </AppLayout>
</template>
