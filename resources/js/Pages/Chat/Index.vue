<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import { useForm, router, Link, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

const props = defineProps({
    conversations: { type: Array, required: true },
    activeConversation: { type: Object, default: null },
    messages: { type: Array, default: () => [] },
});

// Reactivity for messages to append new ones
const chatMessages = ref([...props.messages]);

const page = usePage();
const currentUser = page.props.auth.user;
const isTeacher = currentUser?.roles?.includes('teacher');

const form = useForm({
    conversation_id: props.activeConversation?.id || '',
    message: '',
});

const messagesContainer = ref(null);
let pollingInterval = null;

function scrollToBottom() {
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
}

async function fetchNewMessages() {
    if (!props.activeConversation) return;

    const lastMessageId = chatMessages.value.length > 0 
        ? chatMessages.value[chatMessages.value.length - 1].id 
        : null;

    try {
        const response = await fetch(route('chat.fetch') + `?conversation_id=${props.activeConversation.id}&last_message_id=${lastMessageId || ''}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            if (data.messages && data.messages.length > 0) {
                chatMessages.value.push(...data.messages);
                nextTick(scrollToBottom);
            }
        }
    } catch (error) {
        console.error('Failed to fetch messages:', error);
    }
}

function sendMessage() {
    if (!form.message.trim() || !form.conversation_id) return;
    
    form.post(route('chat.send'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('message');
            fetchNewMessages(); // Immediately fetch instead of waiting for interval
        }
    });
}

function selectConversation(id) {
    router.get(route('chat.index'), { conversation: id }, { preserveState: false, preserveScroll: false });
}

function getOtherUser(conversation) {
    return isTeacher ? conversation.student : conversation.teacher;
}

onMounted(() => {
    scrollToBottom();
    if (props.activeConversation) {
        pollingInterval = setInterval(fetchNewMessages, 3000); // Poll every 3 seconds
    }
});

onUnmounted(() => {
    if (pollingInterval) clearInterval(pollingInterval);
});
</script>

<template>
    <DashboardLayout>
        <Head title="الرسائل" />

        <div class="container-app px-4 py-8 h-[calc(100vh-64px)] flex flex-col">
            <h1 class="text-3xl font-black text-surface-900 dark:text-white mb-6">الرسائل 💬</h1>

            <div class="flex-1 flex overflow-hidden card rounded-2xl border border-surface-200 dark:border-surface-700">
                
                <!-- Sidebar (Conversations List) -->
                <div class="w-1/3 md:w-1/4 border-l border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-900/50 flex flex-col">
                    <div class="p-4 border-b border-surface-200 dark:border-surface-700">
                        <h2 class="font-bold text-surface-800 dark:text-surface-200">المحادثات</h2>
                    </div>
                    <div class="flex-1 overflow-y-auto">
                        <div v-for="conv in conversations" :key="conv.id" 
                             @click="selectConversation(conv.id)"
                             class="p-4 cursor-pointer hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors border-b border-surface-200 dark:border-surface-700/50"
                             :class="{ 'bg-primary-50 dark:bg-primary-900/30 border-r-4 border-r-primary-500': activeConversation?.id === conv.id }">
                            <div class="flex items-center gap-3">
                                <div class="avatar-sm bg-surface-200 dark:bg-surface-700 shrink-0">
                                    <img v-if="getOtherUser(conv)?.avatar" :src="getOtherUser(conv).avatar" class="w-full h-full object-cover">
                                    <span v-else class="text-surface-500 dark:text-surface-400 font-bold">
                                        {{ getOtherUser(conv)?.name?.charAt(0) }}
                                    </span>
                                </div>
                                <div class="overflow-hidden">
                                    <div class="font-bold text-sm text-surface-900 dark:text-white truncate">
                                        {{ getOtherUser(conv)?.name }}
                                    </div>
                                    <div class="text-xs text-surface-500 truncate mt-0.5">
                                        {{ conv.course?.title }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="conversations.length === 0" class="p-6 text-center text-surface-500 text-sm">
                            لا توجد محادثات حتى الآن.
                        </div>
                    </div>
                </div>

                <!-- Chat Area -->
                <div class="flex-1 flex flex-col bg-white dark:bg-surface-900 relative">
                    <template v-if="activeConversation">
                        <!-- Chat Header -->
                        <div class="p-4 border-b border-surface-200 dark:border-surface-700 flex items-center justify-between bg-white dark:bg-surface-900 z-10">
                            <div class="flex items-center gap-3">
                                <div class="avatar-sm bg-primary-100 dark:bg-primary-900 shrink-0">
                                    <span class="text-primary-700 dark:text-primary-300 font-bold">
                                        {{ getOtherUser(activeConversation)?.name?.charAt(0) }}
                                    </span>
                                </div>
                                <div>
                                    <div class="font-bold text-surface-900 dark:text-white">{{ getOtherUser(activeConversation)?.name }}</div>
                                    <div class="text-xs text-surface-500">{{ activeConversation.course?.title }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Messages List -->
                        <div class="flex-1 overflow-y-auto p-4 space-y-4" ref="messagesContainer" id="chat-messages-container">
                            <div v-for="msg in chatMessages" :key="msg.id" 
                                 class="flex flex-col max-w-[75%]"
                                 :class="msg.sender_id === currentUser.id ? 'self-end items-end' : 'self-start items-start'">
                                <div class="px-4 py-2.5 rounded-2xl text-sm"
                                     :class="msg.sender_id === currentUser.id 
                                        ? 'bg-primary-600 text-white rounded-tl-none' 
                                        : 'bg-surface-100 dark:bg-surface-800 text-surface-900 dark:text-white rounded-tr-none'">
                                    {{ msg.message }}
                                </div>
                                <div class="text-[10px] text-surface-400 mt-1 px-1">
                                    {{ new Date(msg.created_at).toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' }) }}
                                </div>
                            </div>
                            <div v-if="chatMessages.length === 0" class="text-center text-surface-500 py-10">
                                كن أول من يرسل رسالة! 👋
                            </div>
                        </div>

                        <!-- Message Input -->
                        <div class="p-4 bg-surface-50 dark:bg-surface-900 border-t border-surface-200 dark:border-surface-700">
                            <form @submit.prevent="sendMessage" class="flex items-center gap-2 relative">
                                <input v-model="form.message" 
                                       type="text" 
                                       class="input flex-1 py-3 px-4 bg-white dark:bg-surface-950 rounded-full" 
                                       placeholder="اكتب رسالتك هنا..." 
                                       autocomplete="off"
                                       required>
                                <button type="submit" :disabled="form.processing || !form.message.trim()" 
                                        class="absolute left-1.5 btn-primary w-10 h-10 rounded-full p-0 flex items-center justify-center disabled:opacity-50 transition-transform hover:scale-105">
                                    <span class="-mr-1">✈️</span>
                                </button>
                            </form>
                        </div>
                    </template>
                    <div v-else class="flex-1 flex flex-col items-center justify-center text-surface-400">
                        <span class="text-6xl mb-4">💬</span>
                        <p>اختر محادثة للبدء في المراسلة</p>
                    </div>
                </div>

            </div>
        </div>
    </DashboardLayout>
</template>
