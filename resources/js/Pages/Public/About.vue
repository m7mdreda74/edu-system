<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';

const page = usePage();
const settings = computed(() => page.props.settings || {});

const title = computed(() => settings.value.about_title || 'منصة التفوق التعليمية');
const badge = computed(() => settings.value.about_badge || 'منصتكم التعليمية الأولى');
const desc = computed(() => settings.value.about_desc || 'نصنع مستقبل التعليم في قطر من خلال تقديم أفضل الشروحات وأقوى المناهج التعليمية المتكاملة لطلاب المرحلة الثانوية على أيدي نخبة من أكفأ المعلمين.');

const values = computed(() => {
    const raw = settings.value.about_values;
    if (raw) {
        try {
            return typeof raw === 'string' ? JSON.parse(raw) : raw;
        } catch (e) {
            console.warn(e);
        }
    }
    return [
        { title: 'رؤيتنا', desc: 'تمكين جميع الطلاب في دولة قطر من تحقيق الدرجات الكاملة في اختبارات الشهادة الثانوية من خلال تبسيط المفاهيم المعقدة وتوفير بيئة تعليمية مرنة ومتطورة.', icon: 'student' },
        { title: 'رسالتنا', desc: 'تقديم تعليم تفاعلي متميز يعتمد على الجودة العالية، الصوت والصورة النقية، الحصص المباشرة والمسجلة، والمتابعة الأكاديمية المستمرة مع الطلاب وأولياء أمورهم.', icon: 'courses' }
    ];
});

const pillars = computed(() => {
    const raw = settings.value.about_pillars;
    if (raw) {
        try {
            return typeof raw === 'string' ? JSON.parse(raw) : raw;
        } catch (e) {
            console.warn(e);
        }
    }
    return [
        { title: 'التعليم المرن والمستمر', desc: 'نمنح الطالب كامل الحرية في الوصول للدروس المسجلة والحصص المباشرة في أي وقت ومكان، مع ملازم تفاعلية شاملة مطابقة لخطة الوزارة.' },
        { title: 'تبسيط وتفكيك المعلومة', desc: 'نعتمد على استراتيجيات شرح حديثة تركز على الفهم والتطبيق العملي بدلاً من الحفظ التلقائي، لتبسيط المسائل والتمارين المعقدة.' },
        { title: 'جودة الصوت والصورة الاحترافية', desc: 'نلتزم بتسجيل وعرض الفيديوهات بدقة HD وصوت نقي تماماً لضمان عدم تشتيت انتباه الطالب وتركيزه الكامل أثناء المذاكرة.' },
        { title: 'تكامل الحصص التفاعلية والزووم', desc: 'نربط الدروس المسجلة بحصص تفاعلية مباشرة عبر Zoom لحل الواجبات، والإجابة عن استفسارات الطلاب بشكل شخصي وفعال.' }
    ];
});
</script>

<template>
    <AppLayout>
        <Head title="من نحن" />

        <div class="bg-transparent py-16" dir="rtl">
            <div class="container-app px-4 max-w-4xl">
                <!-- Header -->
                <div class="text-center mb-12">
                    <span class="badge bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300 mb-3 inline-block">{{ badge }}</span>
                    <h1 class="text-3xl md:text-4xl font-black text-surface-900 dark:text-white mb-4">{{ title }}</h1>
                    <p class="text-surface-500 dark:text-surface-400 text-sm leading-relaxed max-w-2xl mx-auto">
                        {{ desc }}
                    </p>
                </div>

                <!-- Core Values Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-16">
                    <div v-for="val in values" :key="val.title" class="card p-8 border border-surface-200 dark:border-surface-800">
                        <div class="p-3.5 bg-primary-50 dark:bg-primary-950/40 text-primary-600 dark:text-primary-400 rounded-2xl w-fit mb-4">
                            <Icon :name="val.icon" class="w-7 h-7" />
                        </div>
                        <h3 class="text-xl font-bold text-surface-850 dark:text-white mb-2">{{ val.title }}</h3>
                        <p class="text-sm text-surface-500 dark:text-surface-400 leading-relaxed">{{ val.desc }}</p>
                    </div>
                </div>

                <!-- Core Pillars / Why Us details -->
                <div class="card p-8 md:p-10 border border-surface-200 dark:border-surface-800">
                    <h2 class="text-2xl font-black text-surface-900 dark:text-white mb-6 text-center">ركائز منصة التفوق</h2>
                    
                    <div class="space-y-6">
                        <div v-for="pillar in pillars" :key="pillar.title" class="flex gap-4 items-start border-b border-surface-100 dark:border-surface-800 pb-4 last:border-0 last:pb-0">
                            <div class="p-2 bg-accent-50 dark:bg-accent-950 text-accent-600 dark:text-accent-400 rounded-lg flex-shrink-0 mt-1">
                                <Icon name="success" class="w-4 h-4" />
                            </div>
                            <div>
                                <h4 class="font-bold text-surface-800 dark:text-white text-sm mb-1">{{ pillar.title }}</h4>
                                <p class="text-xs text-surface-500 dark:text-surface-400 leading-relaxed">{{ pillar.desc }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
