<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    session: { type: Object, required: true },
    roomName: { type: String, required: true },
    user: { type: Object, required: true },
    jitsi: { type: Object, required: true },
});

const jitsiContainer = ref(null);
const isLoading = ref(true);
const isJoined = ref(false);
const roomError = ref('');
const whiteboardNotice = ref('');

let jitsiApi = null;
let hasNavigated = false;
let leaveFallbackTimer = null;
let conferenceJoinTimeout = null;

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

    if (leaveFallbackTimer) {
        window.clearTimeout(leaveFallbackTimer);
        leaveFallbackTimer = null;
    }

    returnToSchedule();
}

function openWhiteboard() {
    whiteboardNotice.value = '';

    if (!jitsiApi) {
        whiteboardNotice.value = 'جاري تجهيز غرفة Jitsi، جرّب مرة أخرى خلال لحظات.';
        return;
    }

    if (!props.jitsi.whiteboard?.enabled) {
        whiteboardNotice.value = 'السبورة غير مفعّلة في إعدادات Jitsi الحالية.';
        return;
    }

    try {
        const commands = jitsiApi.getSupportedCommands?.();
        if (Array.isArray(commands) && !commands.includes('toggleWhiteboard')) {
            whiteboardNotice.value = 'خادم Jitsi الحالي لا يدعم السبورة التفاعلية.';
            return;
        }

        jitsiApi.executeCommand('toggleWhiteboard');
    } catch (error) {
        console.error('Could not open the Jitsi whiteboard.', error);
        whiteboardNotice.value = 'تعذّر فتح السبورة التفاعلية.';
    }
}

function leaveRoom() {
    if (!jitsiApi) {
        returnToSchedule();
        return;
    }

    jitsiApi.executeCommand('hangup');
    leaveFallbackTimer = window.setTimeout(() => {
        returnToSchedule();
    }, 1500);
}

function createMeeting() {
    const options = {
        roomName: props.roomName,
        parentNode: jitsiContainer.value,
        width: '100%',
        height: '100%',
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
            whiteboard: whiteboardConfig(),
        },
    };

    if (props.jitsi.jwt) {
        options.jwt = props.jitsi.jwt;
    }

    jitsiApi = new window.JitsiMeetExternalAPI(props.jitsi.domain, options);
    jitsiApi.addListener('videoConferenceJoined', handleConferenceJoined);
    jitsiApi.addListener('videoConferenceLeft', handleConferenceLeft);
    jitsiApi.addListener('readyToClose', handleConferenceLeft);
    jitsiApi.addListener('errorOccurred', (event) => {
        console.error('Jitsi room error.', event);
        clearConferenceJoinTimeout();
        roomError.value = 'تعذّر الاتصال بغرفة Jitsi. تأكد من اتصالك بالإنترنت ثم أعد المحاولة.';
        isLoading.value = false;
    });
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

            <p v-if="whiteboardNotice" class="whiteboard-notice" role="status">{{ whiteboardNotice }}</p>
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

.whiteboard-button:hover:not(:disabled),
.whiteboard-button:focus-visible:not(:disabled),
.retry-button:hover,
.retry-button:focus-visible {
    transform: translateY(-1px);
    background: #2551d8;
}

.whiteboard-button:disabled {
    cursor: not-allowed;
    opacity: 0.5;
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
    height: 100% !important;
    min-height: 100% !important;
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

.whiteboard-notice {
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
