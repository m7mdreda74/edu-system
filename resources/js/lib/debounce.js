/**
 * Delay a call until the caller stops firing for `wait` milliseconds.
 * Used to keep filter inputs from hitting the server on every keystroke.
 */
export function debounce(fn, wait = 300) {
    let timer = null;

    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), wait);
    };
}
