<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';
import StatCard from '@/Components/StatCard.vue';
import { formatQAR } from '@/lib/money';

const props = defineProps({
    stats:          { type: Object, required: true },
    revenueChart:   { type: Array,  default: () => [] },
    recentPayments: { type: Array,  default: () => [] },
    recentActivity: { type: Array,  default: () => [] },
    term:           { type: Object, default: null },
});

// ── Live figures ───────────────────────────────────────────────
// Polled rather than pushed: Vercel runs this serverless, so there is no
// socket to hold open. Twenty seconds is frequent enough to feel live without
// hammering the database.
const POLL_MS = 60_000;

const live = ref({ ...props.stats });
const lastSync = ref(new Date());
const isSyncing = ref(false);
let timer = null;

async function sync() {
    if (document.hidden) return; // Don't poll a tab nobody is looking at.

    isSyncing.value = true;

    try {
        const { data } = await axios.get(route('admin.dashboard.stats'));
        live.value = data.stats;
        lastSync.value = new Date(data.fetchedAt);
    } catch (e) {
        console.warn('Dashboard stats refresh failed:', e.message);
    } finally {
        isSyncing.value = false;
    }
}

onMounted(() => {
    timer = setInterval(sync, POLL_MS);
    document.addEventListener('visibilitychange', onVisible);
});

onBeforeUnmount(() => {
    clearInterval(timer);
    document.removeEventListener('visibilitychange', onVisible);
});

// Catch up immediately when the admin comes back to the tab.
function onVisible() {
    if (!document.hidden) sync();
}

const syncedAgo = computed(() => {
    const seconds = Math.round((Date.now() - lastSync.value.getTime()) / 1000);
    if (seconds < 60) return 'محدّث الآن';
    return `آخر تحديث منذ ${Math.round(seconds / 60)} دقيقة`;
});

// ── Action queue ───────────────────────────────────────────────
const actions = computed(() => {
    const n = live.value.needs_action ?? {};

    return [
        { key: 'receipts', count: n.payment_receipts,  label: 'إيصال تحويل بانتظار المراجعة', href: route('admin.payments') + '?status=pending_verification', tone: 'accent', icon: 'payments' },
        { key: 'reviews',  count: n.pending_reviews,   label: 'تقييم بانتظار الاعتماد',        href: route('admin.reviews'),                                  tone: 'accent', icon: 'chat' },
        { key: 'payouts',  count: n.pending_payouts,   label: 'تسوية أرباح لم تُدفع',          href: route('admin.payouts'),                                  tone: 'red',    icon: 'earnings' },
        { key: 'requests', count: n.purchase_requests, label: 'طلب شراء من طالب لولي أمره',    href: route('admin.subscriptions'),                            tone: 'slate',  icon: 'student' },
        { key: 'empty',    count: n.empty_groups,      label: 'مجموعة مفعّلة بلا طلاب',        href: route('admin.teaching-groups') + '?status=active',       tone: 'slate',  icon: 'courses' },
        { key: 'video',    count: n.teachers_no_video, label: 'معلم بدون فيديو تعريفي',        href: route('admin.users') + '?role=teacher',                  tone: 'red',    icon: 'video' },
    ].filter((a) => (a.count ?? 0) > 0);
});

const totalActions = computed(() => actions.value.reduce((sum, a) => sum + a.count, 0));

// ── Revenue chart ──────────────────────────────────────────────
const chartMax = computed(() => Math.max(...props.revenueChart.map((r) => r.amount), 1));

function relative(iso) {
    if (!iso) return '';
    const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
    if (minutes < 1) return 'الآن';
    if (minutes < 60) return `منذ ${minutes} د`;
    const hours = Math.round(minutes / 60);
    if (hours < 24) return `منذ ${hours} س`;
    return `منذ ${Math.round(hours / 24)} يوم`;
}

const BADGES = {
    active: 'badge-green', approved: 'badge-green',
    pending: 'badge-accent', expired: 'badge-gray', cancelled: 'badge-red',
};

const BADGE_LABELS = {
    active: 'فعّال', approved: 'معتمد', pending: 'معلّق', expired: 'منتهي', cancelled: 'ملغي',
};
</script>

<template>
    <Head title="لوحة الإدارة" />

    <DashboardLayout>
        <div class="space-y-8">

            <!-- ── Header ─────────────────────────────────────── -->
            <header class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white">لوحة الإدارة</h1>
                    <div class="flex items-center gap-3 mt-1.5 flex-wrap">
                        <span class="text-xs text-surface-500 dark:text-surface-400 flex items-center gap-1.5">
                            <span
                                class="w-1.5 h-1.5 rounded-full"
                                :class="isSyncing ? 'bg-accent-500 animate-pulse' : 'bg-green-500'"
                            ></span>
                            {{ syncedAgo }}
                        </span>

                        <span v-if="term" class="badge-primary text-[10px]">
                            {{ term.name }}
                            <template v-if="term.is_current">— متبقٍ {{ term.weeks_remaining }} أسبوع</template>
                            <template v-else>— يبدأ {{ term.starts_on }}</template>
                        </span>

                        <span v-if="term?.is_provisional" class="badge-gray text-[10px]" title="تواريخ مبدئية بانتظار التقويم الرسمي">
                            تواريخ مبدئية
                        </span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link :href="route('admin.users')" class="btn-outline btn-sm">المستخدمون</Link>
                    <Link :href="route('admin.teaching-groups')" class="btn-outline btn-sm">المجموعات</Link>
                    <Link :href="route('admin.payments')" class="btn-primary btn-sm">المدفوعات</Link>
                </div>
            </header>

            <!-- ── Needs attention ────────────────────────────── -->
            <section v-if="actions.length" class="card p-5 border-s-4 border-accent-500">
                <div class="flex items-center gap-2 mb-4">
                    <Icon name="bell" class="w-4 h-4 text-accent-500" />
                    <h2 class="font-bold text-sm text-surface-900 dark:text-white">
                        يحتاج إجراء منك ({{ totalActions }})
                    </h2>
                </div>

                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="action in actions"
                        :key="action.key"
                        :href="action.href"
                        class="flex items-center gap-3 p-3 rounded-xl border border-surface-100 dark:border-surface-800 hover:border-primary-300 dark:hover:border-primary-700 transition-colors"
                    >
                        <span
                            class="w-9 h-9 rounded-lg flex items-center justify-center font-black text-xs shrink-0"
                            :class="{
                                'bg-accent-50 text-accent-700 dark:bg-accent-950/50 dark:text-accent-300': action.tone === 'accent',
                                'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300': action.tone === 'red',
                                'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300': action.tone === 'slate',
                            }"
                        >{{ action.count }}</span>
                        <span class="text-xs text-surface-700 dark:text-surface-200 leading-snug">{{ action.label }}</span>
                    </Link>
                </div>
            </section>

            <section v-else class="alert-success">
                <Icon name="success" class="w-5 h-5 shrink-0" />
                <span>لا يوجد شيء بانتظار إجراء منك — كل الطوابير فاضية.</span>
            </section>

            <!-- ── Money ──────────────────────────────────────── -->
            <section>
                <h2 class="text-sm font-black text-surface-800 dark:text-surface-100 mb-3">المالية</h2>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <StatCard label="إيرادات اليوم"        :value="live.revenue_today" icon="payments" tone="green"   money />
                    <StatCard label="إيرادات هذا الشهر"    :value="live.revenue_month" icon="chart"    tone="primary" money />
                    <StatCard label="الدخل الشهري المتكرر" :value="live.mrr"           icon="earnings" tone="accent"  money hint="مجموع الاشتراكات الفعّالة" />
                    <StatCard label="عمولة المنصة"         :value="live.platform_cut"  icon="earnings" tone="slate"   money hint="من إجمالي المحصّل" />
                </div>
            </section>

            <!-- ── People & teaching ──────────────────────────── -->
            <section>
                <h2 class="text-sm font-black text-surface-800 dark:text-surface-100 mb-3">المنصة</h2>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <StatCard label="طلاب نشطون"    :value="live.students"    icon="student"  tone="primary" :href="route('admin.users') + '?role=student'" />
                    <StatCard label="معلمون نشطون"  :value="live.teachers"    icon="teacher"  tone="accent"  :href="route('admin.users') + '?role=teacher'" />
                    <StatCard label="مجموعات مفعّلة" :value="live.groups"      icon="courses"  tone="primary" :href="route('admin.teaching-groups')" />
                    <StatCard label="اشتراكات فعّالة" :value="live.subs_active" icon="success"  tone="green"   :href="route('admin.subscriptions') + '?status=active'" />
                </div>
            </section>

            <!-- ── Today ──────────────────────────────────────── -->
            <section>
                <h2 class="text-sm font-black text-surface-800 dark:text-surface-100 mb-3">اليوم</h2>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <StatCard
                        label="حصص مباشرة الآن"
                        :value="live.live_now"
                        icon="live"
                        :tone="live.live_now > 0 ? 'red' : 'slate'"
                    />
                    <StatCard label="حصص مجدولة اليوم" :value="live.sessions_today" icon="calendar" tone="primary" />
                    <StatCard label="اشتراكات معلّقة"   :value="live.subs_pending"   icon="clock"    tone="accent" hint="بانتظار الدفع" />
                    <StatCard label="أولياء أمور"       :value="live.parents"        icon="users"    tone="slate" />
                </div>
            </section>

            <div class="grid lg:grid-cols-5 gap-5">

                <!-- ── Revenue chart ──────────────────────────── -->
                <section class="lg:col-span-3 card p-6">
                    <h2 class="font-bold text-sm text-surface-900 dark:text-white mb-5">الإيرادات — آخر 6 أشهر</h2>

                    <div v-if="revenueChart.length" class="flex items-end gap-3 h-44">
                        <div v-for="bar in revenueChart" :key="bar.label" class="flex-1 flex flex-col items-center gap-2 group">
                            <div class="relative w-full flex-1 flex items-end">
                                <div
                                    class="w-full rounded-t-lg bg-gradient-to-t from-primary-600 to-primary-400 transition-all duration-500 group-hover:from-primary-500 group-hover:to-primary-300"
                                    :style="{ height: Math.max((bar.amount / chartMax) * 100, 4) + '%' }"
                                ></div>

                                <div class="absolute -top-1 start-1/2 -translate-x-1/2 bg-surface-900 text-white text-[10px] rounded-lg px-2 py-1 whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                    {{ formatQAR(bar.amount) }} · {{ bar.payments }} عملية
                                </div>
                            </div>
                            <div class="text-[10px] text-surface-400 text-center leading-tight">{{ bar.label }}</div>
                        </div>
                    </div>

                    <p v-else class="h-44 flex items-center justify-center text-sm text-surface-400">
                        لا توجد مدفوعات في آخر 6 أشهر.
                    </p>
                </section>

                <!-- ── Activity feed ──────────────────────────── -->
                <section class="lg:col-span-2 card p-6">
                    <h2 class="font-bold text-sm text-surface-900 dark:text-white mb-4">آخر النشاطات</h2>

                    <div v-if="recentActivity.length" class="space-y-3">
                        <div v-for="(item, index) in recentActivity" :key="index" class="flex items-start gap-3">
                            <div class="w-7 h-7 rounded-lg bg-surface-100 dark:bg-surface-800 flex items-center justify-center shrink-0 text-surface-500">
                                <Icon :name="item.icon" class="w-3.5 h-3.5" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs text-surface-700 dark:text-surface-200 leading-snug">{{ item.text }}</p>
                                <p class="text-[10px] text-surface-400 mt-0.5">{{ relative(item.at) }}</p>
                            </div>
                            <span v-if="item.badge" :class="BADGES[item.badge] ?? 'badge-gray'" class="text-[9px] shrink-0">
                                {{ BADGE_LABELS[item.badge] ?? item.badge }}
                            </span>
                        </div>
                    </div>

                    <p v-else class="text-sm text-surface-400 text-center py-8">لا يوجد نشاط بعد.</p>
                </section>
            </div>

            <!-- ── Recent payments ────────────────────────────── -->
            <section class="card overflow-hidden">
                <div class="flex items-center justify-between p-5 pb-4">
                    <h2 class="font-bold text-sm text-surface-900 dark:text-white">آخر المدفوعات</h2>
                    <Link :href="route('admin.payments')" class="text-xs font-bold text-primary-600 dark:text-primary-400">عرض الكل</Link>
                </div>

                <div v-if="recentPayments.length" class="divide-y divide-surface-100 dark:divide-surface-800">
                    <div v-for="payment in recentPayments" :key="payment.id" class="flex items-center gap-3 px-5 py-3">
                        <div class="avatar-sm">
                            <img v-if="payment.student?.avatar" :src="payment.student.avatar" :alt="payment.student.name" class="w-full h-full object-cover" />
                            <span v-else class="text-primary-700 dark:text-primary-300 font-bold text-xs">
                                {{ payment.student?.name?.charAt(0) }}
                            </span>
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-bold text-surface-800 dark:text-surface-100 truncate">
                                {{ payment.student?.name }}
                            </div>
                            <div class="text-[10px] text-surface-400 truncate">
                                {{ payment.subject }}<span v-if="payment.teacher"> · {{ payment.teacher }}</span>
                            </div>
                        </div>

                        <span class="badge-gray text-[9px] shrink-0 hidden sm:inline-flex">{{ payment.gateway }}</span>
                        <span class="text-[10px] text-surface-400 shrink-0 hidden md:block">{{ relative(payment.paid_at) }}</span>
                        <span class="text-xs font-black text-primary-700 dark:text-primary-400 shrink-0">
                            {{ formatQAR(payment.amount) }}
                        </span>
                    </div>
                </div>

                <p v-else class="text-sm text-surface-400 text-center py-10">لا توجد مدفوعات بعد.</p>
            </section>
        </div>
    </DashboardLayout>
</template>
