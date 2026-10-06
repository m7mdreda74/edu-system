/**
 * Money & Formatting helpers.
 *
 * The server stores every amount in the smallest currency unit (dirham of QAR)
 * so nothing ever touches a float. Formatting back to riyals happens here, in
 * one place, so the whole UI agrees on how a price looks.
 */

const formatter = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});

/** 15000 → "150 ر.ق." */
export function formatQAR(smallestUnit) {
    if (smallestUnit === null || smallestUnit === undefined) return '';
    return `${formatter.format(smallestUnit / 100)} ر.ق.`;
}

/** Same as formatQAR, but a zero reads as "free" rather than "0 ر.ق.". */
export function formatPrice(smallestUnit) {
    if (smallestUnit === null || smallestUnit === undefined) return '';
    return smallestUnit === 0 ? 'مجاني' : formatQAR(smallestUnit);
}

/** Monthly subscription price, e.g. "150 ر.ق. / شهرياً". */
export function formatMonthly(smallestUnit) {
    if (smallestUnit === null || smallestUnit === undefined) return '';
    return smallestUnit === 0 ? 'مجاني' : `${formatQAR(smallestUnit)} / شهرياً`;
}

export const DAY_NAMES = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

/**
 * Formats a 24-hour time string ("19:00", "07:30", etc.) to 12-hour format in Arabic
 * e.g. "19:00" → "7 مساءً", "19:30" → "7:30 مساءً", "07:00" → "7 صباحاً"
 */
export function formatTime12(timeStr) {
    if (!timeStr) return '';
    const str = String(timeStr).trim();
    if (str.includes('صباحاً') || str.includes('مساءً')) return str;

    const parts = str.split(':');
    if (parts.length < 1) return str;

    const hour = parseInt(parts[0], 10);
    if (isNaN(hour)) return str;

    const minute = parts.length > 1 ? parseInt(parts[1], 10) : 0;
    const period = hour >= 12 ? 'مساءً' : 'صباحاً';
    const hour12 = hour % 12 === 0 ? 12 : hour % 12;
    const minStr = isNaN(minute) || minute === 0 ? '' : `:${String(minute).padStart(2, '0')}`;

    return `${hour12}${minStr} ${period}`;
}

/** [{day:0,start:'19:00',end:'20:30'}] → "الأحد (7 مساءً إلى 8:30 مساءً)" */
export function formatSchedule(schedules) {
    if (!schedules?.length) return '';
    return schedules
        .map((s) => {
            const dayIdx = s.day ?? s.day_of_week;
            const dayName = DAY_NAMES[dayIdx] ?? '';
            const start = formatTime12(s.start ?? s.start_time);
            const end = formatTime12(s.end ?? s.end_time);

            if (start && end) {
                return `${dayName} (${start} إلى ${end})`;
            }
            if (start) {
                return `${dayName} ${start}`;
            }
            return dayName;
        })
        .filter(Boolean)
        .join('، ');
}
