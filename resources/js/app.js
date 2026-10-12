// The navigation drawer works with CSS alone; this only adds the polish a
// keyboard user expects - Escape closes it, and the page never scrolls behind.
const toggle = document.getElementById('nav-toggle');
const pageLoader = document.getElementById('page-transition-loader');

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
        if (!event.defaultPrevented) {
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
