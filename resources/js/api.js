const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

class ApiError extends Error {
    constructor(message, status, errors) {
        super(message);
        this.status = status;
        this.errors = errors ?? {};
    }
}

async function request(method, url, body) {
    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(body ? { 'Content-Type': 'application/json' } : {}),
            'X-CSRF-TOKEN': csrf(),
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    if (response.status === 401 || response.status === 419) {
        window.location.href = '/login';
        throw new ApiError('Lejárt a munkamenet.', response.status);
    }

    const text = await response.text();
    const data = text ? JSON.parse(text) : null;

    if (!response.ok) {
        const errors = data?.errors ?? {};
        const first = Object.values(errors).flat()[0];
        throw new ApiError(first ?? data?.message ?? `Hiba (${response.status})`, response.status, errors);
    }

    return data;
}

const query = (params) => {
    const search = new URLSearchParams();
    Object.entries(params ?? {}).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            search.append(key, value);
        }
    });
    const string = search.toString();
    return string ? `?${string}` : '';
};

export const api = {
    tree: () => request('GET', '/api/tree'),
    move: (payload) => request('POST', '/api/tree/move', payload),

    createGroup: (payload) => request('POST', '/api/groups', payload),
    updateGroup: (id, payload) => request('PUT', `/api/groups/${id}`, payload),
    deleteGroup: (id) => request('DELETE', `/api/groups/${id}`),

    createEndpoint: (payload) => request('POST', '/api/endpoints', payload),
    endpoint: (id) => request('GET', `/api/endpoints/${id}`),
    updateEndpoint: (id, payload) => request('PUT', `/api/endpoints/${id}`, payload),
    deleteEndpoint: (id) => request('DELETE', `/api/endpoints/${id}`),
    rotateSecret: (id) => request('POST', `/api/endpoints/${id}/rotate-secret`),
    clearMessages: (id) => request('DELETE', `/api/endpoints/${id}/messages`),

    messages: (params) => request('GET', `/api/messages${query(params)}`),
    message: (uuid) => request('GET', `/api/messages/${uuid}`),
    deleteMessage: (uuid) => request('DELETE', `/api/messages/${uuid}`),
    replayMessage: (uuid) => request('POST', `/api/messages/${uuid}/replay`),
    variables: (uuid) => request('GET', `/api/messages/${uuid}/variables`),

    rules: (params) => request('GET', `/api/rules${query(params)}`),
    createRule: (payload) => request('POST', '/api/rules', payload),
    updateRule: (id, payload) => request('PUT', `/api/rules/${id}`, payload),
    deleteRule: (id) => request('DELETE', `/api/rules/${id}`),

    testConditions: (payload) => request('POST', '/api/test/conditions', payload),
    testAction: (payload) => request('POST', '/api/test/action', payload),
};

export { ApiError };
