const CACHE_VERSION = 'pacekeeper-shell-v18';
const STATIC_ASSETS = [
    '/offline.html',
    '/manifest.webmanifest',
    '/icons/icon-180.png',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/brand/logo-mark.svg',
    '/brand/app-icon.svg',
    '/brand/mascot-guide.webp',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_VERSION).then((cache) => cache.addAll(STATIC_ASSETS)));
});


self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key.startsWith('pacekeeper-shell-') && key !== CACHE_VERSION)
                .map((key) => caches.delete(key))
        ))
    );
    self.clients.claim();
});

function timeoutAfter(ms) {
    return new Promise((_, reject) => setTimeout(() => reject(new Error('network-timeout')), ms));
}

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        // The offline shell probes /health while Render wakes up. Once the
        // server is confirmed ready it retries the original navigation with
        // _pk_network=1. That retry must be network-only; otherwise the normal
        // 1.2s Instant Start timeout can serve offline.html again and create a
        // reload/fallback loop even though the server is already awake.
        if (url.searchParams.get('_pk_network') === '1') {
            event.respondWith(
                fetch(request, { cache: 'no-store' })
                    .catch(() => caches.match('/offline.html'))
            );
            return;
        }

        const networkRequest = fetch(request, { cache: 'no-store' });
        event.waitUntil(networkRequest.then(() => undefined).catch(() => undefined));
        event.respondWith(
            Promise.race([networkRequest, timeoutAfter(1200)])
                .catch(() => caches.match('/offline.html'))
        );
        return;
    }

    if (STATIC_ASSETS.includes(url.pathname)) {
        event.respondWith(caches.match(request).then((cached) => cached || fetch(request)));
    }
});
