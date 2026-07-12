<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useForm, router, Link, usePage, Head } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    conversations: { type: Array, required: true },
    activeConversation: { type: Object, default: null },
    messages: { type: Array, default: () => [] },
    enrolledStudents: { type: Array, default: () => [] },
});

// Reactivity for messages to append new ones
const chatMessages = ref([...props.messages]);
const showMobileChat = ref(!!props.activeConversation);

const page = usePage();
const currentUser = page.props.auth.user;
const isTeacher = currentUser?.roles?.includes('teacher');

const form = useForm({
    conversation_id: props.activeConversation?.id || '',
    message: '',
    attachment: null,
});

// File upload and emoji references
const attachmentInput = ref(null);
const selectedFile = ref(null);
const selectedFilePreview = ref(null);
const showEmojiPicker = ref(false);

// Teacher new conversation search & modal
const showNewChatModal = ref(false);
const searchStudentQuery = ref('');

const filteredStudents = computed(() => {
    const query = searchStudentQuery.value.trim().toLowerCase();
    if (!query) return props.enrolledStudents;
    return props.enrolledStudents.filter(s => 
        s.name.toLowerCase().includes(query) || 
        s.course_title.toLowerCase().includes(query)
    );
});

const emojis = ['😊', '😂', '❤️', '👍', '🎉', '🙌', '🔥', '✨', '👏', '💡', '📚', '📝', '👌', '🤔', '😍', '🙏'];

function insertEmoji(emoji) {
    form.message += emoji;
    showEmojiPicker.value = false;
}

function triggerFileInput() {
    if (attachmentInput.value) {
        attachmentInput.value.click();
    }
}

function handleFileChange(event) {
    const file = event.target.files[0];
    if (!file) return;

    form.attachment = file;
    selectedFile.value = file;

    if (file.type.startsWith('image/')) {
        selectedFilePreview.value = URL.createObjectURL(file);
    } else {
        selectedFilePreview.value = null;
    }
}

function clearAttachment() {
    form.attachment = null;
    selectedFile.value = null;
    if (selectedFilePreview.value) {
        URL.revokeObjectURL(selectedFilePreview.value);
        selectedFilePreview.value = null;
    }
    if (attachmentInput.value) {
        attachmentInput.value.value = '';
    }
}

function startTeacherChat(studentId, courseId) {
    router.post(route('chat.start'), {
        course_id: courseId,
        teacher_id: currentUser.id,
        student_id: studentId
    }, {
        onSuccess: () => {
            showNewChatModal.value = false;
        }
    });
}

function isImage(path) {
    if (!path) return false;
    const extension = path.split('.').pop().toLowerCase();
    return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(extension);
}

function getFileName(path) {
    if (!path) return 'ملف مرفق';
    return path.split('/').pop();
}

// Keep local messages in sync with backend page props
watch(() => props.messages, (newMessages) => {
    chatMessages.value = [...newMessages];
    nextTick(scrollToBottom);
}, { deep: true });

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
                // Prevent duplicate messages
                data.messages.forEach(msg => {
                    if (!chatMessages.value.some(existing => existing.id === msg.id)) {
                        chatMessages.value.push(msg);
                    }
                });
                nextTick(scrollToBottom);
            }
        }
    } catch (error) {
        console.error('Failed to fetch messages:', error);
    }
}

function sendMessage() {
    if (!form.message.trim() && !form.attachment) return;
    if (!form.conversation_id) return;
    
    form.post(route('chat.send'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset('message');
            clearAttachment();
            fetchNewMessages(); // Immediately fetch instead of waiting for interval
        }
    });
}

function selectConversation(id) {
    showMobileChat.value = true;
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
            <h1 class="text-3xl font-black text-surface-900 dark:text-white mb-6 flex items-center gap-2">
                <Icon name="chat" class="w-8 h-8 text-primary-500" />
                <span>الرسائل</span>
            </h1>

            <div class="flex-1 flex overflow-hidden card rounded-2xl border border-surface-200 dark:border-surface-700">
                
                <!-- Sidebar (Conversations List) -->
                <div class="w-full md:w-1/3 lg:w-1/4 border-l border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-900/50 flex flex-col"
                     :class="{ 'hidden md:flex': showMobileChat, 'flex': !showMobileChat }">
                    <div class="p-4 border-b border-surface-200 dark:border-surface-700 flex items-center justify-between">
                        <h2 class="font-bold text-surface-800 dark:text-surface-200">المحادثات</h2>
                        <button v-if="isTeacher" @click="showNewChatModal = true" class="btn-xs bg-primary-600 hover:bg-primary-700 text-white font-bold rounded-lg px-2.5 py-1.5 flex items-center gap-1 text-[11px] transition-colors">
                            <Icon name="plus" class="w-3.5 h-3.5" />
                            <span>محادثة جديدة</span>
                        </button>
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
                <div class="flex-1 flex flex-col bg-white dark:bg-surface-900 relative"
                     :class="{ 'hidden md:flex': !showMobileChat, 'flex': showMobileChat }">
                    <template v-if="activeConversation">
                        <!-- Chat Header -->
                        <div class="p-4 border-b border-surface-200 dark:border-surface-700 flex items-center justify-between bg-white dark:bg-surface-900 z-10">
                            <div class="flex items-center gap-3">
                                <!-- Back button on mobile -->
                                <Link :href="route('chat.index')" 
                                      @click="showMobileChat = false"
                                      class="md:hidden p-2 -mr-2 rounded-lg text-surface-500 hover:text-surface-950 dark:hover:text-white transition-colors">
                                    <Icon name="arrowRight" class="w-5 h-5 rtl-flip" />
                                </Link>
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
                                <!-- Message Text -->
                                <div v-if="msg.message" class="px-4 py-2.5 rounded-2xl text-sm"
                                     :class="msg.sender_id === currentUser.id 
                                        ? 'bg-primary-600 text-white rounded-tl-none' 
                                        : 'bg-surface-100 dark:bg-surface-800 text-surface-900 dark:text-white rounded-tr-none'">
                                    {{ msg.message }}
                                </div>

                                <!-- Attachment Display -->
                                <div v-if="msg.attachment_path" class="mt-1">
                                    <!-- Image Mimetype -->
                                    <div v-if="isImage(msg.attachment_path)" class="rounded-xl overflow-hidden border border-surface-200 dark:border-surface-800 max-w-xs shadow-sm">
                                        <a :href="msg.attachment_path" target="_blank" class="block group relative">
                                            <img :src="msg.attachment_path" class="w-full h-auto max-h-48 object-cover group-hover:opacity-90 transition-opacity" />
                                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-xs font-bold gap-1">
                                                <Icon name="expand" class="w-3.5 h-3.5" />
                                                <span>عرض الصورة</span>
                                            </div>
                                        </a>
                                    </div>
                                    <!-- Other Files -->
                                    <div v-else class="bg-surface-50 dark:bg-surface-800/40 border border-surface-200 dark:border-surface-800 px-3.5 py-2.5 rounded-2xl flex items-center gap-3 max-w-xs shadow-sm">
                                        <div class="p-2 bg-primary-50 dark:bg-primary-950 text-primary-500 rounded-xl shrink-0">
                                            <Icon name="file" class="w-5 h-5" />
                                        </div>
                                        <div class="overflow-hidden min-w-0">
                                            <div class="text-xs font-bold text-surface-900 dark:text-white truncate">
                                                {{ getFileName(msg.attachment_path) }}
                                            </div>
                                            <a :href="msg.attachment_path" target="_blank" download class="text-[10px] text-primary-500 hover:underline font-semibold block mt-0.5">
                                                تحميل الملف
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-[10px] text-surface-400 mt-1 px-1">
                                    {{ new Date(msg.created_at).toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' }) }}
                                </div>
                            </div>
                            <div v-if="chatMessages.length === 0" class="text-center text-surface-500 py-10">
                                ابدأ المحادثة بإرسال رسالة ترحيبية!
                            </div>
                        </div>

                        <!-- Message Input Panel -->
                        <div class="p-4 bg-surface-50 dark:bg-surface-900 border-t border-surface-200 dark:border-surface-700 flex flex-col gap-2">
                            <!-- Attachment Preview Panel -->
                            <div v-if="selectedFile" class="flex items-center gap-3 p-2 bg-white dark:bg-surface-950 rounded-2xl border border-surface-200 dark:border-surface-800 self-start max-w-sm relative">
                                <button type="button" @click="clearAttachment" class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center text-xs hover:bg-red-600 transition-colors shadow-sm">
                                    ×
                                </button>
                                <img v-if="selectedFilePreview" :src="selectedFilePreview" class="w-12 h-12 object-cover rounded-xl" />
                                <div v-else class="p-2 bg-primary-50 dark:bg-primary-950 text-primary-500 rounded-xl">
                                    <Icon name="file" class="w-6 h-6" />
                                </div>
                                <div class="text-xs overflow-hidden truncate max-w-[200px] text-surface-700 dark:text-surface-300 pr-1">
                                    <div class="font-bold truncate">{{ selectedFile.name }}</div>
                                    <div class="text-[10px] text-surface-400 mt-0.5">{{ (selectedFile.size / 1024).toFixed(1) }} KB</div>
                                </div>
                            </div>

                            <form @submit.prevent="sendMessage" class="flex items-center gap-2 relative">
                                <!-- Hidden File Input -->
                                <input type="file" ref="attachmentInput" @change="handleFileChange" class="hidden" />

                                <!-- Attachment Trigger -->
                                <button type="button" @click="triggerFileInput" class="w-11 h-11 bg-white dark:bg-surface-950 rounded-full flex items-center justify-center hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors border border-surface-200 dark:border-surface-800 text-surface-500" title="إرفاق ملف">
                                    <Icon name="attachment" class="w-5 h-5" />
                                </button>

                                <!-- Emoji Trigger -->
                                <div class="relative">
                                    <button type="button" @click="showEmojiPicker = !showEmojiPicker" class="w-11 h-11 bg-white dark:bg-surface-950 rounded-full flex items-center justify-center hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors border border-surface-200 dark:border-surface-800 text-surface-500" title="رموز تعبيرية">
                                        <Icon name="emoji" class="w-5 h-5" />
                                    </button>

                                    <!-- Emoji Picker Popover -->
                                    <div v-if="showEmojiPicker" class="absolute bottom-14 right-0 bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-2xl p-3 shadow-xl z-30 w-52 grid grid-cols-4 gap-2">
                                        <button v-for="emoji in emojis" :key="emoji" type="button" @click="insertEmoji(emoji)" class="text-xl p-1.5 hover:bg-surface-100 dark:hover:bg-surface-800 rounded-lg transition-colors text-center">
                                            {{ emoji }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Text Input -->
                                <input v-model="form.message" 
                                       type="text" 
                                       class="input flex-1 py-3 px-4 bg-white dark:bg-surface-950 rounded-full border border-surface-200 dark:border-surface-800" 
                                       placeholder="اكتب رسالتك هنا..." 
                                       autocomplete="off">

                                <!-- Send Submit Button -->
                                <button type="submit" :disabled="form.processing || (!form.message.trim() && !form.attachment)" 
                                        class="absolute left-1.5 btn-primary w-10 h-10 rounded-full p-0 flex items-center justify-center disabled:opacity-50 transition-transform hover:scale-105">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-white">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </template>
                    <div v-else class="flex-1 flex flex-col items-center justify-center text-surface-400">
                        <Icon name="chat" class="w-16 h-16 text-surface-300 dark:text-surface-600 mb-4" />
                        <p>اختر محادثة للبدء في المراسلة</p>
                    </div>
                </div>

            </div>
        </div>

        <!-- New Chat Modal (For Teachers) -->
        <Transition enter-active-class="ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="showNewChatModal" class="fixed inset-0 bg-surface-950/70 dark:bg-black/80 flex items-center justify-center p-4 z-50 overflow-y-auto">
                <div class="bg-white dark:bg-surface-900 w-full max-w-lg rounded-3xl overflow-hidden border border-surface-200 dark:border-surface-800 shadow-2xl relative" dir="rtl">
                    <!-- Header -->
                    <div class="p-5 border-b border-surface-200 dark:border-surface-800 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Icon name="chat" class="w-5 h-5 text-primary-500" />
                            <h3 class="font-bold text-surface-900 dark:text-white text-base">بدء محادثة جديدة</h3>
                        </div>
                        <button @click="showNewChatModal = false" class="text-surface-400 hover:text-surface-900 dark:hover:text-white text-lg font-bold">
                            ×
                        </button>
                    </div>

                    <!-- Search Input -->
                    <div class="p-4 bg-surface-50 dark:bg-surface-850">
                        <div class="relative">
                            <input v-model="searchStudentQuery" type="text" class="input w-full pr-10 pl-4 py-2 text-sm bg-white dark:bg-surface-950 rounded-xl" placeholder="البحث باسم الطالب أو اسم الكورس..." />
                            <Icon name="search" class="w-4 h-4 text-surface-400 absolute right-3 top-3.5" />
                        </div>
                    </div>

                    <!-- Enrolled Students List -->
                    <div class="max-h-80 overflow-y-auto divide-y divide-surface-100 dark:divide-surface-800">
                        <div v-for="item in filteredStudents" :key="item.id + '-' + item.course_id" class="p-4 flex items-center justify-between hover:bg-surface-50 dark:hover:bg-surface-800/40 transition-colors">
                            <div class="flex items-center gap-3 overflow-hidden">
                                <div class="avatar-sm bg-surface-100 dark:bg-surface-800 shrink-0">
                                    <img v-if="item.avatar" :src="item.avatar" class="w-full h-full object-cover">
                                    <span v-else class="text-surface-500 dark:text-surface-400 font-bold">
                                        {{ item.name.charAt(0) }}
                                    </span>
                                </div>
                                <div class="overflow-hidden">
                                    <div class="font-bold text-sm text-surface-900 dark:text-white truncate">
                                        {{ item.name }}
                                    </div>
                                    <div class="text-xs text-surface-500 truncate mt-0.5">
                                        كورس: {{ item.course_title }}
                                    </div>
                                </div>
                            </div>
                            <button @click="startTeacherChat(item.id, item.course_id)" class="btn-primary text-xs py-1.5 px-3 rounded-lg shrink-0">
                                بدء محادثة
                            </button>
                        </div>
                        <div v-if="filteredStudents.length === 0" class="p-8 text-center text-surface-500 text-sm">
                            لا يوجد طلاب مطابقين للبحث.
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </DashboardLayout>
</template>
