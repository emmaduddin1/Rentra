import { test } from 'node:test';
import assert from 'node:assert/strict';
import { initPortalNavigation } from '../../../resources/js/portal-navigation.js';

function harness({ mobile = false, restricted = false } = {}) {
    const doc = new EventTarget();
    const element = () => {
        const el = new EventTarget();
        el.attributes = {};
        el.setAttribute = (name, value) => { el.attributes[name] = value; };
        el.focus = () => { doc.activeElement = el; };
        el.getClientRects = () => [{}];
        return el;
    };
    const toggle = element(), sidebar = element(), collapse = element(), control = element(), first = element(), last = element();
    const shell = {}, classes = new Set(), saved = new Map();
    const media = Object.assign(new EventTarget(), { matches: mobile });
    toggle.checked = false;
    control.click = () => {
        control.dispatchEvent(new Event('click'));
        toggle.focus(); // Match the browser's associated-label activation.
        toggle.checked = !toggle.checked;
        toggle.dispatchEvent(new Event('change'));
    };
    sidebar.querySelector = () => first;
    sidebar.querySelectorAll = () => [first, last];
    doc.body = { classList: { contains: key => classes.has(key), toggle(key, force = !classes.has(key)) { force ? classes.add(key) : classes.delete(key); return force; } } };
    doc.getElementById = () => toggle;
    doc.querySelector = selector => ({ '[data-sidebar]': sidebar, '[data-app-shell]': shell, '[data-sidebar-collapse]': collapse })[selector];
    doc.querySelectorAll = selector => selector === '[data-sidebar-toggle]' ? [control] : [];
    const win = { matchMedia: () => media, localStorage: {
        getItem(key) { if (restricted) throw new Error('Storage blocked'); return saved.get(key); },
        setItem(key, value) { if (restricted) throw new Error('Storage blocked'); saved.set(key, value); },
    } };
    const key = (target, value, shiftKey = false) => {
        const event = new Event('keydown', { cancelable: true });
        Object.assign(event, { key: value, shiftKey });
        target.dispatchEvent(event);
        return event;
    };
    initPortalNavigation(doc, win);
    return { doc, toggle, collapse, control, first, last, shell, saved, media, classes, key };
}

test('sidebar collapse stays usable and labelled when browser storage is blocked', () => {
    const h = harness({ restricted: true });
    h.collapse.dispatchEvent(new Event('click'));
    assert.equal(h.classes.has('sidebar-collapsed'), true);
    assert.equal(h.collapse.attributes['aria-label'], 'Expand sidebar');
    assert.equal(h.collapse.attributes['aria-pressed'], 'true');
    h.collapse.dispatchEvent(new Event('click'));
    assert.equal(h.classes.has('sidebar-collapsed'), false);
    assert.equal(h.collapse.attributes['aria-label'], 'Collapse sidebar');
});

test('sidebar collapse persists the chosen state for later pages', () => {
    const h = harness();
    h.collapse.dispatchEvent(new Event('click'));
    assert.equal(h.saved.get('tenantism.sidebarCollapsed'), 'true');
    h.collapse.dispatchEvent(new Event('click'));
    assert.equal(h.saved.get('tenantism.sidebarCollapsed'), 'false');
});

test('mobile navigation opens by keyboard, contains focus and restores the opener on Escape', () => {
    const h = harness({ mobile: true });
    h.control.focus();
    assert.equal(h.key(h.control, 'Enter').defaultPrevented, true);
    assert.equal(h.toggle.checked, true);
    assert.equal(h.control.attributes['aria-expanded'], 'true');
    assert.equal(h.shell.inert, true);
    assert.equal(h.doc.activeElement, h.first);
    h.key(h.doc, 'Tab', true);
    assert.equal(h.doc.activeElement, h.last);
    h.key(h.doc, 'Tab');
    assert.equal(h.doc.activeElement, h.first);
    h.key(h.doc, 'Escape');
    assert.equal(h.toggle.checked, false);
    assert.equal(h.shell.inert, false);
    assert.equal(h.doc.activeElement, h.control);
    assert.equal(h.control.attributes['aria-expanded'], 'false');
});

test('switching to desktop clears the mobile scroll lock and inert content', () => {
    const h = harness({ mobile: true });
    h.control.focus();
    h.key(h.control, ' ');
    h.media.matches = false;
    h.media.dispatchEvent(new Event('change'));
    assert.equal(h.toggle.checked, false);
    assert.equal(h.shell.inert, false);
    assert.equal(h.classes.has('max-lg:overflow-hidden'), false);
});
