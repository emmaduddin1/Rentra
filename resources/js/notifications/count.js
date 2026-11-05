export function renderUnreadCount(root, value) {
    const count = Math.max(0, Math.trunc(Number(value) || 0));
    root.querySelectorAll('[data-notifications-read-all]').forEach(el => { el.hidden = count < 1; });
    root.querySelectorAll('[data-notification-count]').forEach(el => {
        el.textContent = count > 99 ? '99+' : String(count);
        // Display utilities can override Tailwind's .hidden class. The native
        // hidden attribute also keeps this out of the accessibility tree.
        el.classList.remove('hidden');
        el.hidden = count < 1;
    });
    root.querySelectorAll('[data-notification-count-url]').forEach(el => {
        el.setAttribute('aria-label', count ? `Notifications: ${count} unread` : 'Notifications: none unread');
    });
}
