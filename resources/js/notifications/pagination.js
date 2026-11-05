export function notificationPages(current, last) {
    const start = Math.max(1, Math.min(current - 1, last - 2));
    const end = Math.min(last, Math.max(current + 1, 3));
    const pages = new Set([1, last]);
    for (let page = start; page <= end; page++) pages.add(page);
    const items = [];
    let previous = 0;
    for (const page of [...pages].sort((a, b) => a - b)) {
        if (previous && page > previous + 1) items.push(null);
        items.push(page);
        previous = page;
    }
    return items;
}

export function renderNotificationPagination(root, current, last, href) {
    if (last <= 1 && current <= 1) return;
    const doc = root.ownerDocument;
    const nav = doc.createElement('nav');
    nav.className = 'notification-pagination';
    nav.setAttribute('aria-label', 'Notification pages');
    const add = (label, page, { disabled = false, active = false, rel } = {}) => {
        const item = doc.createElement(disabled || active ? 'span' : 'a');
        item.className = 'notification-page-link';
        item.textContent = label;
        if (disabled) item.setAttribute('aria-disabled', 'true');
        else if (active) item.setAttribute('aria-current', 'page');
        else {
            const url = new URL(href);
            url.searchParams.set('page', String(page));
            item.href = url.href;
            if (rel) item.setAttribute('rel', rel);
        }
        if (typeof label === 'number') item.setAttribute('aria-label', active ? `Page ${page}` : `Go to page ${page}`);
        nav.append(item);
    };
    add('Previous', Math.min(current - 1, last), { disabled: current <= 1, rel: 'prev' });
    for (const page of notificationPages(current, last)) {
        if (page === null) {
            const gap = doc.createElement('span');
            gap.className = 'px-1 text-sm text-slate-500';
            gap.textContent = '…';
            gap.setAttribute('aria-hidden', 'true');
            nav.append(gap);
        } else add(page, page, { active: page === current });
    }
    add('Next', current + 1, { disabled: current >= last, rel: 'next' });
    root.append(nav);
}
