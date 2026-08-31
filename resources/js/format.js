import { locale, t } from './i18n';

/** Dates follow the active UI language, so a locale switch reformats them too. */
const intlLocale = () => (locale.value === 'en' ? 'en-GB' : locale.value);

export function formatTime(iso) {
    if (!iso) return '';
    const date = new Date(iso);
    const now = new Date();
    const sameDay = date.toDateString() === now.toDateString();

    return sameDay
        ? date.toLocaleTimeString(intlLocale(), { hour: '2-digit', minute: '2-digit', second: '2-digit' })
        : date.toLocaleString(intlLocale(), { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}

export function relativeTime(iso) {
    if (!iso) return t('time.never');
    const seconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000);

    if (seconds < 60) return t('time.now');
    if (seconds < 3600) return t('time.minutesAgo', { count: Math.floor(seconds / 60) });
    if (seconds < 86400) return t('time.hoursAgo', { count: Math.floor(seconds / 3600) });
    return t('time.daysAgo', { count: Math.floor(seconds / 86400) });
}

export function formatSize(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} kB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export const methodColor = (method) =>
    ({
        GET: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
        POST: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
        PUT: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
        PATCH: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
        DELETE: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    })[method] ?? 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';

export async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch {
        // The clipboard API only exists on HTTPS (or localhost) — manual fallback.
        const area = document.createElement('textarea');
        area.value = text;
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(area);
        return ok;
    }
}

/**
 * A step's name is also the key a template addresses it by, so the field only
 * accepts what a template can use: no accents, no capitals, no spaces. Typed
 * text is converted rather than rejected — an error message on every keystroke
 * would be worse than simply showing what the name becomes.
 *
 * Trailing separators survive here on purpose, or "get_" could never be typed
 * on the way to "get_email"; the server trims them when the rule is saved.
 */
export function stepName(value) {
    return (value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9_]+/g, '_')
        .replace(/_{2,}/g, '_')
        .slice(0, 150);
}
