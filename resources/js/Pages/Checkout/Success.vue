<script setup>
import { onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';
import { useCartStore } from '@/stores/cartStore';

defineProps({ session_id: { type: String, default: null } });

onMounted(() => {
    const cartStore = useCartStore();
    cartStore.clearCart();
});
</script>

<template>
    <AppLayout>
        <Head title="تم الدفع بنجاح!" />
        <div class="container-app px-4 py-20 max-w-lg text-center flex flex-col items-center justify-center">
            <div class="p-5 bg-green-50 dark:bg-green-950/30 rounded-full text-green-500 mb-6 animate-bounce">
                <Icon name="success" class="w-16 h-16" />
            </div>
            <h1 class="text-3xl font-black text-surface-900 dark:text-white mb-3">تم الدفع بنجاح!</h1>
            <p class="text-surface-500 dark:text-surface-400 mb-8 leading-relaxed">
                تمت عملية الشراء بنجاح. سيظهر الكورس في لوحتك خلال لحظات.
            </p>
            <div class="flex gap-3 justify-center">
                <Link :href="route('dashboard')" class="btn-primary btn-lg">
                    انتقل للوحة التحكم
                </Link>
                <Link :href="route('courses.index')" class="btn-ghost">
                    تصفح المزيد
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
