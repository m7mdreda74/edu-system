<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useCartStore } from '@/stores/cartStore';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';

const page = usePage();

function getGradeLabel(key) {
    const gl = page.props.grade_levels?.find(item => item.key === key);
    return gl ? gl.name : key;
}

const props = defineProps({
    course:     { type: Object,  required: true },
    isEnrolled: { type: Boolean, default: false },
});

const cartStore = useCartStore();

const effectivePrice = computed(() =>
    props.course.discount_price ?? props.course.price
);

const effectivePriceFormatted = computed(() =>
    effectivePrice.value === 0 ? 'مجاني' : formatQAR(effectivePrice.value)
);

const originalPriceFormatted = computed(() =>
    props.course.price ? formatQAR(props.course.price) : null
);

const hasDiscount = computed(() =>
    props.course.discount_price != null && props.course.discount_price < props.course.price
);

const discountPercent = computed(() => {
    if (!hasDiscount.value) return 0;
    return Math.round((1 - props.course.discount_price / props.course.price) * 100);
});

const avgRating = computed(() => {
    const reviews = props.course.reviews ?? [];
    if (!reviews.length) return 0;
    return (reviews.reduce((sum, r) => sum + r.rating, 0) / reviews.length).toFixed(1);
});

const totalDurationFormatted = computed(() => {
    if (!props.course.total_duration) return null;
    const h = Math.floor(props.course.total_duration / 3600);
    const m = Math.floor((props.course.total_duration % 3600) / 60);
    return h > 0 ? `${h} ساعة ${m} دقيقة` : `${m} دقيقة`;
});

function formatQAR(halala) {
    return new Intl.NumberFormat('ar-QA', {
        style: 'currency', currency: 'QAR', minimumFractionDigits: 0,
    }).format(halala / 100);
}

function handleEnroll() {
    if (props.course.price === 0 || effectivePrice.value === 0) {
        // Free course — enroll directly
        router.post(route('student.enroll', { slug: props.course.slug }));
    } else {
        cartStore.addToCart(props.course);
        router.visit(route('checkout.show', { slug: props.course.slug }));
    }
}
</script>

<template>
    <AppLayout>
        <Head :title="course.title" />

        <!-- ── Breadcrumb ────────────────────────────────────── -->
        <div class="bg-surface-50 dark:bg-surface-900 border-b border-surface-200 dark:border-surface-700">
            <div class="container-app px-4 py-3 text-sm text-surface-500 dark:text-surface-400 flex items-center gap-2">
                <Link :href="route('home')" class="hover:text-primary-600">الرئيسية</Link>
                <span>›</span>
                <Link :href="route('courses.index')" class="hover:text-primary-600">الكورسات</Link>
                <span>›</span>
                <span class="text-surface-700 dark:text-surface-200 line-clamp-1">{{ course.title }}</span>
            </div>
        </div>

        <div class="container-app px-4 py-10">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">

                <!-- ── Main Content ────────────────────────────── -->
                <div class="lg:col-span-2 space-y-8">

                    <!-- Title + meta -->
                    <div>
                        <div class="flex flex-wrap gap-2 mb-3">
                            <span v-if="course.subject" class="badge-primary">
                                {{ course.subject.name }}
                            </span>
                            <span v-if="course.grade_level && course.grade_level !== 'all'" class="badge-gray">
                                {{ getGradeLabel(course.grade_level) }}
                            </span>
                        </div>

                        <h1 class="text-3xl font-black text-surface-900 dark:text-white mb-4 leading-[1.3]">
                            {{ course.title }}
                        </h1>

                        <p class="text-surface-600 dark:text-surface-300 leading-relaxed">
                            {{ course.description }}
                        </p>
                    </div>

                    <!-- Teacher -->
                    <div v-if="course.teacher" class="flex items-center gap-4 p-4 card">
                        <Link :href="route('teachers.show', { id: course.teacher.id })">
                            <div class="avatar-xl bg-primary-100 dark:bg-primary-900">
                                <img v-if="course.teacher.avatar"
                                     :src="course.teacher.avatar"
                                     :alt="course.teacher.name"
                                     class="w-full h-full object-cover">
                                <span v-else class="text-2xl font-bold text-primary-700">
                                    {{ course.teacher.name?.charAt(0) }}
                                </span>
                            </div>
                        </Link>
                        <div>
                            <div class="text-xs text-surface-400 mb-1">المدرس</div>
                            <Link :href="route('teachers.show', { id: course.teacher.id })"
                                class="font-bold text-surface-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400">
                                {{ course.teacher.name }}
                            </Link>
                            <p v-if="course.teacher.bio" class="text-sm text-surface-500 dark:text-surface-400 mt-1 line-clamp-2">
                                {{ course.teacher.bio }}
                            </p>
                        </div>
                    </div>

                    <!-- Stats row -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div v-for="stat in [
                            { label: 'الدروس', value: `${course.total_lessons} درس` },
                            { label: 'المدة',  value: totalDurationFormatted ?? 'غير محدد' },
                            { label: 'التقييم', value: avgRating ? `${avgRating} / 5` : 'جديد' },
                            { label: 'المستوى', value: { beginner: 'مبتدئ', intermediate: 'متوسط', advanced: 'متقدم' }[course.level] },
                        ]" :key="stat.label"
                            class="card p-4 text-center"
                        >
                            <div class="text-lg font-bold text-primary-700 dark:text-primary-400">{{ stat.value }}</div>
                            <div class="text-xs text-surface-500 dark:text-surface-400 mt-1">{{ stat.label }}</div>
                        </div>
                    </div>

                    <!-- Curriculum -->
                    <div>
                        <h2 class="text-xl font-bold text-surface-900 dark:text-white mb-4">محتوى الكورس</h2>
                        <div class="card divide-y divide-surface-100 dark:divide-surface-700">
                            <div
                                v-for="(lesson, idx) in course.lessons"
                                :key="lesson.id"
                                class="flex items-center gap-4 p-4"
                            >
                                <div class="w-8 h-8 rounded-full bg-surface-100 dark:bg-surface-700
                                            flex items-center justify-center text-sm font-bold
                                            text-surface-500 dark:text-surface-400 flex-shrink-0">
                                    {{ idx + 1 }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-surface-800 dark:text-surface-100 line-clamp-1">
                                        {{ lesson.title }}
                                    </div>
                                    <div class="text-xs text-surface-400 mt-0.5 flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        {{ Math.floor(lesson.duration_seconds / 60) }} دقيقة
                                    </div>
                                </div>
                                <div v-if="lesson.is_free_preview" class="badge-green text-xs">
                                    مجاني
                                </div>
                                <div v-else class="text-surface-300 dark:text-surface-600 flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reviews -->
                    <div v-if="course.reviews?.length">
                        <h2 class="text-xl font-bold text-surface-900 dark:text-white mb-4">
                            التقييمات ({{ course.reviews.length }})
                        </h2>
                        <div class="space-y-4">
                            <div v-for="review in course.reviews.slice(0, 5)" :key="review.id"
                                 class="card p-4 flex gap-3">
                                <div class="avatar-md bg-primary-100 dark:bg-primary-900 flex-shrink-0">
                                    <span class="text-primary-700 font-bold text-sm">
                                        {{ review.user?.name?.charAt(0) }}
                                    </span>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="font-semibold text-sm text-surface-800 dark:text-white">
                                            {{ review.user?.name }}
                                        </span>
                                        <span class="flex items-center gap-0.5 text-yellow-500">
                                            <svg v-for="i in 5" :key="i" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5"
                                                 :class="i <= review.rating ? 'text-yellow-500' : 'text-surface-300 dark:text-surface-700'">
                                                <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.6 3.102-1.196 4.49c-.258 1.074.877 1.898 1.777 1.329L10 15.657l4.188 2.581c.9.569 2.035-.255 1.777-1.33l-1.196-4.49 3.6-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    </div>
                                    <p class="text-sm text-surface-500 dark:text-surface-400">{{ review.comment }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Sticky Purchase Card ────────────────────── -->
                <div class="lg:col-span-1">
                    <div class="card p-6 sticky top-20 space-y-4">

                        <!-- Thumbnail -->
                        <div class="aspect-video rounded-xl overflow-hidden bg-surface-200 dark:bg-surface-700 mb-4">
                            <img v-if="course.thumbnail"
                                 :src="course.thumbnail"
                                 :alt="course.title"
                                 class="w-full h-full object-cover">
                            <div v-else class="w-full h-full flex items-center justify-center text-surface-400 bg-surface-100 dark:bg-surface-800"><Icon name="courses" class="w-12 h-12" /></div>
                        </div>

                        <!-- Price -->
                        <div>
                            <div class="flex items-baseline gap-3">
                                <span class="text-3xl font-black text-primary-700 dark:text-primary-400">
                                    {{ effectivePriceFormatted }}
                                </span>
                                <span v-if="hasDiscount" class="text-lg text-surface-400 line-through">
                                    {{ originalPriceFormatted }}
                                </span>
                            </div>
                            <div v-if="hasDiscount" class="badge-accent mt-1">
                                وفّر {{ discountPercent }}%
                            </div>
                        </div>

                        <!-- Actions -->
                    <div class="flex flex-col sm:flex-row gap-3">
                        <template v-if="isEnrolled">
                            <Link :href="route('student.learn', { slug: course.slug })" class="btn-primary w-full sm:w-auto">
                                إكمال التعلم
                            </Link>
                            <!-- Chat Teacher Button -->
                            <form @submit.prevent="router.post(route('chat.start'), { course_id: course.id, teacher_id: course.teacher.id })" class="w-full sm:w-auto">
                                <button type="submit" class="btn-outline w-full hover:bg-surface-100 dark:hover:bg-surface-700">
                                    راسل المدرس
                                </button>
                            </form>
                        </template>
                        <template v-else>
                            <button @click="handleEnroll"
                                    class="btn-primary w-full" id="enroll-btn">
                                {{ effectivePrice === 0 ? 'سجّل مجاناً' : 'اشتري الكورس' }}
                            </button>
                        </template>
                    </div>

                        <!-- Features list -->
                        <ul class="space-y-2 text-sm text-surface-600 dark:text-surface-300 pt-2 border-t border-surface-100 dark:border-surface-700">
                            <li class="flex items-center gap-2"><Icon name="success" class="w-4 h-4 text-green-500" /> <span>وصول مدى الحياة</span></li>
                            <li class="flex items-center gap-2"><Icon name="globe" class="w-4 h-4 text-primary-500" /> <span>يعمل على الموبايل والكمبيوتر</span></li>
                            <li class="flex items-center gap-2"><Icon name="certificate" class="w-4 h-4 text-accent-500" /> <span>شهادة إتمام</span></li>
                            <li v-if="course.lessons?.some(l => l.is_free_preview)" class="flex items-center gap-2">
                                <Icon name="info" class="w-4 h-4 text-indigo-500" />
                                <span>بعض الدروس مجانية</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
