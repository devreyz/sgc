'use strict';

const OFFLINE_CACHE = 'sgc-offline-2';
const ASSET_CACHE = 'sgc-static-3';
const PROVIDER_CACHE = 'sgc-provider-shell-3';
const OFFLINE_URL = '/offline';
const PROVIDER_PAGE = '/provider-offline.html';
const PROVIDER_ASSETS = [PROVIDER_PAGE, '/assets/provider-offline.css', '/assets/provider-offline.js',
    '/assets/provider-offline-store.js', '/assets/provider-offline-document.js', '/assets/icons/campo-dark-192.png'];
const PUBLIC_ASSET = /^(?:\/(?:assets|icons)\/.*|\/favicon[^/]*)\.(?:png|jpe?g|webp|gif|svg|ico|woff2?|ttf|otf)$/i;
const REFRESH_INTERVAL = 10 * 60 * 1000;
let providerRefresh;
let lastProviderRefresh = 0;

function refreshProviderShell() {
    if (providerRefresh) return providerRefresh;
    if (lastProviderRefresh && Date.now() - lastProviderRefresh < REFRESH_INTERVAL) return Promise.resolve();
    providerRefresh = caches.open(PROVIDER_CACHE)
        .then(cache => cache.addAll(PROVIDER_ASSETS.map(path => new Request(path, {cache: 'reload', redirect: 'error'}))))
        .then(() => { lastProviderRefresh = Date.now(); })
        .finally(() => { providerRefresh = null; });
    return providerRefresh;
}

self.addEventListener('install', event => {
    event.waitUntil(Promise.all([
        refreshProviderShell(),
        caches.open(OFFLINE_CACHE)
            .then(cache => cache.add(new Request(OFFLINE_URL, {cache: 'reload'})))
            .catch(() => {}),
    ]).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
    event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key =>
        /^sgc-(?:offline-|static-|provider-shell-)/.test(key) && ![OFFLINE_CACHE, ASSET_CACHE, PROVIDER_CACHE].includes(key)
    ).map(key => caches.delete(key)))).then(() => self.clients.claim()));
});

self.addEventListener('message', event => {
    if (event.data?.type === 'SGC_REFRESH_OFFLINE_SHELL') {
        event.waitUntil(refreshProviderShell().catch(() => {}));
    }
});

async function networkFirst(request, cacheName, fallback, save = false, offlineRedirect = null) {
    async function cachedFallback() {
        const cached = await (await caches.open(cacheName)).match(fallback);
        return cached && offlineRedirect ? Response.redirect(offlineRedirect) : cached;
    }
    let response;
    try {
        response = await fetch(request, {cache: 'no-cache'});
    } catch {
        return await cachedFallback() || Response.error();
    }
    if (response.status >= 500) {
        const cached = await cachedFallback();
        if (cached) return cached;
    }
    if (save && response.ok && response.type === 'basic' && !response.redirected
        && !/\b(?:private|no-store)\b/i.test(response.headers.get('Cache-Control') || '')) {
        try {
            await (await caches.open(cacheName)).put(fallback, response.clone());
        } catch {
            // A resposta online continua disponível mesmo sem espaço para armazená-la.
        }
    }
    return response;
}

self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') return;
    const url = new URL(event.request.url);
    if (url.origin !== self.location.origin) return;

    if (PROVIDER_ASSETS.includes(url.pathname)) {
        event.respondWith(networkFirst(event.request, PROVIDER_CACHE, url.pathname));
        if (url.pathname === PROVIDER_PAGE) event.waitUntil(refreshProviderShell().catch(() => {}));
        return;
    }
    const provider = url.pathname.match(/^\/([a-z0-9_-]+)\/provider\/(?:dashboard|orders(?:\/(create|\d+))?)\/?$/i);
    if (event.request.mode === 'navigate' && (url.pathname === '/app' || provider)) {
        const fallback = new URL(PROVIDER_PAGE, url.origin);
        if (provider) {
            fallback.searchParams.set('tenant', provider[1]);
            if (provider[2] === 'create') fallback.searchParams.set('new', '1');
            else if (provider[2]) fallback.searchParams.set('os', provider[2]);
        }
        event.respondWith(networkFirst(event.request, PROVIDER_CACHE, PROVIDER_PAGE, false, fallback.href));
        return;
    }
    if (url.pathname === OFFLINE_URL) {
        event.respondWith(networkFirst(event.request, OFFLINE_CACHE, OFFLINE_URL, true));
        return;
    }
    if (PUBLIC_ASSET.test(url.pathname)) {
        event.respondWith(networkFirst(event.request, ASSET_CACHE, event.request, true));
    }
});
