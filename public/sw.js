const CACHE_VERSION = 'sgc-v2026-07-06-02';
const RUNTIME_CACHE = `${CACHE_VERSION}-runtime`;
const STATIC_CACHE = `${CACHE_VERSION}-static`;

const STATIC_ASSETS = [
    '/manifest.json',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(STATIC_ASSETS))
            .catch(() => undefined)
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => key.startsWith('sgc-') && key !== RUNTIME_CACHE && key !== STATIC_CACHE)
                    .map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    if (!['http:', 'https:'].includes(url.protocol)) {
        return;
    }

    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.headers.has('range')) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request));
        return;
    }

    if (!isStaticRequest(request, url)) {
        return;
    }

    event.respondWith(staleWhileRevalidate(request, event));
});

async function staleWhileRevalidate(request, event) {
    const cached = await caches.match(request);

    const updateCache = fetch(request)
        .then((response) => {
            if (canCache(response)) {
                const responseForCache = response.clone();
                event.waitUntil(
                    caches.open(RUNTIME_CACHE)
                        .then((cache) => cache.put(request, responseForCache))
                        .catch(() => undefined)
                );
            }
            return response;
        })
        .catch(() => cached);

    return cached || updateCache;
}

function canCache(response) {
    return response
        && response.ok
        && response.type === 'basic'
        && response.status === 200;
}

function isStaticRequest(request, url) {
    if (request.destination && ['script', 'style', 'image', 'font', 'manifest'].includes(request.destination)) {
        return true;
    }

    return url.pathname.startsWith('/build/')
        || url.pathname.startsWith('/icons/')
        || url.pathname === '/manifest.json'
        || /\.(?:js|css|png|jpg|jpeg|svg|webp|gif|ico|woff2?|ttf)$/i.test(url.pathname);
}
