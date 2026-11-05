import { test } from 'node:test';
import assert from 'node:assert/strict';
import { pushSettingsView, deliverySummary, permissionState } from '../../../resources/js/notifications/settings.js';

test('restoring granted permission reconnects a revoked binding while respecting explicit disable', () => {
    assert.equal(permissionState('denied', false, true), 'blocked');
    assert.equal(permissionState('granted', false, false), 'connect');
    assert.equal(permissionState('granted', false, true), 'enabled');
    assert.equal(permissionState('granted', true, false), 'disabled');
    assert.equal(permissionState('default', false, false), 'disabled');
});

test('a confirmed enabled browser offers disable and test instead of another enable action', () => {
    const view = pushSettingsView('enabled');
    assert.equal(view.enable, false);
    assert.equal(view.disable, true);
    assert.equal(view.test, true);
    assert.match(view.title, /Enabled/);
});

test('permission and availability states never offer actions that cannot run', () => {
    for (const status of ['checking', 'connecting', 'requesting', 'disabling', 'blocked', 'unsupported']) {
        const view = pushSettingsView(status);
        assert.equal(view.enable, false, status);
        assert.equal(view.disable, false, status);
        assert.equal(view.test, false, status);
    }
    assert.equal(pushSettingsView('disabled').enable, true);
    assert.equal(pushSettingsView('unavailable', false).enable, false);
    assert.equal(pushSettingsView('unavailable', true).enableLabel, 'Try again');
    assert.match(pushSettingsView('blocked').hint, /site’s browser settings/);
});

test('delivery summaries distinguish transport receipts from the independently updating inbox', () => {
    const connected = { ready: true, matches: true, subscribed: true };
    assert.match(deliverySummary(connected), /no push has reached/);
    assert.match(deliverySummary({ ...connected, last: { outcome: 'foreground' } }), /foreground/);
    assert.match(deliverySummary({ ...connected, last: { outcome: 'desktop' } }), /Chrome created/);
    assert.match(deliverySummary({ ...connected, subscribed: false }), /not fully connected/);
});
