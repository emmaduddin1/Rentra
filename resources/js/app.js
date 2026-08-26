// The navigation drawer works with CSS alone; this only adds the polish a
// keyboard user expects - Escape closes it, and the page never scrolls behind.
const toggle = document.getElementById('nav-toggle');

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

// FR-TEN-001 - a renter is assigned to the property they applied for, so the
// renter select only offers that property's applicants. The page works without
// this: the options carry their property in a data attribute and every
// applicant stays selectable, each one labelled with its application status.
const propertySelect = document.getElementById('property_id');
const applicantSelect = document.querySelector('[data-applicant-select]');

if (propertySelect && applicantSelect) {
    const emptyNote = document.querySelector('[data-applicant-empty-note]');
    const options = Array.from(applicantSelect.options);
    const applicants = options.filter((option) => option.dataset.propertyId);
    const placeholders = Object.fromEntries(
        options
            .filter((option) => option.dataset.placeholderFor)
            .map((option) => [option.dataset.placeholderFor, option]),
    );

    const sync = () => {
        const propertyId = propertySelect.value;

        let matches = 0;

        for (const option of applicants) {
            // A hidden option is still submittable in some browsers, so the
            // ones that do not belong are disabled as well.
            const belongs = propertyId !== '' && option.dataset.propertyId === propertyId;

            option.hidden = !belongs;
            option.disabled = !belongs;

            matches += belongs ? 1 : 0;
        }

        // Whichever placeholder fits the situation is the only one shown, and
        // it takes over the selection when the current pick no longer applies.
        const active = propertyId === '' ? 'unchosen' : (matches === 0 ? 'none' : 'chosen');

        for (const [key, option] of Object.entries(placeholders)) {
            option.hidden = key !== active;
        }

        const selected = applicantSelect.selectedOptions[0];

        if (!selected || selected.disabled || selected.dataset.placeholderFor) {
            placeholders[active].selected = true;
        }

        applicantSelect.disabled = matches === 0;
        emptyNote?.toggleAttribute('hidden', active !== 'none');
    };

    propertySelect.addEventListener('change', sync);
    sync();
}
