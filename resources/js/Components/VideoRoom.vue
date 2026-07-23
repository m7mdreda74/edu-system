<script setup>
/**
 * VideoRoom.vue — Native WebRTC Video Conferencing Component
 * Signaling: HTTP Polling via Laravel backend (no external services)
 * Topology: Full Mesh (everyone connects to everyone)
 */
import { ref, onMounted, onBeforeUnmount, computed, nextTick } from 'vue';
import axios from 'axios';

const props = defineProps({
    sessionId:  { type: Number, required: true },
    user:       { type: Object, required: true }, // { id, name, isTeacher }
    sessionTitle: { type: String, default: '' },
});

const emit = defineEmits(['error', 'participant-count']);

// ─── ICE Servers (STUN/TURN) ─────────────────────────────────────────────────
const ICE_SERVERS = [
    { urls: 'stun:stun.l.google.com:19302' },
    { urls: 'stun:stun1.l.google.com:19302' },
    { urls: 'stun:stun2.l.google.com:19302' },
    { urls: 'stun:stun.cloudflare.com:3478' },
];

// ─── State ────────────────────────────────────────────────────────────────────
const localStream      = ref(null);
const screenStream     = ref(null);
const participants     = ref([]);   // { userId, name, isTeacher, stream, pc, muted, videoOff }
const localMuted       = ref(false);
const localVideoOff    = ref(false);
const isSharingScreen  = ref(false);
const isJoining        = ref(true);
const joinError        = ref(null);
const chatMessages     = ref([]);
const chatInput        = ref('');
const showChat         = ref(false);
const unreadChat       = ref(0);
const raisedHands      = ref(new Set());
const myHandRaised     = ref(false);
const localVideoRef    = ref(null);
const chatEndRef       = ref(null);

// ─── Internals ────────────────────────────────────────────────────────────────
let peerConnections    = {};   // userId → RTCPeerConnection
let pollTimer          = null;
let heartbeatTimer     = null;
let lastPollAt         = Date.now();
let knownParticipants  = new Set(); // userIds we've seen
let pendingCandidates  = {};   // userId → [RTCIceCandidate]

// ─── Computed ─────────────────────────────────────────────────────────────────
const allTiles = computed(() => {
    const tiles = [
        {
            userId: props.user.id,
            name: props.user.name + ' (أنت)',
            isTeacher: props.user.isTeacher,
            stream: localStream.value,
            isLocal: true,
            muted: localMuted.value,
            videoOff: localVideoOff.value,
            handRaised: myHandRaised.value,
        },
        ...participants.value.map(p => ({
            ...p,
            isLocal: false,
            handRaised: raisedHands.value.has(p.userId),
        })),
    ];
    return tiles;
});

const gridClass = computed(() => {
    const n = allTiles.value.length;
    if (n === 1) return 'grid-1';
    if (n === 2) return 'grid-2';
    if (n <= 4)  return 'grid-4';
    if (n <= 9)  return 'grid-9';
    return 'grid-16';
});

// ─── Join Room ────────────────────────────────────────────────────────────────
async function joinRoom() {
    isJoining.value = true;
    joinError.value = null;
    try {
        localStream.value = await navigator.mediaDevices.getUserMedia({
            video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'user' },
            audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
        });

        if (localVideoRef.value) {
            localVideoRef.value.srcObject = localStream.value;
        }

        // Default: students start muted
        if (!props.user.isTeacher) {
            localStream.value.getAudioTracks().forEach(t => (t.enabled = false));
            localMuted.value = true;
        }

        isJoining.value = false;

        // Start heartbeat & polling
        await sendHeartbeat();
        heartbeatTimer = setInterval(sendHeartbeat, 4000);
        startPolling();

    } catch (err) {
        console.error('getUserMedia error:', err);
        joinError.value = err.name === 'NotAllowedError'
            ? 'رفضت إذن الكاميرا/الميكروفون. يرجى السماح بالوصول وإعادة تحميل الصفحة.'
            : 'لم يتم الوصول للكاميرا: ' + err.message;
        isJoining.value = false;
    }
}

// ─── Heartbeat ────────────────────────────────────────────────────────────────
async function sendHeartbeat() {
    try {
        const { data } = await axios.post(`/live-sessions/${props.sessionId}/webrtc/heartbeat`);
        const active = data.participants ?? [];

        // Detect newly joined participants
        active.forEach(p => {
            if (!knownParticipants.has(p.user_id)) {
                knownParticipants.add(p.user_id);
                initiateOffer(p);
            }
        });

        // Detect departed participants
        const activeIds = new Set(active.map(p => p.user_id));
        knownParticipants.forEach(uid => {
            if (!activeIds.has(uid)) {
                knownParticipants.delete(uid);
                removeParticipant(uid);
            }
        });

        emit('participant-count', allTiles.value.length);
    } catch (e) { /* ignore heartbeat failures */ }
}

// ─── Polling ──────────────────────────────────────────────────────────────────
function startPolling() {
    poll();
    pollTimer = setInterval(poll, 1200);
}

async function poll() {
    try {
        const { data } = await axios.get(`/live-sessions/${props.sessionId}/webrtc/poll`, {
            params: { since: lastPollAt },
        });
        lastPollAt = data.server_now ?? Date.now();
        for (const sig of data.signals ?? []) {
            await handleSignal(sig);
        }
    } catch (e) { /* ignore poll failures */ }
}

// ─── Signal Handler ───────────────────────────────────────────────────────────
async function handleSignal(sig) {
    const { from, type, payload } = sig;

    switch (type) {
        case 'offer':
            await handleOffer(from, payload);
            break;
        case 'answer':
            await handleAnswer(from, payload);
            break;
        case 'ice-candidate':
            await handleIceCandidate(from, payload);
            break;
        case 'leave':
            removeParticipant(from);
            break;
        case 'chat':
            chatMessages.value.push({
                from: payload.name,
                text: payload.text,
                at: new Date().toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' }),
                isMe: false,
            });
            if (!showChat.value) unreadChat.value++;
            await nextTick();
            chatEndRef.value?.scrollIntoView({ behavior: 'smooth' });
            break;
        case 'raise-hand':
            if (payload.raised) raisedHands.value.add(from);
            else raisedHands.value.delete(from);
            break;
        case 'mute-all':
            if (!props.user.isTeacher) {
                localStream.value?.getAudioTracks().forEach(t => (t.enabled = false));
                localMuted.value = true;
            }
            break;
    }
}

// ─── Offer (initiator side) ───────────────────────────────────────────────────
async function initiateOffer(participant) {
    const pc = createPeerConnection(participant.user_id, participant.name, !!participant.is_teacher);

    try {
        const offer = await pc.createOffer({ offerToReceiveAudio: true, offerToReceiveVideo: true });
        await pc.setLocalDescription(offer);
        await sendSignal('offer', { sdp: offer.sdp, type: offer.type }, participant.user_id);
    } catch (e) {
        console.error('Error creating offer:', e);
    }
}

// ─── Offer (receiver side) ───────────────────────────────────────────────────
async function handleOffer(fromId, payload) {
    if (peerConnections[fromId]) return; // already connected

    // Find participant name from knownParticipants (might not know it yet)
    const name = participants.value.find(p => p.userId === fromId)?.name ?? `مستخدم ${fromId}`;
    const pc = createPeerConnection(fromId, name, false);

    try {
        await pc.setRemoteDescription(new RTCSessionDescription({ type: payload.type, sdp: payload.sdp }));

        // Flush any queued ICE candidates
        if (pendingCandidates[fromId]) {
            for (const c of pendingCandidates[fromId]) {
                await pc.addIceCandidate(c);
            }
            delete pendingCandidates[fromId];
        }

        const answer = await pc.createAnswer();
        await pc.setLocalDescription(answer);
        await sendSignal('answer', { sdp: answer.sdp, type: answer.type }, fromId);
    } catch (e) {
        console.error('Error handling offer:', e);
    }
}

async function handleAnswer(fromId, payload) {
    const pc = peerConnections[fromId];
    if (!pc || pc.signalingState === 'stable') return;
    try {
        await pc.setRemoteDescription(new RTCSessionDescription({ type: payload.type, sdp: payload.sdp }));
    } catch (e) {
        console.error('Error handling answer:', e);
    }
}

async function handleIceCandidate(fromId, payload) {
    const pc = peerConnections[fromId];
    const candidate = new RTCIceCandidate(payload);

    if (pc && pc.remoteDescription) {
        try { await pc.addIceCandidate(candidate); } catch (e) { /* ignore */ }
    } else {
        // Queue until remote description is set
        if (!pendingCandidates[fromId]) pendingCandidates[fromId] = [];
        pendingCandidates[fromId].push(candidate);
    }
}

// ─── Peer Connection Factory ──────────────────────────────────────────────────
function createPeerConnection(userId, name, isTeacher) {
    const pc = new RTCPeerConnection({ iceServers: ICE_SERVERS });
    peerConnections[userId] = pc;

    // Add our local stream tracks
    if (localStream.value) {
        localStream.value.getTracks().forEach(track => pc.addTrack(track, localStream.value));
    }

    // ICE candidates → send to peer
    pc.onicecandidate = async (event) => {
        if (event.candidate) {
            await sendSignal('ice-candidate', event.candidate.toJSON(), userId);
        }
    };

    // Remote stream received
    pc.ontrack = (event) => {
        const stream = event.streams[0] ?? new MediaStream([event.track]);
        updateParticipantStream(userId, name, isTeacher, stream);
    };

    pc.onconnectionstatechange = () => {
        if (['disconnected', 'failed', 'closed'].includes(pc.connectionState)) {
            removeParticipant(userId);
        }
    };

    // Ensure participant entry exists
    ensureParticipant(userId, name, isTeacher);

    return pc;
}

function ensureParticipant(userId, name, isTeacher) {
    if (!participants.value.find(p => p.userId === userId)) {
        participants.value.push({
            userId, name, isTeacher,
            stream: null, muted: false, videoOff: false,
        });
        knownParticipants.add(userId);
    }
}

function updateParticipantStream(userId, name, isTeacher, stream) {
    const idx = participants.value.findIndex(p => p.userId === userId);
    if (idx >= 0) {
        participants.value[idx] = { ...participants.value[idx], stream };
    } else {
        participants.value.push({ userId, name, isTeacher, stream, muted: false, videoOff: false });
    }
}

function removeParticipant(userId) {
    if (peerConnections[userId]) {
        peerConnections[userId].close();
        delete peerConnections[userId];
    }
    participants.value = participants.value.filter(p => p.userId !== userId);
    knownParticipants.delete(userId);
    raisedHands.value.delete(userId);
}

// ─── Send Signal ─────────────────────────────────────────────────────────────
async function sendSignal(type, payload, toUserId = null) {
    try {
        await axios.post(`/live-sessions/${props.sessionId}/webrtc/signal`, {
            type, payload, to_user_id: toUserId,
        });
    } catch (e) { /* ignore */ }
}

// ─── Controls ────────────────────────────────────────────────────────────────
function toggleMic() {
    localStream.value?.getAudioTracks().forEach(t => {
        t.enabled = !t.enabled;
    });
    localMuted.value = !localMuted.value;
}

function toggleCamera() {
    localStream.value?.getVideoTracks().forEach(t => {
        t.enabled = !t.enabled;
    });
    localVideoOff.value = !localVideoOff.value;
}

async function toggleScreenShare() {
    if (isSharingScreen.value) {
        // Stop sharing
        screenStream.value?.getTracks().forEach(t => t.stop());
        screenStream.value = null;
        isSharingScreen.value = false;

        // Replace screen track with camera track in all peer connections
        const videoTrack = localStream.value?.getVideoTracks()[0];
        if (videoTrack) {
            Object.values(peerConnections).forEach(pc => {
                const sender = pc.getSenders().find(s => s.track?.kind === 'video');
                sender?.replaceTrack(videoTrack);
            });
        }
    } else {
        try {
            screenStream.value = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: true });
            isSharingScreen.value = true;

            const screenTrack = screenStream.value.getVideoTracks()[0];
            // Replace video track in all peer connections
            Object.values(peerConnections).forEach(pc => {
                const sender = pc.getSenders().find(s => s.track?.kind === 'video');
                sender?.replaceTrack(screenTrack);
            });

            // Show screen on own video element
            if (localVideoRef.value) {
                const composed = new MediaStream([
                    screenTrack,
                    ...localStream.value.getAudioTracks(),
                ]);
                localVideoRef.value.srcObject = composed;
            }

            screenTrack.onended = () => toggleScreenShare();
        } catch (e) { /* user cancelled */ }
    }
}

async function muteAll() {
    if (!props.user.isTeacher) return;
    await sendSignal('mute-all', { by: props.user.id });
}

function toggleHand() {
    myHandRaised.value = !myHandRaised.value;
    sendSignal('raise-hand', { raised: myHandRaised.value, name: props.user.name });
}

async function sendChat() {
    const text = chatInput.value.trim();
    if (!text) return;
    chatInput.value = '';
    chatMessages.value.push({
        from: 'أنا',
        text,
        at: new Date().toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' }),
        isMe: true,
    });
    await sendSignal('chat', { name: props.user.name, text });
    await nextTick();
    chatEndRef.value?.scrollIntoView({ behavior: 'smooth' });
}

function openChat() {
    showChat.value = true;
    unreadChat.value = 0;
}

async function leaveRoom() {
    clearInterval(pollTimer);
    clearInterval(heartbeatTimer);
    await axios.post(`/live-sessions/${props.sessionId}/webrtc/leave`).catch(() => {});
    Object.values(peerConnections).forEach(pc => pc.close());
    localStream.value?.getTracks().forEach(t => t.stop());
    screenStream.value?.getTracks().forEach(t => t.stop());
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(joinRoom);
onBeforeUnmount(leaveRoom);
</script>

<template>
    <div class="vr-root" dir="rtl">

        <!-- ═══ JOINING SCREEN ════════════════════════════════════════════════ -->
        <div v-if="isJoining" class="vr-join-screen">
            <div class="vr-join-card">
                <div class="join-spinner"></div>
                <h2 class="join-title">جاري الاتصال بالحصة...</h2>
                <p class="join-sub">يرجى السماح بالوصول للكاميرا والميكروفون</p>
            </div>
        </div>

        <!-- ═══ ERROR SCREEN ══════════════════════════════════════════════════ -->
        <div v-else-if="joinError" class="vr-join-screen">
            <div class="vr-join-card vr-error-card">
                <div class="error-icon">⚠️</div>
                <h2 class="join-title">تعذّر الدخول</h2>
                <p class="join-sub">{{ joinError }}</p>
                <button class="join-retry-btn" @click="joinRoom">إعادة المحاولة</button>
            </div>
        </div>

        <!-- ═══ MAIN ROOM ══════════════════════════════════════════════════════ -->
        <template v-else>
            <!-- Video Grid -->
            <div class="vr-grid" :class="gridClass">
                <div
                    v-for="tile in allTiles"
                    :key="tile.userId"
                    class="vr-tile"
                    :class="{ 'vr-tile-teacher': tile.isTeacher, 'vr-tile-local': tile.isLocal }"
                >
                    <!-- Video element for remote -->
                    <video
                        v-if="!tile.isLocal && tile.stream"
                        class="vr-video"
                        autoplay
                        playsinline
                        :srcObject="tile.stream"
                    ></video>

                    <!-- Video element for local -->
                    <video
                        v-else-if="tile.isLocal"
                        ref="localVideoRef"
                        class="vr-video"
                        autoplay
                        playsinline
                        muted
                    ></video>

                    <!-- No stream placeholder -->
                    <div v-else class="vr-no-stream">
                        <div class="vr-avatar">{{ tile.name.charAt(0) }}</div>
                    </div>

                    <!-- Video Off overlay -->
                    <div v-if="tile.videoOff || (!tile.isLocal && !tile.stream)" class="vr-video-off">
                        <div class="vr-avatar">{{ tile.name.charAt(0) }}</div>
                    </div>

                    <!-- Tile Footer -->
                    <div class="vr-tile-footer">
                        <span class="vr-tile-name">
                            <span v-if="tile.isTeacher" class="teacher-badge">مدرس</span>
                            {{ tile.name }}
                        </span>
                        <div class="vr-tile-icons">
                            <span v-if="tile.muted || (tile.isLocal && localMuted)" title="مكتوم">🔇</span>
                            <span v-if="tile.handRaised" title="يد مرفوعة" class="hand-icon">✋</span>
                        </div>
                    </div>

                    <!-- Teacher badge glow -->
                    <div v-if="tile.isTeacher" class="teacher-glow"></div>
                </div>
            </div>

            <!-- ═══ CONTROLS BAR ═══════════════════════════════════════════════ -->
            <div class="vr-controls">
                <!-- Mic -->
                <button
                    class="ctrl-btn"
                    :class="{ 'ctrl-off': localMuted }"
                    @click="toggleMic"
                    :title="localMuted ? 'تشغيل الميكروفون' : 'كتم الميكروفون'"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <template v-if="!localMuted">
                            <path stroke-linecap="round" d="M12 1a3 3 0 0 1 3 3v8a3 3 0 0 1-6 0V4a3 3 0 0 1 3-3z"/>
                            <path stroke-linecap="round" d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8"/>
                        </template>
                        <template v-else>
                            <path stroke-linecap="round" d="M12 1a3 3 0 0 1 3 3v8a3 3 0 0 1-6 0V4a3 3 0 0 1 3-3z"/>
                            <path stroke-linecap="round" d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8"/>
                            <line x1="1" y1="1" x2="23" y2="23" stroke-linecap="round"/>
                        </template>
                    </svg>
                    <span>{{ localMuted ? 'تشغيل' : 'كتم' }}</span>
                </button>

                <!-- Camera -->
                <button
                    class="ctrl-btn"
                    :class="{ 'ctrl-off': localVideoOff }"
                    @click="toggleCamera"
                    :title="localVideoOff ? 'تشغيل الكاميرا' : 'إيقاف الكاميرا'"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" v-if="!localVideoOff" d="M15 10l4.553-2.069A1 1 0 0121 8.871v6.258a1 1 0 01-1.447.894L15 14M4 8h9a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4a2 2 0 012-2z"/>
                        <path stroke-linecap="round" v-else d="M15 10l4.553-2.069A1 1 0 0121 8.871v6.258a1 1 0 01-1.447.894L15 14M4 8h9a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4a2 2 0 012-2zM1 1l22 22"/>
                    </svg>
                    <span>{{ localVideoOff ? 'الكاميرا' : 'إيقاف' }}</span>
                </button>

                <!-- Screen Share (Teacher only) -->
                <button
                    v-if="user.isTeacher"
                    class="ctrl-btn"
                    :class="{ 'ctrl-active': isSharingScreen }"
                    @click="toggleScreenShare"
                    title="مشاركة الشاشة"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2"/>
                        <path stroke-linecap="round" d="M8 21h8M12 17v4"/>
                        <path stroke-linecap="round" v-if="isSharingScreen" d="M9 10l3-3 3 3M12 7v6" fill="currentColor"/>
                    </svg>
                    <span>{{ isSharingScreen ? 'إيقاف المشاركة' : 'الشاشة' }}</span>
                </button>

                <!-- Raise Hand (Students) -->
                <button
                    v-if="!user.isTeacher"
                    class="ctrl-btn"
                    :class="{ 'ctrl-active': myHandRaised }"
                    @click="toggleHand"
                    title="رفع اليد"
                >
                    <span style="font-size:20px">✋</span>
                    <span>{{ myHandRaised ? 'إنزال اليد' : 'رفع اليد' }}</span>
                </button>

                <!-- Mute All (Teacher) -->
                <button
                    v-if="user.isTeacher"
                    class="ctrl-btn"
                    @click="muteAll"
                    title="كتم الجميع"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" d="M12 1a3 3 0 0 1 3 3v8a3 3 0 0 1-6 0V4a3 3 0 0 1 3-3z"/>
                        <line x1="1" y1="1" x2="23" y2="23" stroke-linecap="round"/>
                    </svg>
                    <span>كتم الكل</span>
                </button>

                <!-- Chat -->
                <button
                    class="ctrl-btn ctrl-btn-chat"
                    :class="{ 'ctrl-active': showChat }"
                    @click="openChat"
                    title="المحادثة"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                    <span>محادثة</span>
                    <span v-if="unreadChat > 0" class="chat-badge">{{ unreadChat }}</span>
                </button>

                <!-- Hang Up -->
                <button class="ctrl-btn ctrl-hangup" @click="leaveRoom(); $emit('leave')" title="مغادرة الحصة">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
                    <span>مغادرة</span>
                </button>
            </div>

            <!-- ═══ CHAT PANEL ═════════════════════════════════════════════════ -->
            <div v-if="showChat" class="vr-chat-panel">
                <div class="chat-header">
                    <span>💬 محادثة الحصة</span>
                    <button class="chat-close" @click="showChat = false">✕</button>
                </div>
                <div class="chat-messages">
                    <div
                        v-for="(msg, i) in chatMessages" :key="i"
                        class="chat-msg"
                        :class="{ 'chat-msg-me': msg.isMe }"
                    >
                        <span class="chat-msg-name">{{ msg.from }}</span>
                        <span class="chat-msg-text">{{ msg.text }}</span>
                        <span class="chat-msg-time">{{ msg.at }}</span>
                    </div>
                    <div v-if="!chatMessages.length" class="chat-empty">لا توجد رسائل بعد</div>
                    <div ref="chatEndRef"></div>
                </div>
                <div class="chat-input-row">
                    <input
                        v-model="chatInput"
                        class="chat-input"
                        placeholder="اكتب رسالة..."
                        @keydown.enter.prevent="sendChat"
                    />
                    <button class="chat-send" @click="sendChat">إرسال</button>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
/* ─── Root ─────────────────────────────────────────────────────── */
.vr-root {
    position: relative;
    width: 100%;
    height: 100%;
    background: #080a10;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    font-family: 'Cairo', 'Segoe UI', sans-serif;
}

/* ─── Joining / Error Screen ───────────────────────────────────── */
.vr-join-screen {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}
.vr-join-card {
    text-align: center;
    padding: 48px 40px;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 24px;
    max-width: 360px;
}
.vr-error-card { border-color: rgba(239,68,68,0.25); }
.join-spinner {
    width: 48px; height: 48px;
    border: 3px solid rgba(99,102,241,0.2);
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 20px;
}
@keyframes spin { to { transform: rotate(360deg); } }
.join-title  { font-size: 20px; font-weight: 800; color: white; margin: 0 0 8px; }
.join-sub    { font-size: 14px; color: rgba(255,255,255,0.5); margin: 0 0 20px; }
.error-icon  { font-size: 40px; margin-bottom: 12px; }
.join-retry-btn {
    padding: 10px 24px;
    background: #6366f1;
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
}

/* ─── Video Grid ───────────────────────────────────────────────── */
.vr-grid {
    flex: 1;
    display: grid;
    gap: 6px;
    padding: 8px;
    min-height: 0;
    overflow: hidden;
}
.grid-1  { grid-template-columns: 1fr; }
.grid-2  { grid-template-columns: repeat(2, 1fr); }
.grid-4  { grid-template-columns: repeat(2, 1fr); grid-template-rows: repeat(2, 1fr); }
.grid-9  { grid-template-columns: repeat(3, 1fr); grid-template-rows: repeat(3, 1fr); }
.grid-16 { grid-template-columns: repeat(4, 1fr); }

/* ─── Tile ─────────────────────────────────────────────────────── */
.vr-tile {
    position: relative;
    background: #13161f;
    border-radius: 12px;
    overflow: hidden;
    border: 2px solid transparent;
    transition: border-color 0.2s;
}
.vr-tile-teacher {
    border-color: rgba(99,102,241,0.5);
    box-shadow: 0 0 20px rgba(99,102,241,0.15);
}
.vr-tile-local { border-color: rgba(52,211,153,0.4); }

.vr-video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transform: scaleX(-1); /* Mirror local video */
}
.vr-tile-local .vr-video { transform: scaleX(-1); }

.vr-no-stream,
.vr-video-off {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #13161f;
}
.vr-avatar {
    width: 64px; height: 64px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: 700;
    color: white;
    text-transform: uppercase;
}

/* Tile Footer */
.vr-tile-footer {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    padding: 6px 10px;
    background: linear-gradient(transparent, rgba(0,0,0,0.7));
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 12px;
}
.vr-tile-name {
    color: white;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.teacher-badge {
    background: #6366f1;
    color: white;
    font-size: 9px;
    font-weight: 700;
    padding: 1px 5px;
    border-radius: 4px;
    flex-shrink: 0;
}
.vr-tile-icons { display: flex; gap: 4px; }
.hand-icon { animation: bounce 0.5s ease infinite alternate; }
@keyframes bounce { from { transform: translateY(0); } to { transform: translateY(-3px); } }

/* Teacher glow */
.teacher-glow {
    position: absolute;
    inset: -1px;
    border-radius: 12px;
    border: 2px solid rgba(99,102,241,0.6);
    pointer-events: none;
    animation: teacher-pulse 2s ease-in-out infinite;
}
@keyframes teacher-pulse { 0%,100%{opacity:0.6} 50%{opacity:1} }

/* ─── Controls Bar ─────────────────────────────────────────────── */
.vr-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 16px 12px;
    background: rgba(10,12,20,0.95);
    border-top: 1px solid rgba(255,255,255,0.07);
    flex-shrink: 0;
    flex-wrap: wrap;
}

.ctrl-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    padding: 8px 14px;
    border-radius: 14px;
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.07);
    color: rgba(255,255,255,0.85);
    cursor: pointer;
    transition: all 0.15s ease;
    font-size: 10px;
    font-weight: 700;
    font-family: 'Cairo', sans-serif;
    position: relative;
    min-width: 54px;
}
.ctrl-btn svg { width: 20px; height: 20px; }
.ctrl-btn:hover { background: rgba(255,255,255,0.12); transform: translateY(-1px); }

.ctrl-off {
    background: rgba(239,68,68,0.15);
    color: #f87171;
    border-color: rgba(239,68,68,0.25);
}
.ctrl-active {
    background: rgba(99,102,241,0.2);
    color: #a5b4fc;
    border-color: rgba(99,102,241,0.4);
}

.ctrl-hangup {
    background: rgba(239,68,68,0.2);
    color: #fca5a5;
    border-color: rgba(239,68,68,0.3);
    min-width: 64px;
}
.ctrl-hangup:hover { background: rgba(239,68,68,0.4); }

.chat-badge {
    position: absolute;
    top: 4px; left: 4px;
    background: #ef4444;
    color: white;
    font-size: 10px;
    font-weight: 700;
    width: 18px; height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ─── Chat Panel ───────────────────────────────────────────────── */
.vr-chat-panel {
    position: absolute;
    bottom: 70px; left: 12px;
    width: 300px;
    max-height: 420px;
    background: rgba(12,15,25,0.97);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    z-index: 30;
    box-shadow: 0 24px 48px rgba(0,0,0,0.5);
    animation: chat-in 0.2s ease;
}
@keyframes chat-in { from { opacity:0; transform: translateY(12px); } to { opacity:1; transform: translateY(0); } }

.chat-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    font-size: 13px;
    font-weight: 700;
    color: white;
}
.chat-close {
    background: none;
    border: none;
    color: rgba(255,255,255,0.4);
    cursor: pointer;
    font-size: 16px;
    padding: 0;
    line-height: 1;
}
.chat-close:hover { color: white; }

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.chat-empty { font-size: 12px; color: rgba(255,255,255,0.3); text-align: center; margin-top: 20px; }

.chat-msg {
    display: flex;
    flex-direction: column;
    gap: 2px;
    align-items: flex-end;
}
.chat-msg-me { align-items: flex-start; }

.chat-msg-name {
    font-size: 10px;
    color: rgba(255,255,255,0.4);
    font-weight: 600;
}
.chat-msg-text {
    background: rgba(255,255,255,0.08);
    padding: 6px 10px;
    border-radius: 10px;
    font-size: 13px;
    color: rgba(255,255,255,0.9);
    max-width: 220px;
    word-break: break-word;
}
.chat-msg-me .chat-msg-text {
    background: rgba(99,102,241,0.25);
    color: #c7d2fe;
}
.chat-msg-time { font-size: 9px; color: rgba(255,255,255,0.25); }

.chat-input-row {
    display: flex;
    gap: 6px;
    padding: 10px;
    border-top: 1px solid rgba(255,255,255,0.07);
}
.chat-input {
    flex: 1;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    padding: 7px 10px;
    font-size: 12px;
    color: white;
    font-family: 'Cairo', sans-serif;
    outline: none;
    direction: rtl;
}
.chat-input::placeholder { color: rgba(255,255,255,0.3); }
.chat-send {
    background: #6366f1;
    color: white;
    border: none;
    border-radius: 10px;
    padding: 7px 12px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    font-family: 'Cairo', sans-serif;
    white-space: nowrap;
}
.chat-send:hover { background: #4f46e5; }
</style>
