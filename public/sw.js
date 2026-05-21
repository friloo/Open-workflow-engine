// Open Workflow Engine — Service Worker
//
// 1. Push-Empfang: zeigt Notification mit title/body/url aus dem Payload
// 2. Notification-Klick: bringt das Tab nach vorne oder oeffnet das URL
// 3. Kein Offline-Cache (App ist datenbankgetrieben — Stale-Cache koennte
//    falsche Sachen zeigen).

const APP_NAME = 'Open Workflow Engine';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    let data = {};
    if (event.data) {
        try { data = event.data.json(); } catch (e) { data = { title: event.data.text() }; }
    }
    const title = data.title || APP_NAME;
    const options = {
        body: data.body || '',
        icon: '/favicon.svg',
        badge: '/favicon.svg',
        data: { url: data.url || '/dashboard' },
        tag: data.tag || 'owe-push',
        renotify: true,
    };
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/dashboard';
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if (client.url.includes(self.location.origin)) {
                    client.focus();
                    return client.navigate(url);
                }
            }
            return self.clients.openWindow(url);
        })
    );
});
