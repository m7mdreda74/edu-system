<script setup>
import { onMounted, ref, onBeforeUnmount, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Whiteboard from '@/Components/Whiteboard.vue';

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

// ─── Whiteboard State ─────────────────────────────────────────────────────────
// viewMode: 'jitsi' | 'split' | 'whiteboard'
const viewMode = ref('jitsi');
const whiteboardVisible = computed(() => viewMode.value === 'split' || viewMode.value === 'whiteboard');
const jitsiVisible = computed(() => viewMode.value === 'split' || viewMode.value === 'jitsi');

function openWhiteboard() {
    viewMode.value = props.user.isTeacher ? 'split' : 'whiteboard';
}
function closeWhiteboard() {
    viewMode.value = 'jitsi';
}
function setViewMode(mode) {
    viewMode.value = mode;
}

const formattedDuration = computed(() => {
    const mins = Math.floor(recordingDuration.value / 60);
    const secs = recordingDuration.value % 60;
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
});

async function startRecording() {
    recordedChunks = [];
    recordingDuration.value = 0;
    
    try {
        const screenStream = await navigator.mediaDevices.getDisplayMedia({
            video: { displaySurface: "browser", width: { ideal: 1280 }, height: { ideal: 720 }, frameRate: { ideal: 30 } },
            audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true }
        });

        let micStream = null;
        try {
            micStream = await navigator.mediaDevices.getUserMedia({
                audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true }
            });
        } catch (e) {
            console.warn("Microphone access denied.", e);
        }

        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        audioContext.value = ctx;
        const dest = ctx.createMediaStreamDestination();
        let hasAudioTracks = false;

        if (screenStream.getAudioTracks().length > 0) {
            const screenSource = ctx.createMediaStreamSource(new MediaStream([screenStream.getAudioTracks()[0]]));
            screenSource.connect(dest);
            hasAudioTracks = true;
        }
        if (micStream && micStream.getAudioTracks().length > 0) {
            const micSource = ctx.createMediaStreamSource(micStream);
            micSource.connect(dest);
            hasAudioTracks = true;
        }

        const tracks = [...screenStream.getVideoTracks()];
        if (hasAudioTracks) tracks.push(...dest.stream.getAudioTracks());
        const mixedStream = new MediaStream(tracks);

        let options = { mimeType: 'video/webm;codecs=vp9,opus' };
        if (!MediaRecorder.isTypeSupported(options.mimeType)) options = { mimeType: 'video/webm;codecs=vp8,opus' };
        if (!MediaRecorder.isTypeSupported(options.mimeType)) options = { mimeType: 'video/webm' };
        if (!MediaRecorder.isTypeSupported(options.mimeType)) options = { mimeType: 'video/mp4' };

        mediaRecorder = new MediaRecorder(mixedStream, options);
        mediaRecorder.ondataavailable = (event) => {
            if (event.data && event.data.size > 0) recordedChunks.push(event.data);
        };
        mediaRecorder.onstop = () => {
            screenStream.getTracks().forEach(t => t.stop());
            if (micStream) micStream.getTracks().forEach(t => t.stop());
            if (audioContext.value && audioContext.value.state !== 'closed') audioContext.value.close();
            clearInterval(durationInterval);
            isRecording.value = false;

            const blob = new Blob(recordedChunks, { type: mediaRecorder.mimeType || 'video/webm' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = `class-recording-${props.session.id}-${new Date().toISOString().slice(0,10)}.webm`;
            document.body.appendChild(a);
            a.click();
            setTimeout(() => { document.body.removeChild(a); window.URL.revokeObjectURL(url); }, 100);
            alert("تم إيقاف التسجيل وتنزيل ملف الحصة بنجاح!");
        };

        mediaRecorder.start(1000);
        isRecording.value = true;
        durationInterval = setInterval(() => { recordingDuration.value++; }, 1000);

    } catch (err) {
        console.error("Error starting recording:", err);
        alert("لم يتم بدء التسجيل. يرجى التأكد من الموافقة على مشاركة الشاشة.");
    }
}

function stopRecording() {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
}

// Moderation handlers
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
    if (jitsiApi && confirm('هل تريد كتم صوت الجميع؟')) jitsiApi.executeCommand('muteEveryone');
}

onMounted(() => {
    const script = document.createElement('script');
    script.src = 'https://meet.jit.si/external_api.js';
    script.async = true;
    script.onload = () => initJitsi();
    document.head.appendChild(script);
});

function initJitsi() {
    if (!window.JitsiMeetExternalAPI) return;

    const options = {
        roomName: props.roomName,
        parentNode: jitsiContainer.value,
        userInfo: { email: props.user.email, displayName: props.user.name },
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

    jitsiApi = new window.JitsiMeetExternalAPI('meet.jit.si', options);
    isRoomLoading.value = false;

    if (props.user.isTeacher) {
        jitsiApi.executeCommand('subject', props.session.title);
        jitsiApi.executeCommand('toggleLobby', lobbyEnabled.value);

        jitsiApi.addEventListener('videoConferenceJoined', () => {
            setTimeout(() => {
                try {
                    const list = jitsiApi.getParticipantsInfo();
                    activeParticipants.value = list.map(p => ({ id: p.participantId, name: p.displayName || 'طالب مجهول' }));
                } catch (e) { console.warn("Could not load participants:", e); }
            }, 3000);
        });
        jitsiApi.addEventListener('participantJoined', (event) => {
            if (!activeParticipants.value.some(p => p.id === event.id))
                activeParticipants.value.push({ id: event.id, name: event.displayName || 'طالب مجهول' });
        });
        jitsiApi.addEventListener('participantLeft', (event) => {
            activeParticipants.value = activeParticipants.value.filter(p => p.id !== event.id);
        });
        jitsiApi.addEventListener('lobbyParticipantJoined', (event) => {
            if (!lobbyParticipants.value.some(p => p.id === event.id))
                lobbyParticipants.value.push({ id: event.id, name: event.displayName || 'طالب ينتظر' });
        });
        jitsiApi.addEventListener('lobbyParticipantLeft', (event) => {
            lobbyParticipants.value = lobbyParticipants.value.filter(p => p.id !== event.id);
        });
    }
}

onBeforeUnmount(() => {
    if (jitsiApi) jitsiApi.dispose();
    clearInterval(durationInterval);
});
</script>

<template>
    <div class="room-root" dir="rtl">
        <Head :title="session.title" />

        <!-- ═══ HEADER BAR ═══════════════════════════════════════════════════ -->
        <div class="room-header">
            <div class="flex items-center gap-4">
                <Link :href="route('dashboard')" class="btn-ghost p-2 text-surface-400 hover:text-white text-sm">
                    ← عودة
                </Link>
                <div class="header-divider"></div>
                <div>
                    <h1 class="font-bold text-base leading-tight text-white">{{ session.title }}</h1>
                    <div class="text-xs text-primary-400">{{ session.course?.title || session.teaching_group?.name || 'حصة مباشرة' }}</div>
                </div>
            </div>

            <div class="flex items-center gap-3">

                <!-- View Mode Toggle (Teacher only) -->
                <div v-if="user.isTeacher" class="view-mode-group">
                    <button
                        @click="setViewMode('jitsi')"
                        class="view-mode-btn"
                        :class="{ active: viewMode === 'jitsi' }"
                        title="عرض الفيديو فقط"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M15 10l4.553-2.069A1 1 0 0121 8.871v6.258a1 1 0 01-1.447.894L15 14M4 8h9a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4a2 2 0 012-2z"/></svg>
                        <span>كاميرا</span>
                    </button>
                    <button
                        @click="setViewMode('split')"
                        class="view-mode-btn"
                        :class="{ active: viewMode === 'split' }"
                        title="عرض مقسم — فيديو + سبورة"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="8" height="18" rx="1"/><rect x="13" y="3" width="8" height="18" rx="1"/></svg>
                        <span>مقسم</span>
                    </button>
                    <button
                        @click="setViewMode('whiteboard')"
                        class="view-mode-btn"
                        :class="{ active: viewMode === 'whiteboard' }"
                        title="السبورة فقط"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path stroke-linecap="round" d="M8 20h8M12 16v4M7 8l3 3-3 3M13 11h4"/></svg>
                        <span>سبورة</span>
                    </button>
                </div>

                <!-- Recording Controls -->
                <div v-if="user.isTeacher" class="flex items-center gap-2">
                    <span v-if="isRecording" class="rec-badge">
                        <span class="rec-dot"></span>
                        REC {{ formattedDuration }}
                    </span>
                    <button v-if="!isRecording" @click="startRecording" class="header-btn header-btn-rec">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4" fill="currentColor"/><circle cx="12" cy="12" r="9"/></svg>
                        <span>تسجيل</span>
                    </button>
                    <button v-else @click="stopRecording" class="header-btn header-btn-stop">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="5" width="14" height="14" rx="2" fill="currentColor"/></svg>
                        <span>إيقاف</span>
                    </button>
                </div>

                <!-- LIVE Badge -->
                <span class="live-badge">
                    <span class="live-dot"></span>
                    مباشر الآن
                </span>
            </div>
        </div>

        <!-- ═══ MAIN CONTENT ═════════════════════════════════════════════════ -->
        <div class="room-body">

            <!-- ── Jitsi Container ──────────────────────────────────────────── -->
            <div
                class="jitsi-panel"
                :class="{
                    'hidden':      viewMode === 'whiteboard',
                    'split-panel': viewMode === 'split',
                    'full-panel':  viewMode === 'jitsi',
                }"
            >
                <div class="relative h-full" ref="jitsiContainer">
                    <div v-if="isRoomLoading" class="loading-overlay">
                        <div class="loading-spinner"></div>
                        <div class="text-surface-400 text-sm">جاري الاتصال بقاعة البث...</div>
                    </div>
                </div>
            </div>

            <!-- ── Whiteboard Panel ─────────────────────────────────────────── -->
            <div
                v-show="whiteboardVisible"
                class="whiteboard-panel"
                :class="{
                    'split-panel':     viewMode === 'split',
                    'full-panel':      viewMode === 'whiteboard',
                }"
            >
                <!-- Header strip -->
                <div class="wb-panel-header">
                    <div class="flex items-center gap-2">
                        <span class="wb-panel-icon">📋</span>
                        <span class="wb-panel-title">السبورة التفاعلية</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-surface-400">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-4 h-4 text-amber-400"><path stroke-linecap="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                        <span>شارك شاشتك في Jitsi لتظهر السبورة للطلاب</span>
                    </div>
                </div>

                <!-- Whiteboard Component -->
                <div class="wb-component-area">
                    <Whiteboard @close="closeWhiteboard" />
                </div>
            </div>

            <!-- ── Teacher Moderation Sidebar ──────────────────────────────── -->
            <div v-if="user.isTeacher && viewMode === 'jitsi'" class="moderation-sidebar">
                <!-- Header -->
                <div class="mod-header">
                    <span>🛡️ لوحة الإشراف</span>
                </div>

                <!-- Controls -->
                <div class="mod-controls">
                    <button
                        @click="toggleLobbyMode"
                        class="mod-btn"
                        :class="lobbyEnabled ? 'mod-btn-green' : 'mod-btn-gray'"
                    >
                        <span>غرفة الانتظار</span>
                        <span>{{ lobbyEnabled ? '🟢 نشطة' : '⚪ ملغاة' }}</span>
                    </button>
                    <button @click="muteAllStudents" class="mod-btn mod-btn-red">
                        🔇 كتم الجميع
                    </button>
                </div>

                <!-- Lobby -->
                <div class="mod-section">
                    <div class="mod-section-title">طلبات الدخول</div>
                    <div v-if="!lobbyParticipants.length" class="mod-empty">لا توجد طلبات معلقة</div>
                    <div v-else class="space-y-2">
                        <div v-for="student in lobbyParticipants" :key="student.id" class="mod-student-card">
                            <span class="truncate font-medium text-white text-xs max-w-[120px]">{{ student.name }}</span>
                            <div class="flex gap-1">
                                <button @click="acceptLobby(student.id)" class="mod-action-btn mod-accept">قبول</button>
                                <button @click="rejectLobby(student.id)" class="mod-action-btn mod-reject">رفض</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Participants -->
                <div class="mod-section flex-1 overflow-y-auto">
                    <div class="mod-section-title">الطلاب المتصلون ({{ activeParticipants.length }})</div>
                    <div v-if="!activeParticipants.length" class="mod-empty">لا يوجد طلاب متصلين</div>
                    <div v-else class="space-y-1.5">
                        <div v-for="student in activeParticipants" :key="student.id" class="mod-student-card">
                            <span class="truncate text-surface-300 text-xs max-w-[140px]">{{ student.name }}</span>
                            <button @click="kickStudent(student.id)" class="mod-action-btn mod-kick">طرد</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ─── Root ────────────────────────────────────────────────────── */
.room-root {
    height: 100vh;
    width: 100%;
    display: flex;
    flex-direction: column;
    background: #080a10;
    color: white;
    overflow: hidden;
}

/* ─── Header ──────────────────────────────────────────────────── */
.room-header {
    height: 60px;
    flex-shrink: 0;
    background: rgba(10,12,20,0.98);
    border-bottom: 1px solid rgba(255,255,255,0.07);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
    gap: 16px;
    backdrop-filter: blur(20px);
    z-index: 30;
}

.header-divider {
    width: 1px;
    height: 28px;
    background: rgba(255,255,255,0.1);
}

/* ─── View Mode Toggle ────────────────────────────────────────── */
.view-mode-group {
    display: flex;
    align-items: center;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    padding: 3px;
    gap: 2px;
}

.view-mode-btn {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    border-radius: 9px;
    border: none;
    background: transparent;
    color: rgba(255,255,255,0.45);
    cursor: pointer;
    font-size: 11px;
    font-weight: 600;
    transition: all 0.18s ease;
    font-family: 'Cairo', sans-serif;
    white-space: nowrap;
}
.view-mode-btn svg { width: 14px; height: 14px; flex-shrink: 0; }
.view-mode-btn:hover {
    color: rgba(255,255,255,0.8);
    background: rgba(255,255,255,0.07);
}
.view-mode-btn.active {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    box-shadow: 0 2px 12px rgba(99,102,241,0.4);
}

/* ─── Recording ───────────────────────────────────────────────── */
.rec-badge {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 700;
    color: #f87171;
    animation: pulse-text 1.5s ease-in-out infinite;
}
.rec-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #ef4444;
    animation: pulse-dot 1s ease-in-out infinite;
}
@keyframes pulse-text { 0%,100%{opacity:1} 50%{opacity:0.6} }
@keyframes pulse-dot  { 0%,100%{transform:scale(1)} 50%{transform:scale(1.4)} }

.header-btn {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    border: none;
    transition: all 0.15s;
    font-family: 'Cairo', sans-serif;
}
.header-btn svg { width: 14px; height: 14px; }

.header-btn-rec {
    background: rgba(239,68,68,0.15);
    color: #f87171;
    border: 1px solid rgba(239,68,68,0.25);
}
.header-btn-rec:hover {
    background: rgba(239,68,68,0.25);
    transform: translateY(-1px);
}
.header-btn-stop {
    background: rgba(100,116,139,0.2);
    color: #94a3b8;
    border: 1px solid rgba(100,116,139,0.2);
}
.header-btn-stop:hover { background: rgba(100,116,139,0.3); }

/* ─── LIVE Badge ──────────────────────────────────────────────── */
.live-badge {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 700;
    color: #34d399;
    background: rgba(52,211,153,0.1);
    border: 1px solid rgba(52,211,153,0.2);
    padding: 5px 10px;
    border-radius: 20px;
    letter-spacing: 0.05em;
}
.live-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 6px #10b981;
    animation: pulse-dot 1.2s ease-in-out infinite;
}

/* ─── Body Layout ─────────────────────────────────────────────── */
.room-body {
    flex: 1;
    display: flex;
    overflow: hidden;
    min-height: 0;
}

/* ─── Panels ──────────────────────────────────────────────────── */
.jitsi-panel,
.whiteboard-panel {
    overflow: hidden;
    transition: flex 0.35s cubic-bezier(0.4,0,0.2,1);
}

.hidden { display: none !important; }

.full-panel  { flex: 1; }
.split-panel { flex: 1; min-width: 0; }

.jitsi-panel.full-panel  { height: 100%; }
.jitsi-panel.split-panel { height: 100%; border-left: 2px solid rgba(99,102,241,0.3); }

/* ─── Whiteboard Panel ────────────────────────────────────────── */
.whiteboard-panel {
    display: flex;
    flex-direction: column;
    background: #0F1117;
}

.wb-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 14px;
    background: rgba(99,102,241,0.08);
    border-bottom: 1px solid rgba(99,102,241,0.2);
    flex-shrink: 0;
}
.wb-panel-icon { font-size: 16px; }
.wb-panel-title { font-size: 13px; font-weight: 700; color: #a5b4fc; }

.wb-component-area {
    flex: 1;
    min-height: 0;
    overflow: hidden;
}

/* ─── Loading Overlay ─────────────────────────────────────────── */
.loading-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,0.8);
    gap: 12px;
    z-index: 20;
}
.loading-spinner {
    width: 32px; height: 32px;
    border: 3px solid rgba(99,102,241,0.2);
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ─── Moderation Sidebar ──────────────────────────────────────── */
.moderation-sidebar {
    width: 280px;
    flex-shrink: 0;
    background: rgba(10,12,20,0.95);
    border-right: 1px solid rgba(255,255,255,0.06);
    display: flex;
    flex-direction: column;
    font-size: 12px;
    overflow: hidden;
}

.mod-header {
    padding: 14px 16px;
    font-weight: 700;
    font-size: 13px;
    color: white;
    background: rgba(99,102,241,0.06);
    border-bottom: 1px solid rgba(255,255,255,0.06);
    flex-shrink: 0;
}

.mod-controls {
    padding: 12px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex-shrink: 0;
}

.mod-btn {
    width: 100%;
    padding: 8px 12px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border: 1px solid transparent;
    transition: all 0.15s;
    font-family: 'Cairo', sans-serif;
}
.mod-btn-green {
    background: rgba(52,211,153,0.08);
    color: #6ee7b7;
    border-color: rgba(52,211,153,0.15);
}
.mod-btn-green:hover { background: rgba(52,211,153,0.14); }
.mod-btn-gray {
    background: rgba(100,116,139,0.08);
    color: #94a3b8;
    border-color: rgba(100,116,139,0.15);
}
.mod-btn-red {
    background: rgba(239,68,68,0.08);
    color: #f87171;
    border-color: rgba(239,68,68,0.15);
    justify-content: center;
}
.mod-btn-red:hover { background: rgba(239,68,68,0.15); }

.mod-section {
    padding: 12px;
    flex-shrink: 0;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}
.mod-section.flex-1 { flex: 1; overflow-y: auto; border-bottom: none; }

.mod-section-title {
    font-size: 10px;
    font-weight: 700;
    color: rgba(255,255,255,0.3);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 8px;
}

.mod-empty {
    font-size: 11px;
    color: rgba(255,255,255,0.25);
    text-align: center;
    padding: 12px;
    border: 1px dashed rgba(255,255,255,0.1);
    border-radius: 8px;
}

.mod-student-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    padding: 7px 10px;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 9px;
}

.mod-action-btn {
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 700;
    cursor: pointer;
    border: none;
    transition: all 0.12s;
    font-family: 'Cairo', sans-serif;
}
.mod-accept { background: #16a34a; color: white; }
.mod-accept:hover { background: #15803d; }
.mod-reject { background: #dc2626; color: white; }
.mod-reject:hover { background: #b91c1c; }
.mod-kick {
    background: transparent;
    color: #f87171;
    border: 1px solid rgba(239,68,68,0.25);
}
.mod-kick:hover { background: rgba(239,68,68,0.1); }

/* ─── Ensure Jitsi iframe fills ───────────────────────────────── */
:deep(iframe) {
    width: 100% !important;
    height: 100% !important;
    border: none !important;
}
</style>
