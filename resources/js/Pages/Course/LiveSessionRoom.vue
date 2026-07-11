<script setup>
import { onMounted, ref, onBeforeUnmount, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    session: { type: Object, required: true },
    roomName: { type: String, required: true },
    user: { type: Object, required: true },
});

const jitsiContainer = ref(null);
let jitsiApi = null;

// Screen Recording state & logic
const isRecording = ref(false);
const recordingDuration = ref(0);
let mediaRecorder = null;
let recordedChunks = [];
let durationInterval = null;
const audioContext = ref(null);

const formattedDuration = computed(() => {
    const mins = Math.floor(recordingDuration.value / 60);
    const secs = recordingDuration.value % 60;
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
});

async function startRecording() {
    recordedChunks = [];
    recordingDuration.value = 0;
    
    try {
        // 1. Capture screen video + system audio (includes Jitsi conference audio)
        const screenStream = await navigator.mediaDevices.getDisplayMedia({
            video: {
                displaySurface: "browser",
                width: { ideal: 1280 },
                height: { ideal: 720 },
                frameRate: { ideal: 30 }
            },
            audio: {
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true
            }
        });

        // 2. Capture teacher microphone audio
        let micStream = null;
        try {
            micStream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                }
            });
        } catch (e) {
            console.warn("Microphone access denied or not available. Recording only system audio.", e);
        }

        // 3. Set up Audio Context to mix System Audio + Mic Audio
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        audioContext.value = ctx;
        
        const dest = ctx.createMediaStreamDestination();
        let hasAudioTracks = false;

        // Add screen/system audio track if exists
        if (screenStream.getAudioTracks().length > 0) {
            const screenSource = ctx.createMediaStreamSource(new MediaStream([screenStream.getAudioTracks()[0]]));
            screenSource.connect(dest);
            hasAudioTracks = true;
        }

        // Add microphone audio track if exists
        if (micStream && micStream.getAudioTracks().length > 0) {
            const micSource = ctx.createMediaStreamSource(micStream);
            micSource.connect(dest);
            hasAudioTracks = true;
        }

        // Combine video track and mixed audio track
        const tracks = [...screenStream.getVideoTracks()];
        if (hasAudioTracks) {
            tracks.push(...dest.stream.getAudioTracks());
        }

        const mixedStream = new MediaStream(tracks);

        // 4. Set up MediaRecorder
        let options = { mimeType: 'video/webm;codecs=vp9,opus' };
        if (!MediaRecorder.isTypeSupported(options.mimeType)) {
            options = { mimeType: 'video/webm;codecs=vp8,opus' };
        }
        if (!MediaRecorder.isTypeSupported(options.mimeType)) {
            options = { mimeType: 'video/webm' };
        }
        if (!MediaRecorder.isTypeSupported(options.mimeType)) {
            options = { mimeType: 'video/mp4' };
        }

        mediaRecorder = new MediaRecorder(mixedStream, options);

        mediaRecorder.ondataavailable = (event) => {
            if (event.data && event.data.size > 0) {
                recordedChunks.push(event.data);
            }
        };

        mediaRecorder.onstop = () => {
            // Stop all source stream tracks
            screenStream.getTracks().forEach(t => t.stop());
            if (micStream) {
                micStream.getTracks().forEach(t => t.stop());
            }
            if (audioContext.value && audioContext.value.state !== 'closed') {
                audioContext.value.close();
            }

            clearInterval(durationInterval);
            isRecording.value = false;

            // Trigger file download
            const blob = new Blob(recordedChunks, { type: mediaRecorder.mimeType || 'video/webm' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            const dateStr = new Date().toISOString().slice(0, 10);
            a.download = `class-recording-${props.session.id}-${dateStr}.webm`;
            document.body.appendChild(a);
            a.click();
            
            setTimeout(() => {
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            }, 100);

            alert("تم إيقاف التسجيل وتنزيل ملف الحصة بنجاح! يمكنك الآن تحويله ورفعه للطلاب كدرس مسجل.");
        };

        // Start recording
        mediaRecorder.start(1000); // chunk every 1 second
        isRecording.value = true;
        
        // Start duration counter
        durationInterval = setInterval(() => {
            recordingDuration.value++;
        }, 1000);

    } catch (err) {
        console.error("Error starting recording:", err);
        alert("لم يتم بدء التسجيل. يرجى التأكد من الموافقة على مشاركة الشاشة والصوت.");
    }
}

function stopRecording() {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
    }
}

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
            <div class="flex items-center gap-4">
                <!-- Screen Recording Panel (Teacher only) -->
                <div v-if="user.isTeacher" class="flex items-center gap-3">
                    <span v-if="isRecording" class="flex items-center gap-1.5 text-xs text-red-500 font-bold animate-pulse">
                        <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
                        تسجيل الحصة: {{ formattedDuration }}
                    </span>
                    
                    <button v-if="!isRecording" @click="startRecording" 
                            class="px-4 py-1.5 bg-red-600 hover:bg-red-700 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 transition-transform hover:scale-105 shadow-glow-primary">
                        <span>📹 تسجيل الشاشة والصوت</span>
                    </button>
                    <button v-else @click="stopRecording" 
                            class="px-4 py-1.5 bg-surface-700 hover:bg-surface-600 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 transition-transform hover:scale-105">
                        <span>⏹️ إيقاف وحفظ</span>
                    </button>
                </div>

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
