// The navigation drawer works with CSS alone; this only adds the polish a
// keyboard user expects - Escape closes it, and the page never scrolls behind.
const toggle = document.getElementById('nav-toggle');
const pageLoader = document.getElementById('page-transition-loader');
const sidebarCollapse = document.querySelector('[data-sidebar-collapse]');
const notificationCountLink = document.querySelector('[data-notification-count-url]');
const notificationCount = document.querySelector('[data-notification-count]');

const showPageLoader = () => {
    if (!pageLoader) {
        return;
    }

    pageLoader.classList.remove('pointer-events-none', 'opacity-0');
    pageLoader.classList.add('pointer-events-auto', 'opacity-100');
    pageLoader.setAttribute('aria-hidden', 'false');
};

const hidePageLoader = () => {
    if (!pageLoader) {
        return;
    }

    pageLoader.classList.remove('pointer-events-auto', 'opacity-100');
    pageLoader.classList.add('pointer-events-none', 'opacity-0');
    pageLoader.setAttribute('aria-hidden', 'true');
};

const isPlainLeftClick = (event) => event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;

const shouldShowLoaderForLink = (link) => {
    const href = link.getAttribute('href');

    if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('javascript:')) {
        return false;
    }

    if (link.target && link.target !== '_self') {
        return false;
    }

    if (link.hasAttribute('download')) {
        return false;
    }

    const url = new URL(link.href, window.location.href);
    const current = new URL(window.location.href);

    return url.origin === current.origin && !(url.pathname === current.pathname && url.search === current.search && url.hash);
};

if (pageLoader) {
    window.addEventListener('pageshow', hidePageLoader);
    window.addEventListener('beforeunload', showPageLoader);

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');

        if (link && isPlainLeftClick(event) && shouldShowLoaderForLink(link) && !event.defaultPrevented) {
            showPageLoader();
        }
    });

    document.addEventListener('submit', (event) => {
        if (!event.defaultPrevented && !event.target.closest('[data-live-message-form]')) {
            showPageLoader();
        }
    });
}

if (toggle) {
    const sync = () => {
        document.body.classList.toggle('max-lg:overflow-hidden', toggle.checked);
    };

    toggle.addEventListener('change', sync);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && toggle.checked) {
            toggle.checked = false;
            sync();
        }
    });
}

if (sidebarCollapse) {
    const storageKey = 'tenantism.sidebarCollapsed';

    const sync = () => {
        const collapsed = document.body.classList.contains('sidebar-collapsed');
        sidebarCollapse.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
        sidebarCollapse.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
    };

    if (window.localStorage.getItem(storageKey) === 'true') {
        document.body.classList.add('sidebar-collapsed');
    }

    sidebarCollapse.addEventListener('click', () => {
        document.body.classList.toggle('sidebar-collapsed');
        window.localStorage.setItem(storageKey, document.body.classList.contains('sidebar-collapsed') ? 'true' : 'false');
        sync();
    });

    sync();
}

if (notificationCountLink && notificationCount) {
    const setNotificationCount = (count) => {
        const safeCount = Number.isFinite(count) ? Math.max(0, count) : 0;

        notificationCount.textContent = safeCount > 99 ? '99+' : String(safeCount);
        notificationCount.classList.toggle('hidden', safeCount < 1);
    };

    const refreshNotificationCount = async () => {
        try {
            const response = await fetch(notificationCountLink.dataset.notificationCountUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            setNotificationCount(Number(data.unread_count));
        } catch {
            // The server-rendered count remains in place if polling fails.
        }
    };

    window.addEventListener('focus', refreshNotificationCount);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refreshNotificationCount();
        }
    });

    refreshNotificationCount();
    window.setInterval(refreshNotificationCount, 30000);
}

const liveMessages = document.querySelector('[data-live-messages]');
const liveMessageForm = document.querySelector('[data-live-message-form]');

if (liveMessages) {
    const renderedMessageIds = new Set(
        Array.from(liveMessages.querySelectorAll('[data-message-id]')).map((element) => element.dataset.messageId),
    );
    const scrollMessagesToBottom = () => {
        liveMessages.scrollTop = liveMessages.scrollHeight;
    };

    const setLatestMessageId = (id) => {
        const latest = Math.max(Number(liveMessages.dataset.latestMessageId || 0), Number(id || 0));
        liveMessages.dataset.latestMessageId = String(latest);
    };

    const appendMessage = (message) => {
        const id = String(message?.id || '');

        if (!id || renderedMessageIds.has(id)) {
            return;
        }

        renderedMessageIds.add(id);
        liveMessages.querySelector('[data-message-empty]')?.remove();

        const article = document.createElement('article');
        article.dataset.messageId = id;
        article.className = message.mine ? 'flex justify-end' : 'flex';

        const bubble = document.createElement('div');
        bubble.className = [
            'max-w-[min(34rem,85%)] rounded-card px-4 py-3',
            message.mine ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-900',
        ].join(' ');

        const meta = document.createElement('div');
        meta.className = [
            'flex flex-wrap items-center gap-2 text-xs',
            message.mine ? 'text-brand-50' : 'text-slate-500',
        ].join(' ');

        const sender = document.createElement('span');
        sender.className = 'font-medium';
        sender.textContent = message.sender_name || 'Someone';

        const date = document.createElement('span');
        date.textContent = message.created_at_label || '';

        const body = document.createElement('p');
        body.className = 'mt-1 whitespace-pre-line text-sm leading-relaxed';
        body.textContent = message.body || '';

        meta.append(sender, date);
        bubble.append(meta, body);
        article.append(bubble);
        liveMessages.append(article);
        setLatestMessageId(message.id);
        scrollMessagesToBottom();
    };

    const refreshMessages = async () => {
        if (document.hidden) {
            return;
        }

        try {
            const url = new URL(liveMessages.dataset.messagesUrl, window.location.href);
            url.searchParams.set('after_id', liveMessages.dataset.latestMessageId || '0');

            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            (data.messages || []).forEach(appendMessage);
        } catch {
            // Keep the rendered thread in place if polling is interrupted.
        }
    };

    window.addEventListener('focus', refreshMessages);
    document.addEventListener('visibilitychange', refreshMessages);
    window.requestAnimationFrame(scrollMessagesToBottom);
    window.setInterval(refreshMessages, 3000);
}

if (liveMessageForm && liveMessages) {
    liveMessageForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (liveMessageForm.dataset.sending === 'true') {
            return;
        }

        const textarea = liveMessageForm.querySelector('[name="body"]');
        const submit = liveMessageForm.querySelector('[type="submit"]');
        const body = textarea?.value?.trim();

        if (!body) {
            textarea?.focus();
            return;
        }

        liveMessageForm.dataset.sending = 'true';
        submit?.setAttribute('disabled', 'disabled');

        try {
            const response = await fetch(liveMessageForm.action, {
                method: liveMessageForm.method || 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                credentials: 'same-origin',
                body: new FormData(liveMessageForm),
            });

            if (!response.ok) {
                liveMessageForm.submit();
                return;
            }

            const data = await response.json();
            appendMessage(data.message);
            textarea.value = '';
            textarea.focus();
        } catch {
            liveMessageForm.submit();
        } finally {
            liveMessageForm.dataset.sending = 'false';
            submit?.removeAttribute('disabled');
        }
    });
}

// FR-PROP-001 / BR-013 - a house let floor by floor is priced and published
// through its floors, so those fields are not its own. The form works without
// this: the rules make the rent optional and the action ignores the rest.
const rentalMode = document.querySelector('[data-rental-mode]');

if (rentalMode) {
    const rent = document.querySelector('[data-rent-field]');
    const visibility = document.querySelector('[data-visibility-fields]');
    const note = document.querySelector('[data-rental-mode-note]');

    const sync = () => {
        const byFloor = rentalMode.value === 'by_floor';

        rent?.toggleAttribute('hidden', byFloor);
        visibility?.toggleAttribute('hidden', byFloor);
        note?.toggleAttribute('hidden', !byFloor);
    };

    rentalMode.addEventListener('change', sync);
    sync();
}

const listingPurpose = document.querySelector('[data-listing-purpose]');

if (listingPurpose) {
    const rent = document.querySelector('[data-rent-field]');
    const sale = document.querySelector('[data-sale-field]');

    const sync = () => {
        const forSale = listingPurpose.value === 'sale';

        rent?.toggleAttribute('hidden', forSale);
        sale?.toggleAttribute('hidden', !forSale);
    };

    listingPurpose.addEventListener('change', sync);
    sync();
}
