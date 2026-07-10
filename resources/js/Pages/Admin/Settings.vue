<script setup>
import { ref } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    dbSettings: { type: Array, required: true },
});

// Convert array of settings to form structure
const form = useForm({
    settings: props.dbSettings.map(s => ({ ...s }))
});

const isDirty = ref(false);

function addSetting() {
    form.settings.push({
        key: '',
        value: '',
        type: 'string',
    });
    isDirty.value = true;
}

function removeSetting(index) {
    const setting = form.settings[index];
    if (setting.id) {
        if(confirm('هل أنت متأكد من حذف هذا الإعداد؟')) {
            router.delete(route('admin.settings.destroy', setting.id), {
                onSuccess: () => {
                    form.settings.splice(index, 1);
                }
            });
        }
    } else {
        form.settings.splice(index, 1);
    }
}

function saveSettings() {
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

        <div class="container-app px-4 py-10 max-w-5xl">

            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-3">
                    <Link :href="route('admin.dashboard')" class="btn-ghost p-2 rounded-lg">
                        <Icon name="arrowRight" class="w-5 h-5 rtl-flip" />
                    </Link>
                    <h1 class="text-2xl font-black text-surface-900 dark:text-white flex items-center gap-2">
                        <Icon name="settings" class="w-7 h-7 text-primary-500" />
                        <span>إعدادات المنصة والمحتوى</span>
                    </h1>
                </div>
                <button @click="saveSettings" :disabled="form.processing" class="btn-primary">
                    {{ form.processing ? '⏳ جاري الحفظ...' : '💾 حفظ التغييرات' }}
                </button>
            </div>

            <div class="card p-6 mb-6 overflow-hidden">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="font-bold text-surface-700 dark:text-surface-200">إدارة نصوص وإعدادات المنصة</h2>
                    <button @click="addSetting" class="btn-outline text-xs py-1.5 px-3">
                        + إضافة إعداد جديد
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-surface-50 dark:bg-surface-800 border-b border-surface-200 dark:border-surface-700">
                            <tr>
                                <th class="text-start p-3 font-semibold w-1/4">المفتاح (Key)</th>
                                <th class="text-start p-3 font-semibold w-2/4">القيمة (Value)</th>
                                <th class="text-start p-3 font-semibold w-1/6">النوع</th>
                                <th class="text-start p-3 font-semibold">إجراء</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-700">
                            <tr v-for="(setting, index) in form.settings" :key="index" class="hover:bg-surface-50 dark:hover:bg-surface-800/50">
                                <td class="p-2">
                                    <input v-model="setting.key" type="text" dir="ltr" class="input py-1.5 text-xs font-mono w-full" placeholder="home_hero_title" @input="isDirty = true">
                                </td>
                                <td class="p-2">
                                    <textarea v-if="setting.type === 'string'" v-model="setting.value" rows="2" class="input py-1.5 text-xs w-full resize-y" placeholder="القيمة هنا..." @input="isDirty = true"></textarea>
                                    <input v-else-if="setting.type === 'integer'" v-model="setting.value" type="number" class="input py-1.5 text-xs w-full" @input="isDirty = true">
                                    <select v-else-if="setting.type === 'boolean'" v-model="setting.value" class="input py-1.5 text-xs w-full" @change="isDirty = true">
                                        <option value="true">نعم / مفعل</option>
                                        <option value="false">لا / معطل</option>
                                    </select>
                                </td>
                                <td class="p-2">
                                    <select v-model="setting.type" class="input py-1.5 text-xs w-full" @change="isDirty = true">
                                        <option value="string">نص (String)</option>
                                        <option value="integer">رقم (Integer)</option>
                                        <option value="boolean">منطقي (Boolean)</option>
                                    </select>
                                </td>
                                <td class="p-2">
                                    <button @click="removeSetting(index)" class="btn-ghost text-red-500 hover:bg-red-50 p-1.5 rounded" title="حذف">
                                        🗑️
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="form.settings.length === 0">
                                <td colspan="4" class="p-6 text-center text-surface-400">لا توجد إعدادات مضافة حتى الآن.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- System Info -->
            <div class="card p-6">
                <h2 class="font-bold text-surface-700 dark:text-surface-200 mb-4">معلومات النظام</h2>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-surface-500">بيئة التشغيل</span>
                        <span class="badge-gray text-xs">{{ $page.props.env ?? 'local' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
