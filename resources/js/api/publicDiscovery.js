const API_BASE = '/api/v1';

async function requestJson(path) {
    let response;
    try {
        response = await fetch(`${API_BASE}${path}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
    } catch {
        throw new Error('Connection problem. Check your internet connection and try again.');
    }

    let payload;
    try {
        payload = await response.json();
    } catch {
        throw new Error('The service returned an unreadable response. Please try again.');
    }

    if (!response.ok) {
        const message = typeof payload?.message === 'string' ? payload.message : 'The request could not be completed.';
        const error = new Error(message);
        error.status = response.status;
        error.errors = payload?.errors ?? {};
        throw error;
    }

    return payload;
}

export async function getCategories() {
    const payload = await requestJson('/categories');
    return Array.isArray(payload?.data) ? payload.data : [];
}

export async function getOpportunities(filters = {}) {
    const allowed = ['q', 'category', 'location', 'work_mode', 'sort', 'page', 'per_page'];
    const params = new URLSearchParams();
    for (const key of allowed) {
        const value = filters[key];
        if (value !== undefined && value !== null && String(value).trim() !== '') {
            params.set(key, String(value).trim());
        }
    }
    const suffix = params.size ? `?${params.toString()}` : '';
    const payload = await requestJson(`/opportunities${suffix}`);
    return {
        data: Array.isArray(payload?.data) ? payload.data : [],
        meta: payload?.meta ?? { current_page: 1, per_page: 15, total: 0, last_page: 1 },
    };
}

export async function getOpportunity(slug) {
    const payload = await requestJson(`/opportunities/${encodeURIComponent(slug)}`);
    if (!payload?.data || typeof payload.data !== 'object') {
        throw new Error('The service returned incomplete opportunity details.');
    }
    return payload.data;
}
