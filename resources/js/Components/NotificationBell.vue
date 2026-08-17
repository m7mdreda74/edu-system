<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import Icon from '@/Components/Icon.vue';
import axios from 'axios';

const isOpen = ref(false);
const unread = ref([]);
const read = ref([]);
const unreadCount = ref(0);
let pollingInterval = null;

async function fetchNotifications() {
    try {
        const res = await axios.get(route('notifications.index'));
        unread.value = res.data.unread;
        read.value = res.data.read;
        unreadCount.value = res.data.count;
    } catch (err) {
        console.error('Failed to fetch notifications:', err);
    }
}

async function fetchUnreadCount() {
    if (document.hidden) return;

    try {
        const res = await axios.get(route('notifications.index'), {
            params: { summary: 1 },
        });
        unreadCount.value = res.data.count;
    } catch (err) {
        console.error('Failed to fetch notification count:', err);
    }
}

async function markAsRead(notification) {
    try {
        await axios.post(route('notifications.read', notification.id));
        // Remove from unread, add to read
        unread.value = unread.value.filter(n => n.id !== notification.id);
        read.value = [notification, ...read.value].slice(0, 10);
        unreadCount.value = Math.max(0, unreadCount.value - 1);
        
        // Redirect to link if exists
        if (notification.data?.link) {
            window.location.href = notification.data.link;
        }
    } catch (err) {
        console.error('Failed to mark notification as read:', err);
    }
}

async function markAllAsRead() {
    try {
        await axios.post(route('notifications.read-all'));
        // Move all to read
        read.value = [...unread.value, ...read.value].slice(0, 10);
        unread.value = [];
        unreadCount.value = 0;
    } catch (err) {
        console.error('Failed to mark all as read:', err);
    }
}

function timeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffMins < 1) return 'الآن';
    if (diffMins < 60) return `منذ ${diffMins} د`;
    if (diffHours < 24) return `منذ ${diffHours} س`;
    return `منذ ${diffDays} يوم`;
}

function toggleDropdown() {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        fetchNotifications();
    }
}

// Close when clicking outside
function clickOutside(e) {
    if (!e.target.closest('#notification-bell-container')) {
        isOpen.value = false;
    }
}

function onVisibilityChange() {
    if (!document.hidden) fetchUnreadCount();
}

onMounted(() => {
    fetchUnreadCount();
    pollingInterval = setInterval(fetchUnreadCount, 60000);
    window.addEventListener('click', clickOutside);
    document.addEventListener('visibilitychange', onVisibilityChange);
});

onUnmounted(() => {
    if (pollingInterval) clearInterval(pollingInterval);
    window.removeEventListener('click', clickOutside);
    document.removeEventListener('visibilitychange', onVisibilityChange);
});
</script>

<template>
    <div class="relative" id="notification-bell-container">
        <!-- Bell Icon Button -->
        <button type="button" @click="toggleDropdown"
            class="relative btn-ghost p-2 rounded-xl bg-surface-100 dark:bg-surface-800 transition-all duration-300 transform active:scale-95"
            :title="'التنبيهات'"
            :aria-label="unreadCount > 0 ? `التنبيهات، ${unreadCount} غير مقروءة` : 'التنبيهات'"
            :aria-expanded="isOpen"
            aria-controls="notification-bell-menu"
        >
            <Icon name="bell" class="w-5 h-5 text-surface-600 dark:text-surface-300 transition-transform duration-200 hover:scale-105" :class="{ 'animate-pulse': unreadCount > 0 }" />
            
            <!-- Red Badge -->
            <span v-if="unreadCount > 0" 
                  class="absolute -top-1 -start-1 w-5 h-5 bg-red-500 text-white rounded-full text-[10px] flex items-center justify-center font-black animate-bounce shadow-md">
                {{ unreadCount }}
            </span>
        </button>

        <!-- Dropdown Menu -->
        <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-2 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition-all duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-2 scale-95"
        >
            <div v-if="isOpen" id="notification-bell-menu"
                 class="absolute end-0 mt-2 w-80 md:w-96 bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-2xl shadow-xl z-50 overflow-hidden"
                 dir="rtl"
            >
                <!-- Dropdown Header -->
                <div class="p-4 border-b border-surface-200 dark:border-surface-800 flex items-center justify-between bg-surface-50 dark:bg-surface-900/50">
                    <h4 class="font-bold text-sm text-surface-900 dark:text-white">الإشعارات</h4>
                    <button v-if="unreadCount > 0" type="button" @click="markAllAsRead"
                            class="text-xs text-primary-600 dark:text-primary-400 hover:underline">
                        قراءة الكل
                    </button>
                </div>

                <!-- Notification List -->
                <div class="max-h-96 overflow-y-auto divide-y divide-surface-100 dark:divide-surface-800">
                    
                    <!-- Unread Section -->
                    <template v-if="unread.length > 0">
                        <button v-for="item in unread" :key="item.id" type="button"
                             @click="markAsRead(item)"
                             class="w-full p-4 text-start hover:bg-primary-50/30 dark:hover:bg-primary-950/10 cursor-pointer transition-colors flex items-start gap-3 bg-primary-50/10 dark:bg-primary-950/5"
                        >
                            <div class="w-2.5 h-2.5 rounded-full bg-primary-500 mt-1.5 shrink-0"></div>
                            <div class="flex-1 min-w-0">
                                <div class="font-bold text-xs text-surface-900 dark:text-white">{{ item.data?.title }}</div>
                                <div class="text-xs text-surface-500 dark:text-surface-400 mt-1 leading-relaxed">{{ item.data?.message }}</div>
                                <div class="text-[10px] text-surface-400 mt-1.5">{{ timeAgo(item.created_at) }}</div>
                            </div>
                        </button>
                    </template>

                    <!-- Read Section -->
                    <template v-if="read.length > 0">
                        <button v-for="item in read" :key="item.id" type="button"
                             @click="markAsRead(item)"
                             class="w-full p-4 text-start hover:bg-surface-50 dark:hover:bg-surface-800 cursor-pointer transition-colors flex items-start gap-3"
                        >
                            <div class="w-2.5 h-2.5 rounded-full bg-surface-300 dark:bg-surface-700 mt-1.5 shrink-0"></div>
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-xs text-surface-700 dark:text-surface-300">{{ item.data?.title }}</div>
                                <div class="text-xs text-surface-500 dark:text-surface-400 mt-1 leading-relaxed">{{ item.data?.message }}</div>
                                <div class="text-[10px] text-surface-400 mt-1.5">{{ timeAgo(item.created_at) }}</div>
                            </div>
                        </button>
                    </template>

                    <!-- Empty State -->
                    <div v-if="unread.length === 0 && read.length === 0" 
                         class="p-8 text-center text-surface-400 dark:text-surface-500 flex flex-col items-center justify-center gap-2"
                    >
                        <div class="w-12 h-12 rounded-full bg-surface-100 dark:bg-surface-800 flex items-center justify-center">
                            <Icon name="bell" class="w-6 h-6 text-surface-400" />
                        </div>
                        <div class="text-xs mt-1">لا توجد إشعارات حتى الآن</div>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
