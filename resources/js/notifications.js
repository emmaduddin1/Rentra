import { safeRoute, validPush, newInstallationId } from './notifications/payload.js';

const bootstrap = document.getElementById('tenantism-push-config');
if (bootstrap) {
    try { start(JSON.parse(bootstrap.textContent)); }
    catch { document.querySelectorAll('[data-push-state]').forEach(el => { el.textContent = 'Temporarily unavailable'; }); }
}

function start(config) {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const key = `tenantism.push.${config.environment}.${config.userId}`;
    const memory = new Map();
    const storage = {
        get(name) { try { return localStorage.getItem(name) ?? memory.get(name) ?? null; } catch { return memory.get(name) ?? null; } },
        set(name, value) { memory.set(name, value); try { localStorage.setItem(name, value); } catch { /* Keep this tab working when storage is restricted. */ } },
    };
    let installation = storage.get('tenantism.push.installation');
    if (!installation) { installation = newInstallationId(); if (installation) storage.set('tenantism.push.installation', installation); }
    let binding = null;
    let pushIntent = 0;
    let messaging, sdk, registration, unsubscribe;
    let stopped = false;
    let initialized = false;
    let latest = [];
    let refreshing = null;
    let channel = null;
    try { if ('BroadcastChannel' in window) channel = new BroadcastChannel(`${key}.updates`); } catch { /* Storage events and polling remain available. */ }
    const announce = () => { channel?.postMessage('refresh'); storage.set(`${key}.refresh`, `${Date.now()}-${Math.random()}`); };
    const api = async (url, method = 'GET', data) => {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 10000);
        try {
            const response = await fetch(url, { method, signal: controller.signal, credentials: 'same-origin', headers: {
                Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf,
            }, ...(data ? { body: JSON.stringify(data) } : {}) });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return await response.json();
        } finally { clearTimeout(timer); }
    };
    const state = (text) => document.querySelectorAll('[data-push-state]').forEach(el => { el.textContent = text; });
    const setCount = count => document.querySelectorAll('[data-notification-count]').forEach(el => {
        el.textContent = count > 99 ? '99+' : String(count);
        el.classList.toggle('hidden', count < 1);
    });
    const read = async (id, navigate = true) => {
        if (!/^[1-9]\d*$/.test(String(id))) return;
        const result = await api(`/notifications/${id}/read`, 'POST');
        setCount(result.unread_count);
        announce();
        if (navigate) window.location.assign(safeRoute(result.route, location.origin) || '/notifications');
        else await refresh();
    };
    function render(root, items) {
        if (!root) return;
        const list = document.createElement('ul');
        list.className = 'divide-y divide-slate-100';
        if (!items.length) {
            const empty = document.createElement('li'); empty.textContent = 'No notifications.'; empty.className = 'py-4 text-sm text-slate-500'; list.append(empty);
        }
        for (const item of items) {
            const row = document.createElement('li'); row.className = 'py-3'; row.dataset.notificationId = item.id;
            const open = document.createElement('button'); open.type = 'button'; open.className = 'w-full text-left text-sm';
            const title = document.createElement('span'); title.className = item.read_at ? 'block' : 'block font-semibold';
            title.textContent = `${item.read_at ? '' : 'New · '}${item.title}`;
            const body = document.createElement('span'); body.className = 'block text-slate-600'; body.textContent = item.body || '';
            open.append(title, body); open.addEventListener('click', () => read(item.id).catch(() => state('Temporarily unavailable')));
            row.append(open);
            if (!item.read_at) {
                const mark = document.createElement('button'); mark.type = 'button'; mark.className = 'mt-2 text-xs underline'; mark.textContent = 'Mark as read';
                mark.addEventListener('click', () => read(item.id, false).catch(() => state('Temporarily unavailable'))); row.append(mark);
            }
            list.append(row);
        }
        root.replaceChildren(list);
    }
    async function refresh() {
        if (stopped) return;
        if (refreshing) return refreshing;
        refreshing = (async () => {
            const result = await api(config.feed);
            if (stopped) return;
            latest = result.notifications;
            setCount(Number(result.unread_count));
            render(document.querySelector('[data-notification-recent]'), latest.slice(0, 8));
            if (document.querySelector('[data-notification-page-list]')) {
                const params = new URLSearchParams(location.search);
                const page = await api(`${config.feed}?${new URLSearchParams({ page: params.get('page') || '1', filter: params.get('filter') || 'all' })}`);
                const root = document.querySelector('[data-notification-page-list]');
                render(root, page.notifications);
                if (page.last_page > 1) {
                    const pagination = document.createElement('nav'); pagination.setAttribute('aria-label', 'Notification pages'); pagination.className = 'flex gap-4 mt-4';
                    for (const [label, number] of [['Previous', page.page - 1], ['Next', page.page + 1]]) {
                        if (number < 1 || number > page.last_page) continue;
                        const link = document.createElement('a'); link.textContent = label; link.className = 'underline';
                        link.href = `/notifications?${new URLSearchParams({ page: number, filter: params.get('filter') || 'all' })}`; pagination.append(link);
                    }
                    root.append(pagination);
                }
            }
            // Reconciliation remains useful when permission is denied or push is unavailable.
            if (initialized && !document.hidden && document.hasFocus()) {
                const recent = latest.find(item => !item.read_at && !storage.get(`${key}.seen.${item.id}`));
                if (recent) await toast(recent.id, recent.title);
            }
            for (const item of latest) storage.set(`${key}.seen.${item.id}`, '1');
            initialized = true;
        })().finally(() => { refreshing = null; });
        return refreshing;
    }
    async function toast(id, text) {
        const show = () => {
            if (document.hidden || !document.hasFocus() || stopped || storage.get(`${key}.seen.${id}`)) return;
            storage.set(`${key}.seen.${id}`, '1');
            const box = document.querySelector('[data-notification-toast]');
            box.querySelector('[data-toast-body]').textContent = text;
            box.querySelector('[data-toast-open]').onclick = () => read(id).catch(() => state('Temporarily unavailable'));
            box.classList.remove('hidden');
        };
        if (navigator.locks) await navigator.locks.request(`${key}.toast`, show); else show();
    }
    const workerState = async (value) => {
        if (!registration) return;
        const worker = registration.active || registration.waiting || registration.installing;
        if (!worker) return;
        await new Promise((resolve, reject) => {
            const messages = new MessageChannel();
            const timer = setTimeout(() => reject(new Error('Worker unavailable')), 5000);
            messages.port1.onmessage = event => { clearTimeout(timer); if (value && !event.data?.ready) reject(new Error('Messaging unavailable')); else resolve(); };
            worker.postMessage({ type: 'TENANTISM_BIND', value }, [messages.port2]);
        });
    };
    async function receive(data) {
        if (stopped || !validPush(data, config.environment, binding, location.origin)) return;
        // Claim the alert before refreshing (refresh reconciles its own IDs).
        await toast(data.notification_id, data.body);
        announce();
        window.dispatchEvent(new CustomEvent('tenantism:notification', { detail: data }));
        await refresh().catch(() => {});
    }
    async function connect() {
        const intent = ++pushIntent;
        if (!config.enabled) { state('Temporarily unavailable'); return; }
        if (!installation || !window.isSecureContext || !('serviceWorker' in navigator) || !('Notification' in window)) { state('Not supported'); return; }
        sdk = await import('firebase/messaging');
        if (!(await sdk.isSupported())) { state('Not supported'); return; }
        const { initializeApp, getApps } = await import('firebase/app');
        registration = await navigator.serviceWorker.register(config.workerUrl || '/firebase-messaging-sw.js', { scope: '/', updateViaCache: 'none' });
        await navigator.serviceWorker.ready;
        messaging ||= sdk.getMessaging(getApps()[0] || initializeApp(config.firebase));
        if (Notification.permission !== 'granted') return;
        const token = await sdk.getToken(messaging, { vapidKey: config.vapidKey, serviceWorkerRegistration: registration });
        if (stopped || intent !== pushIntent || storage.get(`${key}.disabled`) === '1') return;
        const result = await api('/push-subscriptions', 'POST', { token, installation_id: installation,
            browser: navigator.userAgent.slice(0, 255), device_name: 'Web browser', notifications_enabled: true });
        if (stopped) return;
        if (storage.get(`${key}.disabled`) === '1') { await api('/push-subscriptions/current', 'DELETE', { installation_id: installation }); return; }
        if (intent !== pushIntent) return;
        binding = result.subscription_key;
        await workerState({ binding, environment: config.environment });
        if (stopped || intent !== pushIntent) return;
        unsubscribe?.(); unsubscribe = sdk.onMessage(messaging, message => receive(message.data));
        state('Enabled');
    }
    async function disable(updateState = true) {
        ++pushIntent;
        binding = null;
        unsubscribe?.();
        if (!registration && 'serviceWorker' in navigator) registration = await navigator.serviceWorker.getRegistration('/');
        await workerState(null).catch(() => {});
        await api('/push-subscriptions/current', 'DELETE', { installation_id: installation });
        if (messaging) await sdk.deleteToken(messaging).catch(() => {});
        if (updateState) state('Not enabled');
    }
    document.querySelector('[data-push-enable]')?.addEventListener('click', async () => {
        try {
            if (!('Notification' in window)) { state('Not supported'); return; }
            if (!config.enabled) { state('Temporarily unavailable'); return; }
            if (Notification.permission === 'denied') { state('Blocked by browser. Allow notifications in your browser’s site settings.'); return; }
            storage.set(`${key}.disabled`, '0');
            state('Permission requested');
            const permission = await Notification.requestPermission();
            if (permission === 'granted') await connect();
            else state(permission === 'denied' ? 'Blocked by browser. Allow notifications in your browser’s site settings.' : 'Not enabled');
        } catch { state('Temporarily unavailable'); }
    });
    document.querySelector('[data-push-disable]')?.addEventListener('click', async () => {
        storage.set(`${key}.disabled`, '1');
        await disable().catch(() => state('Temporarily unavailable'));
    });
    document.querySelector('[data-push-test]')?.addEventListener('click', async event => {
        const button = event.currentTarget;
        const feedback = document.querySelector('[data-push-test-state]');
        if (!('Notification' in window) || !('serviceWorker' in navigator)) {
            feedback.textContent = 'Desktop notifications are not supported in this browser.';
            return;
        }
        if (Notification.permission !== 'granted') {
            feedback.textContent = 'Select Enable notifications first. If blocked, allow notifications in Chrome’s site settings.';
            return;
        }
        button.disabled = true;
        feedback.textContent = 'Sending a desktop test…';
        try {
            const worker = registration || await navigator.serviceWorker.getRegistration('/');
            if (!worker?.active) throw new Error('Worker unavailable');
            await worker.showNotification(`${config.environment === 'staging' ? '[STAGING] ' : ''}Tenantism browser test`, {
                body: 'This is a desktop notification test from Tenantism.',
                icon: '/icons/favicon-192.png', badge: '/icons/favicon-32.png',
                tag: `tenantism-browser-test-${config.environment}`, renotify: true,
                data: { tenantism_test: true },
            });
            feedback.textContent = 'Test sent to your browser. If no popup appears, check Chrome notifications in your system settings and turn off Do Not Disturb.';
        } catch {
            feedback.textContent = 'Could not show the desktop test. Refresh, enable notifications, then try again.';
        } finally { button.disabled = false; }
    });
    document.querySelector('[data-toast-close]')?.addEventListener('click', () => document.querySelector('[data-notification-toast]').classList.add('hidden'));
    document.addEventListener('submit', event => {
        const form = event.target;
        if (form.matches('[data-push-preferences]')) {
            event.preventDefault();
            const data = Object.fromEntries([...form.querySelectorAll('input[type="checkbox"]')].map(input => [input.name, input.checked]));
            api('/notification-preferences', 'PATCH', data).then(() => { form.querySelector('[data-preferences-state]').textContent = 'Preferences saved.'; }).catch(() => { form.querySelector('[data-preferences-state]').textContent = 'Could not save. Please retry.'; });
        } else if (form.matches('[data-notification-read], [data-notification-open]')) {
            event.preventDefault();
            const id = form.dataset.notificationRead || form.action.match(/notifications\/(\d+)\/read/)?.[1];
            read(id).catch(() => state('Temporarily unavailable'));
        } else if (form.matches('[data-notifications-read-all]')) {
            event.preventDefault();
            api('/notifications/read-all', 'POST').then(async result => { setCount(result.unread_count); announce(); await refresh(); }).catch(() => {});
        } else if (new URL(form.action).pathname === '/logout') {
            event.preventDefault(); stopped = true; storage.set('tenantism.push.logout', `${Date.now()}-${Math.random()}`);
            disable().catch(() => {}).finally(() => HTMLFormElement.prototype.submit.call(form));
        }
    });
    channel?.addEventListener('message', () => refresh().catch(() => {}));
    window.addEventListener('storage', event => {
        if (event.key === `${key}.refresh`) refresh().catch(() => {});
        if (event.key === 'tenantism.push.logout') { stopped = true; binding = null; unsubscribe?.(); document.querySelector('[data-notification-toast]')?.classList.add('hidden'); }
    });
    navigator.serviceWorker?.addEventListener('message', event => {
        if (event.data?.type === 'TENANTISM_PUSH') receive(event.data.data);
        if (!stopped && event.data?.type === 'TENANTISM_NOTIFICATIONS_CHANGED') {
            refresh().catch(() => {});
            announce();
        }
    });
    const reconcilePermission = () => {
        if (!('Notification' in window)) { state('Not supported'); return; }
        if (Notification.permission === 'denied') {
            state('Blocked by browser. Allow notifications in your browser’s site settings.');
            if (binding) disable(false).catch(() => {});
        }
    };
    window.addEventListener('focus', () => { reconcilePermission(); refresh().catch(() => {}); });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) { reconcilePermission(); refresh().catch(() => {}); } });
    document.querySelector('[data-notification-panel]')?.addEventListener('toggle', () => refresh().catch(() => {}));
    setInterval(() => refresh().catch(() => {}), 30000);
    // getToken also detects replacements; renew on each page and periodically while open.
    setInterval(() => { if (!stopped && binding && Notification.permission === 'granted') connect().catch(() => state('Temporarily unavailable')); }, 60 * 60 * 1000);
    if (!config.enabled) state('Temporarily unavailable');
    if (!installation || !window.isSecureContext || !('serviceWorker' in navigator) || !('Notification' in window)) state('Not supported');
    reconcilePermission();
    refresh().catch(() => {});
    if ('Notification' in window && Notification.permission === 'granted' && storage.get(`${key}.disabled`) !== '1') connect().catch(() => state('Temporarily unavailable'));
    const opening = document.querySelector('[data-notification-auto-open]');
    if (opening) read(opening.action.match(/notifications\/(\d+)\/read/)?.[1]).catch(() => {});
}
