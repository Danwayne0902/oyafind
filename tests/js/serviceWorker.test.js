import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

async function loadWorker() {
    const source = await readFile(new URL('../../public/service-worker.js', import.meta.url), 'utf8');
    const listeners = {};
    const cached = [];
    const matched = [];
    const cache = {
        add: async (path) => { cached.push(path); },
        match: async (path) => {
            matched.push(path);
            return path === '/offline.html' ? new Response('offline shell') : undefined;
        },
    };
    const self = {
        location: { origin: 'https://oyafind.test' },
        clients: { claim: async () => {} },
        addEventListener: (name, callback) => { listeners[name] = callback; },
        skipWaiting: async () => {},
    };
    const caches = {
        open: async () => cache,
        keys: async () => ['oyafind-shell-v1', 'unrelated-cache'],
        delete: async () => true,
    };
    vm.runInNewContext(source, {
        self,
        caches,
        fetch: async () => { throw new TypeError('offline'); },
        URL,
        Response,
        Promise,
    });

    return { listeners, cached, matched };
}

test('service worker install caches only the explicitly allowlisted public offline shell', async () => {
    const { listeners, cached } = await loadWorker();
    let installPromise;
    listeners.install({ waitUntil: (promise) => { installPromise = promise; } });
    await installPromise;

    assert.deepEqual(cached, ['/offline.html']);
});

test('service worker never caches API, authentication, write, account, report or staff responses', async () => {
    const { listeners, cached, matched } = await loadWorker();
    let responsePromise;
    const request = (url, method = 'GET', mode = 'cors') => {
        let responseWasHandled = false;
        listeners.fetch({
            request: { url, method, mode },
            respondWith: (promise) => {
                responseWasHandled = true;
                responsePromise = promise;
            },
        });
        return responseWasHandled;
    };

    assert.equal(request('https://oyafind.test/api/v1/locations'), false);
    assert.equal(request('https://oyafind.test/api/v1/auth/me'), false);
    assert.equal(request('https://oyafind.test/api/v1/saved-opportunities'), false);
    assert.equal(request('https://oyafind.test/api/v1/reports', 'POST'), false);
    assert.equal(request('https://oyafind.test/api/v1/tracker', 'PATCH'), false);
    assert.equal(request('https://oyafind.test/api/v1/notifications'), false);
    assert.equal(request('https://oyafind.test/admin', 'GET', 'navigate'), true);
    assert.equal(await (await responsePromise).text(), 'offline shell');
    assert.deepEqual(cached, []);
    assert.deepEqual(matched, ['/offline.html']);
});

test('service worker does not handle cross-origin navigation and production location code has no locator calls', async () => {
    const { listeners } = await loadWorker();
    let handled = false;
    listeners.fetch({
        request: { url: 'https://locator.example/search', method: 'GET', mode: 'navigate' },
        respondWith: () => { handled = true; },
    });
    assert.equal(handled, false);

    const productionFiles = [
        new URL('../../resources/js/api/locationCatalogue.js', import.meta.url),
        new URL('../../resources/js/components/LocationPicker.vue', import.meta.url),
        new URL('../../public/service-worker.js', import.meta.url),
    ];
    const sources = await Promise.all(productionFiles.map((file) => readFile(file, 'utf8')));
    const source = sources.join('\n');
    assert.doesNotMatch(source, /navigator\.geolocation|Geolocation|getCurrentPosition|watchPosition/i);
    assert.doesNotMatch(source, /nominatim|geocoder|mapbox|googleapis\.com\/maps|tile\.openstreetmap/i);
});
