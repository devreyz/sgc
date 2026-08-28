'use strict';

const OFFLINE_CACHE = 'sgc-offline-v1';

// Network-only: este worker existe somente para instalacao PWA.
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(OFFLINE_CACHE)
            .then((cache) => cache.add('/offline'))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys
                .filter((key) => key !== OFFLINE_CACHE)
                .map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') return;

    event.respondWith(
        fetch(event.request).catch(() => caches.match('/offline'))
    );
});
