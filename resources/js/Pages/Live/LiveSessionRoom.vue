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
const isBrowserRecording = ref(false);
const browserRecordingSeconds = ref(0);
const showRecordingSavedModal = ref(false);
const savedRecordingFilename = ref('');
const savedRecordingBlobUrl = ref('');
const postRecordingUrl = ref('');
const isSavingPostRecording = ref(false);
const postRecordingNotice = ref('');

const browserRecordingTimeFormatted = computed(() => {
    const total = browserRecordingSeconds.value;
    const m = Math.floor(total / 60).toString().padStart(2, '0');
    const s = (total % 60).toString().padStart(2, '0');
    const h = Math.floor(total / 3600);
    return h > 0 ? `${h}:${m}:${s}` : `${m}:${s}`;
});
const isWhiteboardOpen = ref(false);
const isWhiteboardFullscreen = ref(false);
const whiteboardBg = ref('dark');
const currentTool = ref('pen');
const currentColor = ref('#38bdf8');
const currentLineWidth = ref(4);
const whiteboardCanvas = ref(null);
const whiteboardContainer = ref(null);
const textInputElem = ref(null);
const textInputState = ref({
    visible: false,
    x: 0,
    y: 0,
    text: '',
});
const strokes = ref([]);
const redoStack = ref([]);
const isDrawing = ref(false);
let activeStroke = null;
let canvasCtx = null;
const sessionStatus = ref(props.session.status);
const sessionStartedAt = ref(props.startedAt);
const isEndingSession = ref(false);

const whiteboardEnabled = computed(() => true);

const autoStartRecording = computed(() => props.jitsi.recording?.auto_start === true);

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
let automaticRecordingAttempted = false;
let waitingForRecordingLink = false;
let recordingLinkTimeout = null;
let endAfterRecording = false;
let studentAttendanceJoined = false;
let studentAttendanceLeft = false;
let attendanceHeartbeatTimer = null;

function startAttendanceHeartbeat() {
    stopAttendanceHeartbeat();
    attendanceHeartbeatTimer = window.setInterval(async () => {
        if (!props.user.isTeacher && isJoined.value && studentAttendanceJoined && !studentAttendanceLeft) {
            try {
                await axios.post(route('live-sessions.attendance.heartbeat', props.session.id));
            } catch (error) {
                // Heartbeat fails silently so student experience is uninterrupted
            }
        }
    }, 45000);
}

function stopAttendanceHeartbeat() {
    if (attendanceHeartbeatTimer) {
        window.clearInterval(attendanceHeartbeatTimer);
        attendanceHeartbeatTimer = null;
    }
}

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
        startAttendanceHeartbeat();
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
    stopAttendanceHeartbeat();

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

        if (sessionStatus.value === 'live' && autoStartRecording.value) {
            await startServerRecording(true);
        }
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
    stopAttendanceHeartbeat();

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
    // Intentionally no-op: built-in canvas whiteboard is self-contained and does not rely on 3rd-party Jitsi backend
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

function startServerRecording(automatic = false) {
    if (automatic && automaticRecordingAttempted) {
        return false;
    }

    if (props.jitsi.recording?.enabled !== true) {
        toolNotice.value = 'التسجيل السحابي غير مفعّل على خادم Jitsi الحالي.';
        return false;
    }

    if (!jitsiApi || !isJoined.value) {
        toolNotice.value = 'جاري تجهيز غرفة Jitsi، جرّب التسجيل بعد الاتصال.';
        return false;
    }

    if (isRecordingLinkPending.value) {
        toolNotice.value = 'جاري تجهيز رابط التسجيل، انتظر لحظات.';
        return false;
    }

    if (recordingMode === 'file' && !isRecording.value) {
        toolNotice.value = 'جاري تجهيز التسجيل، انتظر لحظات.';
        return false;
    }

    if (isRecording.value) {
        return true;
    }

    if (!commandSupported('startRecording')) {
        toolNotice.value = 'التسجيل غير مدعوم في خادم Jitsi الحالي.';
        return false;
    }

    if (automatic) {
        automaticRecordingAttempted = true;
    }

    try {
        recordingMode = props.jitsi.recording?.mode || 'file';
        jitsiApi.executeCommand('startRecording', {
            mode: recordingMode,
            onlySelf: false,
            shouldShare: false,
        });
        toolNotice.value = automatic
            ? 'بدأ تسجيل الحصة المجانية تلقائيًا، وسيُنشر بعد انتهائها.'
            : 'سيتم تجهيز رابط التسجيل تلقائيًا عند إيقافه.';
        return true;
    } catch (error) {
        recordingMode = null;
        console.error('Could not toggle server recording.', error);
        toolNotice.value = 'تعذّر بدء التسجيل السحابي. تأكد من تشغيل خدمة التسجيل على Jitsi.';
        return false;
    }
}

let localMediaRecorder = null;
let localRecordedChunks = [];
let localRecordingTimer = null;

async function startBrowserRecording() {
    if (!navigator.mediaDevices?.getDisplayMedia) {
        toolNotice.value = 'متصفحك لا يدعم تسجيل الشاشة المباشر. استخدم Chrome أو Edge أو Firefox.';
        return;
    }

    try {
        toolNotice.value = 'يرجى اختيار شاشة أو نافذة الحصة وتفعيل مشاركة الصوت لبدء التسجيل...';

        const displayStream = await navigator.mediaDevices.getDisplayMedia({
            video: {
                displaySurface: 'browser',
                frameRate: { ideal: 30, max: 60 },
            },
            audio: true,
        });

        // Mix teacher's mic audio if available
        let mixedStream = displayStream;
        let micStream = null;
        try {
            micStream = await navigator.mediaDevices.getUserMedia({
                audio: { echoCancellation: true, noiseSuppression: true },
            });
        } catch (e) {
            console.warn('Microphone stream could not be captured, recording display audio only', e);
        }

        if (micStream && micStream.getAudioTracks().length > 0) {
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (AudioCtx) {
                    const audioCtx = new AudioCtx();
                    const dest = audioCtx.createMediaStreamDestination();

                    if (displayStream.getAudioTracks().length > 0) {
                        const displaySource = audioCtx.createMediaStreamSource(displayStream);
                        displaySource.connect(dest);
                    }
                    const micSource = audioCtx.createMediaStreamSource(micStream);
                    micSource.connect(dest);

                    mixedStream = new MediaStream([
                        ...displayStream.getVideoTracks(),
                        ...dest.stream.getAudioTracks(),
                    ]);
                }
            } catch (mixErr) {
                console.warn('Audio mixing failed, using display audio', mixErr);
                mixedStream = displayStream;
            }
        }

        const candidateMimes = [
            'video/webm;codecs=vp9,opus',
            'video/webm;codecs=vp8,opus',
            'video/webm',
            'video/mp4',
        ];
        const selectedMime = candidateMimes.find((mime) => MediaRecorder.isTypeSupported(mime)) || '';

        localRecordedChunks = [];
        localMediaRecorder = new MediaRecorder(mixedStream, selectedMime ? { mimeType: selectedMime } : {});

        localMediaRecorder.ondataavailable = (event) => {
            if (event.data && event.data.size > 0) {
                localRecordedChunks.push(event.data);
            }
        };

        localMediaRecorder.onstop = () => {
            // Stop all tracks
            displayStream.getTracks().forEach((track) => track.stop());
            micStream?.getTracks().forEach((track) => track.stop());

            if (localRecordingTimer) {
                window.clearInterval(localRecordingTimer);
                localRecordingTimer = null;
            }
            isBrowserRecording.value = false;

            if (localRecordedChunks.length > 0) {
                const blob = new Blob(localRecordedChunks, { type: selectedMime || 'video/webm' });
                const blobUrl = URL.createObjectURL(blob);
                const safeTitle = (props.session.title || 'حصة').replace(/[\s/\\?%*:|"<>]+/g, '_');
                const dateStr = new Date().toISOString().slice(0, 10);
                const ext = selectedMime.includes('mp4') ? 'mp4' : 'webm';
                const filename = `تسجيل_${safeTitle}_${dateStr}.${ext}`;

                savedRecordingBlobUrl.value = blobUrl;
                savedRecordingFilename.value = filename;

                // Automatically trigger download to teacher's computer
                const downloadAnchor = document.createElement('a');
                downloadAnchor.href = blobUrl;
                downloadAnchor.download = filename;
                document.body.appendChild(downloadAnchor);
                downloadAnchor.click();
                window.setTimeout(() => {
                    document.body.removeChild(downloadAnchor);
                }, 1000);

                showRecordingSavedModal.value = true;
                toolNotice.value = `تم حفظ ملف تسجيل الحصة (${filename}) بنجاح في مجلد التنزيلات (Downloads).`;
            }
        };

        displayStream.getVideoTracks()[0].onended = () => {
            if (isBrowserRecording.value) {
                stopBrowserRecording();
            }
        };

        localMediaRecorder.start(1000);
        isBrowserRecording.value = true;
        browserRecordingSeconds.value = 0;
        localRecordingTimer = window.setInterval(() => {
            browserRecordingSeconds.value++;
        }, 1000);

        toolNotice.value = 'بدأ تسجيل الحصة الآن! سيتم حفظ الفيديو تلقائياً على جهازك في مجلد التنزيلات عند الإيقاف.';
    } catch (err) {
        console.error('Failed to start browser screen recording:', err);
        if (err.name === 'NotAllowedError') {
            toolNotice.value = 'تم إلغاء مشاركة الشاشة للتسجيل.';
        } else {
            toolNotice.value = 'تعذّر بدء تسجيل الشاشة: ' + (err.message || 'خطأ غير معروف');
        }
    }
}

function stopBrowserRecording() {
    if (localMediaRecorder && localMediaRecorder.state !== 'inactive') {
        try {
            localMediaRecorder.stop();
        } catch (e) {
            console.error('Error stopping local media recorder:', e);
        }
    }
}

async function toggleRecording() {
    toolNotice.value = '';

    if (isBrowserRecording.value) {
        stopBrowserRecording();
        return;
    }

    if (isRecording.value) {
        try {
            jitsiApi?.executeCommand('stopRecording', recordingMode || 'file', false);
        } catch (error) {
            console.error('Could not stop server recording.', error);
        }
        isRecording.value = false;
        return;
    }

    await startBrowserRecording();
}

async function submitPostRecordingUrl() {
    if (!postRecordingUrl.value.trim()) return;

    isSavingPostRecording.value = true;
    postRecordingNotice.value = '';

    try {
        await axios.patch(route('teacher.live-sessions.status', props.session.id), {
            status: 'ended',
            recording_url: postRecordingUrl.value.trim(),
        });
        postRecordingNotice.value = 'تم حفظ ونشر رابط التسجيل لطلاب الحصة بنجاح!';
        window.setTimeout(() => {
            showRecordingSavedModal.value = false;
        }, 2000);
    } catch (error) {
        postRecordingNotice.value = error.response?.data?.message || 'تعذّر حفظ الرابط. تأكد أنه رابط YouTube صالح.';
    } finally {
        isSavingPostRecording.value = false;
    }
}

function initWhiteboardCanvas() {
    if (!whiteboardCanvas.value || !whiteboardContainer.value) return;
    const canvas = whiteboardCanvas.value;
    const container = whiteboardContainer.value;
    const rect = container.getBoundingClientRect();
    if (rect.width <= 0 || rect.height <= 0) return;

    const dpr = window.devicePixelRatio || 1;
    canvas.width = rect.width * dpr;
    canvas.height = rect.height * dpr;
    canvas.style.width = `${rect.width}px`;
    canvas.style.height = `${rect.height}px`;

    canvasCtx = canvas.getContext('2d');
    canvasCtx.scale(dpr, dpr);
    redrawWhiteboard();
}

function redrawWhiteboard() {
    if (!canvasCtx || !whiteboardCanvas.value || !whiteboardContainer.value) return;
    const canvas = whiteboardCanvas.value;
    const container = whiteboardContainer.value;
    const rect = container.getBoundingClientRect();
    const width = rect.width;
    const height = rect.height;
    if (width <= 0 || height <= 0) return;

    canvasCtx.save();
    canvasCtx.setTransform(1, 0, 0, 1, 0, 0);
    const dpr = window.devicePixelRatio || 1;
    canvasCtx.scale(dpr, dpr);

    if (whiteboardBg.value === 'white') {
        canvasCtx.fillStyle = '#ffffff';
        canvasCtx.fillRect(0, 0, width, height);
    } else if (whiteboardBg.value === 'grid') {
        canvasCtx.fillStyle = '#0f172a';
        canvasCtx.fillRect(0, 0, width, height);
        canvasCtx.strokeStyle = 'rgba(255, 255, 255, 0.08)';
        canvasCtx.lineWidth = 1;
        const gridSize = 28;
        canvasCtx.beginPath();
        for (let x = 0; x < width; x += gridSize) {
            canvasCtx.moveTo(x, 0);
            canvasCtx.lineTo(x, height);
        }
        for (let y = 0; y < height; y += gridSize) {
            canvasCtx.moveTo(0, y);
            canvasCtx.lineTo(width, y);
        }
        canvasCtx.stroke();
    } else {
        canvasCtx.fillStyle = '#0f172a';
        canvasCtx.fillRect(0, 0, width, height);
    }

    for (const stroke of strokes.value) {
        drawSingleStroke(canvasCtx, stroke);
    }

    canvasCtx.restore();
}

function drawSingleStroke(ctx, stroke) {
    if (!stroke) return;
    ctx.save();
    ctx.strokeStyle = stroke.color || '#38bdf8';
    ctx.fillStyle = stroke.color || '#38bdf8';
    ctx.lineWidth = stroke.width || 3;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    if (stroke.tool === 'highlighter') {
        ctx.globalAlpha = 0.35;
        ctx.lineWidth = (stroke.width || 4) * 3;
    } else if (stroke.tool === 'eraser') {
        ctx.strokeStyle = whiteboardBg.value === 'white' ? '#ffffff' : '#0f172a';
        ctx.lineWidth = (stroke.width || 4) * 4;
    }

    if (stroke.type === 'path' && Array.isArray(stroke.points) && stroke.points.length > 0) {
        ctx.beginPath();
        ctx.moveTo(stroke.points[0].x, stroke.points[0].y);
        for (let i = 1; i < stroke.points.length; i++) {
            ctx.lineTo(stroke.points[i].x, stroke.points[i].y);
        }
        ctx.stroke();
    } else if (stroke.type === 'rect') {
        ctx.strokeRect(stroke.x, stroke.y, stroke.w, stroke.h);
    } else if (stroke.type === 'circle') {
        ctx.beginPath();
        const rx = Math.abs(stroke.w) / 2;
        const ry = Math.abs(stroke.h) / 2;
        const cx = stroke.x + stroke.w / 2;
        const cy = stroke.y + stroke.h / 2;
        ctx.ellipse(cx, cy, Math.max(1, rx), Math.max(1, ry), 0, 0, Math.PI * 2);
        ctx.stroke();
    } else if (stroke.type === 'line') {
        ctx.beginPath();
        ctx.moveTo(stroke.x1, stroke.y1);
        ctx.lineTo(stroke.x2, stroke.y2);
        ctx.stroke();
    } else if (stroke.type === 'arrow') {
        drawArrowShape(ctx, stroke.x1, stroke.y1, stroke.x2, stroke.y2, stroke.width || 3);
    } else if (stroke.type === 'text') {
        ctx.font = `${Math.max(16, stroke.size || 20)}px sans-serif`;
        ctx.fillText(stroke.text, stroke.x, stroke.y);
    }

    ctx.restore();
}

function drawArrowShape(ctx, fromX, fromY, toX, toY, width) {
    const headLen = Math.max(12, width * 3);
    const angle = Math.atan2(toY - fromY, toX - fromX);

    ctx.beginPath();
    ctx.moveTo(fromX, fromY);
    ctx.lineTo(toX, toY);
    ctx.stroke();

    ctx.beginPath();
    ctx.moveTo(toX, toY);
    ctx.lineTo(toX - headLen * Math.cos(angle - Math.PI / 6), toY - headLen * Math.sin(angle - Math.PI / 6));
    ctx.lineTo(toX - headLen * Math.cos(angle + Math.PI / 6), toY - headLen * Math.sin(angle + Math.PI / 6));
    ctx.closePath();
    ctx.fill();
}

function getCanvasCoords(e) {
    if (!whiteboardCanvas.value) return { x: 0, y: 0 };
    const rect = whiteboardCanvas.value.getBoundingClientRect();
    const clientX = e.touches?.[0]?.clientX ?? e.clientX;
    const clientY = e.touches?.[0]?.clientY ?? e.clientY;
    return {
        x: clientX - rect.left,
        y: clientY - rect.top,
    };
}

function onBoardMouseDown(e) {
    if (!props.user.isTeacher) return;
    if (textInputState.value.visible) {
        commitTextInput();
    }
    const coords = getCanvasCoords(e);

    if (currentTool.value === 'text') {
        textInputState.value = {
            visible: true,
            x: coords.x,
            y: coords.y,
            text: '',
        };
        nextTick(() => textInputElem.value?.focus());
        return;
    }

    isDrawing.value = true;
    redoStack.value = [];

    if (['pen', 'highlighter', 'eraser'].includes(currentTool.value)) {
        activeStroke = {
            type: 'path',
            tool: currentTool.value,
            color: currentColor.value,
            width: currentLineWidth.value,
            points: [coords],
        };
    } else if (['rect', 'circle'].includes(currentTool.value)) {
        activeStroke = {
            type: currentTool.value,
            tool: currentTool.value,
            color: currentColor.value,
            width: currentLineWidth.value,
            startX: coords.x,
            startY: coords.y,
            x: coords.x,
            y: coords.y,
            w: 0,
            h: 0,
        };
    } else if (['line', 'arrow'].includes(currentTool.value)) {
        activeStroke = {
            type: currentTool.value,
            tool: currentTool.value,
            color: currentColor.value,
            width: currentLineWidth.value,
            x1: coords.x,
            y1: coords.y,
            x2: coords.x,
            y2: coords.y,
        };
    }
}

function onBoardMouseMove(e) {
    if (!isDrawing.value || !activeStroke) return;
    const coords = getCanvasCoords(e);

    if (activeStroke.type === 'path') {
        activeStroke.points.push(coords);
        redrawWhiteboard();
        drawSingleStroke(canvasCtx, activeStroke);
    } else if (activeStroke.type === 'rect' || activeStroke.type === 'circle') {
        activeStroke.x = Math.min(activeStroke.startX, coords.x);
        activeStroke.y = Math.min(activeStroke.startY, coords.y);
        activeStroke.w = coords.x - activeStroke.startX;
        activeStroke.h = coords.y - activeStroke.startY;
        redrawWhiteboard();
        drawSingleStroke(canvasCtx, activeStroke);
    } else if (activeStroke.type === 'line' || activeStroke.type === 'arrow') {
        activeStroke.x2 = coords.x;
        activeStroke.y2 = coords.y;
        redrawWhiteboard();
        drawSingleStroke(canvasCtx, activeStroke);
    }
}

function onBoardMouseUp() {
    if (!isDrawing.value || !activeStroke) return;
    isDrawing.value = false;

    strokes.value.push(activeStroke);
    broadcastWhiteboardMessage({ action: 'stroke', stroke: activeStroke });
    activeStroke = null;
    redrawWhiteboard();
}

function onBoardTouchStart(e) {
    if (e.touches?.length === 1) {
        onBoardMouseDown(e);
    }
}

function onBoardTouchMove(e) {
    if (e.touches?.length === 1) {
        onBoardMouseMove(e);
    }
}

function onBoardTouchEnd() {
    onBoardMouseUp();
}

function commitTextInput() {
    if (!textInputState.value.visible || !textInputState.value.text.trim()) {
        textInputState.value.visible = false;
        return;
    }

    const stroke = {
        type: 'text',
        tool: 'text',
        text: textInputState.value.text.trim(),
        color: currentColor.value,
        size: currentLineWidth.value * 4 + 14,
        x: textInputState.value.x,
        y: textInputState.value.y + 16,
    };

    strokes.value.push(stroke);
    broadcastWhiteboardMessage({ action: 'stroke', stroke });
    textInputState.value = { visible: false, x: 0, y: 0, text: '' };
    redrawWhiteboard();
}

function undoWhiteboard() {
    if (strokes.value.length === 0) return;
    const popped = strokes.value.pop();
    redoStack.value.push(popped);
    broadcastWhiteboardMessage({ action: 'undo' });
    redrawWhiteboard();
}

function redoWhiteboard() {
    if (redoStack.value.length === 0) return;
    const restored = redoStack.value.pop();
    strokes.value.push(restored);
    broadcastWhiteboardMessage({ action: 'stroke', stroke: restored });
    redrawWhiteboard();
}

function clearWhiteboard() {
    if (strokes.value.length === 0) return;
    if (!window.confirm('هل تريد مسح محتويات السبورة بالكامل؟')) return;
    strokes.value = [];
    redoStack.value = [];
    broadcastWhiteboardMessage({ action: 'clear', bg: whiteboardBg.value });
    redrawWhiteboard();
}

function setWhiteboardBg(bg) {
    whiteboardBg.value = bg;
    broadcastWhiteboardMessage({ action: 'set_bg', bg });
    redrawWhiteboard();
}

function exportWhiteboardImage() {
    if (!whiteboardCanvas.value) return;
    const link = document.createElement('a');
    link.download = `altafawwuq-whiteboard-${props.session.id}.png`;
    link.href = whiteboardCanvas.value.toDataURL('image/png');
    link.click();
}

function toggleWhiteboard() {
    if (!props.user.isTeacher) return;
    isWhiteboardOpen.value = !isWhiteboardOpen.value;
    if (isWhiteboardOpen.value) {
        nextTick(() => {
            initWhiteboardCanvas();
            broadcastWhiteboardMessage({ action: 'request_sync' });
        });
    }
    broadcastWhiteboardMessage({
        action: 'visibility',
        isOpen: isWhiteboardOpen.value,
    });
}

function broadcastWhiteboardMessage(message) {
    if (!jitsiApi || !isJoined.value) return;
    try {
        jitsiApi.executeCommand('sendEndpointTextMessage', '', JSON.stringify({
            source: 'altafawwuq_whiteboard',
            ...message,
        }));
    } catch (e) {
        console.warn('Whiteboard broadcast failed', e);
    }
}

function handleEndpointTextMessage(event) {
    try {
        const text = event?.data?.eventData?.text || event?.text || '';
        if (!text) return;
        const payload = JSON.parse(text);
        if (payload.source !== 'altafawwuq_whiteboard') return;

        if (payload.action === 'stroke' && payload.stroke) {
            strokes.value.push(payload.stroke);
            if (isWhiteboardOpen.value) {
                redrawWhiteboard();
            }
        } else if (payload.action === 'visibility') {
            if (typeof payload.isOpen === 'boolean' && !props.user.isTeacher) {
                isWhiteboardOpen.value = payload.isOpen;
                if (isWhiteboardOpen.value) {
                    nextTick(() => {
                        initWhiteboardCanvas();
                        redrawWhiteboard();
                    });
                }
            }
        } else if (payload.action === 'clear') {
            strokes.value = [];
            if (payload.bg) whiteboardBg.value = payload.bg;
            if (isWhiteboardOpen.value) {
                redrawWhiteboard();
            }
        } else if (payload.action === 'undo') {
            strokes.value.pop();
            if (isWhiteboardOpen.value) {
                redrawWhiteboard();
            }
        } else if (payload.action === 'set_bg') {
            whiteboardBg.value = payload.bg;
            if (isWhiteboardOpen.value) {
                redrawWhiteboard();
            }
        } else if (payload.action === 'request_sync') {
            if (strokes.value.length > 0 || isWhiteboardOpen.value) {
                broadcastWhiteboardMessage({
                    action: 'sync_response',
                    strokes: strokes.value,
                    bg: whiteboardBg.value,
                    isOpen: isWhiteboardOpen.value,
                });
            }
        } else if (payload.action === 'sync_response') {
            strokes.value = payload.strokes || [];
            if (payload.bg) whiteboardBg.value = payload.bg;
            if (typeof payload.isOpen === 'boolean' && !props.user.isTeacher) {
                isWhiteboardOpen.value = payload.isOpen;
            }
            if (isWhiteboardOpen.value) {
                nextTick(() => {
                    initWhiteboardCanvas();
                    redrawWhiteboard();
                });
            }
        }
    } catch (e) {
        // ignore non-json messages
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

    if (isBrowserRecording.value) {
        stopBrowserRecording();
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
    jitsiApi.addListener('endpointTextMessageReceived', handleEndpointTextMessage);
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

    resizeHandler = () => {
        jitsiApi?.resizeHeight?.(jitsiFrameHeight());
        if (isWhiteboardOpen.value) {
            initWhiteboardCanvas();
        }
    };
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
    stopAttendanceHeartbeat();
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
    api?.removeListener?.('endpointTextMessageReceived', handleEndpointTextMessage);
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
                        :class="{ active: isRecording || isBrowserRecording, recording: isBrowserRecording }"
                        :disabled="!isJoined"
                        @click="toggleRecording"
                    >
                        <span aria-hidden="true" class="recording-dot">●</span>
                        {{ isBrowserRecording ? `إيقاف التسجيل (${browserRecordingTimeFormatted})` : (isRecording ? 'إيقاف التسجيل' : 'تسجيل الحصة') }}
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
                    v-if="user.isTeacher"
                    type="button"
                    class="whiteboard-button"
                    :class="{ active: isWhiteboardOpen }"
                    :aria-pressed="isWhiteboardOpen"
                    :disabled="!isJoined"
                    @click="toggleWhiteboard"
                >
                    <span aria-hidden="true">✎</span>
                    {{ isWhiteboardOpen ? 'إغلاق السبورة' : 'السبورة التفاعلية' }}
                </button>
                <span class="connection-status" :class="{ connected: isJoined }" aria-live="polite">
                    <span class="status-dot" aria-hidden="true"></span>
                    {{ isJoined ? 'متصل عبر Jitsi' : 'جاري الاتصال' }}
                </span>
            </div>
        </header>

        <main class="jitsi-stage">
            <div ref="jitsiContainer" class="jitsi-container"></div>

            <!-- Built-in Interactive Whiteboard Workspace -->
            <div
                v-show="isWhiteboardOpen"
                ref="whiteboardContainer"
                class="whiteboard-workspace"
                :class="{ fullscreen: isWhiteboardFullscreen, readonly: !user.isTeacher }"
            >
                <div v-if="user.isTeacher" class="whiteboard-toolbar">
                    <div class="wb-group">
                        <button
                            type="button"
                            class="wb-tool-btn"
                            :class="{ active: currentTool === 'pen' }"
                            title="قلم عادي"
                            @click="currentTool = 'pen'"
                        >
                            ✏️
                        </button>
                        <button
                            type="button"
                            class="wb-tool-btn"
                            :class="{ active: currentTool === 'highlighter' }"
                            title="قلم تمييز"
                            @click="currentTool = 'highlighter'"
                        >
                            🖍️
                        </button>
                        <button
                            type="button"
                            class="wb-tool-btn"
                            :class="{ active: currentTool === 'eraser' }"
                            title="ممحاة"
                            @click="currentTool = 'eraser'"
                        >
                            🧹
                        </button>
                        <button
                            type="button"
                            class="wb-tool-btn"
                            :class="{ active: currentTool === 'rect' }"
                            title="مستطيل"
                            @click="currentTool = 'rect'"
                        >
                            🔲
                        </button>
                        <button
                            type="button"
                            class="wb-tool-btn"
                            :class="{ active: currentTool === 'circle' }"
                            title="دائرة"
                            @click="currentTool = 'circle'"
                        >
                            ⭕
                        </button>
                        <button
                            type="button"
                            class="wb-tool-btn"
                            :class="{ active: currentTool === 'line' }"
                            title="خط مستقيم"
                            @click="currentTool = 'line'"
                        >
                            ➖
                        </button>
                        <button
                            type="button"
                            class="wb-tool-btn"
                            :class="{ active: currentTool === 'arrow' }"
                            title="سهم"
                            @click="currentTool = 'arrow'"
                        >
                            ➡️
                        </button>
                        <button
                            type="button"
                            class="wb-tool-btn"
                            :class="{ active: currentTool === 'text' }"
                            title="كتابة نص"
                            @click="currentTool = 'text'"
                        >
                            🔤
                        </button>
                    </div>

                    <div class="wb-divider"></div>

                    <!-- Color Swatches -->
                    <div class="wb-group wb-colors">
                        <button
                            v-for="color in ['#ffffff', '#38bdf8', '#ef4444', '#22c55e', '#eab308', '#a855f7', '#f97316', '#0f172a']"
                            :key="color"
                            type="button"
                            class="wb-color-dot"
                            :class="{ active: currentColor === color && currentTool !== 'eraser' }"
                            :style="{ backgroundColor: color }"
                            :title="color"
                            @click="currentColor = color; if (currentTool === 'eraser') currentTool = 'pen';"
                        ></button>
                    </div>

                    <div class="wb-divider"></div>

                    <!-- Line Width -->
                    <div class="wb-group">
                        <button
                            v-for="w in [2, 4, 8, 14]"
                            :key="w"
                            type="button"
                            class="wb-width-btn"
                            :class="{ active: currentLineWidth === w }"
                            :title="w + 'px'"
                            @click="currentLineWidth = w"
                        >
                            <span class="width-circle" :style="{ width: Math.max(4, w) + 'px', height: Math.max(4, w) + 'px' }"></span>
                        </button>
                    </div>

                    <div class="wb-divider"></div>

                    <!-- Backgrounds -->
                    <div class="wb-group">
                        <button
                            type="button"
                            class="wb-tool-btn"
                            :class="{ active: whiteboardBg === 'dark' }"
                            title="سبورة داكنة"
                            @click="setWhiteboardBg('dark')"
                        >
                            ⬛
                        </button>
                        <button
                            type="button"
                            class="wb-tool-btn"
                            :class="{ active: whiteboardBg === 'grid' }"
                            title="شبكة هندسية"
                            @click="setWhiteboardBg('grid')"
                        >
                            📐
                        </button>
                        <button
                            type="button"
                            class="wb-tool-btn"
                            :class="{ active: whiteboardBg === 'white' }"
                            title="سبورة بيضاء"
                            @click="setWhiteboardBg('white')"
                        >
                            ⬜
                        </button>
                    </div>

                    <div class="wb-divider"></div>

                    <!-- Actions -->
                    <div class="wb-group">
                        <button
                            type="button"
                            class="wb-action-btn"
                            title="تراجع"
                            :disabled="strokes.length === 0"
                            @click="undoWhiteboard"
                        >
                            ↩️
                        </button>
                        <button
                            type="button"
                            class="wb-action-btn"
                            title="إعادة"
                            :disabled="redoStack.length === 0"
                            @click="redoWhiteboard"
                        >
                            ↪️
                        </button>
                        <button
                            type="button"
                            class="wb-action-btn danger"
                            title="مسح السبورة بالكامل"
                            :disabled="strokes.length === 0"
                            @click="clearWhiteboard"
                        >
                            🗑️
                        </button>
                        <button
                            type="button"
                            class="wb-action-btn"
                            title="تحميل كصورة PNG"
                            @click="exportWhiteboardImage"
                        >
                            💾
                        </button>
                        <button
                            type="button"
                            class="wb-action-btn"
                            :title="isWhiteboardFullscreen ? 'تصغير' : 'ملء الشاشة'"
                            @click="isWhiteboardFullscreen = !isWhiteboardFullscreen; nextTick(initWhiteboardCanvas)"
                        >
                            {{ isWhiteboardFullscreen ? '⤡' : '⤢' }}
                        </button>
                        <button
                            type="button"
                            class="wb-action-btn close-btn"
                            title="إغلاق السبورة"
                            @click="toggleWhiteboard"
                        >
                            ✕
                        </button>
                    </div>
                </div>

                <!-- Student Readonly Bar -->
                <div v-else class="whiteboard-toolbar whiteboard-student-bar">
                    <div class="wb-group">
                        <span class="wb-student-badge">✎ سبورة المعلم المباشرة</span>
                    </div>
                    <div class="wb-divider"></div>
                    <div class="wb-group">
                        <button
                            type="button"
                            class="wb-action-btn"
                            title="تنزيل الشرح كصورة PNG"
                            @click="exportWhiteboardImage"
                        >
                            💾 حفظ كصورة
                        </button>
                        <button
                            type="button"
                            class="wb-action-btn"
                            :title="isWhiteboardFullscreen ? 'تصغير' : 'ملء الشاشة'"
                            @click="isWhiteboardFullscreen = !isWhiteboardFullscreen; nextTick(initWhiteboardCanvas)"
                        >
                            {{ isWhiteboardFullscreen ? '⤡' : '⤢' }}
                        </button>
                        <button
                            type="button"
                            class="wb-action-btn close-btn"
                            title="إخفاء السبورة"
                            @click="isWhiteboardOpen = false"
                        >
                            ✕
                        </button>
                    </div>
                </div>

                <canvas
                    ref="whiteboardCanvas"
                    class="whiteboard-canvas"
                    @mousedown="onBoardMouseDown"
                    @mousemove="onBoardMouseMove"
                    @mouseup="onBoardMouseUp"
                    @mouseleave="onBoardMouseUp"
                    @touchstart.prevent="onBoardTouchStart"
                    @touchmove.prevent="onBoardTouchMove"
                    @touchend.prevent="onBoardTouchEnd"
                ></canvas>

                <div
                    v-if="textInputState.visible"
                    class="whiteboard-text-input-wrap"
                    :style="{ top: textInputState.y + 'px', left: textInputState.x + 'px' }"
                >
                    <input
                        ref="textInputElem"
                        v-model="textInputState.text"
                        type="text"
                        class="wb-inline-input"
                        :style="{ color: currentColor, fontSize: (currentLineWidth * 3 + 14) + 'px' }"
                        placeholder="اكتب هنا ثم اضغط Enter..."
                        @keydown.enter.prevent="commitTextInput"
                        @keydown.esc.prevent="textInputState.visible = false"
                        @blur="commitTextInput"
                    />
                </div>
            </div>

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

            <!-- Recording Saved Success Modal -->
            <div v-if="showRecordingSavedModal" class="recording-modal-overlay" dir="rtl" role="dialog" aria-modal="true">
                <div class="recording-modal-card animate-fade-up">
                    <div class="recording-modal-icon">
                        <span>🎬</span>
                    </div>
                    <h3 class="recording-modal-title">تم تسجيل وحفظ الحصة بنجاح!</h3>
                    <p class="recording-modal-subtext">
                        تم تنزيل ملف الفيديو تلقائياً على جهازك باسم:
                        <br>
                        <strong class="filename-badge">{{ savedRecordingFilename }}</strong>
                        <br>
                        ستجد الملف داخل مجلد <strong>التنزيلات (Downloads)</strong> على جهازك.
                    </p>

                    <div class="recording-modal-steps">
                        <div class="steps-title">
                            <span>💡</span>
                            <span>خطوات نشر التسجيل للطلاب داخل المنصة:</span>
                        </div>
                        <ol class="steps-list">
                            <li>ارفع الفيديو على قناتك في <strong>YouTube</strong> واجعله <strong>غير مدرج (Unlisted)</strong> لضمان الخصوصية.</li>
                            <li>انسخ رابط الفيديو والصقه في الحقل أدناه واضغط نشر (أو يمكنك وضعه لاحقاً من جدول الحصص).</li>
                        </ol>
                    </div>

                    <div class="recording-input-wrap">
                        <label class="recording-input-label">رابط الفيديو (YouTube)</label>
                        <input
                            v-model="postRecordingUrl"
                            type="url"
                            class="recording-input"
                            placeholder="https://www.youtube.com/watch?v=..."
                            :disabled="isSavingPostRecording"
                        />
                        <p v-if="postRecordingNotice" class="recording-notice">{{ postRecordingNotice }}</p>
                    </div>

                    <div class="recording-modal-actions">
                        <button
                            v-if="postRecordingUrl.trim()"
                            type="button"
                            class="btn-publish-recording"
                            :disabled="isSavingPostRecording"
                            @click="submitPostRecordingUrl"
                        >
                            {{ isSavingPostRecording ? 'جاري النشر...' : 'نشر التسجيل للطلاب الآن' }}
                        </button>
                        <button
                            type="button"
                            class="btn-dismiss-recording"
                            @click="showRecordingSavedModal = false"
                        >
                            إغلاق (سأنشره لاحقاً من جدول الحصص)
                        </button>
                    </div>
                </div>
            </div>
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

/* Built-in Interactive Whiteboard Styles */
.whiteboard-workspace {
    position: absolute;
    inset: 16px;
    z-index: 100;
    display: flex;
    flex-direction: column;
    border-radius: 14px;
    overflow: hidden;
    background: #0f172a;
    border: 1px solid rgba(255, 255, 255, 0.12);
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6);
    user-select: none;
}

.whiteboard-workspace.readonly .whiteboard-canvas {
    cursor: default;
}

.wb-student-badge {
    font-size: 13px;
    font-weight: 700;
    color: #38bdf8;
    padding: 4px 12px;
    border-radius: 9999px;
    background: rgba(56, 189, 248, 0.12);
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.whiteboard-workspace.fullscreen {
    position: fixed;
    inset: 0;
    z-index: 9999;
    border-radius: 0;
    border: 0;
}

.whiteboard-toolbar {
    position: absolute;
    top: 14px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 110;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    background: rgba(15, 23, 42, 0.88);
    backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 9999px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.45);
    max-width: 95%;
    overflow-x: auto;
}

.wb-group {
    display: flex;
    align-items: center;
    gap: 4px;
}

.wb-divider {
    width: 1px;
    height: 22px;
    background: rgba(255, 255, 255, 0.15);
    margin: 0 4px;
}

.wb-tool-btn,
.wb-action-btn,
.wb-width-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: transparent;
    border: 1px solid transparent;
    color: #cbd5e1;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.15s ease;
}

.wb-tool-btn:hover,
.wb-action-btn:hover:not(:disabled),
.wb-width-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
}

.wb-tool-btn.active,
.wb-width-btn.active {
    background: #2563eb;
    border-color: #3b82f6;
    color: #ffffff;
    box-shadow: 0 0 12px rgba(37, 99, 235, 0.4);
}

.wb-action-btn:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

.wb-action-btn.danger:hover:not(:disabled) {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.wb-action-btn.close-btn {
    font-weight: bold;
    color: #f87171;
}

.wb-action-btn.close-btn:hover {
    background: rgba(239, 68, 68, 0.25);
    color: #ffffff;
}

.wb-colors {
    gap: 6px;
}

.wb-color-dot {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 2px solid transparent;
    cursor: pointer;
    transition: transform 0.15s ease, border-color 0.15s ease;
}

.wb-color-dot:hover {
    transform: scale(1.15);
}

.wb-color-dot.active {
    border-color: #ffffff;
    box-shadow: 0 0 8px rgba(255, 255, 255, 0.6);
    transform: scale(1.15);
}

.width-circle {
    display: inline-block;
    border-radius: 50%;
    background: currentColor;
}

.whiteboard-canvas {
    width: 100%;
    height: 100%;
    cursor: crosshair;
    touch-action: none;
}

.whiteboard-text-input-wrap {
    position: absolute;
    z-index: 70;
}

.wb-inline-input {
    background: rgba(15, 23, 42, 0.9);
    border: 1px dashed #38bdf8;
    border-radius: 6px;
    padding: 4px 8px;
    outline: none;
    font-family: inherit;
    min-width: 180px;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3);
}

.whiteboard-button.active {
    background: #059669;
    box-shadow: 0 5px 16px rgba(5, 150, 105, 0.4);
}

.recording-button.recording {
    background: #dc2626 !important;
    border-color: #f87171 !important;
    color: #fff !important;
    animation: pulseRecording 1.5s infinite;
}

.recording-button .recording-dot {
    display: inline-block;
    color: #ef4444;
}

.recording-button.recording .recording-dot {
    color: #fff;
}

@keyframes pulseRecording {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.85; transform: scale(1.02); }
}

.recording-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 120;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(6px);
}

.recording-modal-card {
    width: 100%;
    max-width: 480px;
    background: #1e293b;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 20px;
    padding: 28px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    color: #f8fafc;
    text-align: center;
}

.recording-modal-icon {
    width: 56px;
    height: 56px;
    margin: 0 auto 16px;
    border-radius: 16px;
    background: rgba(56, 189, 248, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
}

.recording-modal-title {
    margin: 0 0 10px;
    font-size: 20px;
    font-weight: 800;
    color: #fff;
}

.recording-modal-subtext {
    margin: 0 0 16px;
    font-size: 13px;
    line-height: 1.6;
    color: #94a3b8;
}

.filename-badge {
    display: inline-block;
    margin-top: 6px;
    padding: 3px 10px;
    background: rgba(56, 189, 248, 0.12);
    border: 1px solid rgba(56, 189, 248, 0.25);
    border-radius: 8px;
    color: #38bdf8;
    word-break: break-all;
}

.recording-modal-steps {
    text-align: right;
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 14px 16px;
    margin-bottom: 18px;
    font-size: 12px;
}

.steps-title {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 700;
    color: #fbbf24;
    margin-bottom: 8px;
}

.steps-list {
    margin: 0;
    padding-right: 18px;
    color: #cbd5e1;
    line-height: 1.6;
}

.steps-list li {
    margin-bottom: 4px;
}

.recording-input-wrap {
    text-align: right;
    margin-bottom: 20px;
}

.recording-input-label {
    display: block;
    margin-bottom: 6px;
    font-size: 12px;
    font-weight: 700;
    color: #94a3b8;
}

.recording-input {
    width: 100%;
    padding: 10px 14px;
    background: #0f172a;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 10px;
    color: #fff;
    font-size: 13px;
    direction: ltr;
    text-align: left;
    outline: none;
    transition: border-color 150ms ease;
}

.recording-input:focus {
    border-color: #38bdf8;
}

.recording-notice {
    margin: 6px 0 0;
    font-size: 12px;
    color: #38bdf8;
}

.recording-modal-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.btn-publish-recording {
    width: 100%;
    padding: 11px;
    border-radius: 10px;
    background: #2563eb;
    color: #fff;
    font-weight: 700;
    font-size: 14px;
    border: none;
    cursor: pointer;
    transition: background 150ms ease;
}

.btn-publish-recording:hover {
    background: #1d4ed8;
}

.btn-dismiss-recording {
    width: 100%;
    padding: 9px;
    border-radius: 10px;
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: #94a3b8;
    font-size: 13px;
    cursor: pointer;
    transition: background 150ms ease, color 150ms ease;
}

.btn-dismiss-recording:hover {
    background: rgba(255, 255, 255, 0.06);
    color: #fff;
}
</style>
