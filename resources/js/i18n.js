/**
 * Minimal i18n for the Vue app — no runtime dependency.
 *
 * Adding a language means dropping one file into `lang/`: copy `lang/en.json`
 * to `lang/<code>.json`, translate the values, done. The glob below picks it up
 * at build time, and the language switcher lists it automatically using the
 * `_name` key. No code changes anywhere.
 *
 * Server-side strings (console output, validation errors, e-mail subjects) are
 * translated separately by Laravel from `lang/<code>/*.php`.
 */
import { computed, reactive } from 'vue';

const STORAGE_KEY = 'webhookhub-locale';
const FALLBACK = 'en';

const catalogs = {};

for (const [path, module] of Object.entries(import.meta.glob('../../lang/*.json', { eager: true }))) {
    const code = path.match(/([^/]+)\.json$/)[1];
    catalogs[code] = module.default ?? module;
}

/** Language codes that actually have a catalog, `en` first, then alphabetical. */
export const available = Object.keys(catalogs).sort((a, b) => {
    if (a === FALLBACK) return -1;
    if (b === FALLBACK) return 1;
    return a.localeCompare(b);
});

/** Display names come from each catalog's `_name`, so the list stays self-describing. */
export const languageName = (code) => catalogs[code]?._name ?? code;

function detect() {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored && catalogs[stored]) return stored;

    // What the server rendered into <html lang="…"> (APP_LOCALE), then the browser.
    const candidates = [document.documentElement.lang, ...(navigator.languages ?? [navigator.language])];

    for (const candidate of candidates) {
        if (!candidate) continue;
        const code = candidate.toLowerCase().split('-')[0];
        if (catalogs[code]) return code;
    }

    return FALLBACK;
}

const state = reactive({ locale: detect() });

export const locale = computed(() => state.locale);

export function setLocale(code) {
    if (!catalogs[code]) return;

    state.locale = code;
    localStorage.setItem(STORAGE_KEY, code);
    document.documentElement.lang = code;
}

function lookup(code, key) {
    const value = catalogs[code]?.[key];
    return typeof value === 'string' ? value : null;
}

/**
 * Translate `key`, filling `{placeholders}` from `params`.
 *
 * A value may carry a singular and a plural form separated by `|`
 * ("{count} message|{count} messages"); the form is chosen by `params.count`.
 * Languages that do not inflect after a numeral just repeat the same text.
 */
export function t(key, params = {}) {
    let text = lookup(state.locale, key) ?? lookup(FALLBACK, key);

    // An untranslated key is more useful on screen than an empty string.
    if (text === null) {
        if (import.meta.env.DEV) console.warn(`[i18n] missing key: ${key}`);
        return key;
    }

    if (text.includes('|')) {
        const forms = text.split('|');
        text = Number(params.count) === 1 ? forms[0] : forms[forms.length - 1];
    }

    return text.replace(/\{(\w+)\}/g, (match, name) => (name in params ? String(params[name]) : match));
}

export function useI18n() {
    return { t, locale, setLocale, available, languageName };
}

export default {
    install(app) {
        // Exposed as $t so templates can translate without a per-component import.
        app.config.globalProperties.$t = t;
        document.documentElement.lang = state.locale;
    },
};
