const KEY = 'webhookhub-theme';

export const MODES = ['system', 'light', 'dark'];

export function storedMode() {
    const mode = localStorage.getItem(KEY);
    return MODES.includes(mode) ? mode : 'system';
}

export function prefersDark() {
    return window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false;
}

export function applyMode(mode) {
    const dark = mode === 'dark' || (mode === 'system' && prefersDark());
    document.documentElement.classList.toggle('dark', dark);
    localStorage.setItem(KEY, mode);
    return dark;
}

/** The OS setting is only followed while the mode is "system". */
export function watchSystem(onChange) {
    window.matchMedia?.('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (storedMode() === 'system') onChange();
    });
}
