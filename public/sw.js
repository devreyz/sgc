'use strict';

const OFFLINE_CACHE = 'sgc-offline-3';
const STATIC_PREFIX = 'sgc-static-day-';
const PROVIDER_CACHE = 'sgc-provider-shell-6';
const OFFLINE_URL = '/offline';
const PROVIDER_PAGE = '/provider-offline.html';
const PROVIDER_ASSETS = [PROVIDER_PAGE, '/assets/provider-offline.css', '/assets/provider-offline.js',
    '/assets/provider-offline-store.js', '/assets/provider-offline-document.js', '/assets/icons/campo-dark-192.png'];
const PUBLIC_ASSET = /^(?:\/(?:assets|icons)\/.*|\/favicon[^/]*)\.(?:png|jpe?g|webp|gif|svg|ico|woff2?|ttf|otf)$/i;
const PHOSPHOR_ASSET = /^\/npm\/@phosphor-icons\/web@2\.1\.2\/src\/regular\/(?:style\.css|Phosphor\.(?:woff2?|ttf))$/;
const DAY = 24 * 60 * 60 * 1000;
const REFRESH_INTERVAL = 10 * 60 * 1000;
const SHELL_MAX_AGE = 7 * DAY;
const SHELL_DATE = '/__sgc_offline_shell_date__';
let providerRefresh;
let staticCleanup;
let staticDay;

async function shellDate(cache) {
    const stamp = await cache.match(SHELL_DATE);
    return Number(await stamp?.text()) || 0;
}

async function cachedShell(path) {
    const cache = await caches.open(PROVIDER_CACHE);
    const stamp = await shellDate(cache);
    if (stamp && Date.now() - stamp < SHELL_MAX_AGE) return cache.match(path);
    // Keep the previously installed files only until their offline lifetime ends.
    if (stamp) await caches.delete(PROVIDER_CACHE);
}

function refreshProviderShell() {
    if (providerRefresh) return providerRefresh;
    providerRefresh = caches.open(PROVIDER_CACHE)
        .then(async cache => {
            const date = await shellDate(cache);
            if (date && Date.now() - date < REFRESH_INTERVAL) return;
            await cache.addAll(PROVIDER_ASSETS.map(path => new Request(path, {cache: 'reload', redirect: 'error', credentials: 'omit'})));
            await cache.put(SHELL_DATE, new Response(String(Date.now())));
        })
        .finally(() => { providerRefresh = null; });
    return providerRefresh;
}

function staticCache() {
    const current = STATIC_PREFIX + Math.floor(Date.now() / DAY);
    if (staticDay !== current) {
        staticDay = current;
        staticCleanup = caches.keys().then(keys => Promise.all(keys
            .filter(key => key.startsWith(STATIC_PREFIX) && key !== current).map(key => caches.delete(key))));
    }
    return staticCleanup.then(() => caches.open(current));
}

async function dailyAsset(request, key, cacheFirst) {
    const cache = await staticCache();
    const cached = await cache.match(key);
    if (cacheFirst && cached) return cached;
    let response;
    try {
        response = await fetch(request, {cache: 'no-cache', credentials: 'omit'});
    } catch {
        return cached || Response.error();
    }
    if (response.status >= 500 && cached) return cached;
    if (response.ok && ['basic', 'cors'].includes(response.type) && !response.redirected
        && !/\b(?:private|no-store)\b/i.test(response.headers.get('Cache-Control') || '')) {
        try {
            await cache.put(key, response.clone());
            const keys = await cache.keys();
            await Promise.all(keys.slice(0, Math.max(0, keys.length - 64)).map(key => cache.delete(key)));
        } catch {}
    }
    return response;
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
    const daily = STATIC_PREFIX + Math.floor(Date.now() / DAY);
    event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key =>
        /^sgc-(?:offline-|static-|provider-shell-)/.test(key) && ![OFFLINE_CACHE, PROVIDER_CACHE, daily].includes(key)
    ).map(key => caches.delete(key)))).then(() => self.clients.claim()));
});

self.addEventListener('message', event => {
    if (event.data?.type === 'SGC_REFRESH_OFFLINE_SHELL') {
        event.waitUntil(refreshProviderShell().catch(() => {}));
    }
});

async function networkFirst(request, cacheName, fallback, save = false, offlineRedirect = null) {
    async function cachedFallback() {
        const cached = cacheName === PROVIDER_CACHE ? await cachedShell(fallback) : await (await caches.open(cacheName)).match(fallback);
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
    if (url.origin === 'https://cdn.jsdelivr.net' && !url.search && PHOSPHOR_ASSET.test(url.pathname)) {
        event.respondWith(dailyAsset(event.request, url.href, true));
        return;
    }
    if (url.origin !== self.location.origin) return;

    if (PROVIDER_ASSETS.includes(url.pathname)) {
        if (url.pathname === PROVIDER_PAGE) event.waitUntil(staticCache().catch(() => {}));
        if (url.pathname === '/assets/provider-offline-store.js' && url.searchParams.has('v')) {
            event.respondWith(networkFirst(event.request, PROVIDER_CACHE, url.pathname));
            return;
        }
        event.respondWith((async () => {
            if (url.pathname === PROVIDER_PAGE) await refreshProviderShell().catch(() => {});
            return await cachedShell(url.pathname) || networkFirst(event.request, PROVIDER_CACHE, url.pathname);
        })());
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
        event.respondWith(dailyAsset(event.request, url.pathname, false));
    }
});
