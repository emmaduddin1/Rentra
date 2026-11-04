/* Root-scoped FCM worker. Public config only; no service-account material. */
/* Register click first so Firebase never replaces our validated navigation. */
const database = () => new Promise((resolve, reject) => {
    const request = indexedDB.open('tenantism-web-push', 1);
    request.onupgradeneeded = () => request.result.createObjectStore('state');
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
});
let messagingReady = false;
let deliveryQueue = Promise.resolve();
async function state(value, write = false, key = 'binding') {
    const db = await database();
    try {
        return await new Promise((resolve, reject) => {
            const tx = db.transaction('state', write ? 'readwrite' : 'readonly');
            const request = write ? tx.objectStore('state').put(value, key) : tx.objectStore('state').get(key);
            tx.oncomplete = () => resolve(request.result);
            tx.onerror = () => reject(tx.error);
        });
    } finally { db.close(); }
}
function validRoute(route) {
    return typeof route === 'string' && /^\/(?:notifications|messages(?:\/[1-9]\d*)?|(?:owner|renter)\/(?:tenancies|monthly-records|maintenance|applications)(?:\/[1-9]\d*)?)$/.test(route);
}
function validBinding(data, binding) {
    return data && /^[1-9]\d*$/.test(data.notification_id) && validRoute(data.route)
        && binding?.binding === data.subscription_key && binding.environment === data.environment
        && typeof data.title === 'string' && data.title.length <= 100
        && typeof data.body === 'string' && data.body.length <= 200;
}
function valid(data, binding) {
    return validBinding(data, binding) && self.TENANTISM_PUSH?.enabled && data.environment === self.TENANTISM_PUSH.environment;
}
async function receipt(binding, outcome) {
    if (!binding?.binding) return;
    try {
        await state({ binding: binding.binding, environment: binding.environment, outcome, at: Date.now() }, true, 'last-delivery');
    } catch { /* Diagnostics must never prevent delivery. */ }
}
async function openNotificationRoute(route) {
    const target = `${self.location.origin}${route}`;
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    const existing = windows.find(client => new URL(client.url).origin === self.location.origin);
    if (existing) {
        const navigated = await existing.navigate(target).catch(() => null);
        if (navigated) { await navigated.focus(); return; }
    }
    await self.clients.openWindow(target);
}
function receivePush(data) {
    deliveryQueue = deliveryQueue.then(async () => {
        let binding;
        try {
            binding = await state();
            if (!valid(data, binding)) {
                await receipt(binding, 'ignored');
                return;
            }
            const tag = `tenantism-${data.environment}-${data.notification_id}`;
            if (await state(undefined, false, tag)) {
                await receipt(binding, 'duplicate');
                return;
            }
            const windows = (await self.clients.matchAll({ type: 'window', includeUncontrolled: true }))
                .filter(client => new URL(client.url).origin === self.location.origin);
            // A visible page behind another window is still a background recipient.
            const diagnostic = data.environment === 'staging' && data.type === 'account.browser_click_check';
            const foreground = !diagnostic && windows.some(client => client.focused && client.visibilityState === 'visible');
            if (!foreground && !(await self.registration.getNotifications({ tag })).length) {
                await self.registration.showNotification(data.title, { body: data.body, tag,
                    icon: '/icons/favicon-192.png', badge: '/icons/favicon-32.png', data });
            }
            await state(Date.now(), true, tag);
            await receipt(binding, foreground ? 'foreground' : 'desktop');
            windows.forEach(client => client.postMessage({ type: 'TENANTISM_PUSH', data }));
        } catch {
            await receipt(binding, 'display-failed');
            /* Missing binding or storage: fail closed, keep database notification. */
        }
    });
    return deliveryQueue;
}
self.addEventListener('message', event => {
    if (!event.source?.url || new URL(event.source.url).origin !== self.location.origin) return;
    if (event.data?.type === 'TENANTISM_STATUS') {
        event.waitUntil((async () => {
            const binding = await state();
            const matches = !!binding?.binding && binding.binding === event.data.binding;
            const last = matches ? await state(undefined, false, 'last-delivery') : null;
            const subscription = matches ? await self.registration.pushManager?.getSubscription() : null;
            event.ports[0]?.postMessage({ ready: messagingReady, matches,
                subscribed: !!subscription,
                last: last && last.binding === binding?.binding && last.environment === binding?.environment
                    ? { outcome: last.outcome, at: last.at } : null });
        })().catch(() => event.ports[0]?.postMessage({ ready: false })));
        return;
    }
    if (event.data?.type !== 'TENANTISM_BIND') return;
    const value = event.data.value;
    if (value !== null && (!/^[a-f0-9]{64}$/.test(value?.binding) || typeof value.environment !== 'string')) return;
    event.waitUntil(state(value, true).then(() => event.ports[0]?.postMessage({ ready: messagingReady })));
});
self.addEventListener('notificationclick', event => {
    // Accept the direct data we show and Firebase's wrapper for already displayed alerts.
    const wrapped = event.notification.data;
    const data = wrapped?.notification_id ? wrapped : wrapped?.FCM_MSG?.data;
    event.notification.close();
    event.stopImmediatePropagation();
    if (wrapped?.tenantism_test === true) {
        event.waitUntil(openNotificationRoute('/notifications'));
        return;
    }
    event.waitUntil((async () => {
        // Previously displayed alerts remain clickable if push is subsequently disabled/offline.
        if (!validBinding(data, await state())) return;
        // Use the click's activation immediately. The protected page performs the
        // authorized CSRF POST in its signed-in window, then follows the safe route.
        await openNotificationRoute(`/notifications/${data.notification_id}/open`);
    })().catch(() => {}));
});
// Own display routing before Firebase's visibility-only foreground shortcut runs.
// Firebase still manages token registration, but cannot auto-display arbitrary payloads.
self.addEventListener('push', event => {
    event.stopImmediatePropagation();
    try {
        const raw = event.data?.json();
        if (!raw?.data || raw.notification || !validRoute(raw.data.route) || !/^[1-9]\d*$/.test(raw.data.notification_id)
            || raw.data.environment !== self.TENANTISM_PUSH?.environment) return;
        event.waitUntil(receivePush(raw.data));
    } catch { /* Reject malformed messages before Firebase can display them. */ }
});
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));
self.addEventListener('install', () => self.skipWaiting());
try {
    importScripts('/push-config.js');
    if (self.TENANTISM_PUSH?.enabled) {
        importScripts('https://www.gstatic.com/firebasejs/13.1.0/firebase-app-compat.js', 'https://www.gstatic.com/firebasejs/13.1.0/firebase-messaging-compat.js');
        firebase.initializeApp(self.TENANTISM_PUSH.firebase);
        firebase.messaging();
        messagingReady = true;
    }
} catch { /* Misconfiguration/offline does not break normal navigation. */ }
