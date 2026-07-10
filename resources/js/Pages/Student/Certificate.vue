<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    certificate: { type: Object, required: true },
    enrollment:  { type: Object, required: true },
});

function printCertificate() {
    window.print();
}
</script>

<template>
    <AppLayout>
        <Head :title="`شهادة — ${certificate.course_title}`" />

        <div class="container-app px-4 py-10 max-w-4xl">

            <!-- Actions (hidden in print) -->
            <div class="flex items-center justify-between mb-8 print:hidden">
                <Link :href="route('dashboard')" class="btn-ghost">
                    ← العودة للداشبورد
                </Link>
                <button @click="printCertificate" class="btn-primary" id="print-cert-btn">
                    طباعة الشهادة
                </button>
            </div>

            <!-- ── Certificate Card ─────────────────────────── -->
            <div id="certificate-card"
                 class="relative bg-white rounded-3xl overflow-hidden shadow-2xl border-8 border-primary-100"
                 style="font-family: 'Cairo', sans-serif;"
            >
                <!-- Top decorative band -->
                <div class="h-4 bg-gradient-to-r from-primary-600 via-primary-400 to-accent-400"></div>

                <!-- Content -->
                <div class="p-12 md:p-16 text-center relative">
                    <!-- Background watermark -->
                    <div class="absolute inset-0 flex items-center justify-center opacity-5 pointer-events-none">
                        <div class="text-[200px] font-black text-primary-600">ت</div>
                    </div>

                    <!-- Logo & Platform -->
                    <div class="flex items-center justify-center gap-3 mb-8">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary-500 to-primary-700
                                    flex items-center justify-center shadow-glow-primary">
                            <span class="text-white font-black text-2xl">ت</span>
                        </div>
                        <div class="text-start">
                            <div class="text-2xl font-black text-primary-800">{{ certificate.platform_name }}</div>
                            <div class="text-xs text-surface-400">التميز في التعليم</div>
                        </div>
                    </div>

                    <!-- Certificate Title -->
                    <div class="text-xs font-semibold tracking-widest text-primary-500 uppercase mb-3">
                        شهادة إتمام
                    </div>
                    <div class="w-20 h-0.5 bg-gradient-to-r from-primary-400 to-accent-400 mx-auto mb-8"></div>

                    <!-- This is to certify -->
                    <p class="text-surface-500 text-sm mb-4">يُشهد بأن الطالب/ة</p>
                    <h2 class="text-4xl font-black text-surface-900 mb-6"
                        style="font-family: 'Cairo', sans-serif;">
                        {{ certificate.student_name }}
                    </h2>

                    <p class="text-surface-500 text-sm mb-3">قد أتمّ/أتمّت بنجاح كورس</p>
                    <h3 class="text-2xl font-bold text-primary-800 mb-3 leading-snug">
                        {{ certificate.course_title }}
                    </h3>
                    <p class="text-surface-500 text-sm mb-10">
                        بإشراف المدرس/ة: <strong class="text-surface-700">{{ certificate.teacher_name }}</strong>
                    </p>

                    <!-- Decorative divider -->
                    <div class="flex items-center gap-4 mb-8">
                        <div class="flex-1 h-px bg-gradient-to-r from-transparent to-primary-200"></div>
                        <svg class="w-6 h-6 text-accent-500 mx-auto" fill="currentColor" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                        <div class="flex-1 h-px bg-gradient-to-l from-transparent to-primary-200"></div>
                    </div>

                    <!-- Certificate details -->
                    <div class="grid grid-cols-2 gap-6">
                        <div class="text-center">
                            <div class="text-xs text-surface-400 mb-1">تاريخ الإتمام</div>
                            <div class="font-bold text-surface-700">{{ certificate.completed_at }}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-xs text-surface-400 mb-1">رقم الشهادة</div>
                            <div class="font-mono font-bold text-primary-700">{{ certificate.cert_number }}</div>
                        </div>
                    </div>

                    <!-- Seal -->
                    <div class="absolute bottom-8 end-8 w-20 h-20 rounded-full
                                border-4 border-primary-200 flex items-center justify-center
                                bg-white shadow-glow-primary opacity-60">
                        <div class="text-center">
                            <div class="text-primary-600 font-black text-xs">مُعتمد</div>
                            <div class="text-primary-400 text-xs">التفوق</div>
                        </div>
                    </div>
                </div>

                <!-- Bottom decorative band -->
                <div class="h-4 bg-gradient-to-r from-accent-400 via-primary-400 to-primary-600"></div>
            </div>

            <!-- Share / Download actions -->
            <div class="flex gap-3 justify-center mt-8 print:hidden">
                <button @click="printCertificate" class="btn-outline">
                    حفظ كـ PDF
                </button>
            </div>
        </div>
    </AppLayout>
</template>

<style>
@media print {
    body * { visibility: hidden; }
    #certificate-card, #certificate-card * { visibility: visible; }
    #certificate-card { position: fixed; top: 0; left: 0; width: 100%; }
}
</style>
