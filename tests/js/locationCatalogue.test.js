import assert from 'node:assert/strict';
import test from 'node:test';
import { getLocationChoices } from '../../resources/js/api/locationCatalogue.js';

const STORAGE_KEY = 'oyafind.public-location-catalogue.v1';

function createStorage() {
    const values = new Map();
    return {
        getItem: (key) => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, value),
    };
}

function catalogueResponse({ version = 'synthetic-v1', etag = '"synthetic-v1"', data = [] } = {}) {
    return new Response(JSON.stringify({
        data,
        meta: { catalogueVersion: version, nextCursor: null },
    }), {
        status: 200,
        headers: {
            'Content-Type': 'application/json',
            ETag: etag,
            'X-Catalogue-Version': version,
        },
    });
}

const syntheticArea = {
    id: 7001,
    name: 'Synthetic Area',
    slug: 'synthetic-area',
    location_type: 'synthetic type',
    parent: {
        id: 7000,
        name: 'Synthetic Parent',
        slug: 'synthetic-parent',
        location_type: 'synthetic parent type',
    },
    reviewed_by: 'must not be persisted',
    source_reference: 'must not be persisted',
};

test('fetches only the OyaFind reviewed catalogue and persists an allowlisted public projection', async () => {
    const storage = createStorage();
    const requested = [];
    const result = await getLocationChoices('', {
        storage,
        now: () => new Date('2026-10-23T08:00:00.000Z'),
        fetchImpl: async (url, options) => {
            requested.push({ url, options });
            return catalogueResponse({ data: [syntheticArea] });
        },
    });

    assert.deepEqual(result.items, [{
        id: 7001,
        name: 'Synthetic Area',
        slug: 'synthetic-area',
        location_type: 'synthetic type',
        parent: {
            id: 7000,
            name: 'Synthetic Parent',
            slug: 'synthetic-parent',
            location_type: 'synthetic parent type',
        },
    }]);
    assert.equal(result.state, 'fresh');
    assert.equal(result.catalogueVersion, 'synthetic-v1');
    assert.equal(result.fetchedAt, '2026-10-23T08:00:00.000Z');
    assert.equal(requested.length, 1);
    assert.equal(requested[0].url, '/api/v1/locations?limit=100');
    assert.equal(requested[0].options.method, 'GET');
    assert.equal(requested[0].options.credentials, 'same-origin');
    assert.equal(requested[0].options.headers.Accept, 'application/json');

    const stored = storage.getItem(STORAGE_KEY);
    assert.match(stored, /synthetic-v1/);
    assert.match(stored, /"etag":"\\"synthetic-v1\\""/);
    assert.match(stored, /fetchedAt/);
    assert.doesNotMatch(stored, /reviewed_by|source_reference|must not be persisted/);
});

test('conditionally revalidates with If-None-Match and retains the cached body on 304', async () => {
    const storage = createStorage();
    await getLocationChoices('', {
        storage,
        fetchImpl: async () => catalogueResponse({ data: [syntheticArea] }),
    });

    let requestOptions;
    const refreshed = await getLocationChoices('', {
        storage,
        now: () => new Date('2026-10-23T09:00:00.000Z'),
        fetchImpl: async (_url, options) => {
            requestOptions = options;
            return new Response(null, {
                status: 304,
                headers: { ETag: '"synthetic-v1"', 'X-Catalogue-Version': 'synthetic-v1' },
            });
        },
    });

    assert.equal(requestOptions.headers['If-None-Match'], '"synthetic-v1"');
    assert.equal(refreshed.state, 'fresh');
    assert.equal(refreshed.items[0].slug, 'synthetic-area');
    assert.equal(refreshed.fetchedAt, '2026-10-23T09:00:00.000Z');
});

test('a changed catalogue version invalidates prior cached query results', async () => {
    const storage = createStorage();
    await getLocationChoices('', {
        storage,
        fetchImpl: async () => catalogueResponse({ version: 'synthetic-v1', data: [syntheticArea] }),
    });

    const newArea = { id: 8001, name: 'Synthetic New Area', slug: 'synthetic-new-area', parent: null };
    await getLocationChoices('new', {
        storage,
        fetchImpl: async () => catalogueResponse({
            version: 'synthetic-v2',
            etag: '"synthetic-v2"',
            data: [newArea],
        }),
    });

    const stored = JSON.parse(storage.getItem(STORAGE_KEY));
    assert.equal(stored.version, 'synthetic-v2');
    assert.deepEqual(Object.keys(stored.queries), ['new']);
    assert.equal(stored.queries.new.items[0].slug, 'synthetic-new-area');
    assert.doesNotMatch(JSON.stringify(stored), /synthetic-area"/);
});

test('network failure returns previously cached choices marked stale', async () => {
    const storage = createStorage();
    await getLocationChoices('', {
        storage,
        fetchImpl: async () => catalogueResponse({ data: [syntheticArea] }),
    });

    const result = await getLocationChoices('', {
        storage,
        fetchImpl: async () => { throw new TypeError('offline'); },
    });

    assert.equal(result.state, 'stale');
    assert.equal(result.items[0].slug, 'synthetic-area');
    assert.equal(result.catalogueVersion, 'synthetic-v1');
});

test('network failure with no cache raises a recoverable all-location browsing error', async () => {
    await assert.rejects(
        getLocationChoices('', {
            storage: createStorage(),
            fetchImpl: async () => { throw new TypeError('offline'); },
        }),
        /You can still browse all areas/,
    );
});

test('search sends bounded query text only to the same-origin OyaFind endpoint', async () => {
    const storage = createStorage();
    let requestedUrl;
    const result = await getLocationChoices('  Synthetic Area  ', {
        storage,
        fetchImpl: async (url) => {
            requestedUrl = url;
            return catalogueResponse({ data: [syntheticArea] });
        },
    });

    const url = new URL(requestedUrl, 'https://oyafind.test');
    assert.equal(url.origin, 'https://oyafind.test');
    assert.equal(url.pathname, '/api/v1/locations');
    assert.equal(url.searchParams.get('q'), 'Synthetic Area');
    assert.equal(url.searchParams.get('limit'), '100');
    assert.equal(result.items[0].slug, 'synthetic-area');
});
