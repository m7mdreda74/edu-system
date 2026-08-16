<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';

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
const isRecordingLinkPending = ref(false);
const isWhiteboardOpen = ref(false);
const sessionStatus = ref(props.session.status);
const sessionStartedAt = ref(props.startedAt);
const isEndingSession = ref(false);

const whiteboardEnabled = computed(() => props.jitsi.whiteboard?.enabled === true
    && typeof props.jitsi.whiteboard?.collabServerBaseUrl === 'string'
    && props.jitsi.whiteboard.collabServerBaseUrl.trim() !== '');

const sessionDuration = computed(() => {
    if (!sessionStartedAt.value) {
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
let iframePermissionTimer = null;
let recordingMode = null;
let waitingForRecordingLink = false;
let recordingLinkTimeout = null;
let endAfterRecording = false;
let studentAttendanceJoined = false;
let studentAttendanceLeft = false;

function updateSessionDuration() {
    const startedAt = Date.parse(sessionStartedAt.value || '');

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
        enabled: whiteboardEnabled.value,
        userLimit: Number(props.jitsi.whiteboard?.userLimit || 30),
    };

    if (props.jitsi.whiteboard?.collabServerBaseUrl) {
        config.collabServerBaseUrl = props.jitsi.whiteboard.collabServerBaseUrl;
    }

    return config;
}

function configureJitsiFramePermissions() {
    const iframe = jitsiApi?.getIFrame?.() || jitsiContainer.value?.querySelector('iframe');

    if (!(iframe instanceof HTMLIFrameElement)) {
        return;
    }

    iframe.setAttribute(
        'allow',
        'autoplay; camera; microphone; display-capture; fullscreen; speaker-selection',
    );
    iframe.setAttribute('allowfullscreen', 'true');
}

function scheduleJitsiFramePermissions() {
    configureJitsiFramePermissions();

    if (iframePermissionTimer) {
        window.clearTimeout(iframePermissionTimer);
    }

    iframePermissionTimer = window.setTimeout(() => {
        configureJitsiFramePermissions();
        iframePermissionTimer = null;
    }, 250);
}

function isMediaPermissionError(event) {
    const details = typeof event === 'string' ? event : JSON.stringify(event || {});

    return /permission[_ ]denied|notallowed|gum\.permission_denied/i.test(details);
}

function handleMediaPermissionError(event) {
    if (!isMediaPermissionError(event)) {
        toolNotice.value = 'تعذّر الوصول إلى الكاميرا أو الميكروفون. راجع إعدادات الأجهزة ثم حاول مرة أخرى.';
        return;
    }

    toolNotice.value = 'المتصفح مانع الكاميرا والميكروفون. اضغط أيقونة القفل أو الكاميرا بجوار عنوان الموقع، اختر السماح، ثم أعد تحميل الصفحة.';
}

async function startSessionFromRoom() {
    if (!props.user.isTeacher || sessionStatus.value !== 'scheduled') {
        return;
    }

    try {
        const response = await axios.post(route('teacher.live-sessions.start', props.session.id));
        sessionStatus.value = response.data?.status || 'live';
        sessionStartedAt.value = response.data?.started_at || new Date().toISOString();
        updateSessionDuration();
        toolNotice.value = 'تم بدء الحصة. يمكن للطلاب الدخول الآن.';
    } catch (error) {
        console.error('Could not start the live session from the room.', error);
        toolNotice.value = error.response?.data?.message
            || 'تعذّر بدء الحصة من الخادم. سيظل دخول الطلاب مقفولًا حتى تبدأها.';
    }
}

async function recordStudentJoin() {
    if (props.user.isTeacher || studentAttendanceJoined) {
        return;
    }

    try {
        await axios.post(route('live-sessions.attendance.join', props.session.id));
        studentAttendanceJoined = true;
    } catch (error) {
        console.error('Could not record student attendance join.', error);
        toolNotice.value = error.response?.data?.message
            || 'تعذّر تسجيل دخولك للحصة. سيظل الدخول مفتوحًا، لكن أبلغ ولي أمرك إذا استمر التنبيه.';
    }
}

async function recordStudentLeave() {
    if (props.user.isTeacher || ! studentAttendanceJoined || studentAttendanceLeft) {
        return;
    }

    studentAttendanceLeft = true;

    try {
        await axios.post(route('live-sessions.attendance.leave', props.session.id));
    } catch (error) {
        console.error('Could not record student attendance leave.', error);
    }
}

async function handleConferenceJoined() {
    clearConferenceJoinTimeout();
    isLoading.value = false;
    isJoined.value = true;

    if (props.user.isTeacher) {
        jitsiApi?.executeCommand('subject', props.session.title);
        await startSessionFromRoom();
    } else {
        await recordStudentJoin();
    }
}

function clearRecordingLinkTimeout() {
    if (recordingLinkTimeout) {
        window.clearTimeout(recordingLinkTimeout);
        recordingLinkTimeout = null;
    }
}

async function handleConferenceLeft() {
    if (!props.user.isTeacher && isJoined.value) {
        await recordStudentLeave();
    }

    clearConferenceJoinTimeout();
    isScreenSharing.value = false;
    isRecording.value = false;
    isRecordingLinkPending.value = false;
    isWhiteboardOpen.value = false;
    waitingForRecordingLink = false;
    recordingMode = null;
    isJoined.value = false;

    clearRecordingLinkTimeout();

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
        isRecordingLinkPending.value = false;
        recordingMode = null;
    }

    if (event?.mode === 'file' || recordingMode === 'file') {
        isRecording.value = event?.on === true;

        if (!isRecording.value) {
            isRecordingLinkPending.value = !event?.error;
        }
    }
}

async function handleRecordingLinkAvailable(event) {
    if (!props.user.isTeacher || typeof event?.link !== 'string' || event.link.trim() === '') {
        return;
    }

    isRecordingLinkPending.value = true;
    toolNotice.value = 'جاري حفظ رابط التسجيل تلقائيًا...';

    try {
        const response = await axios.post(route('teacher.live-sessions.recording', props.session.id), {
            recording_url: event.link.trim(),
        });

        recordingMode = null;
        isRecordingLinkPending.value = false;
        toolNotice.value = response.data?.published
            ? 'تم حفظ التسجيل ونشره للطلاب داخل المنصة.'
            : 'تم حفظ رابط التسجيل تلقائيًا.';
    } catch (error) {
        console.error('Could not save the Jitsi recording link.', error);
        recordingMode = null;
        isRecordingLinkPending.value = false;
        toolNotice.value = error.response?.data?.message
            || 'تعذّر حفظ رابط التسجيل تلقائيًا. راجع إعدادات خدمة تسجيل Jitsi.';
    } finally {
        if (waitingForRecordingLink) {
            const shouldEndSession = endAfterRecording;
            endAfterRecording = false;
            waitingForRecordingLink = false;
            clearRecordingLinkTimeout();

            if (shouldEndSession) {
                const ended = await endSessionOnServer();

                if (ended) {
                    finishLeavingRoom();
                }
            } else {
                finishLeavingRoom();
            }
        }
    }
}

function handleWhiteboardStatusChanged(event) {
    const status = String(event?.status || '').toLowerCase();

    isWhiteboardOpen.value = status.includes('open') || status.includes('visible');

    if (status.includes('error') || status.includes('fail') || status.includes('unavailable')) {
        toolNotice.value = 'تعذّر تشغيل السبورة. تأكد من إعداد خادم التعاون الخاص بـ Jitsi.';
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

    if (props.jitsi.recording?.enabled !== true) {
        toolNotice.value = 'التسجيل السحابي غير مفعّل على خادم Jitsi الحالي.';
        return;
    }

    if (!jitsiApi || !isJoined.value) {
        toolNotice.value = 'جاري تجهيز غرفة Jitsi، جرّب التسجيل بعد الاتصال.';
        return;
    }

    if (isRecordingLinkPending.value) {
        toolNotice.value = 'جاري تجهيز رابط التسجيل، انتظر لحظات.';
        return;
    }

    if (recordingMode === 'file' && !isRecording.value) {
        toolNotice.value = 'جاري تجهيز التسجيل، انتظر لحظات.';
        return;
    }

    try {
        if (isRecording.value) {
            jitsiApi.executeCommand('stopRecording', recordingMode || 'file', false);
            return;
        }

        if (!commandSupported('startRecording')) {
            toolNotice.value = 'التسجيل غير مدعوم في خادم Jitsi الحالي.';
            return;
        }

        recordingMode = props.jitsi.recording?.mode || 'file';
        jitsiApi.executeCommand('startRecording', {
            mode: recordingMode,
            onlySelf: false,
            shouldShare: false,
        });
        toolNotice.value = 'سيتم تجهيز رابط التسجيل تلقائيًا عند إيقافه.';
    } catch (error) {
        recordingMode = null;
        console.error('Could not toggle server recording.', error);
        toolNotice.value = 'تعذّر بدء التسجيل السحابي. تأكد من تشغيل خدمة التسجيل على Jitsi.';
    }
}

function openWhiteboard() {
    toolNotice.value = '';

    if (!isJoined.value) {
        toolNotice.value = 'السبورة ستكون متاحة بعد الاتصال بالجلسة.';
        return;
    }

    if (!jitsiApi) {
        toolNotice.value = 'جاري تجهيز غرفة Jitsi، جرّب مرة أخرى خلال لحظات.';
        return;
    }

    if (!whiteboardEnabled.value) {
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

async function endSessionOnServer() {
    try {
        const response = await axios.post(route('teacher.live-sessions.end', props.session.id));
        sessionStatus.value = response.data?.status || 'ended';
        isEndingSession.value = false;
        return true;
    } catch (error) {
        console.error('Could not end the live session from the room.', error);
        isEndingSession.value = false;
        toolNotice.value = error.response?.data?.message
            || 'تعذّر إنهاء الحصة من الخادم. حاول مرة أخرى.';
        return false;
    }
}

function waitForRecordingThenEnd() {
    endAfterRecording = true;
    waitingForRecordingLink = true;
    clearRecordingLinkTimeout();

    if (isRecording.value) {
        try {
            jitsiApi?.executeCommand('stopRecording', recordingMode || 'file', false);
        } catch (error) {
            console.error('Could not stop the Jitsi recording before ending.', error);
        }
    }

    recordingLinkTimeout = window.setTimeout(async () => {
        recordingLinkTimeout = null;

        if (!endAfterRecording) {
            return;
        }

        endAfterRecording = false;
        waitingForRecordingLink = false;
        isRecordingLinkPending.value = false;

        const ended = await endSessionOnServer();

        if (ended) {
            finishLeavingRoom();
        }
    }, 30000);
}

async function endSession() {
    if (!props.user.isTeacher || sessionStatus.value !== 'live' || isEndingSession.value) {
        return;
    }

    if (!window.confirm('هل تريد إنهاء الحصة لجميع الطلاب؟')) {
        return;
    }

    isEndingSession.value = true;
    toolNotice.value = 'جاري إنهاء الحصة...';

    const hasServerRecording = props.jitsi.recording?.enabled === true
        && (recordingMode === 'file' || isRecording.value || isRecordingLinkPending.value);

    if (jitsiApi && hasServerRecording) {
        waitForRecordingThenEnd();
        return;
    }

    const ended = await endSessionOnServer();

    if (ended) {
        finishLeavingRoom();
    }
}

function finishLeavingRoom() {
    waitingForRecordingLink = false;
    endAfterRecording = false;
    clearRecordingLinkTimeout();

    if (!jitsiApi) {
        returnToSchedule();
        return;
    }

    jitsiApi.executeCommand('hangup');
    leaveFallbackTimer = window.setTimeout(() => {
        returnToSchedule();
    }, 1500);
}

function leaveRoom() {
    if (!jitsiApi) {
        returnToSchedule();
        return;
    }

    if (recordingMode === 'file') {
        waitingForRecordingLink = true;

        if (isRecording.value) {
            jitsiApi.executeCommand('stopRecording', 'file', false);
        }

        recordingLinkTimeout = window.setTimeout(() => {
            toolNotice.value = 'انتهى وقت انتظار رابط التسجيل؛ سيتم إغلاق الحصة الآن.';
            finishLeavingRoom();
        }, 30000);
        return;
    }

    finishLeavingRoom();
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
            fileRecordingsEnabled: props.jitsi.recording?.enabled === true,
            fileRecordingsServiceEnabled: props.jitsi.recording?.enabled === true,
            fileRecordingsServiceSharingEnabled: false,
            localRecording: {
                disable: true,
                notifyAllParticipants: true,
            },
            recordings: {
                recordAudioAndVideo: true,
                showRecordingLink: true,
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
    scheduleJitsiFramePermissions();
    jitsiApi.addListener('videoConferenceJoined', handleConferenceJoined);
    jitsiApi.addListener('videoConferenceLeft', handleConferenceLeft);
    jitsiApi.addListener('readyToClose', handleConferenceLeft);
    jitsiApi.addListener('screenSharingStatusChanged', handleScreenSharingStatusChanged);
    jitsiApi.addListener('recordingStatusChanged', handleRecordingStatusChanged);
    jitsiApi.addListener('recordingLinkAvailable', handleRecordingLinkAvailable);
    jitsiApi.addListener('whiteboardStatusChanged', handleWhiteboardStatusChanged);
    jitsiApi.addListener('cameraError', handleMediaPermissionError);
    jitsiApi.addListener('micError', handleMediaPermissionError);
    jitsiApi.addListener('errorOccurred', (event) => {
        console.error('Jitsi room error.', event);
        clearConferenceJoinTimeout();

        if (isMediaPermissionError(event)) {
            handleMediaPermissionError(event);
            isLoading.value = false;
            return;
        }

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

    if (iframePermissionTimer) {
        window.clearTimeout(iframePermissionTimer);
        iframePermissionTimer = null;
    }

    const api = jitsiApi;
    jitsiApi = null;
    clearRecordingLinkTimeout();
    api?.removeListener?.('recordingLinkAvailable', handleRecordingLinkAvailable);
    api?.removeListener?.('whiteboardStatusChanged', handleWhiteboardStatusChanged);
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
                        :disabled="!isJoined || isRecordingLinkPending"
                        @click="toggleRecording"
                    >
                        <span aria-hidden="true">●</span>
                        {{ isRecordingLinkPending ? 'جاري تجهيز الرابط...' : (isRecording ? 'إيقاف التسجيل' : 'تسجيل الحصة') }}
                    </button>
                    <button
                        v-if="sessionStatus === 'live'"
                        type="button"
                        class="classroom-tool-button end-session-button"
                        :disabled="!isJoined || isEndingSession"
                        @click="endSession"
                    >
                        <span aria-hidden="true">■</span>
                        {{ isEndingSession ? 'جاري إنهاء الحصة...' : 'إنهاء الحصة' }}
                    </button>
                </template>
                <div class="session-timer" :class="{ pending: !sessionStartedAt }" aria-live="polite">
                    <span class="session-timer-label">مدة الحصة</span>
                    <strong>{{ sessionDuration }}</strong>
                </div>
                <button
                    type="button"
                    class="whiteboard-button"
                    :aria-pressed="isWhiteboardOpen"
                    :disabled="!isJoined || !whiteboardEnabled"
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

.end-session-button {
    color: #fee2e2;
    background: rgba(185, 28, 28, 0.28);
    border-color: rgba(252, 165, 165, 0.45);
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

.end-session-button:hover:not(:disabled),
.end-session-button:focus-visible:not(:disabled) {
    background: #b91c1c;
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
