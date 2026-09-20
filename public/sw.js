// V38.4 stability rollback.
// This worker intentionally provides no offline/navigation interception.
// It exists only to retire older Canovia/PaceKeeper workers and shell caches.

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(
            keys
                .filter((key) => key.startsWith('canovia-shell-') || key.startsWith('pacekeeper-shell-'))
                .map((key) => caches.delete(key))
        );

        await self.registration.unregister().catch(() => false);

        const windows = await self.clients.matchAll({
            type: 'window',
            includeUncontrolled: true,
        });

        await Promise.all(windows.map(async (client) => {
            if (!('navigate' in client)) return;
            const url = new URL(client.url);
            url.searchParams.set('_canovia_network', '1');
            url.searchParams.set('_canovia_stable', '1');
            await client.navigate(url.href).catch(() => {});
        }));
    })());
});
