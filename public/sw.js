const cacheName = 'necutrack-app-shell-v4';
const offlineUrl = '/offline.html';
const appShell = [
    offlineUrl,
    '/icons/favicon-32-v3.png',
    '/icons/icon-any-192-v2.png',
    '/icons/icon-any-512-v2.png',
    '/icons/icon-192-v2.png',
    '/icons/icon-512-v2.png',
    '/icons/apple-touch-icon-v2.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(cacheName)
            .then((cache) => cache.addAll(appShell))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key !== cacheName)
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') return;

    event.respondWith(
        fetch(event.request).catch(() => caches.match(offlineUrl)),
    );
});
