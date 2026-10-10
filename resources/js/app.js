// The navigation drawer works with CSS alone; this only adds the polish a
// keyboard user expects - Escape closes it, and the page never scrolls behind.
const toggle = document.getElementById('nav-toggle');
const pageLoader = document.getElementById('page-transition-loader');

const showPageLoader = () => {
    pageLoader?.classList.add('opacity-100');
};

if (pageLoader) {
    window.addEventListener('beforeunload', showPageLoader);

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
