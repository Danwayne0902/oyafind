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

const verifiedUser = {
    id: 7,
    name: 'Synthetic account',
    contact_verified: true,
    save_eligible: true,
};

test('saved workspace explains private saving and the unverified-contact gate', async () => {
    const { default: SavedPage } = await server.ssrLoadModule('/resources/js/pages/SavedPage.vue');

    const verified = await renderToString(createSSRApp(SavedPage, {
        currentUser: verifiedUser,
    }));
    assert.match(verified, /Saved opportunities/);
    assert.match(verified, /move the ones you are pursuing into your private tracker/);
    assert.match(verified, /Application tracker/);
    assert.doesNotMatch(verified, /Your contact is not verified yet/);

    const unverified = await renderToString(createSSRApp(SavedPage, {
        currentUser: { ...verifiedUser, contact_verified: false, save_eligible: false },
    }));
    assert.match(unverified, /Your contact is not verified yet/);
    assert.match(unverified, /Saving new opportunities stays paused/);
});

test('tracker states progress is user-reported and never sent to an employer', async () => {
    const { default: MyOpportunitiesPage } = await server.ssrLoadModule('/resources/js/pages/MyOpportunitiesPage.vue');
    const html = await renderToString(createSSRApp(MyOpportunitiesPage, {
        currentUser: verifiedUser,
    }));

    assert.match(html, /Application tracker/);
    assert.match(html, /User-reported and private/);
    assert.match(html, /does not submit applications/);
    assert.match(html, /Nothing in your tracker yet|Loading your tracked applications/);
    assert.doesNotMatch(html, /verified opportunity/i);
});

test('saved and tracker pages render their own loading states and cross-links', async () => {
    const { default: SavedPage } = await server.ssrLoadModule('/resources/js/pages/SavedPage.vue');
    const saved = await renderToString(createSSRApp(SavedPage, { currentUser: verifiedUser }));
    assert.match(saved, /Loading your saved opportunities/);
    assert.match(saved, /href="\/tracker" data-internal-link/);
    assert.doesNotMatch(saved, /opportunity_id/);

    const { default: MyOpportunitiesPage } = await server.ssrLoadModule('/resources/js/pages/MyOpportunitiesPage.vue');
    const tracker = await renderToString(createSSRApp(MyOpportunitiesPage, { currentUser: verifiedUser }));
    assert.match(tracker, /Loading your tracked applications/);
    assert.match(tracker, /href="\/saved" data-internal-link/);
    assert.doesNotMatch(tracker, /opportunity_id/);
});
