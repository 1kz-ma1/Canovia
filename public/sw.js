const CACHE_VERSION = 'canovia-shell-v38';
const META_CACHE = 'canovia-shell-meta-v1';
const LAST_NETWORK_KEY = '/__canovia_last_network_success__';
const LIKELY_SLEEP_AFTER_MS = 12 * 60 * 1000;
const RECENT_NETWORK_TIMEOUT_MS = 900;
const STATIC_ASSETS = [
    '/offline.html',
    '/icons/icon-180.png',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/brand/logo-mark.svg',
    '/brand/app-icon.svg',
    '/brand/mascot-guide.webp',
];

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE_VERSION);
        await cache.addAll(STATIC_ASSETS);
        await rememberNetworkSuccess();
    })());
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(
            keys
                .filter((key) => (key.startsWith('canovia-shell-') || key.startsWith('pacekeeper-shell-'))
                    && key !== CACHE_VERSION
                    && key !== META_CACHE)
                .map((key) => caches.delete(key))
        );

        if (self.registration.navigationPreload) {
            await self.registration.navigationPreload.enable().catch(() => {});
        }
        await self.clients.claim();
    })());
});

async function rememberNetworkSuccess() {
    try {
        const cache = await caches.open(META_CACHE);
        await cache.put(LAST_NETWORK_KEY, new Response(String(Date.now()), {
            headers: { 'Content-Type': 'text/plain' },
        }));
    } catch (_) {}
}

async function lastNetworkSuccessAt() {
    try {
        const cache = await caches.open(META_CACHE);
        const response = await cache.match(LAST_NETWORK_KEY);
        if (!response) return 0;
        const value = Number(await response.text());
        return Number.isFinite(value) ? value : 0;
    } catch (_) {
        return 0;
    }
}

function markServerWarm(response) {
    if (response?.ok) void rememberNetworkSuccess();
    return response;
}

function timeoutAfter(ms) {
    return new Promise((_, reject) => setTimeout(() => reject(new Error('network-timeout')), ms));
}

async function offlineShell() {
    return (await caches.match('/offline.html')) || fetch('/offline.html', { cache: 'no-store' });
}

async function navigationNetworkResponse(event, request) {
    const preload = event.preloadResponse ? await event.preloadResponse.catch(() => null) : null;
    if (preload) return markServerWarm(preload);
    return fetch(request, { cache: 'no-store' }).then(markServerWarm);
}

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        if (url.searchParams.get('_canovia_network') === '1' || url.searchParams.get('_pk_network') === '1') {
            event.respondWith(navigationNetworkResponse(event, request).catch(() => offlineShell()));
            return;
        }

        const networkPromise = navigationNetworkResponse(event, request);
        event.waitUntil(networkPromise.then(() => undefined).catch(() => undefined));

        event.respondWith((async () => {
            const lastSuccess = await lastNetworkSuccessAt();
            const likelySleeping = !lastSuccess || (Date.now() - lastSuccess) >= LIKELY_SLEEP_AFTER_MS;
            if (likelySleeping) return offlineShell();

            return Promise.race([
                networkPromise,
                timeoutAfter(RECENT_NETWORK_TIMEOUT_MS),
            ]).catch(() => offlineShell());
        })());
        return;
    }

    if (STATIC_ASSETS.includes(url.pathname)) {
        event.respondWith(caches.match(request).then((cached) => cached || fetch(request)));
    }
});
