import { test } from 'node:test';
import assert from 'node:assert/strict';
import { notificationPages, renderNotificationPagination } from '../../../resources/js/notifications/pagination.js';

function root() {
    const doc = { createElement(tagName) {
        return { tagName, children: [], attributes: {}, append(...children) { this.children.push(...children); },
            setAttribute(key, value) { this.attributes[key] = value; } };
    } };
    doc.createElementNS = (_namespace, tagName) => doc.createElement(tagName);
    return { ...doc.createElement('div'), ownerDocument: doc };
}

test('numbered pagination keeps the ends and nearby pages with gaps for large inboxes', () => {
    assert.deepEqual(notificationPages(1, 3), [1, 2, 3]);
    assert.deepEqual(notificationPages(1, 12), [1, 2, 3, null, 12]);
    assert.deepEqual(notificationPages(6, 12), [1, null, 5, 6, 7, null, 12]);
    assert.deepEqual(notificationPages(12, 12), [1, null, 10, 11, 12]);
    assert.equal(notificationPages(500, 1000).length, 7);
});

test('live pagination preserves query filters, current page semantics and boundary controls', () => {
    for (const current of [1, 6, 12]) {
        const target = root();
        renderNotificationPagination(target, current, 12, 'https://tenantism.test/notifications?filter=unread&page=6', { from: 101, to: 120, total: 231 });
        const [footer] = target.children;
        const [summary, nav] = footer.children;
        assert.equal(footer.className, 'notification-pagination-footer');
        assert.equal(summary.textContent, 'Showing 101 to 120 of 231 results');
        assert.equal(nav.attributes['aria-label'], 'Notification pages');
        const active = nav.children.find(item => item.attributes['aria-current'] === 'page');
        assert.equal(active.textContent, current);
        assert.equal(active.tagName, 'span');
        for (const link of nav.children.filter(item => item.tagName === 'a')) {
            const url = new URL(link.href);
            assert.equal(url.pathname, '/notifications');
            assert.equal(url.searchParams.get('filter'), 'unread');
            assert.ok(Number(url.searchParams.get('page')) >= 1 && Number(url.searchParams.get('page')) <= 12);
        }
        assert.equal(nav.children[0].attributes['aria-disabled'], current === 1 ? 'true' : undefined);
        assert.equal(nav.children.at(-1).attributes['aria-disabled'], current === 12 ? 'true' : undefined);
        assert.equal(nav.children[0].attributes['aria-label'], 'Previous');
        assert.equal(nav.children.at(-1).attributes['aria-label'], 'Next');
        assert.equal(nav.children[0].children[0].tagName, 'svg');
        assert.equal(nav.children[0].children[0].attributes['aria-hidden'], 'true');
    }
});

test('single-page inboxes hide pagination and a shrunken unread inbox still offers a way back', () => {
    const single = root();
    renderNotificationPagination(single, 1, 1, 'https://tenantism.test/notifications');
    assert.equal(single.children.length, 0);
    const shrunk = root();
    renderNotificationPagination(shrunk, 4, 1, 'https://tenantism.test/notifications?filter=unread&page=4', { from: 0, to: 0, total: 0 });
    const previous = shrunk.children[0].children[1].children[0];
    assert.equal(new URL(previous.href).searchParams.get('page'), '1');
});
