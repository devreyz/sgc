'use strict';

const OFFLINE_CACHE = 'sgc-offline-v1';

// Network-only: este worker existe somente para instalacao PWA e Web Push.
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

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (_) {
        data = { title: 'SGC', body: event.data ? event.data.text() : '' };
    }

    const actions = Array.isArray(data.actions)
        ? data.actions.slice(0, 2).map((action, index) => ({
            action: `open-${index}`,
            title: String(action.label || 'Abrir').slice(0, 30),
        }))
        : [];

    event.waitUntil(self.registration.showNotification(data.title || 'SGC', {
        body: data.body || '',
        icon: data.icon || '/assets/favicon-192.png',
        badge: data.badge || '/assets/favicon-192.png',
        tag: data.tag || undefined,
        renotify: ['high', 'critical'].includes(data.priority),
        requireInteraction: data.priority === 'critical',
        actions,
        data: {
            url: data.url || '/',
            actions: data.actions || [],
        },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const data = event.notification.data || {};
    const actionIndex = event.action && event.action.startsWith('open-')
        ? Number(event.action.replace('open-', ''))
        : null;
    const path = actionIndex !== null ? data.actions?.[actionIndex]?.url : data.url;

    try {
        const target = new URL(path || '/', self.location.origin);
        if (target.origin !== self.location.origin) return;

        event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            const existing = clients.find((client) => new URL(client.url).origin === target.origin);
            if (existing) {
                existing.navigate(target.href);
                return existing.focus();
            }
            return self.clients.openWindow(target.href);
        }));
    } catch (_) {
        return;
    }
});
