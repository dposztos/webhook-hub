const KEY = 'webhookhub-theme';

export const MODES = ['system', 'light', 'dark'];

export const MODE_LABELS = {
    system: 'Rendszer szerint',
    light: 'Világos',
    dark: 'Sötét',
};

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

/** A rendszer-beállítás változását csak "system" módban követjük. */
export function watchSystem(onChange) {
    window.matchMedia?.('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (storedMode() === 'system') onChange();
    });
}
