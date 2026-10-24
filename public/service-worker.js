const CACHE_NAME = 'oyafind-shell-v2';
const OFFLINE_PAGE = '/offline.html';
// The worker's persistent cache is intentionally limited to this public shell page.
// Reviewed catalogue responses are stored by the client with version/ETag metadata.
const PUBLIC_CACHE_ALLOWLIST = new Set([OFFLINE_PAGE]);

function isExplicitlyCacheablePublicRequest(request) {
    const url = new URL(request.url, self.location.origin);

    return request.method === 'GET'
        && url.origin === self.location.origin
        && !url.search
        && PUBLIC_CACHE_ALLOWLIST.has(url.pathname);
}

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            const offlineRequest = {
                url: new URL(OFFLINE_PAGE, self.location.origin).href,
                method: 'GET',
            };
            if (isExplicitlyCacheablePublicRequest(offlineRequest)) {
                return cache.add(OFFLINE_PAGE);
            }
        }),
    );

    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key.startsWith('oyafind-shell-') && key !== CACHE_NAME)
                    .map((key) => caches.delete(key)),
            ),
        ),
    );

    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET' || request.mode !== 'navigate') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin || url.pathname.startsWith('/api/')) {
        return;
    }

    event.respondWith(
        fetch(request).catch(async () => {
            const cache = await caches.open(CACHE_NAME);

            return (await cache.match(OFFLINE_PAGE)) ?? Response.error();
        }),
    );
});
