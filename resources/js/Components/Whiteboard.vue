<script setup>
import { ref, onMounted, onBeforeUnmount, computed, nextTick } from 'vue';
import { useConfirm } from '@/composables/useConfirm';

const emit = defineEmits(['close']);

// ─── Canvas & Drawing State ────────────────────────────────────────────────────
const canvasRef = ref(null);
const overlayRef = ref(null);
let ctx = null;
let isDrawing = false;
const { confirm } = useConfirm();
let startX = 0;
let startY = 0;
let lastX = 0;
let lastY = 0;
let snapshotBeforeShape = null;
let laserTrail = [];
let laserAnimFrame = null;

// ─── Tool State ───────────────────────────────────────────────────────────────
const activeTool = ref('pen');       // pen | highlighter | neon | eraser | line | rect | circle | arrow | text | laser
const activeColor = ref('#FFFFFF');
const brushSize = ref(4);
const eraserSize = ref(30);
const opacity = ref(1);
const showColorPicker = ref(false);
const showSizePicker = ref(false);
const laserPos = ref({ x: -100, y: -100, visible: false });

// ─── Undo / Redo ─────────────────────────────────────────────────────────────
const history = ref([]);
const redoStack = ref([]);
const MAX_HISTORY = 40;

// ─── Text Tool ────────────────────────────────────────────────────────────────
const textInput = ref('');
const textPos = ref(null);
const textInputRef = ref(null);
const textFontSize = ref(24);
const isTextMode = ref(false);

// ─── Fullscreen ───────────────────────────────────────────────────────────────
const isFullscreen = ref(false);
const wrapperRef = ref(null);

// ─── Tool Groups ─────────────────────────────────────────────────────────────
const penTools = [
    { id: 'pen',         icon: '✏️', label: 'قلم' },
    { id: 'highlighter', icon: '🖊️', label: 'تظليل' },
    { id: 'neon',        icon: '✨', label: 'نيون' },
];
const shapeTools = [
    { id: 'line',   icon: '╱',  label: 'خط' },
    { id: 'rect',   icon: '▭',  label: 'مستطيل' },
    { id: 'circle', icon: '○',  label: 'دائرة' },
    { id: 'arrow',  icon: '→',  label: 'سهم' },
];
const otherTools = [
    { id: 'text',   icon: '🔤', label: 'نص' },
    { id: 'laser',  icon: '🔴', label: 'ليزر' },
    { id: 'eraser', icon: '🧽', label: 'ممحاة' },
];

const PALETTE = [
    '#FFFFFF', '#F8FAFC', '#FCA5A5', '#FB923C', '#FDE68A',
    '#86EFAC', '#67E8F9', '#93C5FD', '#C4B5FD', '#F9A8D4',
    '#EF4444', '#F97316', '#EAB308', '#22C55E', '#06B6D4',
    '#3B82F6', '#8B5CF6', '#EC4899', '#0EA5E9', '#10B981',
    '#1E293B', '#334155', '#475569', '#64748B', '#94A3B8',
];

const currentSize = computed(() => activeTool.value === 'eraser' ? eraserSize.value : brushSize.value);

// ─── Computed Cursor ─────────────────────────────────────────────────────────
const canvasCursor = computed(() => {
    if (activeTool.value === 'laser') return 'none';
    if (activeTool.value === 'text')  return 'text';
    if (activeTool.value === 'eraser') return 'cell';
    return 'crosshair';
});

// ─── Setup Canvas ─────────────────────────────────────────────────────────────
function initCanvas() {
    const canvas = canvasRef.value;
    if (!canvas) return;
    ctx = canvas.getContext('2d');
    // Use requestAnimationFrame to ensure parent has its final CSS dimensions
    requestAnimationFrame(() => {
        resizeCanvas();
        fillBackground();
        saveHistory();
    });
}

function resizeCanvas() {
    const canvas = canvasRef.value;
    if (!canvas || !ctx) return;
    const parent = canvas.parentElement;
    if (!parent) return;

    // Only snapshot if canvas has valid dimensions
    let imageData = null;
    if (canvas.width > 0 && canvas.height > 0) {
        try { imageData = ctx.getImageData(0, 0, canvas.width, canvas.height); } catch (e) { /* ignore */ }
    }

    const newW = parent.clientWidth  || parent.offsetWidth  || window.innerWidth;
    const newH = parent.clientHeight || parent.offsetHeight || window.innerHeight;
    if (newW <= 0 || newH <= 0) return;

    canvas.width  = newW;
    canvas.height = newH;

    if (imageData instanceof ImageData) {
        ctx.putImageData(imageData, 0, 0);
    } else {
        fillBackground();
    }
}

function fillBackground() {
    ctx.fillStyle = '#0F1117';
    ctx.fillRect(0, 0, canvasRef.value.width, canvasRef.value.height);
    drawGrid();
}

function drawGrid() {
    ctx.save();
    ctx.strokeStyle = 'rgba(255,255,255,0.04)';
    ctx.lineWidth = 1;
    const gap = 40;
    for (let x = 0; x < canvasRef.value.width; x += gap) {
        ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, canvasRef.value.height); ctx.stroke();
    }
    for (let y = 0; y < canvasRef.value.height; y += gap) {
        ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(canvasRef.value.width, y); ctx.stroke();
    }
    ctx.restore();
}

// ─── History ──────────────────────────────────────────────────────────────────
function saveHistory() {
    const canvas = canvasRef.value;
    if (!canvas || !ctx) return;
    if (canvas.width <= 0 || canvas.height <= 0) return;
    try {
        const snap = ctx.getImageData(0, 0, canvas.width, canvas.height);
        history.value.push(snap);
        if (history.value.length > MAX_HISTORY) history.value.shift();
        redoStack.value = [];
    } catch (e) { /* ignore if canvas not ready */ }
}

function undo() {
    if (history.value.length <= 1) return;
    const last = history.value.pop();
    if (last instanceof ImageData) redoStack.value.push(last);
    const prev = history.value[history.value.length - 1];
    if (prev instanceof ImageData) ctx.putImageData(prev, 0, 0);
}

function redo() {
    if (!redoStack.value.length) return;
    const next = redoStack.value.pop();
    if (next instanceof ImageData) {
        ctx.putImageData(next, 0, 0);
        history.value.push(next);
    }
}

// ─── Drawing Context Setup ────────────────────────────────────────────────────
function setupCtxForTool() {
    ctx.lineCap  = 'round';
    ctx.lineJoin = 'round';

    if (activeTool.value === 'eraser') {
        ctx.globalCompositeOperation = 'destination-out';
        ctx.globalAlpha = 1;
        ctx.lineWidth = eraserSize.value;
        ctx.strokeStyle = 'rgba(0,0,0,1)';
        return;
    }

    ctx.globalCompositeOperation = 'source-over';

    if (activeTool.value === 'highlighter') {
        ctx.globalAlpha = 0.38;
        ctx.lineWidth = brushSize.value * 3.5;
        ctx.strokeStyle = activeColor.value;
    } else if (activeTool.value === 'neon') {
        ctx.globalAlpha = 0.95;
        ctx.lineWidth = brushSize.value;
        ctx.strokeStyle = activeColor.value;
        ctx.shadowColor = activeColor.value;
        ctx.shadowBlur  = brushSize.value * 5;
    } else {
        ctx.globalAlpha = opacity.value;
        ctx.lineWidth = brushSize.value;
        ctx.strokeStyle = activeColor.value;
        ctx.shadowColor = 'transparent';
        ctx.shadowBlur  = 0;
    }

    ctx.fillStyle = activeColor.value;
}

function resetCtx() {
    if (!ctx) return;
    ctx.globalAlpha = 1;
    ctx.globalCompositeOperation = 'source-over';
    ctx.shadowColor = 'transparent';
    ctx.shadowBlur  = 0;
}

// ─── Pointer Events ───────────────────────────────────────────────────────────
function getPos(e) {
    const canvas = canvasRef.value;
    const rect = canvas.getBoundingClientRect();
    const src  = e.touches ? e.touches[0] : e;
    return {
        x: (src.clientX - rect.left) * (canvas.width  / rect.width),
        y: (src.clientY - rect.top)  * (canvas.height / rect.height),
    };
}

function onPointerDown(e) {
    if (activeTool.value === 'text') {
        handleTextClick(e);
        return;
    }
    if (activeTool.value === 'laser') return;

    const { x, y } = getPos(e);
    isDrawing = true;
    startX = x; startY = y;
    lastX  = x; lastY  = y;

    setupCtxForTool();

    if (['pen', 'highlighter', 'neon', 'eraser'].includes(activeTool.value)) {
        ctx.beginPath();
        ctx.moveTo(x, y);
    } else {
        // shape tool — take snapshot
        snapshotBeforeShape = ctx.getImageData(0, 0, canvasRef.value.width, canvasRef.value.height);
    }
}

function onPointerMove(e) {
    e.preventDefault();
    const { x, y } = getPos(e);

    // Laser pointer
    if (activeTool.value === 'laser') {
        laserPos.value = { x, y, visible: true };
        addLaserTrail(x, y);
        return;
    }

    if (!isDrawing) return;

    if (['pen', 'highlighter', 'neon', 'eraser'].includes(activeTool.value)) {
        ctx.lineTo(x, y);
        ctx.stroke();
        lastX = x; lastY = y;
    } else {
        // Live shape preview
        ctx.putImageData(snapshotBeforeShape, 0, 0);
        setupCtxForTool();
        drawShape(startX, startY, x, y);
    }
}

function onPointerUp(e) {
    if (!isDrawing && activeTool.value !== 'laser') return;
    if (activeTool.value === 'laser') {
        laserPos.value.visible = false;
        return;
    }

    isDrawing = false;
    const { x, y } = getPos(e);

    if (!['pen', 'highlighter', 'neon', 'eraser'].includes(activeTool.value)) {
        ctx.putImageData(snapshotBeforeShape, 0, 0);
        setupCtxForTool();
        drawShape(startX, startY, x, y);
        snapshotBeforeShape = null;
    }

    resetCtx();
    saveHistory();
}

function onPointerLeave() {
    if (activeTool.value === 'laser') {
        laserPos.value.visible = false;
        return;
    }
    if (isDrawing && ['pen', 'highlighter', 'neon', 'eraser'].includes(activeTool.value)) {
        isDrawing = false;
        resetCtx();
        saveHistory();
    }
}

// ─── Shape Drawing ────────────────────────────────────────────────────────────
function drawShape(x1, y1, x2, y2) {
    ctx.beginPath();

    switch (activeTool.value) {
        case 'line':
            ctx.moveTo(x1, y1);
            ctx.lineTo(x2, y2);
            ctx.stroke();
            break;

        case 'rect':
            ctx.strokeRect(x1, y1, x2 - x1, y2 - y1);
            break;

        case 'circle': {
            const rx = Math.abs(x2 - x1) / 2;
            const ry = Math.abs(y2 - y1) / 2;
            const cx = x1 + (x2 - x1) / 2;
            const cy = y1 + (y2 - y1) / 2;
            ctx.ellipse(cx, cy, rx, ry, 0, 0, Math.PI * 2);
            ctx.stroke();
            break;
        }

        case 'arrow': {
            const angle = Math.atan2(y2 - y1, x2 - x1);
            const headLen = Math.min(Math.hypot(x2 - x1, y2 - y1) * 0.25, 40);
            ctx.moveTo(x1, y1);
            ctx.lineTo(x2, y2);
            ctx.stroke();
            // Arrowhead
            ctx.beginPath();
            ctx.moveTo(x2, y2);
            ctx.lineTo(x2 - headLen * Math.cos(angle - Math.PI / 6), y2 - headLen * Math.sin(angle - Math.PI / 6));
            ctx.lineTo(x2 - headLen * Math.cos(angle + Math.PI / 6), y2 - headLen * Math.sin(angle + Math.PI / 6));
            ctx.closePath();
            ctx.fillStyle = activeColor.value;
            ctx.fill();
            break;
        }
    }
}

// ─── Text Tool ────────────────────────────────────────────────────────────────
function handleTextClick(e) {
    const { x, y } = getPos(e);
    textPos.value = { x, y };
    isTextMode.value = true;
    textInput.value = '';
    setTimeout(() => textInputRef.value?.focus(), 50);
}

function commitText() {
    if (!textInput.value.trim() || !textPos.value) {
        isTextMode.value = false;
        return;
    }
    setupCtxForTool();
    ctx.font = `bold ${textFontSize.value}px 'Cairo', sans-serif`;
    ctx.fillStyle = activeColor.value;
    if (activeTool.value === 'neon') {
        ctx.shadowColor = activeColor.value;
        ctx.shadowBlur  = 12;
    }
    ctx.fillText(textInput.value, textPos.value.x, textPos.value.y);
    resetCtx();
    saveHistory();
    isTextMode.value = false;
    textInput.value = '';
    textPos.value = null;
}

// ─── Laser Trail ─────────────────────────────────────────────────────────────
function addLaserTrail(x, y) {
    laserTrail.push({ x, y, t: Date.now() });
    if (laserTrail.length > 20) laserTrail.shift();
}

// ─── Board Actions ────────────────────────────────────────────────────────────
async function clearBoard() {
    const ok = await confirm({
        title: 'مسح السبورة',
        message: 'هل تريد مسح السبورة بالكامل؟',
        confirmLabel: 'مسح',
        variant: 'danger',
    });
    if (!ok) return;
    fillBackground();
    saveHistory();
}

function downloadBoard() {
    const link = document.createElement('a');
    link.download = `whiteboard-${Date.now()}.png`;
    link.href = canvasRef.value.toDataURL('image/png');
    link.click();
}

function toggleFullscreen() {
    const el = wrapperRef.value;
    if (!isFullscreen.value) {
        (el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullscreen).call(el);
    } else {
        (document.exitFullscreen || document.webkitExitFullscreen || document.mozCancelFullScreen).call(document);
    }
}

// ─── Keyboard Shortcuts ───────────────────────────────────────────────────────
function onKeyDown(e) {
    if (e.ctrlKey || e.metaKey) {
        if (e.key === 'z') { e.preventDefault(); undo(); }
        if (e.key === 'y') { e.preventDefault(); redo(); }
        if (e.key === 's') { e.preventDefault(); downloadBoard(); }
    }
    if (e.key === 'Escape') {
        if (isTextMode.value) { isTextMode.value = false; }
        else emit('close');
    }
    // Quick tool shortcuts
    const shortcuts = { p: 'pen', h: 'highlighter', n: 'neon', e: 'eraser', l: 'laser', t: 'text', r: 'rect', c: 'circle', a: 'arrow' };
    if (!isTextMode.value && shortcuts[e.key]) activeTool.value = shortcuts[e.key];
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(async () => {
    // Wait for Vue to fully render the DOM, then initialize canvas
    await nextTick();
    initCanvas();
    window.addEventListener('keydown', onKeyDown);
    window.addEventListener('resize', () => {
        // Debounce resize to avoid rapid resize flicker
        clearTimeout(window._wbResizeTimer);
        window._wbResizeTimer = setTimeout(resizeCanvas, 100);
    });
    document.addEventListener('fullscreenchange', () => {
        isFullscreen.value = !!document.fullscreenElement;
        // Re-init canvas after fullscreen transition settles
        setTimeout(resizeCanvas, 300);
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeyDown);
    window.removeEventListener('resize', resizeCanvas);
    if (laserAnimFrame) cancelAnimationFrame(laserAnimFrame);
});

// Close pickers when clicking outside
function closePickersOnOutsideClick(e) {
    if (!e.target.closest('.color-picker-wrapper')) showColorPicker.value = false;
    if (!e.target.closest('.size-picker-wrapper')) showSizePicker.value = false;
}
</script>

<template>
    <div
        ref="wrapperRef"
        class="wb-wrapper"
        @click.self="closePickersOnOutsideClick"
    >
        <!-- ═══ TOOLBAR ═══════════════════════════════════════════════════════ -->
        <div class="wb-toolbar" dir="rtl">

            <!-- Close -->
            <button class="wb-btn wb-btn-close" @click="emit('close')" title="إغلاق السبورة (Esc)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            <div class="wb-separator"></div>

            <!-- ── Pen Tools ── -->
            <div class="wb-group">
                <button
                    v-for="tool in penTools" :key="tool.id"
                    class="wb-tool-btn"
                    :class="{ active: activeTool === tool.id }"
                    @click="activeTool = tool.id"
                    :title="tool.label"
                >
                    <span class="tool-icon">{{ tool.icon }}</span>
                    <span class="tool-label">{{ tool.label }}</span>
                </button>
            </div>

            <div class="wb-separator"></div>

            <!-- ── Shape Tools ── -->
            <div class="wb-group">
                <button
                    v-for="tool in shapeTools" :key="tool.id"
                    class="wb-tool-btn"
                    :class="{ active: activeTool === tool.id }"
                    @click="activeTool = tool.id"
                    :title="tool.label"
                >
                    <span class="tool-icon shape-icon">{{ tool.icon }}</span>
                    <span class="tool-label">{{ tool.label }}</span>
                </button>
            </div>

            <div class="wb-separator"></div>

            <!-- ── Other Tools ── -->
            <div class="wb-group">
                <button
                    v-for="tool in otherTools" :key="tool.id"
                    class="wb-tool-btn"
                    :class="{ active: activeTool === tool.id }"
                    @click="activeTool = tool.id"
                    :title="tool.label"
                >
                    <span class="tool-icon">{{ tool.icon }}</span>
                    <span class="tool-label">{{ tool.label }}</span>
                </button>
            </div>

            <div class="wb-separator"></div>

            <!-- ── Color Picker ── -->
            <div class="color-picker-wrapper" style="position:relative;">
                <button
                    class="wb-color-btn"
                    :style="{ '--swatch': activeColor }"
                    @click="showColorPicker = !showColorPicker; showSizePicker = false"
                    title="اللون"
                ></button>
                <div v-if="showColorPicker" class="wb-popover" dir="rtl">
                    <div class="popover-label">لوحة الألوان السريعة</div>
                    <div class="palette-grid">
                        <button
                            v-for="c in PALETTE" :key="c"
                            class="palette-swatch"
                            :class="{ selected: activeColor === c }"
                            :style="{ background: c }"
                            @click="activeColor = c"
                        ></button>
                    </div>
                    <div class="popover-label" style="margin-top:10px;">لون مخصص</div>
                    <input type="color" v-model="activeColor" class="wb-native-color" />
                </div>
            </div>

            <!-- ── Brush Size ── -->
            <div class="size-picker-wrapper" style="position:relative;">
                <button
                    class="wb-btn wb-size-preview-btn"
                    @click="showSizePicker = !showSizePicker; showColorPicker = false"
                    title="حجم الأداة"
                >
                    <span
                        class="size-dot"
                        :style="{ width: Math.min(currentSize, 32) + 'px', height: Math.min(currentSize, 32) + 'px', background: activeColor }"
                    ></span>
                </button>
                <div v-if="showSizePicker" class="wb-popover" dir="rtl">
                    <div class="popover-label">{{ activeTool === 'eraser' ? 'حجم الممحاة' : 'سُمك القلم' }}</div>
                    <input
                        v-if="activeTool === 'eraser'"
                        type="range"
                        v-model.number="eraserSize"
                        min="1" max="100"
                        class="wb-slider"
                    />
                    <input
                        v-else
                        type="range"
                        v-model.number="brushSize"
                        min="1" max="50"
                        class="wb-slider"
                    />
                    <div class="size-value">{{ currentSize }}px</div>


                    <template v-if="activeTool === 'text'">
                        <div class="popover-label" style="margin-top:8px;">حجم الخط</div>
                        <input type="range" v-model.number="textFontSize" min="12" max="96" class="wb-slider" />
                        <div class="size-value">{{ textFontSize }}px</div>
                    </template>
                </div>
            </div>

            <!-- ── Spacer ── -->
            <div style="flex:1"></div>

            <!-- ── Actions ── -->
            <div class="wb-group">
                <button class="wb-action-btn" @click="undo" title="تراجع (Ctrl+Z)" :disabled="history.length <= 1">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                </button>
                <button class="wb-action-btn" @click="redo" title="إعادة (Ctrl+Y)" :disabled="!redoStack.length">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M15 15l6-6m0 0l-6-6m6 6H9a6 6 0 000 12h3"/></svg>
                </button>
            </div>

            <div class="wb-separator"></div>

            <div class="wb-group">
                <button class="wb-action-btn" @click="downloadBoard" title="تحميل السبورة (Ctrl+S)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                </button>
                <button class="wb-action-btn" @click="toggleFullscreen" title="ملء الشاشة">
                    <svg v-if="!isFullscreen" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                    <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25"/></svg>
                </button>
                <button class="wb-action-btn wb-action-btn-danger" @click="clearBoard" title="مسح الكل">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </div>
        </div>

        <!-- ═══ CANVAS AREA ════════════════════════════════════════════════════ -->
        <div class="wb-canvas-area" @click="closePickersOnOutsideClick">
            <!-- Active Tool Indicator -->
            <div class="wb-tool-indicator">
                <span>{{ [...penTools, ...shapeTools, ...otherTools].find(t => t.id === activeTool)?.icon }}</span>
                <span>{{ [...penTools, ...shapeTools, ...otherTools].find(t => t.id === activeTool)?.label }}</span>
                <kbd>Ctrl+Z</kbd>
                <kbd>Ctrl+Y</kbd>
            </div>

            <!-- Canvas -->
            <canvas
                ref="canvasRef"
                class="wb-canvas"
                :style="{ cursor: canvasCursor }"
                @mousedown="onPointerDown"
                @mousemove="onPointerMove"
                @mouseup="onPointerUp"
                @mouseleave="onPointerLeave"
                @touchstart.prevent="onPointerDown"
                @touchmove.prevent="onPointerMove"
                @touchend.prevent="onPointerUp"
            ></canvas>

            <!-- Laser Pointer Cursor -->
            <div
                v-if="activeTool === 'laser' && laserPos.visible"
                class="laser-cursor"
                :style="{ left: laserPos.x + 'px', top: laserPos.y + 'px' }"
            ></div>

            <!-- Text Input Overlay -->
            <div
                v-if="isTextMode && textPos"
                class="text-overlay"
                :style="{ left: textPos.x + 'px', top: textPos.y + 'px' }"
            >
                <input
                    ref="textInputRef"
                    v-model="textInput"
                    class="text-input-overlay"
                    :style="{ fontSize: textFontSize + 'px', color: activeColor }"
                    placeholder="اكتب هنا ثم Enter..."
                    @keydown.enter.prevent="commitText"
                    @keydown.escape="isTextMode = false"
                    @blur="commitText"
                />
            </div>
        </div>

        <!-- ═══ SHORTCUTS HINT ═════════════════════════════════════════════════ -->
        <div class="wb-shortcuts" dir="rtl">
            <span>P: قلم</span>
            <span>H: تظليل</span>
            <span>N: نيون</span>
            <span>L: ليزر</span>
            <span>T: نص</span>
            <span>E: ممحاة</span>
            <span>R: مستطيل</span>
            <span>C: دائرة</span>
            <span>A: سهم</span>
            <span>Esc: إغلاق</span>
        </div>
    </div>
</template>

<style scoped>
/* ─── Wrapper ─────────────────────────────────────────────────── */
.wb-wrapper {
    display: flex;
    flex-direction: column;
    width: 100%;
    height: 100%;
    background: #0F1117;
    border-radius: 0;
    overflow: hidden;
    position: relative;
    font-family: 'Cairo', 'Segoe UI', sans-serif;
}

/* ─── Toolbar ─────────────────────────────────────────────────── */
.wb-toolbar {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    background: rgba(15, 17, 23, 0.95);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(255,255,255,0.07);
    flex-shrink: 0;
    flex-wrap: wrap;
    min-height: 58px;
    z-index: 10;
}

.wb-group {
    display: flex;
    gap: 4px;
    align-items: center;
}

.wb-separator {
    width: 1px;
    height: 28px;
    background: rgba(255,255,255,0.1);
    flex-shrink: 0;
    margin: 0 4px;
}

/* ─── Tool Buttons ────────────────────────────────────────────── */
.wb-tool-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    padding: 6px 10px;
    border-radius: 10px;
    border: 1px solid transparent;
    background: transparent;
    color: rgba(255,255,255,0.55);
    cursor: pointer;
    transition: all 0.18s ease;
    font-family: inherit;
    min-width: 44px;
}

.wb-tool-btn:hover {
    background: rgba(255,255,255,0.08);
    color: white;
    border-color: rgba(255,255,255,0.12);
    transform: translateY(-1px);
}

.wb-tool-btn.active {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: white;
    border-color: rgba(99,102,241,0.5);
    box-shadow: 0 0 16px rgba(99,102,241,0.4), 0 4px 12px rgba(0,0,0,0.3);
    transform: translateY(-1px);
}

.tool-icon {
    font-size: 16px;
    line-height: 1;
}
.shape-icon {
    font-size: 18px;
    font-weight: 700;
}
.tool-label {
    font-size: 9px;
    font-weight: 600;
    white-space: nowrap;
    letter-spacing: 0.02em;
}

/* ─── Generic Btn ─────────────────────────────────────────────── */
.wb-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 9px;
    border: 1px solid transparent;
    background: transparent;
    color: rgba(255,255,255,0.55);
    cursor: pointer;
    transition: all 0.15s ease;
}
.wb-btn:hover {
    background: rgba(255,255,255,0.08);
    color: white;
    border-color: rgba(255,255,255,0.1);
}
.wb-btn svg { width: 18px; height: 18px; }

.wb-btn-close:hover {
    background: rgba(239,68,68,0.15);
    color: #f87171;
    border-color: rgba(239,68,68,0.2);
}

/* ─── Action Buttons ──────────────────────────────────────────── */
.wb-action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 9px;
    border: 1px solid rgba(255,255,255,0.08);
    background: rgba(255,255,255,0.05);
    color: rgba(255,255,255,0.6);
    cursor: pointer;
    transition: all 0.15s ease;
}
.wb-action-btn svg { width: 16px; height: 16px; }
.wb-action-btn:hover:not(:disabled) {
    background: rgba(255,255,255,0.12);
    color: white;
    transform: translateY(-1px);
    border-color: rgba(255,255,255,0.15);
}
.wb-action-btn:disabled { opacity: 0.3; cursor: not-allowed; }
.wb-action-btn-danger:hover:not(:disabled) {
    background: rgba(239,68,68,0.15);
    color: #f87171;
    border-color: rgba(239,68,68,0.25);
}

/* ─── Color Button ────────────────────────────────────────────── */
.wb-color-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.25);
    background: var(--swatch, #fff);
    cursor: pointer;
    transition: all 0.15s ease;
    box-shadow: 0 0 0 1px rgba(0,0,0,0.3), 0 2px 8px rgba(0,0,0,0.4);
    flex-shrink: 0;
}
.wb-color-btn:hover {
    transform: scale(1.12);
    border-color: white;
    box-shadow: 0 0 0 2px rgba(255,255,255,0.3), 0 4px 16px rgba(0,0,0,0.5);
}

/* ─── Size Preview Button ─────────────────────────────────────── */
.wb-size-preview-btn {
    width: 40px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
}
.size-dot {
    border-radius: 50%;
    min-width: 4px;
    min-height: 4px;
    max-width: 32px;
    max-height: 32px;
    transition: all 0.15s;
    display: block;
}

/* ─── Popovers ────────────────────────────────────────────────── */
.wb-popover {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    background: rgba(20, 22, 32, 0.97);
    backdrop-filter: blur(24px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    padding: 16px;
    min-width: 220px;
    box-shadow: 0 24px 48px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.04);
    z-index: 100;
    animation: popover-in 0.15s ease;
}
@keyframes popover-in {
    from { opacity: 0; transform: translateY(-6px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

.popover-label {
    font-size: 11px;
    font-weight: 700;
    color: rgba(255,255,255,0.4);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 8px;
}

.palette-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 5px;
}

.palette-swatch {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 2px solid transparent;
    cursor: pointer;
    transition: all 0.12s ease;
    box-shadow: inset 0 0 0 1px rgba(0,0,0,0.2);
}
.palette-swatch:hover { transform: scale(1.15); border-color: white; }
.palette-swatch.selected {
    border-color: white;
    box-shadow: 0 0 0 2px rgba(255,255,255,0.5), 0 4px 8px rgba(0,0,0,0.4);
    transform: scale(1.1);
}

.wb-native-color {
    width: 100%;
    height: 36px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.15);
    background: rgba(255,255,255,0.05);
    cursor: pointer;
    padding: 2px;
}

.wb-slider {
    width: 100%;
    accent-color: #6366f1;
    cursor: pointer;
}

.size-value {
    font-size: 11px;
    color: rgba(255,255,255,0.5);
    text-align: center;
    margin-top: 4px;
}

/* ─── Canvas Area ─────────────────────────────────────────────── */
.wb-canvas-area {
    flex: 1;
    position: relative;
    overflow: hidden;
}

.wb-canvas {
    display: block;
    width: 100%;
    height: 100%;
    touch-action: none;
}

/* ─── Tool Indicator ──────────────────────────────────────────── */
.wb-tool-indicator {
    position: absolute;
    bottom: 16px;
    right: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(15,17,23,0.8);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px;
    padding: 6px 12px;
    font-size: 12px;
    color: rgba(255,255,255,0.5);
    pointer-events: none;
    z-index: 5;
}
.wb-tool-indicator kbd {
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 5px;
    padding: 1px 5px;
    font-size: 10px;
    color: rgba(255,255,255,0.35);
    font-family: monospace;
}

/* ─── Laser Cursor ────────────────────────────────────────────── */
.laser-cursor {
    position: absolute;
    transform: translate(-50%, -50%);
    pointer-events: none;
    z-index: 20;
    width: 18px;
    height: 18px;
}
.laser-cursor::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: radial-gradient(circle, #ff3030 0%, #ff6060 40%, transparent 70%);
    animation: laser-pulse 0.8s ease-in-out infinite;
    box-shadow: 0 0 12px 4px rgba(255,48,48,0.6);
}
@keyframes laser-pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.4); opacity: 0.7; }
}

/* ─── Text Input Overlay ──────────────────────────────────────── */
.text-overlay {
    position: absolute;
    z-index: 15;
    transform: translateY(-50%);
}
.text-input-overlay {
    background: transparent;
    border: none;
    outline: none;
    font-family: 'Cairo', sans-serif;
    font-weight: 700;
    caret-color: white;
    min-width: 200px;
    direction: rtl;
    text-shadow: 0 0 8px currentColor;
}
.text-input-overlay::placeholder {
    color: rgba(255,255,255,0.25);
    font-size: 14px;
}

/* ─── Shortcuts Bar ───────────────────────────────────────────── */
.wb-shortcuts {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 5px 14px;
    background: rgba(0,0,0,0.4);
    border-top: 1px solid rgba(255,255,255,0.05);
    flex-shrink: 0;
    flex-wrap: wrap;
    justify-content: flex-end;
}
.wb-shortcuts span {
    font-size: 10px;
    color: rgba(255,255,255,0.22);
    font-family: monospace;
    letter-spacing: 0.04em;
}
</style>
