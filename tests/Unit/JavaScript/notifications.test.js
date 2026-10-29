import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { safeRoute, validPush, newInstallationId } from '../../../resources/js/notifications/payload.js';

const origin = 'https://staging.tenantism.com';
const payload = { notification_id: '12', route: '/messages/9', title: '[STAGING] Tenantism', body: 'You received a new message.', environment: 'staging', subscription_key: 'a'.repeat(64) };

test('only allowlisted relative routes are accepted', () => {
    for (const value of ['https://evil.test', '//evil.test', '/messages/../logout', '/logout', '/messages/1?next=evil', '/messages/1#evil', '/messages/0', '/messages/%31', '/messages/1\\evil']) assert.equal(safeRoute(value, origin), null);
    for (const value of ['/notifications', '/messages/1', '/renter/tenancies/42', '/owner/maintenance/2', '/owner/monthly-records/9']) assert.equal(safeRoute(value, origin), value);
});
test('push payload must match environment and current account/device binding', () => {
    assert.equal(validPush(payload, 'staging', payload.subscription_key, origin), true);
    for (const value of [{ ...payload, environment: 'production' }, { ...payload, subscription_key: 'previous-account' }, { ...payload, notification_id: '../logout' }, { ...payload, route: '//evil.test' }, { ...payload, body: '<'.repeat(201) }, null]) assert.equal(validPush(value, 'staging', payload.subscription_key, origin), false);
    assert.equal(validPush(payload, 'staging', null, origin), false);
});
test('worker uses the same route and binding validation and data-only display', () => {
    const listeners = new Map();
    const context = vm.createContext({ self: { addEventListener: (name, cb) => listeners.set(name, cb), TENANTISM_PUSH: { enabled: false }, location: { origin } }, importScripts() {}, URL });
    vm.runInContext(readFileSync(new URL('../../../public/firebase-messaging-sw.js', import.meta.url), 'utf8'), context);
    context.self.TENANTISM_PUSH = { enabled: true, environment: 'staging' };
    context.payload = payload; context.binding = { binding: payload.subscription_key, environment: 'staging' };
    assert.equal(vm.runInContext('valid(payload, binding)', context), true);
    assert.equal(vm.runInContext('valid(payload, null)', context), false);
    assert.equal(vm.runInContext("validRoute('//evil.test')", context), false);
    assert.equal(listeners.has('notificationclick'), true);
    assert.equal(listeners.has('message'), true);
});

function workerHarness({ visible = false, focused = visible, noWindows = false, contextStatus = 200, readStatus = 200, readRoute = '/messages/9', navigationFails = false } = {}) {
    const listeners = new Map();
    const store = new Map([['binding', { binding: payload.subscription_key, environment: 'staging' }]]);
    const shown = [], navigated = [], posted = [], requests = [];
    const client = { url: `${origin}/dashboard`, focused, visibilityState: visible ? 'visible' : 'hidden',
        postMessage: message => posted.push(message), navigate: async url => { if (navigationFails) throw new Error('Window cannot navigate'); navigated.push(url); return client; }, focus: async () => {} };
    const db = { createObjectStore() {}, close() {}, transaction() {
        const tx = { objectStore: () => ({
            get: key => ({ result: store.get(key) }),
            put: (value, key) => { store.set(key, value); return { result: key }; },
        }) };
        queueMicrotask(() => tx.oncomplete?.()); return tx;
    } };
    const context = vm.createContext({ URL, Date, AbortController, setTimeout, clearTimeout, fetch: async (url, options) => {
        requests.push({ url, options });
        const status = url.endsWith('/open') ? contextStatus : readStatus;
        return { ok: status === 200, status, json: async () => url.endsWith('/open') ? { csrf_token: 'c'.repeat(40) } : { route: readRoute, unread_count: 0 } };
    }, indexedDB: { open() {
        const request = { result: db };
        queueMicrotask(() => request.onsuccess?.()); return request;
    } }, importScripts() {}, firebase: { initializeApp() {}, messaging: () => ({}) },
    self: { addEventListener: (name, cb) => listeners.set(name, cb), TENANTISM_PUSH: { enabled: true, environment: 'staging', firebase: {} }, location: { origin },
        clients: { matchAll: async () => noWindows ? [] : [client], openWindow: async url => navigated.push(url) },
        registration: { getNotifications: async () => [], showNotification: async (...args) => shown.push(args) } } });
    vm.runInContext(readFileSync(new URL('../../../public/firebase-messaging-sw.js', import.meta.url), 'utf8'), context);
    const background = async message => {
        let pending, stopped = false;
        listeners.get('push')({ data: { json: () => message }, stopImmediatePropagation: () => { stopped = true; }, waitUntil: value => { pending = value; } });
        assert.equal(stopped, true, 'native push routing must run before Firebase’s visibility shortcut');
        await pending;
    };
    return { listeners, store, shown, navigated, posted, background, requests, context };
}

test('worker displays one branded background alert even after dismissal and replay', async () => {
    const worker = workerHarness();
    await worker.background({ data: payload });
    await worker.background({ data: payload });
    assert.equal(worker.shown.length, 1);
    assert.equal(worker.shown[0][1].icon, '/icons/favicon-192.png');
    assert.equal(worker.shown[0][1].tag, 'tenantism-staging-12');
});
test('a focused visible tab receives updates without a duplicate system alert', async () => {
    const worker = workerHarness({ visible: true });
    await worker.background({ data: payload });
    assert.equal(worker.shown.length, 0);
    assert.equal(worker.posted.length, 1);
    assert.equal(worker.posted[0].type, 'TENANTISM_PUSH');
});
test('a visible Tenantism window behind another app still produces a desktop alert', async () => {
    const worker = workerHarness({ visible: true, focused: false });
    await worker.background({ data: payload });
    assert.equal(worker.shown.length, 1);
    assert.equal(worker.posted.length, 1);
});
test('native push displays with no open Tenantism windows', async () => {
    const worker = workerHarness({ noWindows: true });
    await worker.background({ data: payload });
    assert.equal(worker.shown.length, 1);
    assert.equal(worker.posted.length, 0);
});
test('malformed, cross-environment, and disabled messages cannot display alerts', async () => {
    const worker = workerHarness();
    for (const message of [{}, { data: { ...payload, environment: 'production' } }, { data: { ...payload, route: '/logout' } }]) {
        await worker.background(message);
    }
    worker.context.self.TENANTISM_PUSH.enabled = false;
    await worker.background({ data: payload });
    assert.equal(worker.shown.length, 0);
    assert.equal(worker.posted.length, 0);
});
test('logout and stale account bindings suppress delayed background delivery', async () => {
    const worker = workerHarness();
    worker.store.set('binding', null);
    await worker.background({ data: payload });
    worker.store.set('binding', { binding: 'new-account', environment: 'staging' });
    await worker.background({ data: payload });
    assert.equal(worker.shown.length, 0);
    assert.equal(worker.posted.length, 0);
});
test('click marks read with the session CSRF token before navigating to the server deep link', async () => {
    const worker = workerHarness();
    let closed = false, pending;
    worker.listeners.get('notificationclick')({ notification: { data: payload, close: () => { closed = true; } }, stopImmediatePropagation() {}, waitUntil: p => { pending = p; } });
    await pending;
    assert.equal(closed, true);
    assert.deepEqual(worker.navigated, [`${origin}/messages/9`]);
    assert.equal(worker.requests.length, 2);
    assert.equal(worker.requests[0].url, '/notifications/12/open');
    assert.equal(worker.requests[1].url, '/notifications/12/read');
    assert.equal(worker.requests[1].options.method, 'POST');
    assert.equal(worker.requests[1].options.headers['X-CSRF-TOKEN'], 'c'.repeat(40));
    assert.equal(worker.requests[1].options.credentials, 'same-origin');
    assert.equal(worker.posted[0].type, 'TENANTISM_NOTIFICATIONS_CHANGED');
});
test('worker rejects auto-display payloads outside the data-only contract', () => {
    const worker = workerHarness(); let stopped = false;
    worker.listeners.get('push')({ data: { json: () => ({ notification: { title: 'Untrusted' }, data: payload }) }, stopImmediatePropagation: () => { stopped = true; } });
    assert.equal(stopped, true);
});

test('installation IDs have a secure compatibility fallback and fail safely without crypto', () => {
    assert.equal(newInstallationId({ getRandomValues: bytes => bytes }), '00000000-0000-4000-8000-000000000000');
    assert.equal(newInstallationId(null), null);
});

async function click(worker, data = payload) {
    let pending;
    worker.listeners.get('notificationclick')({ notification: { data, close() {} }, stopImmediatePropagation() {}, waitUntil: value => { pending = value; } });
    await pending;
}
test('wrapped Firebase alerts use the same authorized read flow', async () => {
    const worker = workerHarness();
    await click(worker, { FCM_MSG: { data: payload } });
    assert.equal(worker.requests[1].options.method, 'POST');
    assert.deepEqual(worker.navigated, [`${origin}/messages/9`]);
});
test('a rejected window navigation falls back to opening a new window', async () => {
    const worker = workerHarness({ navigationFails: true });
    await click(worker);
    assert.deepEqual(worker.navigated, [`${origin}/messages/9`]);
});
test('expired login or forbidden ownership falls back without posting a read', async () => {
    for (const contextStatus of [401, 403]) {
        const worker = workerHarness({ contextStatus });
        await click(worker);
        assert.equal(worker.requests.length, 1);
        assert.deepEqual(worker.navigated, [`${origin}/notifications/12/open`]);
    }
});
test('a failed read request leaves the protected opening form as the fallback', async () => {
    const worker = workerHarness({ readStatus: 419 });
    await click(worker);
    assert.deepEqual(worker.navigated, [`${origin}/notifications/12/open`]);
    assert.equal(worker.posted.length, 0);
});
test('the server response cannot redirect a clicked alert off origin', async () => {
    const worker = workerHarness({ readRoute: 'https://evil.test/' });
    await click(worker);
    assert.deepEqual(worker.navigated, [`${origin}/notifications`]);
});
test('a previously displayed alert remains clickable if push is disabled afterward', async () => {
    const worker = workerHarness();
    worker.context.self.TENANTISM_PUSH.enabled = false;
    await click(worker);
    assert.equal(worker.requests.length, 2);
});
test('a previous account binding cannot post reads or navigate on a stale click', async () => {
    const worker = workerHarness();
    worker.store.set('binding', { binding: 'other-account', environment: 'staging' });
    await click(worker);
    assert.equal(worker.requests.length, 0);
    assert.equal(worker.navigated.length, 0);
});
test('the local desktop test opens only the inbox and never reads a business notification', async () => {
    const worker = workerHarness();
    await click(worker, { tenantism_test: true, route: 'https://evil.test/' });
    assert.equal(worker.requests.length, 0);
    assert.deepEqual(worker.navigated, [`${origin}/notifications`]);
});
