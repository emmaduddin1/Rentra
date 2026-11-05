import { test } from 'node:test';
import assert from 'node:assert/strict';
import { renderUnreadCount } from '../../../resources/js/notifications/count.js';

function harness() {
    const badges = Array.from({ length: 2 }, () => ({ textContent: '', hidden: false, classes: new Set(['hidden']), classList: { remove(value) { this.owner.classes.delete(value); } } }));
    badges.forEach(el => { el.classList.owner = el; });
    const action = { hidden: false };
    const toggle = { setAttribute(name, value) { this[name] = value; } };
    return { badges, action, toggle, querySelectorAll(selector) {
        return { '[data-notification-count]': badges, '[data-notifications-read-all]': [action], '[data-notification-count-url]': [toggle] }[selector];
    } };
}

test('zero count hides both navbar and inbox badges with native hidden and keeps the bell accessible', () => {
    const root = harness();
    renderUnreadCount(root, 0);
    assert.ok(root.badges.every(el => el.hidden && !el.classes.has('hidden')));
    assert.equal(root.action.hidden, true);
    assert.equal(root.toggle['aria-label'], 'Notifications: none unread');
});

test('live counts and read-all synchronize every badge, including large counts', () => {
    const root = harness();
    renderUnreadCount(root, 105);
    assert.ok(root.badges.every(el => !el.hidden && el.textContent === '99+'));
    assert.equal(root.toggle['aria-label'], 'Notifications: 105 unread');
    assert.equal(root.action.hidden, false);
    renderUnreadCount(root, 1);
    assert.ok(root.badges.every(el => el.textContent === '1'));
    renderUnreadCount(root, 0);
    assert.ok(root.badges.every(el => el.hidden));
});
