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

export async function getSavedOpportunities() {
    const payload = await request('/me/saved-opportunities');
    return payload.data;
}

export async function saveOpportunity(opportunityId) {
    const payload = await request('/me/saved-opportunities', {
        method: 'POST',
        body: { opportunity_id: opportunityId },
    });
    return payload.data;
}

export async function unsaveOpportunity(opportunityId) {
    return request(`/me/saved-opportunities/${encodeURIComponent(opportunityId)}`, {
        method: 'DELETE',
    });
}

export async function getMyApplications() {
    const payload = await request('/me/applications');
    return payload.data;
}

export async function trackApplication({ opportunity_id, status = 'interested', notes = null, applied_at = null }) {
    const payload = await request('/me/applications', {
        method: 'POST',
        body: {
            opportunity_id,
            status,
            notes,
            applied_at,
        },
    });
    return payload.data;
}

export async function updateApplication(id, { status, notes, applied_at }) {
    const payload = await request(`/me/applications/${encodeURIComponent(id)}`, {
        method: 'PATCH',
        body: {
            ...(status !== undefined ? { status } : {}),
            ...(notes !== undefined ? { notes } : {}),
            ...(applied_at !== undefined ? { applied_at } : {}),
        },
    });
    return payload.data;
}

export async function deleteApplication(id) {
    return request(`/me/applications/${encodeURIComponent(id)}`, {
        method: 'DELETE',
    });
}

