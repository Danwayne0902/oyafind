const API_PATH = '/api/v1/locations';
const STORAGE_KEY = 'oyafind.public-location-catalogue.v2';
const PAGE_SIZE = 100;
const MAX_CACHED_QUERIES = 12;
const MAX_CACHED_LOCATIONS = 100;

function emptyCache(version) {
    return { version, queries: Object.create(null) };
}

function getStorage() {
    try {
        return globalThis.localStorage;
    } catch {
        return null;
    }
}

function normalizeQuery(value) {
    return typeof value === 'string' ? value.trim().slice(0, 100) : '';
}

function normalizeFilter(value) {
    return typeof value === 'string' && /^[A-Za-z0-9-]{1,100}$/.test(value) ? value : '';
}

function catalogueKey(query, type, parent) {
    return `${query}|${type}|${parent}`;
}

function publicLocation(location) {
    if (!location || (typeof location.id !== 'number' && typeof location.id !== 'string')
        || typeof location.name !== 'string'
        || typeof location.slug !== 'string') {
        return null;
    }

    const parent = location.parent && typeof location.parent.id !== 'undefined'
        && typeof location.parent.name === 'string'
        && typeof location.parent.slug === 'string'
        ? {
            id: location.parent.id,
            name: location.parent.name,
            slug: location.parent.slug,
            location_type: typeof location.parent.location_type === 'string'
                ? location.parent.location_type
                : null,
        }
        : null;

    return {
        id: location.id,
        name: location.name,
        slug: location.slug,
        location_type: typeof location.location_type === 'string' ? location.location_type : null,
        parent,
    };
}

function readCache(storage) {
    if (!storage) return null;

    try {
        const value = JSON.parse(storage.getItem(STORAGE_KEY) ?? 'null');
        if (!value || typeof value.version !== 'string' || !value.queries || typeof value.queries !== 'object') {
            return null;
        }

        const queries = Object.create(null);
        for (const [query, entry] of Object.entries(value.queries)) {
            if (!entry || !Array.isArray(entry.items)) continue;
            queries[query] = {
                etag: typeof entry.etag === 'string' ? entry.etag : null,
                fetchedAt: typeof entry.fetchedAt === 'string' ? entry.fetchedAt : null,
                items: entry.items.map(publicLocation).filter(Boolean).slice(0, MAX_CACHED_LOCATIONS),
            };
        }

        return { version: value.version, queries };
    } catch {
        return null;
    }
}

function writeCache(storage, cache) {
    if (!storage) return;

    try {
        const queries = Object.entries(cache.queries)
            .slice(-MAX_CACHED_QUERIES)
            .reduce((result, [query, entry]) => {
                result[query] = entry;
                return result;
            }, Object.create(null));
        storage.setItem(STORAGE_KEY, JSON.stringify({ version: cache.version, queries }));
    } catch {
        // Catalogue browsing remains available when local storage is unavailable or full.
    }
}

function cachedItems(cache, key, query) {
    const exact = cache?.queries && Object.hasOwn(cache.queries, key)
        ? cache.queries[key]
        : null;
    if (exact) return exact.items;

    const knownItems = Object.values(cache?.queries ?? {}).flatMap((entry) => entry.items);
    const uniqueItems = [...new Map(knownItems.map((item) => [String(item.id), item])).values()];
    if (!query) return uniqueItems.slice(0, MAX_CACHED_LOCATIONS);

    const needle = query.toLocaleLowerCase();
    return uniqueItems.filter((item) =>
        item.name.toLocaleLowerCase().includes(needle)
        || item.slug.toLocaleLowerCase().includes(needle)
        || item.parent?.name.toLocaleLowerCase().includes(needle),
    ).slice(0, MAX_CACHED_LOCATIONS);
}

/**
 * Load one bounded page from OyaFind's reviewed public catalogue.
 * Only allowlisted public location fields and freshness validators are persisted.
 */
export async function getLocationChoices(query = '', {
    storage = getStorage(),
    fetchImpl = globalThis.fetch,
    now = () => new Date(),
    type = '',
    parent = '',
} = {}) {
    const normalizedQuery = normalizeQuery(query);
    const normalizedType = normalizeFilter(type);
    const normalizedParent = normalizeFilter(parent);
    const key = catalogueKey(normalizedQuery, normalizedType, normalizedParent);
    let cache = readCache(storage);
    const previous = cache?.queries && Object.hasOwn(cache.queries, key)
        ? cache.queries[key]
        : null;
    const params = new URLSearchParams({ limit: String(PAGE_SIZE) });
    if (normalizedQuery) params.set('q', normalizedQuery);
    if (normalizedType) params.set('type', normalizedType);
    if (normalizedParent) params.set('parent', normalizedParent);

    const headers = { Accept: 'application/json' };
    if (previous?.etag) headers['If-None-Match'] = previous.etag;

    let response;
    try {
        response = await fetchImpl(`${API_PATH}?${params.toString()}`, {
            method: 'GET',
            cache: 'no-store',
            headers,
            credentials: 'same-origin',
        });
    } catch {
        const items = cachedItems(cache, key, normalizedQuery);
        const hasCachedCatalogue = cache && Object.keys(cache.queries).length > 0;
        if (hasCachedCatalogue) {
            return {
                items,
                state: 'stale',
                catalogueVersion: cache.version,
                etag: previous?.etag ?? null,
                fetchedAt: previous?.fetchedAt ?? null,
            };
        }

        throw new Error('The area list is unavailable right now. You can still browse all areas.');
    }

    const responseVersion = response.headers.get('X-Catalogue-Version');
    if (response.status === 304) {
        if (!cache || !previous) {
            throw new Error('The area list could not be refreshed. You can still browse all areas.');
        }
        if (responseVersion && responseVersion !== cache.version) {
            cache = emptyCache(responseVersion);
            writeCache(storage, cache);
            throw new Error('The area list changed and could not be refreshed. You can still browse all areas.');
        }

        const refreshed = {
            ...previous,
            etag: response.headers.get('ETag') ?? previous.etag,
            fetchedAt: now().toISOString(),
        };
        cache.queries[key] = refreshed;
        writeCache(storage, cache);

        return {
            items: refreshed.items,
            state: 'fresh',
            catalogueVersion: cache.version,
            etag: refreshed.etag,
            fetchedAt: refreshed.fetchedAt,
        };
    }

    if (!response.ok) {
        const items = cachedItems(cache, key, normalizedQuery);
        if (cache && Object.keys(cache.queries).length > 0) {
            return {
                items,
                state: 'stale',
                catalogueVersion: cache.version,
                etag: previous?.etag ?? null,
                fetchedAt: previous?.fetchedAt ?? null,
            };
        }
        throw new Error('The area list is unavailable right now. You can still browse all areas.');
    }

    let payload;
    try {
        payload = await response.json();
    } catch {
        throw new Error('The area list could not be read. You can still browse all areas.');
    }

    const version = responseVersion ?? payload?.meta?.catalogueVersion;
    if (typeof version !== 'string' || !Array.isArray(payload?.data)) {
        throw new Error('The area list could not be read. You can still browse all areas.');
    }

    // A new public projection invalidates every cached query from the prior version.
    if (cache?.version !== version) cache = emptyCache(version);
    const items = payload.data.map(publicLocation).filter(Boolean).slice(0, MAX_CACHED_LOCATIONS);
    const etag = response.headers.get('ETag');
    const fetchedAt = now().toISOString();
    cache.queries[key] = { etag, fetchedAt, items };
    writeCache(storage, cache);

    return {
        items,
        state: 'fresh',
        catalogueVersion: version,
        etag,
        fetchedAt,
    };
}
