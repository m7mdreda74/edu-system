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
const isRoomLoading = ref(true);

// Moderation & security panel state
const activeParticipants = ref([]);
const lobbyParticipants = ref([]);
const lobbyEnabled = ref(true);

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

// Moderation & Lobby control handlers
function acceptLobby(id) {
    if (jitsiApi) {
        jitsiApi.executeCommand('lobbyAcceptAccess', id);
        lobbyParticipants.value = lobbyParticipants.value.filter(p => p.id !== id);
    }
}

function rejectLobby(id) {
    if (jitsiApi) {
        jitsiApi.executeCommand('lobbyRejectAccess', id);
        lobbyParticipants.value = lobbyParticipants.value.filter(p => p.id !== id);
    }
}

function kickStudent(id) {
    if (jitsiApi && confirm('هل تريد طرد هذا الطالب من الحصة؟')) {
        jitsiApi.executeCommand('kickParticipant', id);
        activeParticipants.value = activeParticipants.value.filter(p => p.id !== id);
    }
}

function toggleLobbyMode() {
    if (jitsiApi) {
        lobbyEnabled.value = !lobbyEnabled.value;
        jitsiApi.executeCommand('toggleLobby', lobbyEnabled.value);
    }
}

function muteAllStudents() {
    if (jitsiApi && confirm('هل تريد كتم صوت الجميع؟')) {
        jitsiApi.executeCommand('muteEveryone');
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
    isRoomLoading.value = false;
    
    // Only teacher is moderator in this setup
    if (props.user.isTeacher) {
        jitsiApi.executeCommand('subject', props.session.title);
        jitsiApi.executeCommand('toggleLobby', lobbyEnabled.value);

        // Listen for Jitsi meeting events to update the moderation lists
        jitsiApi.addEventListener('videoConferenceJoined', () => {
            setTimeout(() => {
                try {
                    const list = jitsiApi.getParticipantsInfo();
                    activeParticipants.value = list.map(p => ({
                        id: p.participantId,
                        name: p.displayName || 'طالب مجهول'
                    }));
                } catch (e) {
                    console.warn("Could not load initial participants:", e);
                }
            }, 3000);
        });

        jitsiApi.addEventListener('participantJoined', (event) => {
            if (!activeParticipants.value.some(p => p.id === event.id)) {
                activeParticipants.value.push({
                    id: event.id,
                    name: event.displayName || 'طالب مجهول'
                });
            }
        });

        jitsiApi.addEventListener('participantLeft', (event) => {
            activeParticipants.value = activeParticipants.value.filter(p => p.id !== event.id);
        });

        jitsiApi.addEventListener('lobbyParticipantJoined', (event) => {
            if (!lobbyParticipants.value.some(p => p.id === event.id)) {
                lobbyParticipants.value.push({
                    id: event.id,
                    name: event.displayName || 'طالب ينتظر'
                });
            }
        });

        jitsiApi.addEventListener('lobbyParticipantLeft', (event) => {
            lobbyParticipants.value = lobbyParticipants.value.filter(p => p.id !== event.id);
        });
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
                    <div class="text-xs text-primary-400">{{ session.course?.title || session.teaching_group?.name || 'حصة مباشرة' }}</div>
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
                            class="px-4 py-1.5 bg-red-600 hover:bg-red-700 text-white font-bold rounded-xl text-xs flex items-center gap-2 transition-transform hover:scale-105 shadow-glow-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                        <span>تسجيل الشاشة والصوت</span>
                    </button>
                    <button v-else @click="stopRecording" 
                            class="px-4 py-1.5 bg-surface-700 hover:bg-surface-600 text-white font-bold rounded-xl text-xs flex items-center gap-2 transition-transform hover:scale-105">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <rect x="5.25" y="5.25" width="13.5" height="13.5" rx="1.5" fill="currentColor" />
                        </svg>
                        <span>إيقاف وحفظ</span>
                    </button>
                </div>

                <span class="badge-accent animate-pulse flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-red-500"></span>
                    <span>مباشر الآن</span>
                </span>
            </div>
        </div>

        <div class="flex-1 w-full flex overflow-hidden">
            <!-- Jitsi Meet iframe container -->
            <div class="flex-1 bg-black relative h-full" ref="jitsiContainer">
                <!-- Loading overlay -->
                <div v-if="isRoomLoading" class="absolute inset-0 flex items-center justify-center bg-black/80 z-20">
                    <div class="text-surface-400 flex flex-col items-center gap-3">
                        <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
                        <div>جاري الاتصال بقاعة البث...</div>
                    </div>
                </div>
            </div>

            <!-- Teacher Moderation Panel (Sidebar on the left) -->
            <div v-if="user.isTeacher" class="w-80 shrink-0 bg-surface-900 border-r border-surface-800 flex flex-col overflow-hidden text-sm">
                <!-- Header -->
                <div class="p-4 border-b border-surface-800 flex items-center justify-between bg-surface-950">
                    <div class="flex items-center gap-2 font-bold text-white">
                        <span>🛡️ لوحة التحكم والإشراف</span>
                    </div>
                </div>

                <!-- Controls -->
                <div class="p-4 border-b border-surface-800 flex flex-col gap-2.5">
                    <button @click="toggleLobbyMode" 
                            class="w-full py-2 px-3 text-xs font-bold rounded-xl flex items-center justify-between transition-colors"
                            :class="lobbyEnabled 
                                ? 'bg-green-600/10 text-green-400 hover:bg-green-600/20 border border-green-600/20' 
                                : 'bg-surface-800 text-surface-400 hover:bg-surface-700 border border-surface-700'">
                        <span>غرفة الانتظار (Lobby)</span>
                        <span>{{ lobbyEnabled ? 'نشطة 🟢' : 'ملغاة ⚪' }}</span>
                    </button>

                    <button @click="muteAllStudents" 
                            class="w-full py-2 px-3 bg-red-600/10 hover:bg-red-600/20 text-red-400 font-bold border border-red-600/20 rounded-xl text-xs flex items-center justify-center gap-1.5 transition-colors">
                        <span>🔇 كتم صوت الجميع</span>
                    </button>
                </div>

                <!-- Waiting Lobby Section -->
                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    <div>
                        <h4 class="text-xs font-bold text-surface-400 uppercase tracking-wider mb-2">طلبات الدخول (غرفة الانتظار)</h4>
                        <div v-if="!lobbyParticipants.length" class="text-xs text-surface-500 py-3 bg-surface-950/30 rounded-xl text-center border border-dashed border-surface-800">
                            لا توجد طلبات معلقة حالياً
                        </div>
                        <div v-else class="space-y-2">
                            <div v-for="student in lobbyParticipants" :key="student.id" 
                                 class="p-2.5 bg-surface-950/60 rounded-xl flex items-center justify-between gap-2 border border-surface-800">
                                <span class="font-medium text-white truncate max-w-[120px]">{{ student.name }}</span>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <button @click="acceptLobby(student.id)" class="px-2 py-1 bg-green-600 hover:bg-green-700 text-white rounded-lg text-[10px] font-bold">قبول</button>
                                    <button @click="rejectLobby(student.id)" class="px-2 py-1 bg-red-600 hover:bg-red-700 text-white rounded-lg text-[10px] font-bold">رفض</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Active Participants Section -->
                    <div>
                        <h4 class="text-xs font-bold text-surface-400 uppercase tracking-wider mb-2">الطلاب المتواجدون حالياً</h4>
                        <div v-if="!activeParticipants.length" class="text-xs text-surface-500 py-3 text-center">
                            لا يوجد طلاب متصلين بقاعة البث
                        </div>
                        <div v-else class="space-y-2">
                            <div v-for="student in activeParticipants" :key="student.id" 
                                 class="p-2.5 bg-surface-950/30 rounded-xl flex items-center justify-between gap-2 border border-surface-800/50">
                                <span class="font-medium text-surface-300 truncate max-w-[150px]">{{ student.name }}</span>
                                <button @click="kickStudent(student.id)" 
                                        class="px-2.5 py-1 text-red-500 hover:text-white hover:bg-red-600 rounded-lg text-[10px] font-bold border border-red-500/20 hover:border-transparent transition-colors">
                                    طرد
                                </button>
                            </div>
                        </div>
                    </div>
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
