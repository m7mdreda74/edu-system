<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    session: { type: Object, required: true },
    startedAt: { type: String, default: null },
    roomName: { type: String, required: true },
    user: { type: Object, required: true },
    jitsi: { type: Object, required: true },
});

const jitsiContainer = ref(null);
const isLoading = ref(true);
const isJoined = ref(false);
const roomError = ref('');
const toolNotice = ref('');
const elapsedSeconds = ref(0);
const isScreenSharing = ref(false);
const isRecording = ref(false);

const sessionDuration = computed(() => {
    if (!props.startedAt) {
        return 'لم تبدأ';
    }

    const hours = Math.floor(elapsedSeconds.value / 3600);
    const minutes = Math.floor((elapsedSeconds.value % 3600) / 60).toString().padStart(2, '0');
    const seconds = (elapsedSeconds.value % 60).toString().padStart(2, '0');

    return hours > 0 ? `${hours}:${minutes}:${seconds}` : `${minutes}:${seconds}`;
});

let jitsiApi = null;
let hasNavigated = false;
let leaveFallbackTimer = null;
let conferenceJoinTimeout = null;
let resizeHandler = null;
let sessionTimer = null;
let recordingMode = null;

function updateSessionDuration() {
    const startedAt = Date.parse(props.startedAt || '');

    elapsedSeconds.value = Number.isNaN(startedAt)
        ? 0
        : Math.max(0, Math.floor((Date.now() - startedAt) / 1000));
}

function clearConferenceJoinTimeout() {
    if (conferenceJoinTimeout) {
        window.clearTimeout(conferenceJoinTimeout);
        conferenceJoinTimeout = null;
    }
}

function returnToSchedule() {
    if (hasNavigated) return;

    hasNavigated = true;
    router.visit(props.user.isTeacher
        ? route('teacher.live-sessions')
        : route('student.schedule'));
}

function externalApiUrl() {
    return `https://${props.jitsi.domain}/external_api.js`;
}

function loadExternalApi() {
    if (window.JitsiMeetExternalAPI) {
        return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
        const selector = `script[data-jitsi-domain="${props.jitsi.domain}"]`;
        let script = document.querySelector(selector);

        const onLoad = () => {
            script.dataset.loaded = 'true';
            window.JitsiMeetExternalAPI
                ? resolve()
                : reject(new Error('Jitsi external API was not available after loading.'));
        };
        const onError = () => reject(new Error('Could not load the Jitsi external API.'));

        if (script) {
            if (script.dataset.loaded === 'true') {
                onLoad();
                return;
            }

            script.addEventListener('load', onLoad, { once: true });
            script.addEventListener('error', onError, { once: true });
            return;
        }

        script = document.createElement('script');
        script.src = externalApiUrl();
        script.async = true;
        script.dataset.jitsiDomain = props.jitsi.domain;
        script.addEventListener('load', onLoad, { once: true });
        script.addEventListener('error', onError, { once: true });
        document.head.appendChild(script);
    });
}

function whiteboardConfig() {
    const config = {
        enabled: props.jitsi.whiteboard?.enabled === true,
        userLimit: Number(props.jitsi.whiteboard?.userLimit || 30),
    };

    if (props.jitsi.whiteboard?.collabServerBaseUrl) {
        config.collabServerBaseUrl = props.jitsi.whiteboard.collabServerBaseUrl;
    }

    return config;
}

function handleConferenceJoined() {
    clearConferenceJoinTimeout();
    isLoading.value = false;
    isJoined.value = true;

    if (props.user.isTeacher) {
        jitsiApi?.executeCommand('subject', props.session.title);
    }
}

function handleConferenceLeft() {
    clearConferenceJoinTimeout();
    isScreenSharing.value = false;
    isRecording.value = false;
    recordingMode = null;

    if (leaveFallbackTimer) {
        window.clearTimeout(leaveFallbackTimer);
        leaveFallbackTimer = null;
    }

    returnToSchedule();
}

function handleScreenSharingStatusChanged(event) {
    isScreenSharing.value = event?.on === true;
}

function handleRecordingStatusChanged(event) {
    if (event?.error) {
        toolNotice.value = 'تعذّر بدء التسجيل على خادم Jitsi الحالي.';
    }

    if (event?.mode === 'local' || recordingMode === 'local') {
        isRecording.value = event?.on === true;

        if (!isRecording.value) {
            recordingMode = null;
        }
    }
}

function commandSupported(command) {
    const commands = jitsiApi?.getSupportedCommands?.();

    return !Array.isArray(commands) || commands.includes(command);
}

function toggleScreenShare() {
    toolNotice.value = '';

    if (!jitsiApi || !isJoined.value) {
        toolNotice.value = 'جاري تجهيز غرفة Jitsi، جرّب مشاركة الشاشة بعد الاتصال.';
        return;
    }

    if (!commandSupported('toggleShareScreen')) {
        toolNotice.value = 'مشاركة الشاشة غير مدعومة في المتصفح أو خادم Jitsi الحالي.';
        return;
    }

    try {
        jitsiApi.executeCommand('toggleShareScreen');
    } catch (error) {
        console.error('Could not toggle screen sharing.', error);
        toolNotice.value = 'تعذّرت مشاركة الشاشة. اسمح للمتصفح بمشاركة الشاشة ثم حاول مرة أخرى.';
    }
}

function toggleRecording() {
    toolNotice.value = '';

    if (!jitsiApi || !isJoined.value) {
        toolNotice.value = 'جاري تجهيز غرفة Jitsi، جرّب التسجيل بعد الاتصال.';
        return;
    }

    try {
        if (isRecording.value) {
            jitsiApi.executeCommand('stopRecording', 'local', false);
            return;
        }

        if (!commandSupported('startRecording')) {
            toolNotice.value = 'التسجيل غير مدعوم في خادم Jitsi الحالي.';
            return;
        }

        recordingMode = 'local';
        jitsiApi.executeCommand('startRecording', {
            mode: 'local',
            onlySelf: false,
            shouldShare: false,
        });
        toolNotice.value = 'سيتم حفظ التسجيل محليًا على جهاز المدرس عند إيقافه.';
    } catch (error) {
        recordingMode = null;
        console.error('Could not toggle local recording.', error);
        toolNotice.value = 'تعذّر بدء التسجيل. قد لا يدعم خادم Jitsi التسجيل المحلي.';
    }
}

function openWhiteboard() {
    toolNotice.value = '';

    if (!jitsiApi) {
        toolNotice.value = 'جاري تجهيز غرفة Jitsi، جرّب مرة أخرى خلال لحظات.';
        return;
    }

    if (!props.jitsi.whiteboard?.enabled) {
        toolNotice.value = 'السبورة غير مفعّلة في إعدادات Jitsi الحالية.';
        return;
    }

    try {
        const commands = jitsiApi.getSupportedCommands?.();
        if (Array.isArray(commands) && !commands.includes('toggleWhiteboard')) {
            toolNotice.value = 'خادم Jitsi الحالي لا يدعم السبورة التفاعلية.';
            return;
        }

        jitsiApi.executeCommand('toggleWhiteboard');
    } catch (error) {
        console.error('Could not open the Jitsi whiteboard.', error);
        toolNotice.value = 'تعذّر فتح السبورة التفاعلية.';
    }
}

function leaveRoom() {
    if (!jitsiApi) {
        returnToSchedule();
        return;
    }

    if (isRecording.value) {
        jitsiApi.executeCommand('stopRecording', 'local', false);
    }

    jitsiApi.executeCommand('hangup');
    leaveFallbackTimer = window.setTimeout(() => {
        returnToSchedule();
    }, 1500);
}

function jitsiFrameHeight() {
    const containerHeight = jitsiContainer.value?.getBoundingClientRect().height || 0;

    return Math.max(320, Math.floor(containerHeight || window.innerHeight - 150));
}

function createMeeting() {
    const options = {
        roomName: props.roomName,
        parentNode: jitsiContainer.value,
        width: '100%',
        height: jitsiFrameHeight(),
        lang: 'ar',
        userInfo: {
            email: props.user.email,
            displayName: props.user.name,
        },
        configOverwrite: {
            prejoinConfig: { enabled: false },
            startWithAudioMuted: !props.user.isTeacher,
            startWithVideoMuted: !props.user.isTeacher,
            disableDeepLinking: true,
            disableInviteFunctions: true,
            doNotStoreRoom: true,
            useHostPageLocalStorage: true,
            localRecording: {
                disable: false,
                notifyAllParticipants: true,
            },
            recordings: {
                recordAudioAndVideo: true,
            },
            timeTimer: { enabled: false },
            whiteboard: whiteboardConfig(),
        },
        interfaceConfigOverwrite: {
            SHOW_JITSI_WATERMARK: false,
            SHOW_WATERMARK_FOR_GUESTS: false,
            SHOW_BRAND_WATERMARK: false,
            SHOW_POWERED_BY: false,
            SHOW_CHROME_EXTENSION_BANNER: false,
        },
    };

    if (props.jitsi.jwt) {
        options.jwt = props.jitsi.jwt;
    }

    jitsiApi = new window.JitsiMeetExternalAPI(props.jitsi.domain, options);
    jitsiApi.addListener('videoConferenceJoined', handleConferenceJoined);
    jitsiApi.addListener('videoConferenceLeft', handleConferenceLeft);
    jitsiApi.addListener('readyToClose', handleConferenceLeft);
    jitsiApi.addListener('screenSharingStatusChanged', handleScreenSharingStatusChanged);
    jitsiApi.addListener('recordingStatusChanged', handleRecordingStatusChanged);
    jitsiApi.addListener('errorOccurred', (event) => {
        console.error('Jitsi room error.', event);
        clearConferenceJoinTimeout();
        roomError.value = 'تعذّر الاتصال بغرفة Jitsi. تأكد من اتصالك بالإنترنت ثم أعد المحاولة.';
        isLoading.value = false;
    });

    resizeHandler = () => jitsiApi?.resizeHeight?.(jitsiFrameHeight());
    window.addEventListener('resize', resizeHandler);

    conferenceJoinTimeout = window.setTimeout(() => {
        if (isJoined.value || roomError.value) return;

        roomError.value = 'تعذر الاتصال بغرفة Jitsi خلال 30 ثانية. تحقق من الإنترنت ثم أعد المحاولة.';
        isLoading.value = false;
        conferenceJoinTimeout = null;
    }, 30000);
}

function retryRoom() {
    window.location.reload();
}

onMounted(async () => {
    updateSessionDuration();
    sessionTimer = window.setInterval(updateSessionDuration, 1000);

    try {
        await nextTick();
        await loadExternalApi();
        createMeeting();
    } catch (error) {
        console.error('Could not initialise Jitsi.', error);
        roomError.value = 'تعذّر تحميل Jitsi. تأكد من اتصالك بالإنترنت ثم أعد المحاولة.';
        isLoading.value = false;
    }
});

onBeforeUnmount(() => {
    clearConferenceJoinTimeout();

    if (sessionTimer) {
        window.clearInterval(sessionTimer);
        sessionTimer = null;
    }

    if (resizeHandler) {
        window.removeEventListener('resize', resizeHandler);
        resizeHandler = null;
    }

    if (leaveFallbackTimer) {
        window.clearTimeout(leaveFallbackTimer);
    }

    const api = jitsiApi;
    jitsiApi = null;
    api?.dispose();
});
</script>

<template>
    <div class="jitsi-room" dir="rtl">
        <Head :title="session.title" />

        <header class="jitsi-header">
            <div class="session-heading">
                <button type="button" class="back-button" @click="leaveRoom">
                    <span aria-hidden="true">→</span>
                    العودة
                </button>
                <span class="header-divider" aria-hidden="true"></span>
                <div>
                    <h1>{{ session.title }}</h1>
                    <p>{{ session.teaching_group?.name || 'حصة مباشرة خاصة' }}</p>
                </div>
            </div>

            <div class="header-actions">
                <template v-if="user.isTeacher">
                    <button
                        type="button"
                        class="classroom-tool-button"
                        :class="{ active: isScreenSharing }"
                        :disabled="!isJoined"
                        @click="toggleScreenShare"
                    >
                        <span aria-hidden="true">▣</span>
                        {{ isScreenSharing ? 'إيقاف مشاركة الشاشة' : 'مشاركة الشاشة / PDF' }}
                    </button>
                    <button
                        type="button"
                        class="classroom-tool-button recording-button"
                        :class="{ active: isRecording }"
                        :disabled="!isJoined"
                        @click="toggleRecording"
                    >
                        <span aria-hidden="true">●</span>
                        {{ isRecording ? 'إيقاف التسجيل' : 'تسجيل الحصة' }}
                    </button>
                </template>
                <div class="session-timer" :class="{ pending: !startedAt }" aria-live="polite">
                    <span class="session-timer-label">مدة الحصة</span>
                    <strong>{{ sessionDuration }}</strong>
                </div>
                <button
                    type="button"
                    class="whiteboard-button"
                    :disabled="!jitsi.whiteboard?.enabled"
                    @click="openWhiteboard"
                >
                    <span aria-hidden="true">✎</span>
                    السبورة التفاعلية
                </button>
                <span class="connection-status" :class="{ connected: isJoined }" aria-live="polite">
                    <span class="status-dot" aria-hidden="true"></span>
                    {{ isJoined ? 'متصل عبر Jitsi' : 'جاري الاتصال' }}
                </span>
            </div>
        </header>

        <main class="jitsi-stage">
            <div ref="jitsiContainer" class="jitsi-container"></div>

            <div v-if="isLoading && !roomError" class="room-overlay" role="status" aria-live="polite">
                <span class="loader" aria-hidden="true"></span>
                <p>جاري تجهيز غرفة Jitsi…</p>
            </div>

            <div v-if="roomError" class="room-overlay room-error" role="alert">
                <div class="error-icon" aria-hidden="true">!</div>
                <h2>تعذّر فتح الحصة</h2>
                <p>{{ roomError }}</p>
                <div class="error-actions">
                    <button type="button" class="retry-button" @click="retryRoom">إعادة المحاولة</button>
                    <button type="button" class="back-button secondary" @click="leaveRoom">العودة</button>
                </div>
            </div>

            <p v-if="toolNotice" class="tool-notice" role="status">{{ toolNotice }}</p>
        </main>

        <footer class="jitsi-footer">
            <span>يثبّت المعلم كشف حضور الحصة من لوحة الحصص.</span>
            <span>السبورة التفاعلية مشتركة بين المشاركين في الحصة.</span>
        </footer>
    </div>
</template>

<style scoped>
.jitsi-room {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    color: #f8fafc;
    background: #101728;
}

.jitsi-header {
    min-height: 72px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 12px 24px;
    background: #17213a;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.session-heading,
.header-actions {
    display: flex;
    align-items: center;
    gap: 14px;
}

.header-actions {
    flex-wrap: wrap;
}

.session-heading h1 {
    margin: 0;
    color: #fff;
    font-size: 16px;
    font-weight: 800;
}

.session-heading p {
    margin: 4px 0 0;
    color: #97b8ff;
    font-size: 12px;
}

.header-divider {
    width: 1px;
    height: 34px;
    background: rgba(255, 255, 255, 0.15);
}

.back-button,
.whiteboard-button,
.classroom-tool-button,
.retry-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 38px;
    padding: 0 13px;
    border: 1px solid transparent;
    border-radius: 10px;
    font: inherit;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: background 150ms ease, border-color 150ms ease, transform 150ms ease;
}

.back-button {
    color: #dbeafe;
    background: transparent;
    border-color: rgba(219, 234, 254, 0.2);
}

.back-button:hover,
.back-button:focus-visible {
    background: rgba(219, 234, 254, 0.1);
}

.back-button.secondary {
    color: #334155;
    border-color: #cbd5e1;
}

.whiteboard-button {
    color: #fff;
    background: #315fe9;
    box-shadow: 0 5px 16px rgba(49, 95, 233, 0.28);
}

.classroom-tool-button {
    color: #dbeafe;
    background: rgba(37, 81, 216, 0.2);
    border-color: rgba(147, 197, 253, 0.28);
}

.classroom-tool-button.active {
    color: #fff;
    background: #2563eb;
    border-color: #60a5fa;
}

.recording-button.active {
    background: #b91c1c;
    border-color: #fca5a5;
}

.whiteboard-button:hover:not(:disabled),
.whiteboard-button:focus-visible:not(:disabled),
.classroom-tool-button:hover:not(:disabled),
.classroom-tool-button:focus-visible:not(:disabled),
.retry-button:hover,
.retry-button:focus-visible {
    transform: translateY(-1px);
    background: #2551d8;
}

.whiteboard-button:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

.classroom-tool-button:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

.session-timer {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 38px;
    padding: 0 12px;
    color: #cbd5e1;
    background: rgba(15, 23, 42, 0.55);
    border: 1px solid rgba(147, 197, 253, 0.22);
    border-radius: 10px;
    font-size: 12px;
    white-space: nowrap;
}

.session-timer strong {
    color: #fff;
    font-size: 13px;
    font-variant-numeric: tabular-nums;
}

.session-timer.pending {
    color: #fcd34d;
}

.connection-status {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: #cbd5e1;
    font-size: 12px;
    white-space: nowrap;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #f59e0b;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15);
}

.connected .status-dot {
    background: #2dd4bf;
    box-shadow: 0 0 0 3px rgba(45, 212, 191, 0.15);
}

.jitsi-stage {
    position: relative;
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    padding: 16px;
}

.jitsi-container {
    display: flex;
    flex: 1 1 auto;
    width: 100%;
    height: auto;
    min-height: calc(100vh - 150px);
    overflow: hidden;
    border-radius: 14px;
    background: #0b1120;
    box-shadow: 0 20px 48px rgba(0, 0, 0, 0.25);
}

.jitsi-container :deep(iframe) {
    display: block !important;
    flex: 1 1 auto;
    width: 100% !important;
    min-height: 320px !important;
    border: 0 !important;
}

.room-overlay {
    position: absolute;
    inset: 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 14px;
    color: #cbd5e1;
    text-align: center;
    background: rgba(11, 17, 32, 0.96);
    border-radius: 14px;
}

.loader {
    width: 34px;
    height: 34px;
    border: 3px solid rgba(255, 255, 255, 0.18);
    border-top-color: #60a5fa;
    border-radius: 50%;
    animation: spin 700ms linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.room-error {
    color: #334155;
    background: #f8fafc;
}

.room-error h2,
.room-error p {
    margin: 0;
}

.room-error h2 {
    color: #0f172a;
    font-size: 20px;
}

.room-error p {
    max-width: 440px;
    line-height: 1.7;
}

.error-icon {
    display: grid;
    width: 40px;
    height: 40px;
    place-items: center;
    color: #fff;
    background: #ef4444;
    border-radius: 50%;
    font-size: 24px;
    font-weight: 900;
}

.error-actions {
    display: flex;
    gap: 10px;
    margin-top: 8px;
}

.retry-button {
    color: #fff;
    background: #315fe9;
}

.tool-notice {
    position: absolute;
    bottom: 30px;
    right: 30px;
    max-width: min(420px, calc(100% - 60px));
    margin: 0;
    padding: 10px 14px;
    color: #fef3c7;
    background: rgba(120, 53, 15, 0.96);
    border: 1px solid rgba(253, 230, 138, 0.35);
    border-radius: 10px;
    font-size: 12px;
}

.jitsi-footer {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 24px;
    color: #94a3b8;
    font-size: 11px;
    background: #121b2f;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

@media (max-width: 700px) {
    .jitsi-header {
        align-items: flex-start;
        flex-direction: column;
        padding: 12px 16px;
    }

    .header-actions {
        width: 100%;
        justify-content: space-between;
    }

    .jitsi-stage {
        padding: 8px;
    }

    .jitsi-container {
        min-height: calc(100vh - 208px);
        border-radius: 10px;
    }

    .room-overlay {
        inset: 8px;
        border-radius: 10px;
    }

    .jitsi-footer {
        flex-direction: column;
        padding: 9px 16px;
    }
}
</style>
