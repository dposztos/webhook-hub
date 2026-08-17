export function formatTime(iso) {
    if (!iso) return '';
    const date = new Date(iso);
    const now = new Date();
    const sameDay = date.toDateString() === now.toDateString();

    return sameDay
        ? date.toLocaleTimeString('hu-HU', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
        : date.toLocaleString('hu-HU', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}

export function relativeTime(iso) {
    if (!iso) return 'még nem érkezett';
    const seconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000);

    if (seconds < 60) return 'most';
    if (seconds < 3600) return `${Math.floor(seconds / 60)} perce`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)} órája`;
    return `${Math.floor(seconds / 86400)} napja`;
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
        // A vágólap-API csak HTTPS-en (vagy localhoston) elérhető – kézi tartalék.
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
