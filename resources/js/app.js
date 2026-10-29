import './notifications.js';
// The navigation drawer works with CSS alone; this only adds the polish a
// keyboard user expects - Escape closes it, and the page never scrolls behind.
const toggle = document.getElementById('nav-toggle');
const pageLoader = document.getElementById('page-transition-loader');
const sidebarCollapse = document.querySelector('[data-sidebar-collapse]');

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

    window.addEventListener('tenantism:notification', refreshMessages);
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

// First-login product tour. Every step is anchored to a real control, and the
// completion flag is stored on the account so it follows the user across devices.
const productTour = document.querySelector('[data-product-tour]');

if (productTour) {
    const role = productTour.dataset.role;
    const highlight = productTour.querySelector('[data-tour-highlight]');
    const panel = productTour.querySelector('[data-tour-panel]');
    const title = productTour.querySelector('[data-tour-title]');
    const description = productTour.querySelector('[data-tour-description]');
    const progress = productTour.querySelector('[data-tour-progress]');
    const back = productTour.querySelector('[data-tour-back]');
    const next = productTour.querySelector('[data-tour-next]');
    const skip = productTour.querySelector('[data-tour-skip]');
    const manualTriggers = document.querySelectorAll('[data-start-product-tour]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const commonEnd = [
        {
            selector: '[data-tour="notifications"]',
            title: 'Stay informed',
            description: 'New invitations, applications, payments, maintenance updates, and contract changes appear here.',
        },
        {
            selector: '[data-tour="profile-menu"]',
            title: 'Your account and help',
            description: 'Open your profile, settings, this product tour, support, or log out from this menu.',
        },
    ];
    const steps = role === 'owner' ? [
        {
            selector: '[data-tour="brand"]',
            title: 'Welcome to Tenantism',
            description: 'The Tenantism logo always takes you back to your owner dashboard.',
        },
        {
            selector: '[data-tour="owner-add-property"]',
            title: 'Add your first property',
            description: 'Click here to create a property, add its details and images, and prepare it for publishing.',
        },
        {
            selector: '[data-tour="nav-properties"]',
            title: 'Manage your portfolio',
            description: 'View drafts, published listings, available properties, and occupied properties in one place.',
        },
        {
            selector: '[data-tour="owner-assign-renter"]',
            title: 'Invite a renter',
            description: 'Send a tenancy invitation to an existing renter or create a renter account. A property becomes occupied only after acceptance.',
        },
        {
            selector: '[data-tour="nav-approvals"]',
            title: 'Review monthly submissions',
            description: 'Approve rent and utility submissions and keep the financial record accurate.',
        },
        {
            selector: '[data-tour="nav-messages"]',
            title: 'Talk with renters',
            description: 'Open application conversations and respond to renter messages here.',
        },
        ...commonEnd,
    ] : [
        {
            selector: '[data-tour="brand"]',
            title: 'Welcome to Tenantism',
            description: 'The Tenantism logo always takes you back to your renter dashboard.',
        },
        {
            selector: '[data-tour="nav-browse"]',
            title: 'Find a property',
            description: 'Browse published properties and filter by location, type, purpose, and budget.',
        },
        {
            selector: '[data-tour="nav-applications"]',
            title: 'Track your applications',
            description: 'See every application and its latest owner response from this screen.',
        },
        {
            selector: '[data-tour="nav-tenancy"]',
            title: 'Review your tenancy',
            description: 'Accept or reject invitations, view your active contract, request cancellation, and see tenancy history.',
        },
        {
            selector: '[data-tour="nav-monthly"]',
            title: 'Submit monthly records',
            description: 'Record rent and utilities and attach payment evidence for owner review.',
        },
        {
            selector: '[data-tour="nav-maintenance"]',
            title: 'Request maintenance',
            description: 'Report a repair, add evidence, and follow its progress.',
        },
        {
            selector: '[data-tour="nav-messages"]',
            title: 'Message an owner',
            description: 'Continue your property application conversations without leaving Tenantism.',
        },
        ...commonEnd,
    ];
    let currentStep = 0;
    let active = false;
    let restoreCollapsed = false;

    const candidatesFor = (selector) => Array.from(document.querySelectorAll(selector));
    const visibleCandidate = (selector) => candidatesFor(selector).find((element) => {
        const rect = element.getBoundingClientRect();
        const style = window.getComputedStyle(element);

        return rect.width > 0 && rect.height > 0 && style.display !== 'none' && style.visibility !== 'hidden';
    });

    const complete = async () => {
        try {
            await fetch(productTour.dataset.completeUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
                credentials: 'same-origin',
            });
        } catch {
            // Completion can be retried on a future login if the network is down.
        }
    };

    const close = (markComplete = true) => {
        active = false;
        productTour.classList.add('hidden');
        productTour.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');

        if (restoreCollapsed) {
            document.body.classList.add('sidebar-collapsed');
        }

        if (window.innerWidth < 1024 && toggle?.checked) {
            toggle.checked = false;
            toggle.dispatchEvent(new Event('change'));
        }

        if (markComplete) {
            complete();
        }
    };

    const position = () => {
        if (!active) {
            return;
        }

        const step = steps[currentStep];
        const target = visibleCandidate(step.selector);

        if (!target) {
            if (currentStep < steps.length - 1) {
                currentStep += 1;
                position();
            } else {
                close();
            }
            return;
        }

        target.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        const rect = target.getBoundingClientRect();
        const padding = 6;
        highlight.style.top = `${Math.max(4, rect.top - padding)}px`;
        highlight.style.left = `${Math.max(4, rect.left - padding)}px`;
        highlight.style.width = `${Math.min(window.innerWidth - 8, rect.width + padding * 2)}px`;
        highlight.style.height = `${Math.min(window.innerHeight - 8, rect.height + padding * 2)}px`;

        title.textContent = step.title;
        description.textContent = step.description;
        progress.textContent = `Step ${currentStep + 1} of ${steps.length}`;
        back.disabled = currentStep === 0;
        next.textContent = currentStep === steps.length - 1 ? 'Finish' : 'Next';

        const panelRect = panel.getBoundingClientRect();
        const below = rect.bottom + 16;
        const top = below + panelRect.height <= window.innerHeight - 16
            ? below
            : Math.max(16, rect.top - panelRect.height - 16);
        const left = Math.min(
            window.innerWidth - panelRect.width - 16,
            Math.max(16, rect.left + rect.width / 2 - panelRect.width / 2),
        );
        panel.style.top = `${top}px`;
        panel.style.left = `${left}px`;
        next.focus({ preventScroll: true });
    };

    const render = () => {
        const step = steps[currentStep];
        const target = candidatesFor(step.selector)[0];
        const targetsSidebar = target?.closest('[data-sidebar]');

        if (targetsSidebar && window.innerWidth < 1024 && toggle && !toggle.checked) {
            toggle.checked = true;
            toggle.dispatchEvent(new Event('change'));
            window.setTimeout(position, 220);
            return;
        }

        if (!targetsSidebar && window.innerWidth < 1024 && toggle?.checked) {
            toggle.checked = false;
            toggle.dispatchEvent(new Event('change'));
            window.setTimeout(position, 220);
            return;
        }

        position();
    };

    const start = () => {
        if (active) {
            return;
        }

        active = true;
        currentStep = 0;
        restoreCollapsed = document.body.classList.contains('sidebar-collapsed');
        document.body.classList.remove('sidebar-collapsed');
        document.body.classList.add('overflow-hidden');
        productTour.classList.remove('hidden');
        productTour.setAttribute('aria-hidden', 'false');
        document.querySelectorAll('details[open]').forEach((details) => details.removeAttribute('open'));
        window.setTimeout(render, 80);
    };

    manualTriggers.forEach((trigger) => trigger.addEventListener('click', () => {
        const dashboard = new URL(productTour.dataset.dashboardUrl, window.location.href);
        const current = new URL(window.location.href);

        if (dashboard.pathname !== current.pathname) {
            window.sessionStorage.setItem('tenantism.startProductTour', 'true');
            window.location.assign(dashboard.href);
            return;
        }

        start();
    }));

    back.addEventListener('click', () => {
        if (currentStep > 0) {
            currentStep -= 1;
            render();
        }
    });
    next.addEventListener('click', () => {
        if (currentStep === steps.length - 1) {
            close();
            return;
        }

        currentStep += 1;
        render();
    });
    skip.addEventListener('click', () => close());
    window.addEventListener('resize', position);
    document.addEventListener('keydown', (event) => {
        if (active && event.key === 'Escape') {
            close();
        }
    });

    const manuallyRequested = window.sessionStorage.getItem('tenantism.startProductTour') === 'true';
    if (manuallyRequested) {
        window.sessionStorage.removeItem('tenantism.startProductTour');
    }

    if (productTour.dataset.autoStart === 'true' || manuallyRequested) {
        window.setTimeout(start, 400);
    }
}
