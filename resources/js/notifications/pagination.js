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

export function renderNotificationPagination(root, current, last, href, results = {}) {
    if (last <= 1 && current <= 1) return;
    const doc = root.ownerDocument;
    const footer = doc.createElement('div');
    footer.className = 'notification-pagination-footer';
    const summary = doc.createElement('p');
    summary.className = 'notification-pagination-summary';
    const first = Number(results.from) || 0;
    const final = Number(results.to) || 0;
    const total = Number(results.total) || 0;
    summary.textContent = `Showing ${first} to ${final} of ${total} results`;
    const nav = doc.createElement('nav');
    nav.className = 'notification-pagination';
    nav.setAttribute('aria-label', 'Notification pages');
    const start = Math.max(1, Math.min(current - 1, last - 2));
    const end = Math.min(last, Math.max(current + 1, 3));
    const add = (label, page, { disabled = false, active = false, rel } = {}) => {
        const item = doc.createElement(disabled || active ? 'span' : 'a');
        item.className = 'notification-page-link';
        if (typeof label === 'number' && last > 4 && (page < start || page > end)) item.setAttribute('data-pagination-extra', '');
        if (rel) {
            item.setAttribute('aria-label', label);
            const icon = doc.createElementNS('http://www.w3.org/2000/svg', 'svg');
            for (const [name, value] of Object.entries({ viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '2', 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true', focusable: 'false', class: 'size-4' })) icon.setAttribute(name, value);
            const path = doc.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', rel === 'prev' ? 'M15 18l-6-6 6-6' : 'M9 6l6 6-6 6');
            icon.append(path);
            item.append(icon);
        } else item.textContent = label;
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
            gap.className = 'notification-page-link';
            gap.setAttribute('data-pagination-extra', '');
            gap.textContent = '…';
            gap.setAttribute('aria-hidden', 'true');
            nav.append(gap);
        } else add(page, page, { active: page === current });
    }
    add('Next', current + 1, { disabled: current >= last, rel: 'next' });
    footer.append(summary, nav);
    root.append(footer);
}
