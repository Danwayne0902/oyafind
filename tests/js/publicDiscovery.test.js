import assert from 'node:assert/strict';
import test from 'node:test';
import { getCategories, getOpportunity, getOpportunities } from '../../resources/js/api/publicDiscovery.js';
import {
    canonicalPageUrl,
    parsePublicRoute,
    safeExternalUrl,
    sharePageUrl,
    withLocationFilter,
} from '../../resources/js/publicRoutes.js';

test('public route parsing retains shareable search and optional location filters', () => {
    const route = parsePublicRoute({
        pathname: '/search',
        search: '?q=graduate%20role&location=synthetic-area&sort=deadline',
    });

    assert.deepEqual(route, {
        name: 'search',
        query: { q: 'graduate role', location: 'synthetic-area', sort: 'deadline' },
    });
});

test('category and opportunity slugs resolve to stable public route types', () => {
    assert.deepEqual(parsePublicRoute({ pathname: '/categories', search: '' }), {
        name: 'categories',
        query: {},
    });
    assert.deepEqual(parsePublicRoute({ pathname: '/how-it-works', search: '' }), {
        name: 'how-it-works',
        query: {},
    });
    assert.deepEqual(parsePublicRoute({ pathname: '/categories/training', search: '' }), {
        name: 'category',
        slug: 'training',
        query: {},
    });
    assert.deepEqual(parsePublicRoute({ pathname: '/opportunities/graduate-role', search: '' }), {
        name: 'opportunity',
        slug: 'graduate-role',
        query: {},
    });
});

test('signed email verification URLs resolve to the confirmation screen', () => {
    assert.deepEqual(parsePublicRoute({
        pathname: `/verify-email/17/${'a'.repeat(40)}`,
        search: '?expires=1800000000&signature=signature-value',
    }), {
        name: 'verify-email',
        userId: '17',
        hash: 'a'.repeat(40),
        query: { expires: '1800000000', signature: 'signature-value' },
    });
    assert.equal(
        parsePublicRoute({ pathname: `/verify-email/17/${'g'.repeat(40)}`, search: '' }).name,
        'not-found',
    );
});

test('location selection updates the reloadable discovery URL without losing other filters', () => {
    const path = withLocationFilter('/search?q=training&sort=deadline&page=3', 'synthetic-area');
    assert.equal(path, '/search?q=training&sort=deadline&location=synthetic-area');
    assert.deepEqual(parsePublicRoute({ pathname: '/search', search: path.slice('/search'.length) }), {
        name: 'search',
        query: { q: 'training', sort: 'deadline', location: 'synthetic-area' },
    });
    assert.equal(withLocationFilter(path, ''), '/search?q=training&sort=deadline');
});

test('canonical share URL points to the stable public page and omits transient query/hash', () => {
    assert.equal(
        canonicalPageUrl({
            origin: 'https://oyafind.test',
            pathname: '/opportunities/public-role',
            search: '?utm_source=private',
            hash: '#apply',
        }),
        'https://oyafind.test/opportunities/public-role',
    );
});

test('external navigation permits HTTP(S) only', () => {
    assert.equal(safeExternalUrl('https://provider.example/apply'), 'https://provider.example/apply');
    assert.equal(safeExternalUrl('http://provider.example/apply'), 'http://provider.example/apply');
    assert.equal(safeExternalUrl('javascript:alert(1)'), null);
    assert.equal(safeExternalUrl('//provider.example/apply'), null);
    assert.equal(safeExternalUrl('https://trusted.example@untrusted.example/apply'), null);
    assert.equal(safeExternalUrl('not a URL'), null);
});

test('share action uses the Web Share API with a public URL and title', async () => {
    let shared;
    await sharePageUrl({
        share: async (payload) => {
            shared = payload;
        },
    }, 'https://oyafind.test/opportunities/public-role', 'A public role');
    assert.deepEqual(shared, {
        title: 'A public role',
        url: 'https://oyafind.test/opportunities/public-role',
    });
});

test('opportunity API sends only supported query fields and unwraps its public response contract', async (t) => {
    const originalFetch = globalThis.fetch;
    t.after(() => { globalThis.fetch = originalFetch; });

    let requestedUrl;
    globalThis.fetch = async (url, options) => {
        requestedUrl = url;
        assert.equal(options.headers.Accept, 'application/json');
        return new Response(JSON.stringify({
            data: [{
                id: 14,
                title: 'Synthetic public opportunity',
                slug: 'synthetic-public-opportunity',
                organisation_name: 'Example provider',
                source_url: 'https://provider.example/source',
                application_url: 'https://provider.example/apply',
                eligibility: 'Synthetic test eligibility',
                deadline_at: null,
                publication_status: 'published',
                verification_status: 'verified',
            }],
            meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 },
        }), { status: 200, headers: { 'Content-Type': 'application/json' } });
    };

    const result = await getOpportunities({
        q: 'training & support',
        category: 'training',
        location: 'synthetic-area',
        unrecognized: 'must-not-be-sent',
    });
    const url = new URL(requestedUrl, 'https://oyafind.test');
    assert.equal(url.pathname, '/api/v1/opportunities');
    assert.equal(url.searchParams.get('q'), 'training & support');
    assert.equal(url.searchParams.get('location'), 'synthetic-area');
    assert.equal(url.searchParams.has('unrecognized'), false);
    assert.equal(result.data[0].verification_status, 'verified');
    assert.equal(result.meta.total, 1);
});

test('detail and category clients use the documented versioned endpoints', async (t) => {
    const originalFetch = globalThis.fetch;
    t.after(() => { globalThis.fetch = originalFetch; });
    const urls = [];
    globalThis.fetch = async (url) => {
        urls.push(url);
        const isCategory = url.endsWith('/categories');
        return new Response(JSON.stringify(isCategory
            ? { data: [{ id: 1, name: 'Training', slug: 'training' }] }
            : { data: { slug: 'synthetic-role', title: 'Synthetic role' } }), {
            status: 200,
            headers: { 'Content-Type': 'application/json' },
        });
    };

    assert.equal((await getOpportunity('synthetic-role')).title, 'Synthetic role');
    assert.equal((await getCategories())[0].slug, 'training');
    assert.deepEqual(urls, ['/api/v1/opportunities/synthetic-role', '/api/v1/categories']);
});

test('API errors retain status and display the server message', async (t) => {
    const originalFetch = globalThis.fetch;
    t.after(() => { globalThis.fetch = originalFetch; });
    globalThis.fetch = async () => new Response(JSON.stringify({
        message: 'Opportunity not found.',
        errors: { opportunity: ['Opportunity not found.'] },
    }), { status: 404, headers: { 'Content-Type': 'application/json' } });

    await assert.rejects(getOpportunity('missing'), (error) => {
        assert.equal(error.status, 404);
        assert.equal(error.message, 'Opportunity not found.');
        return true;
    });
});
