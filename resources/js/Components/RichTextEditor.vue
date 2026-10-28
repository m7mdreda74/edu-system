<script setup>
import { ref, watch, onMounted, computed } from 'vue';

const props = defineProps({
    modelValue:  { type: String,  default: '' },
    placeholder: { type: String,  default: 'اكتب النص هنا مع التنسيق المطلوب...' },
    minHeight:   { type: String,  default: '180px' },
    disabled:    { type: Boolean, default: false },
    label:       { type: String,  default: '' },
    error:       { type: String,  default: '' },
});

const emit = defineEmits(['update:modelValue']);

const editorRef = ref(null);
const isSourceMode = ref(false);
const rawHtml = ref(props.modelValue || '');

// Keep internal and external models in sync
watch(() => props.modelValue, (newVal) => {
    if (editorRef.value && editorRef.value.innerHTML !== newVal) {
        editorRef.value.innerHTML = newVal || '';
    }
    rawHtml.value = newVal || '';
});

onMounted(() => {
    if (editorRef.value) {
        editorRef.value.innerHTML = props.modelValue || '';
    }
});

function onInput() {
    if (!editorRef.value) return;
    const html = editorRef.value.innerHTML;
    // Don't emit if only empty break
    const sanitized = html === '<br>' || html === '<div><br></div>' ? '' : html;
    rawHtml.value = sanitized;
    emit('update:modelValue', sanitized);
}

function onSourceInput(e) {
    rawHtml.value = e.target.value;
    if (editorRef.value) {
        editorRef.value.innerHTML = e.target.value;
    }
    emit('update:modelValue', e.target.value);
}

function toggleSourceMode() {
    isSourceMode.value = !isSourceMode.value;
    if (!isSourceMode.value && editorRef.value) {
        editorRef.value.innerHTML = rawHtml.value;
    }
}

// ── Rich Text Commands ──────────────────────────────────────────
function exec(command, value = null) {
    if (props.disabled || isSourceMode.value) return;
    editorRef.value?.focus();
    document.execCommand(command, false, value);
    onInput();
}

function formatBlock(tag) {
    exec('formatBlock', `<${tag}>`);
}

function setFontSize(size) {
    exec('fontSize', size);
}

function setTextColor(color) {
    exec('foreColor', color);
}

function setHighlightColor(color) {
    exec('hiliteColor', color);
}

function insertLink() {
    const url = prompt('أدخل رابط الموقع (URL):', 'https://');
    if (url && url !== 'https://') {
        exec('createLink', url);
    }
}

function insertTable() {
    const rows = prompt('عدد الصفوف:', '3');
    const cols = prompt('عدد الأعمدة:', '3');
    const r = parseInt(rows, 10);
    const c = parseInt(cols, 10);
    if (!r || !c || r <= 0 || c <= 0) return;

    let html = '<table class="border-collapse border border-surface-300 dark:border-surface-600 my-3 w-full text-center">';
    for (let i = 0; i < r; i++) {
        html += '<tr>';
        for (let j = 0; j < c; j++) {
            if (i === 0) {
                html += '<th class="border border-surface-300 dark:border-surface-600 bg-surface-100 dark:bg-surface-800 p-2 font-bold text-xs">عنوان ' + (j + 1) + '</th>';
            } else {
                html += '<td class="border border-surface-300 dark:border-surface-600 p-2 text-xs">خلية ' + i + '-' + (j + 1) + '</td>';
            }
        }
        html += '</tr>';
    }
    html += '</table><p><br></p>';
    exec('insertHTML', html);
}

// Word & Character count
const textLength = computed(() => {
    const tmp = document.createElement('div');
    tmp.innerHTML = rawHtml.value;
    return tmp.textContent?.length || 0;
});

const wordCount = computed(() => {
    const tmp = document.createElement('div');
    tmp.innerHTML = rawHtml.value;
    const txt = tmp.textContent?.trim() || '';
    return txt ? txt.split(/\s+/).length : 0;
});
</script>

<template>
    <div class="rich-text-editor-container border rounded-2xl overflow-hidden bg-white dark:bg-surface-900 transition-all focus-within:ring-2 focus-within:ring-primary-500/30"
         :class="error ? 'border-red-400 dark:border-red-600' : 'border-surface-200 dark:border-surface-700'"
    >
        <div v-if="label" class="px-3.5 pt-2.5 pb-1 text-xs font-bold text-surface-700 dark:text-surface-300 flex items-center justify-between">
            <span>{{ label }}</span>
            <span class="text-[11px] font-normal text-surface-400">محرر منسق (أدوات معالجة النصوص)</span>
        </div>

        <!-- ── Word-like Toolbar ──────────────────────────────── -->
        <div class="bg-surface-50 dark:bg-surface-800/70 border-b border-surface-200 dark:border-surface-700 p-1.5 flex flex-wrap items-center gap-1 text-xs select-none">

            <!-- Formatting: Bold, Italic, Underline, Strikethrough -->
            <div class="flex items-center bg-white dark:bg-surface-800 rounded-lg p-0.5 border border-surface-200 dark:border-surface-700 shadow-2xs">
                <button type="button" @click="exec('bold')" title="غامق (Ctrl+B)" class="toolbar-btn font-bold">
                    B
                </button>
                <button type="button" @click="exec('italic')" title="مائل (Ctrl+I)" class="toolbar-btn italic font-serif">
                    I
                </button>
                <button type="button" @click="exec('underline')" title="تسطير (Ctrl+U)" class="toolbar-btn underline">
                    U
                </button>
                <button type="button" @click="exec('strikeThrough')" title="يتوسطه خط" class="toolbar-btn line-through">
                    S
                </button>
            </div>

            <!-- Headings / Block format -->
            <select
                @change="formatBlock($event.target.value)"
                class="toolbar-select text-xs py-1 px-2 rounded-lg border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800"
                title="نمط الفقرة أو العنوان"
            >
                <option value="p">فقرة عادية</option>
                <option value="h2">عنوان رئيسي H2</option>
                <option value="h3">عنوان فرعي H3</option>
                <option value="blockquote">اقتباس بارز</option>
                <option value="pre">نص برمجي / معادلة</option>
            </select>

            <!-- Font Size -->
            <select
                @change="setFontSize($event.target.value)"
                class="toolbar-select text-xs py-1 px-1.5 rounded-lg border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800"
                title="حجم الخط"
            >
                <option value="2">خط صغير</option>
                <option value="3" selected>خط عادي</option>
                <option value="4">خط متوسط</option>
                <option value="5">خط كبير</option>
                <option value="6">خط عريض جداً</option>
            </select>

            <!-- Colors Palette Dropdown -->
            <div class="flex items-center gap-1 bg-white dark:bg-surface-800 rounded-lg p-0.5 border border-surface-200 dark:border-surface-700 shadow-2xs">
                <button type="button" @click="setTextColor('#1e293b')" class="w-4 h-4 rounded-full bg-slate-800 border border-slate-400" title="أسود / غامق"></button>
                <button type="button" @click="setTextColor('#2563eb')" class="w-4 h-4 rounded-full bg-blue-600 border border-blue-400" title="أزرق"></button>
                <button type="button" @click="setTextColor('#16a34a')" class="w-4 h-4 rounded-full bg-green-600 border border-green-400" title="أخضر"></button>
                <button type="button" @click="setTextColor('#dc2626')" class="w-4 h-4 rounded-full bg-red-600 border border-red-400" title="أحمر"></button>
                <button type="button" @click="setTextColor('#d97706')" class="w-4 h-4 rounded-full bg-amber-500 border border-amber-300" title="برتقالي / ذهبي"></button>
                <span class="w-px h-3.5 bg-surface-200 dark:bg-surface-700 mx-0.5"></span>
                <!-- Highlight colors -->
                <button type="button" @click="setHighlightColor('#fef08a')" class="w-4 h-4 rounded bg-yellow-200 border border-yellow-400 text-[10px] font-bold text-yellow-900" title="تظليل أصفر">A</button>
                <button type="button" @click="setHighlightColor('#bbf7d0')" class="w-4 h-4 rounded bg-green-200 border border-green-400 text-[10px] font-bold text-green-900" title="تظليل أخضر">A</button>
                <button type="button" @click="setHighlightColor('transparent')" class="w-4 h-4 rounded bg-white border border-surface-300 text-[9px] text-surface-500" title="إزالة التظليل">✕</button>
            </div>

            <!-- Alignment -->
            <div class="flex items-center bg-white dark:bg-surface-800 rounded-lg p-0.5 border border-surface-200 dark:border-surface-700 shadow-2xs">
                <button type="button" @click="exec('justifyRight')" title="محاذاة لليمين" class="toolbar-btn">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 6H3M21 12H9M21 18H5"/></svg>
                </button>
                <button type="button" @click="exec('justifyCenter')" title="توسيط" class="toolbar-btn">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6H6M21 12H3M18 18H6"/></svg>
                </button>
                <button type="button" @click="exec('justifyLeft')" title="محاذاة لليسار" class="toolbar-btn">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 6H3M15 12H3M19 18H3"/></svg>
                </button>
                <button type="button" @click="exec('justifyFull')" title="ضبط متساوي" class="toolbar-btn">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 6H3M21 12H3M21 18H3"/></svg>
                </button>
            </div>

            <!-- Lists -->
            <div class="flex items-center bg-white dark:bg-surface-800 rounded-lg p-0.5 border border-surface-200 dark:border-surface-700 shadow-2xs">
                <button type="button" @click="exec('insertUnorderedList')" title="قائمة نقطية" class="toolbar-btn">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                </button>
                <button type="button" @click="exec('insertOrderedList')" title="قائمة مرقمة" class="toolbar-btn">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 6h11M10 12h11M10 18h11M4 6h1v4M4 10h2M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/></svg>
                </button>
            </div>

            <!-- Insert Rich Objects: Table, Link, Line, Clear -->
            <div class="flex items-center bg-white dark:bg-surface-800 rounded-lg p-0.5 border border-surface-200 dark:border-surface-700 shadow-2xs">
                <button type="button" @click="insertTable" title="إدراج جدول" class="toolbar-btn text-primary-600 font-bold flex items-center gap-0.5 px-1.5">
                    <span>⊞</span>
                    <span class="text-[10px]">جدول</span>
                </button>
                <button type="button" @click="insertLink" title="إدراج رابط" class="toolbar-btn">
                    🔗
                </button>
                <button type="button" @click="exec('insertHorizontalRule')" title="خط فاصل" class="toolbar-btn font-bold">
                    ―
                </button>
                <button type="button" @click="exec('removeFormat')" title="مسح التنسيق" class="toolbar-btn text-surface-400 hover:text-red-500">
                    🧹
                </button>
            </div>

            <!-- Undo / Redo / Source code -->
            <div class="ms-auto flex items-center gap-1">
                <button type="button" @click="exec('undo')" title="تراجع (Ctrl+Z)" class="toolbar-btn">
                    ↩
                </button>
                <button type="button" @click="exec('redo')" title="إعادة (Ctrl+Y)" class="toolbar-btn">
                    ↪
                </button>
                <button
                    type="button"
                    @click="toggleSourceMode"
                    title="عرض كود HTML"
                    class="toolbar-btn px-1.5 text-[10px] font-mono"
                    :class="isSourceMode ? 'bg-primary-600 text-white' : 'text-surface-500'"
                >
                    &lt;/&gt;
                </button>
            </div>
        </div>

        <!-- ── Editable Content Area ──────────────────────────── -->
        <div class="relative">
            <!-- Visual Contenteditable Editor -->
            <div
                v-show="!isSourceMode"
                ref="editorRef"
                contenteditable="true"
                @input="onInput"
                dir="rtl"
                class="editor-content p-4 outline-none overflow-y-auto font-sans leading-relaxed text-sm text-surface-900 dark:text-surface-100"
                :style="{ minHeight: minHeight, maxHeight: '420px' }"
                :data-placeholder="placeholder"
            ></div>

            <!-- Raw HTML Code Editor Mode -->
            <textarea
                v-if="isSourceMode"
                :value="rawHtml"
                @input="onSourceInput"
                dir="ltr"
                class="w-full p-4 font-mono text-xs bg-slate-900 text-emerald-400 outline-none resize-y"
                :style="{ minHeight: minHeight, maxHeight: '420px' }"
            ></textarea>
        </div>

        <!-- ── Bottom Info Bar ────────────────────────────────── -->
        <div class="bg-surface-50 dark:bg-surface-800/40 border-t border-surface-200 dark:border-surface-700 px-3 py-1.5 text-[11px] text-surface-400 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span>{{ wordCount }} كلمة</span>
                <span>{{ textLength }} حرف</span>
            </div>
            <span v-if="error" class="text-red-500 font-semibold text-xs">{{ error }}</span>
            <span v-else class="text-surface-400">يدعم تنسيقات الجداول والعناوين والقوائم والألوان</span>
        </div>
    </div>
</template>

<style>
.toolbar-btn {
    @apply w-6 h-6 flex items-center justify-center rounded text-xs text-surface-700 dark:text-surface-200 hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors;
}
.editor-content:empty:before {
    content: attr(data-placeholder);
    @apply text-surface-400 dark:text-surface-500 pointer-events-none;
}
.editor-content table {
    @apply border-collapse border border-surface-300 dark:border-surface-600 my-3 w-full text-center;
}
.editor-content th, .editor-content td {
    @apply border border-surface-300 dark:border-surface-600 p-2;
}
.editor-content th {
    @apply bg-surface-100 dark:bg-surface-800 font-bold;
}
.editor-content blockquote {
    @apply border-r-4 border-primary-500 pe-3 my-2 text-surface-600 dark:text-surface-300 italic bg-surface-50 dark:bg-surface-800/40 py-1;
}
.editor-content pre {
    @apply bg-surface-100 dark:bg-surface-800 p-2.5 rounded-lg font-mono text-xs my-2 overflow-x-auto;
}
.editor-content h2 {
    @apply text-xl font-bold my-2 text-surface-900 dark:text-white;
}
.editor-content h3 {
    @apply text-lg font-semibold my-1.5 text-surface-900 dark:text-white;
}
.editor-content ul {
    @apply list-disc list-inside my-1.5 space-y-1;
}
.editor-content ol {
    @apply list-decimal list-inside my-1.5 space-y-1;
}
</style>
