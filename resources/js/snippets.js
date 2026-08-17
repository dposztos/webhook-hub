/**
 * Turns a stored message back into a ready-to-run command for the usual tools.
 */

// Added by the proxy or the browser rather than the sender — noise on a replay.
const NOISE = [
    'host',
    'content-length',
    'connection',
    'keep-alive',
    'accept-encoding',
    'x-real-ip',
    'x-request-id',
    'postman-token',
    'priority',
    'sec-fetch-dest',
    'sec-fetch-mode',
    'sec-fetch-site',
    'upgrade-insecure-requests',
];

const isNoise = (name) => {
    const key = name.toLowerCase();
    return NOISE.includes(key) || key.startsWith('cf-') || key.startsWith('x-forwarded-') || key.startsWith('sec-ch-');
};

/** Target URL: the endpoint's current address plus the captured path suffix and query. */
export function targetUrl(detail) {
    const base = (detail.endpoint?.url ?? '').replace(/\/$/, '');
    const suffix = detail.path_suffix ? `/${detail.path_suffix}` : '';

    const params = new URLSearchParams();
    Object.entries(detail.query ?? {}).forEach(([key, value]) => {
        if (Array.isArray(value)) {
            value.forEach((item) => params.append(`${key}[]`, item));
        } else if (value !== null && typeof value === 'object') {
            params.append(key, JSON.stringify(value));
        } else {
            params.append(key, value);
        }
    });

    const query = params.toString();

    return `${base}${suffix}${query ? `?${query}` : ''}`;
}

export function usefulHeaders(detail, all = false) {
    return Object.entries(detail.headers ?? {})
        .filter(([name]) => all || !isNoise(name))
        .map(([name, value]) => [normalizeName(name), value]);
}

// Symfony lower-cases header names; the conventional casing reads better.
const normalizeName = (name) =>
    name
        .split('-')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join('-');

const shellQuote = (value) => `'${String(value).replaceAll("'", `'\\''`)}'`;
const psQuote = (value) => `'${String(value).replaceAll("'", "''")}'`;
const jsonQuote = (value) => JSON.stringify(String(value));

const hasBody = (detail) => Boolean(detail.body && detail.body.length);

export const TOOLS = [
    { key: 'curl', label: 'curl', language: 'bash' },
    { key: 'powershell', label: 'PowerShell', language: 'powershell' },
    { key: 'httpie', label: 'HTTPie', language: 'bash' },
    { key: 'python', label: 'Python', language: 'python' },
    { key: 'javascript', label: 'JavaScript', language: 'javascript' },
    { key: 'http', labelKey: 'send.rawHttp', language: 'http' },
];

export function buildSnippet(tool, detail, { allHeaders = false } = {}) {
    const url = targetUrl(detail);
    const headers = usefulHeaders(detail, allHeaders);
    const method = detail.method ?? 'POST';
    const body = detail.body ?? '';

    switch (tool) {
        case 'curl':
            return curl(method, url, headers, body, hasBody(detail));
        case 'powershell':
            return powershell(method, url, headers, body, hasBody(detail));
        case 'httpie':
            return httpie(method, url, headers, body, hasBody(detail));
        case 'python':
            return python(method, url, headers, body, hasBody(detail));
        case 'javascript':
            return javascript(method, url, headers, body, hasBody(detail));
        case 'http':
            return rawHttp(method, url, headers, body, hasBody(detail));
        default:
            return '';
    }
}

function curl(method, url, headers, body, withBody) {
    const lines = [`curl -X ${method} ${shellQuote(url)}`];

    headers.forEach(([name, value]) => lines.push(`  -H ${shellQuote(`${name}: ${value}`)}`));

    if (withBody) {
        lines.push(`  --data-raw ${shellQuote(body)}`);
    }

    return lines.join(' \\\n');
}

function powershell(method, url, headers, body, withBody) {
    const lines = [];

    if (headers.length) {
        lines.push('$headers = @{');
        headers.forEach(([name, value]) => lines.push(`    ${psQuote(name)} = ${psQuote(value)}`));
        lines.push('}', '');
    }

    if (withBody) {
        lines.push(`$body = ${psQuote(body)}`, '');
    }

    const args = [
        `-Method ${method}`,
        `-Uri ${psQuote(url)}`,
        headers.length ? '-Headers $headers' : '',
        withBody ? '-Body $body' : '',
    ].filter(Boolean);

    lines.push(`Invoke-RestMethod ${args.join(' ')}`);

    return lines.join('\n');
}

function httpie(method, url, headers, body, withBody) {
    const lines = [`http ${method} ${shellQuote(url)}`];

    headers.forEach(([name, value]) => lines.push(`  ${shellQuote(`${name}:${value}`)}`));

    if (withBody) {
        lines.push(`  --raw ${shellQuote(body)}`);
    }

    return lines.join(' \\\n');
}

function python(method, url, headers, body, withBody) {
    const lines = ['import requests', '', `url = ${jsonQuote(url)}`];

    if (headers.length) {
        lines.push('headers = {');
        headers.forEach(([name, value]) => lines.push(`    ${jsonQuote(name)}: ${jsonQuote(value)},`));
        lines.push('}');
    }

    if (withBody) {
        lines.push(`data = ${jsonQuote(body)}`);
    }

    const args = [
        'url',
        headers.length ? 'headers=headers' : '',
        withBody ? 'data=data.encode()' : '',
    ].filter(Boolean);

    lines.push('', `response = requests.${method.toLowerCase()}(${args.join(', ')})`, 'print(response.status_code, response.text)');

    return lines.join('\n');
}

function javascript(method, url, headers, body, withBody) {
    const lines = [`const response = await fetch(${jsonQuote(url)}, {`, `    method: ${jsonQuote(method)},`];

    if (headers.length) {
        lines.push('    headers: {');
        headers.forEach(([name, value]) => lines.push(`        ${jsonQuote(name)}: ${jsonQuote(value)},`));
        lines.push('    },');
    }

    if (withBody) {
        lines.push(`    body: ${jsonQuote(body)},`);
    }

    lines.push('});', '', 'console.log(response.status, await response.text());');

    return lines.join('\n');
}

function rawHttp(method, url, headers, body, withBody) {
    let parsed;
    try {
        parsed = new URL(url);
    } catch {
        return '';
    }

    const lines = [
        `${method} ${parsed.pathname}${parsed.search} HTTP/1.1`,
        `Host: ${parsed.host}`,
        ...headers.filter(([name]) => name.toLowerCase() !== 'host').map(([name, value]) => `${name}: ${value}`),
    ];

    if (withBody) {
        lines.push(`Content-Length: ${new TextEncoder().encode(body).length}`, '', body);
    } else {
        lines.push('');
    }

    return lines.join('\n');
}
