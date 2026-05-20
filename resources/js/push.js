// Service-Worker registrieren + Push-Subscribe-API exponieren.
//
// Verwendung im Frontend:
//   await window.OWEPush.subscribe();   // fragt nach Erlaubnis, abonniert
//   await window.OWEPush.unsubscribe(); // beendet Abo
//   window.OWEPush.isSupported();        // boolean
//   await window.OWEPush.isSubscribed(); // boolean

const VAPID_PUBLIC_KEY_META = 'vapid-public-key';
const SW_PATH = '/sw.js';

function getVapidPublicKey() {
    const meta = document.querySelector(`meta[name="${VAPID_PUBLIC_KEY_META}"]`);
    return meta ? meta.getAttribute('content') : null;
}

function urlBase64ToUint8Array(base64String) {
    // Web-Push erwartet Uint8Array. VAPID-Keys werden base64url-codiert.
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(base64);
    const out = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out;
}

async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return null;
    try {
        return await navigator.serviceWorker.register(SW_PATH);
    } catch (e) {
        console.warn('SW register failed', e);
        return null;
    }
}

// Beim Laden registrieren - bringt Offline-Manifest / PWA-Install zum
// Laufen, auch wenn Push nicht aktiv ist.
if (typeof window !== 'undefined') {
    window.addEventListener('load', () => { registerServiceWorker(); });
}

async function isSubscribed() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return false;
    const reg = await navigator.serviceWorker.getRegistration();
    if (!reg) return false;
    const sub = await reg.pushManager.getSubscription();
    return !!sub;
}

async function subscribe() {
    const vapid = getVapidPublicKey();
    if (!vapid) throw new Error('Push ist serverseitig nicht konfiguriert (VAPID-Keys fehlen).');
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        throw new Error('Browser unterstuetzt keine Web-Push.');
    }
    const reg = await registerServiceWorker();
    if (!reg) throw new Error('Service-Worker konnte nicht registriert werden.');

    let sub = await reg.pushManager.getSubscription();
    if (!sub) {
        sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapid),
        });
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const res = await fetch('/profile/push/subscribe', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify({
            endpoint: sub.endpoint,
            keys: {
                p256dh: arrayBufferToBase64(sub.getKey('p256dh')),
                auth: arrayBufferToBase64(sub.getKey('auth')),
            },
        }),
    });
    if (!res.ok) throw new Error('Server lehnte Subscription ab.');
    return true;
}

async function unsubscribe() {
    if (!('serviceWorker' in navigator)) return;
    const reg = await navigator.serviceWorker.getRegistration();
    if (!reg) return;
    const sub = await reg.pushManager.getSubscription();
    if (!sub) return;
    const endpoint = sub.endpoint;
    await sub.unsubscribe();

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    await fetch('/profile/push/unsubscribe', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify({ endpoint }),
    });
}

function arrayBufferToBase64(buf) {
    const bytes = new Uint8Array(buf);
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
    return btoa(binary);
}

window.OWEPush = {
    isSupported: () => 'serviceWorker' in navigator && 'PushManager' in window && !!getVapidPublicKey(),
    isSubscribed,
    subscribe,
    unsubscribe,
};
