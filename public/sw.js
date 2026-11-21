'use strict';

const APP_VERSION = new URL(self.location.href).searchParams.get('v') || 'dev';
const OFFLINE_CACHE = `sgc-offline-${APP_VERSION}`;
const ASSET_CACHE = `sgc-static-campo-lima-2-${APP_VERSION}`;
const PROVIDER_CACHE = `sgc-provider-shell-1-${APP_VERSION}`;
const PROVIDER_PAGE = '/provider-offline.html';
const PROVIDER_ASSETS = [PROVIDER_PAGE, '/assets/provider-offline.css', '/assets/provider-offline.js',
    '/assets/provider-offline-store.js', '/assets/provider-offline-document.js'];
const OFFLINE_URL = '/offline';
const STATIC_EXTENSIONS = /\.(?:png|jpe?g|webp|gif|svg|ico|woff2?|ttf|otf)$/i;
const HASHED_BUILD_ASSET = /^\/build\/assets\/[^/]+-[A-Za-z0-9_-]{6,}\.(?:css|js)$/i;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(OFFLINE_CACHE)
            .then((cache) => cache.add(new Request(OFFLINE_URL, { cache: 'reload' })))
            .then(() => caches.open(PROVIDER_CACHE))
            .then(cache => cache.addAll(PROVIDER_ASSETS.map(url => new Request(url, {cache: 'reload'}))))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys
                .filter((key) => key.startsWith('sgc-') && ![OFFLINE_CACHE, ASSET_CACHE, PROVIDER_CACHE].includes(key))
                .map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;

    const url = new URL(event.request.url);
    if (url.origin !== self.location.origin) return;
    if (PROVIDER_ASSETS.includes(url.pathname)) {
        event.respondWith(fetch(event.request).then(async response => {
            if (response.ok) await (await caches.open(PROVIDER_CACHE)).put(url.pathname, response.clone());
            return response;
        }).catch(() => caches.match(url.pathname)));
        return;
    }
    if (event.request.mode === 'navigate' && (url.pathname === '/app' || /^\/[^/]+\/provider\/(dashboard|orders(?:\/.*)?)$/.test(url.pathname))) {
        event.respondWith(fetch(event.request).catch(() => caches.match(PROVIDER_PAGE)));
        return;
    }

    // Rotas HTML e APIs seguem diretamente para a rede e nunca são
    // respondidas pelo worker. Assim não há páginas autenticadas obsoletas.
    if (url.pathname === OFFLINE_URL) {
        event.respondWith(caches.match(OFFLINE_URL).then((cached) => cached || fetch(event.request)));
        return;
    }

    // O Vite já versiona seus arquivos pelo nome. Só mantemos em cache os
    // recursos estáticos sem hash, isolados pela versão da aplicação.
    if (!STATIC_EXTENSIONS.test(url.pathname) || HASHED_BUILD_ASSET.test(url.pathname)) return;

    event.respondWith(caches.open(ASSET_CACHE).then(async (cache) => {
        const cached = await cache.match(event.request);
        if (cached) return cached;
        const response = await fetch(event.request);
        if (response.ok && response.type === 'basic') await cache.put(event.request, response.clone());
        return response;
    }));
});
