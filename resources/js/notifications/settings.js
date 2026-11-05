const states = {
    checking: ['Checking this browser…', 'Your notification inbox is always available.'],
    connecting: ['Connecting this browser…', 'Please wait while we check your registration.'],
    requesting: ['Waiting for permission', 'Choose Allow in your browser’s notification prompt.'],
    disabling: ['Turning off desktop alerts…', 'Your saved preferences and inbox will stay available.'],
    enabled: ['Enabled on this browser', 'This browser is registered for desktop alerts.'],
    disabled: ['Desktop alerts are off', 'Enable alerts for updates when you are away from Tenantism.'],
    blocked: ['Blocked by your browser', 'Allow notifications in this site’s browser settings, then return here to enable alerts.'],
    unsupported: ['Desktop alerts are not supported', 'You can still read all your updates in the notification inbox.'],
    unavailable: ['Desktop alerts are temporarily unavailable', 'Your inbox and notification preferences are still available.'],
};

// Returning from browser settings with permission restored must reconnect;
// an earlier denied state has no binding and cannot remain stuck as blocked.
export function permissionState(permission, disabled, connected) {
    if (permission === 'denied') return 'blocked';
    if (permission !== 'granted' || disabled) return 'disabled';
    return connected ? 'enabled' : 'connect';
}

export function pushSettingsView(status, available = true) {
    const [title, hint] = states[status] || states.unavailable;
    return { title, hint, enabled: status === 'enabled',
        enable: available && ['disabled', 'unavailable'].includes(status),
        disable: status === 'enabled', test: status === 'enabled', check: status === 'enabled',
        enableLabel: status === 'unavailable' ? 'Try again' : 'Enable notifications' };
}

// Presentation is shared by registration updates and isolated browser UI checks.
export function renderPushSettings(root, status, available = true) {
        const view = pushSettingsView(status, available);
        root.querySelectorAll('[data-push-state]').forEach(el => { el.textContent = view.title; });
        root.querySelectorAll('[data-push-hint]').forEach(el => { el.textContent = view.hint; });
        root.querySelectorAll('[data-push-status]').forEach(el => {
            el.classList.toggle('border-green-200', view.enabled);
            el.classList.toggle('bg-green-50', view.enabled);
            el.classList.toggle('border-slate-200', !view.enabled);
            el.classList.toggle('bg-slate-50', !view.enabled);
        });
        for (const action of ['enable', 'disable', 'test', 'check']) {
            root.querySelectorAll(`[data-push-${action}]`).forEach(el => { el.hidden = !view[action]; });
        }
        root.querySelectorAll('[data-push-enable]').forEach(el => { el.textContent = view.enableLabel; });
}

export function deliverySummary(result) {
    if (!result?.ready || !result.matches || !result.subscribed) {
        return 'This browser is not fully connected to push. Disable desktop alerts, then enable them again to reconnect.';
    }
    if (!result.last) return 'This browser is connected, but no push has reached it since the delivery check was added. Your inbox can still update without push.';
    const messages = {
        foreground: 'A push reached this browser while Tenantism was in the foreground. It updated the inbox instead of showing a desktop alert.',
        desktop: 'A push reached this browser and Chrome created its desktop alert. Check your computer’s Notification Center if you missed it.',
        duplicate: 'A repeated push was ignored because this browser had already handled it.',
        ignored: 'A push arrived but did not match this browser’s current sign-in. A fresh test is needed for this account.',
        'display-failed': 'A push reached this browser, but Chrome could not create its desktop alert. Try the desktop notification test again.',
    };
    return messages[result.last.outcome] || 'The last delivery result is unavailable. Please refresh and check again.';
}
