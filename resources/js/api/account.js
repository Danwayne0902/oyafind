const API_BASE = '/api/v1';

let csrfToken = null;

async function parseResponse(response) {
    let payload;
    try {
        payload = await response.json();
    } catch {
        throw new Error('The service returned an unreadable response. Please try again.');
    }

    if (!response.ok) {
        const error = new Error(payload?.message ?? 'The request could not be completed.');
        error.status = response.status;
        error.errors = payload?.errors ?? {};
        throw error;
    }

    return payload;
}

async function request(path, { method = 'GET', body } = {}) {
    if (method !== 'GET' && !csrfToken) {
        const tokenResponse = await fetch(`${API_BASE}/auth/csrf-token`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const tokenPayload = await parseResponse(tokenResponse);
        csrfToken = tokenPayload.csrf_token;
    }

    let response;
    try {
        response = await fetch(`${API_BASE}${path}`, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...(body ? { 'Content-Type': 'application/json' } : {}),
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            ...(body ? { body: JSON.stringify(body) } : {}),
        });
    } catch {
        throw new Error('Connection problem. Check your internet connection and try again.');
    }

    return parseResponse(response);
}

export async function getRegistrationStatus() {
    const payload = await request('/auth/registration-status');
    return payload.data;
}

export async function registerAccount(details) {
    const result = await request('/auth/register', { method: 'POST', body: details });
    csrfToken = null;
    return result;
}

export async function verifyEmail({ userId, hash, expires, signature }) {
    const query = new URLSearchParams({ expires, signature });
    const result = await request(`/auth/verify-email/${encodeURIComponent(userId)}/${encodeURIComponent(hash)}?${query}`, {
        method: 'POST',
        body: {},
    });
    csrfToken = null;
    return result;
}

export async function signIn(credentials) {
    const result = await request('/auth/login', { method: 'POST', body: credentials });
    csrfToken = null;
    return result;
}

export async function signOut() {
    const result = await request('/auth/logout', { method: 'POST', body: {} });
    csrfToken = null;
    return result;
}

export async function getMyProfile() {
    const payload = await request('/me');
    return payload.data;
}

export async function updateMyProfile(profile) {
    const payload = await request('/me', { method: 'PATCH', body: profile });
    return payload.data;
}
