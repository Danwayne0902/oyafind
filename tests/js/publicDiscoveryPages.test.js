import assert from 'node:assert/strict';
import test from 'node:test';
import { createSSRApp } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';

const server = await createServer({
    configFile: false,
    root: process.cwd(),
    plugins: [vue()],
    server: { middlewareMode: true },
    appType: 'custom',
});

test.after(async () => {
    await server.close();
});

test('discovery component renders deterministic fixture data and a clear empty-feed message', async () => {
    const { default: DiscoveryPage } = await server.ssrLoadModule('/resources/js/pages/DiscoveryPage.vue');
    const fixture = {
        id: 51,
        title: 'Synthetic training opportunity',
        slug: 'synthetic-training-opportunity',
        summary: 'A controlled test fixture, not a live listing.',
        organisation_name: 'Synthetic provider',
        category: { name: 'Synthetic training' },
        location: null,
        deadline_at: null,
        verified_at: '2026-10-12T09:00:00+00:00',
        verification_status: 'verified',
    };

    const populated = await renderToString(createSSRApp(DiscoveryPage, {
        title: 'Explore opportunities',
        categories: [{ id: 1, name: 'Synthetic training', slug: 'synthetic-training' }],
        categoriesLoading: false,
        opportunities: [fixture],
        pagination: { total: 1, current_page: 1, last_page: 1 },
    }));
    assert.match(populated, /Synthetic training opportunity/);
    assert.match(populated, /Synthetic provider/);
    assert.match(populated, /Location not specified/);
    assert.match(populated, /Ibadan pilot/);
    assert.match(populated, /Oyo State/);
    assert.match(populated, /Find what’s next/);
    assert.match(populated, /closer to home/);
    assert.match(populated, /Search a role, organisation or skill/);
    assert.match(populated, /What a check means/);
    assert.match(populated, /Source checked 12 Oct/);
    assert.match(populated, /A check is not an endorsement/);
    assert.doesNotMatch(populated, /Illustrative preview · not live listings/);
    assert.doesNotMatch(populated, /Example: applications open/);
    assert.match(populated, /aria-pressed="false"/);
    assert.match(populated, /Browse without an account/);
    for (const slug of ['jobs', 'scholarships', 'internships', 'grants', 'training', 'business-support', 'gigs', 'tenders']) {
        assert.match(populated, new RegExp(`/categories/${slug}`));
    }

    const empty = await renderToString(createSSRApp(DiscoveryPage, {
        title: 'Explore opportunities',
        categories: [],
        categoriesLoading: false,
        opportunities: [],
        pagination: { total: 0, current_page: 1, last_page: 1 },
    }));
    assert.match(empty, /No live listings right now/);
    assert.match(empty, /We don’t pad the feed with examples/);
    assert.doesNotMatch(empty, /Synthetic/);
});

test('detail component renders trust, eligibility, source and safe external actions', async () => {
    const { default: OpportunityDetailPage } = await server.ssrLoadModule('/resources/js/pages/OpportunityDetailPage.vue');
    const fixture = {
        title: 'Synthetic public opportunity',
        organisation_name: 'Synthetic provider',
        summary: 'Controlled UI test data.',
        description: 'The fixture is not live opportunity data.',
        category: { name: 'Synthetic category' },
        publication_status: 'published',
        verification_status: 'verified',
        verified_at: '2026-10-12T09:00:00+00:00',
        source_url: 'https://provider.example/source',
        application_url: 'https://provider.example/apply',
        application_method: 'external_url',
        eligibility: 'Controlled test eligibility',
        deadline_at: null,
    };
    const html = await renderToString(createSSRApp(OpportunityDetailPage, {
        opportunity: fixture,
        safeExternalUrl: (value) => {
            try {
                const url = new URL(value);
                return ['http:', 'https:'].includes(url.protocol) ? url.href : null;
            } catch {
                return null;
            }
        },
    }));

    assert.match(html, /Published on OyaFind/);
    assert.match(html, /Source and details checked 12 October 2026/);
    assert.match(html, /Controlled test eligibility/);
    assert.match(html, /Apply on the provider’s website/);
    assert.match(html, /provider\.example\/source/);
    assert.match(html, /rel="noopener noreferrer"/);
    assert.match(html, /do not guarantee provider safety, selection or payment/);
    assert.match(html, /Track it privately/);
    assert.match(html, /OyaFind does not submit applications or decide outcomes/);
    assert.doesNotMatch(html, /Report this listing|No report has been submitted/);
});

test('unavailable detail view does not disclose details and offers a return path', async () => {
    const { default: OpportunityDetailPage } = await server.ssrLoadModule('/resources/js/pages/OpportunityDetailPage.vue');
    const html = await renderToString(createSSRApp(OpportunityDetailPage, {
        opportunity: null,
        loading: false,
        error: 'This opportunity is no longer available or could not be found.',
        safeExternalUrl: () => null,
    }));
    assert.match(html, /Opportunity unavailable/);
    assert.match(html, /Browse current opportunities/);
    assert.doesNotMatch(html, /source_url|application_url/);
});
