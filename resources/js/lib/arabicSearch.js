/** Normalize spelling variations for user-facing search without changing displayed text. */
export function normalizeSearchText(value) {
    return String(value ?? '')
        .normalize('NFC')
        .toLowerCase()
        .replace(/[أإآٱ]/g, 'ا')
        .replace(/ؤ/g, 'و')
        .replace(/ئ/g, 'ي')
        .replace(/ء/g, '')
        .replace(/ة/g, 'ه')
        .replace(/[\u064b-\u065f\u0670\u0640]/g, '')
        .trim();
}
