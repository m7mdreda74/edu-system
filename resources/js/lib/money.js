/**
 * Money helpers.
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

/** [{day:0,start:'16:00',end:'17:30'}] → "الأحد 16:00–17:30" */
export function formatSchedule(schedules) {
    if (!schedules?.length) return '';
    return schedules
        .map((s) => `${DAY_NAMES[s.day] ?? ''} ${s.start}–${s.end}`)
        .join('، ');
}
