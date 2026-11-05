export function initPortalNavigation(doc = document, win = window) {
    const toggle = doc.getElementById('nav-toggle');
    const sidebar = doc.querySelector('[data-sidebar]');
    const shell = doc.querySelector('[data-app-shell]');
    const collapse = doc.querySelector('[data-sidebar-collapse]');
    const mobile = win.matchMedia('(max-width: 1023px)');
    let wasOpen = false;
    let opener;

    if (toggle && sidebar) {
        const controls = [...doc.querySelectorAll('[data-sidebar-toggle]')];
        const sync = () => {
            const open = toggle.checked && mobile.matches;
            doc.body.classList.toggle('max-lg:overflow-hidden', open);
            controls.forEach(control => control.setAttribute('aria-expanded', String(open)));
            if (shell) shell.inert = open;
            if (open && !wasOpen) {
                opener ||= doc.activeElement;
                if (opener === toggle) opener = controls.find(control => !sidebar.contains(control));
                sidebar.querySelector('a[href]')?.focus({ preventScroll: true });
            } else if (!open && wasOpen) {
                opener?.focus({ preventScroll: true });
                opener = null;
            }
            wasOpen = open;
        };
        controls.forEach(control => {
            // Label activation focuses its checkbox before change fires.
            control.addEventListener('click', () => { if (!toggle.checked) opener = control; });
            control.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    control.click();
                }
            });
        });
        toggle.addEventListener('change', sync);
        mobile.addEventListener('change', () => {
            if (!mobile.matches) toggle.checked = false;
            sync();
        });
        doc.addEventListener('keydown', event => {
            if (!toggle.checked || !mobile.matches) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                toggle.checked = false;
                sync();
            } else if (event.key === 'Tab') {
                const focusable = [...sidebar.querySelectorAll('a[href], button:not([disabled]), [tabindex="0"]')]
                    .filter(element => element.getClientRects().length);
                const first = focusable[0], last = focusable.at(-1);
                if (event.shiftKey && doc.activeElement === first) { event.preventDefault(); last?.focus(); }
                else if (!event.shiftKey && doc.activeElement === last) { event.preventDefault(); first?.focus(); }
            }
        });
        sync();
    }

    if (collapse) {
        const storageKey = 'tenantism.sidebarCollapsed';
        const sync = () => {
            const collapsed = doc.body.classList.contains('sidebar-collapsed');
            collapse.setAttribute('aria-pressed', String(collapsed));
            collapse.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        };
        try {
            doc.body.classList.toggle('sidebar-collapsed', win.localStorage.getItem(storageKey) === 'true');
        } catch { /* Restricted storage must not disable navigation. */ }
        collapse.addEventListener('click', () => {
            doc.body.classList.toggle('sidebar-collapsed');
            try { win.localStorage.setItem(storageKey, String(doc.body.classList.contains('sidebar-collapsed'))); }
            catch { /* The current page can still collapse without persistence. */ }
            sync();
        });
        sync();
    }

    const menus = [...doc.querySelectorAll('[data-portal-menu]')];
    menus.forEach(menu => {
        const summary = menu.querySelector('summary');
        summary?.setAttribute('aria-expanded', String(menu.open));
        menu.addEventListener('toggle', () => {
            summary?.setAttribute('aria-expanded', String(menu.open));
            if (menu.open) menus.filter(other => other !== menu).forEach(other => { other.open = false; });
        });
        menu.addEventListener('keydown', event => {
            if (event.key === 'Escape' && menu.open) {
                event.preventDefault();
                menu.open = false;
                summary?.focus();
            }
        });
    });
    doc.addEventListener('click', event => {
        menus.filter(menu => menu.open && !menu.contains(event.target)).forEach(menu => { menu.open = false; });
    });
}
