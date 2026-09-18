'use strict';

const APP_VERSION = new URL(self.location.href).searchParams.get('v') || 'dev';
const OFFLINE_CACHE = `sgc-offline-${APP_VERSION}`;
const ASSET_CACHE = `sgc-static-${APP_VERSION}`;
const OFFLINE_URL = '/offline';
const STATIC_EXTENSIONS = /\.(?:png|jpe?g|webp|gif|svg|ico|woff2?|ttf|otf)$/i;
const HASHED_BUILD_ASSET = /^\/build\/assets\/[^/]+-[A-Za-z0-9_-]{6,}\.(?:css|js)$/i;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(OFFLINE_CACHE)
            .then((cache) => cache.add(new Request(OFFLINE_URL, { cache: 'reload' })))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys
                .filter((key) => key.startsWith('sgc-') && ![OFFLINE_CACHE, ASSET_CACHE].includes(key))
                .map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;

    const url = new URL(event.request.url);
    if (url.origin !== self.location.origin) return;

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
