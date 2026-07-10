<script setup>
import { onMounted, ref, onBeforeUnmount } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    session: { type: Object, required: true },
    roomName: { type: String, required: true },
    user: { type: Object, required: true },
});

const jitsiContainer = ref(null);
let jitsiApi = null;

onMounted(() => {
    // Load Jitsi Meet API script dynamically
    const script = document.createElement('script');
    script.src = 'https://meet.jit.si/external_api.js';
    script.async = true;
    script.onload = () => {
        initJitsi();
    };
    document.head.appendChild(script);
});

function initJitsi() {
    if (!window.JitsiMeetExternalAPI) return;

    const domain = 'meet.jit.si';
    const options = {
        roomName: props.roomName,
        parentNode: jitsiContainer.value,
        userInfo: {
            email: props.user.email,
            displayName: props.user.name,
        },
        configOverwrite: {
            startWithAudioMuted: !props.user.isTeacher,
            startWithVideoMuted: !props.user.isTeacher,
            prejoinPageEnabled: false,
        },
        interfaceConfigOverwrite: {
            SHOW_JITSI_WATERMARK: false,
            SHOW_WATERMARK_FOR_GUESTS: false,
            TOOLBAR_BUTTONS: [
                'microphone', 'camera', 'closedcaptions', 'desktop', 'fullscreen',
                'fodeviceselection', 'hangup', 'profile', 'chat', 'recording',
                'livestreaming', 'etherpad', 'sharedvideo', 'settings', 'raisehand',
                'videoquality', 'filmstrip', 'invite', 'feedback', 'stats', 'shortcuts',
                'tileview', 'videobackgroundblur', 'download', 'help', 'mute-everyone'
            ],
        },
    };

    jitsiApi = new window.JitsiMeetExternalAPI(domain, options);
    
    // Only teacher is moderator in this basic setup
    if (props.user.isTeacher) {
        jitsiApi.executeCommand('subject', props.session.title);
        jitsiApi.executeCommand('toggleLobby', true); // Optional: turn on lobby
    }
}

onBeforeUnmount(() => {
    if (jitsiApi) {
        jitsiApi.dispose();
    }
});
</script>

<template>
    <div class="h-screen w-full flex flex-col bg-surface-950 text-white">
        <Head :title="session.title" />

        <div class="h-16 shrink-0 bg-surface-900 border-b border-surface-800 flex items-center justify-between px-6">
            <div class="flex items-center gap-4">
                <Link :href="route('dashboard')" class="btn-ghost p-2 text-surface-400 hover:text-white">← عودة للوحة التحكم</Link>
                <div class="h-8 w-px bg-surface-700"></div>
                <div>
                    <h1 class="font-bold text-lg leading-tight">{{ session.title }}</h1>
                    <div class="text-xs text-primary-400">{{ session.course.title }}</div>
                </div>
            </div>
            <div>
                <span class="badge-accent animate-pulse">🔴 مباشر الآن</span>
            </div>
        </div>

        <div class="flex-1 w-full bg-black relative" ref="jitsiContainer">
            <!-- Jitsi Meet iframe will be injected here -->
            <div v-if="!jitsiApi" class="absolute inset-0 flex items-center justify-center">
                <div class="text-surface-400 flex flex-col items-center gap-3">
                    <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
                    <div>جاري الاتصال بقاعة البث...</div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* Ensure the iframe takes full height and width */
#jitsiContainer iframe {
    width: 100%;
    height: 100%;
    border: none;
}
</style>
