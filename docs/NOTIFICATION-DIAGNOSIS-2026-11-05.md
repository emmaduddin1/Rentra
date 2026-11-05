# Web notification diagnosis — 5 November 2026

Web fixes deployed to staging. Android app investigation is deferred at the user's request. No production deployment, Git push/merge, credential replacement, database migration, history reset or cron change was performed.

The latest chat explicitly authorized staging deployment after completion, superseding the attached brief's deployment restriction. Earlier audit changes and project documentation were preserved.

## Confirmed findings and fixes

1. **Zero unread badge stayed visible.** Real Chrome showed `text=0`, class `hidden`, but computed `display=flex`. The generated CSS puts `.inline-flex` after `.hidden`, so the competing display utilities defeat hiding. The bell itself was a visible 20×20 inline SVG with a nontransparent stroke on desktop and mobile. The navbar now uses the native `hidden` attribute, and the shared count renderer synchronizes visibility and accessible labels. The bell remains available at zero unread.
2. **The inbox Unread tab count was static.** It used a server-only tab count with no live-update hook. It now shares `data-notification-count` with the navbar, including transitions from zero to unread and back. Read-all visibility and the navbar's accessible label update together.
3. **Restored notification permission could leave the settings stuck.** Denial clears the browser binding; after permission became granted, the previous reconciliation code only handled an already-present binding, leaving the blocked state without an Enable action. The state now reconnects when permission is granted and no binding exists, without requesting permission again. A pending connection is shared to avoid overlapping registrations. Explicit user opt-out is still respected and permission prompts remain user initiated.
4. **Same-second events lacked a deterministic newest-first order.** The notification relation sorted only by `created_at`, which can tie across comments/events. It now uses descending ID as a tie-breaker, with a feed/inbox regression test. Existing history and pagination contracts are preserved.

Sources: [count rendering](../resources/js/notifications/count.js), [browser connection/reconciliation](../resources/js/notifications.js), [permission state](../resources/js/notifications/settings.js), [navbar](../resources/views/components/nav/authenticated-app-bar.blade.php), [inbox](../resources/views/notifications/index.blade.php), [ordering](../app/Models/User.php).

## Maintenance comments and recipients

The reported scenario is renter on Android → owner on Mac web, followed by owner on web → renter on Android. Comments already use `AddMaintenanceComment` to notify the other party. This was verified rather than replaced or changed to notify the author.

A read-only **server-side aggregate** found 29 stored maintenance comments, 29 corresponding notifications, zero missing notifications and 29 correctly routed notifications. The delivery ledger contained 19 accepted maintenance-comment sends. This establishes aggregate server behavior; it does not identify or certify the particular failed event or the user's Mac receipt.

A more detailed staging trace was rejected by automatic approval review because it would export individual recipient/device metadata to a local file. That query did not run. The safer aggregate returned counts only, without individual user/device IDs, comment contents, tokens or read states. Controlled local fixtures were used for detailed diagnosis.

No staging notification was generated or resent to a real user for testing. Synthetic actual-Firebase sends targeted only a token created in a new disposable local Chrome profile; they did not create staging business/notification/subscription records.

## Stage-by-stage diagnostic results

| Stage | Result | Actual evidence and boundary |
| --- | --- | --- |
| Event generated | Pass, isolated | Renter maintenance-comment HTTP POST executed through the real controller/action; both existing comment directions remain covered by the maintenance suite. |
| Correct recipient | Pass, isolated; aggregate staging check | The queue regression asserts the owner receives the renter comment and the registered author receives no send. Staging aggregate matches all 29 stored comments to the intended recipient. |
| Notification stored | Pass, isolated | One `maintenance.commented` notification, with the expected user ID; no duplicate persisted event. |
| Outbox created | Pass, isolated | `push_pending=true`, `push_queued_at` present, one real database queue job after the business commit. |
| Job processed | Pass, isolated; staging operations healthy | Actual `queue:work` consumes the outbox/fanout/device jobs, leaves zero jobs/failures and one sent delivery ledger entry. Staging cron heartbeat completed with zero queued/failed push jobs. |
| FCM HTTP contract accepted | Pass, simulation | The real queued comment ran over HTTP against the existing local Firebase stand-in: OAuth 200 and send 200, 17 assertions. Generated credentials and isolated data only. |
| Google FCM accepted | Pass, controlled live transport | The existing staging `FcmClient` accepted five synthetic sends to the disposable browser only: first display, replay, foreground, background and restarted profile. Credentials remained on the server. This is a separate transport test, not a claim that a real user's business notification was delivered. |
| Browser received | Pass, controlled live transport | Real Google messages reached the worker/page. Worker diagnostic outcomes were desktop, duplicate, foreground, desktop and desktop after restart. No local push event was injected for this result. |
| Browser alert created | Pass, controlled live transport | Chrome `ServiceWorkerRegistration.getNotifications()` contained the expected synthetic IDs for desktop/background/restart; no extra alert for foreground or replay. |
| Visible macOS banner/person clicked | Not verified in this run | Automated Chrome alert creation cannot certify the human-visible OS banner, Focus behavior or a physical click. |
| Bell/badge/list | Pass, isolated real Chrome | Bell remained 20px and visible; a real renter comment appeared without navigation/refresh, navbar and Unread tab changed 0→1, and marking it read changed both to zero with the badge hidden. |
| Multiple-tab read synchronization | Pass, isolated real Chrome | Both owner tabs first received the new unread count; a read in the inbox synchronized the second tab to zero. |

The end-to-end **business/queue test** and **actual-Google browser transport test** use separate controlled fixtures. No claim is made that all table rows represent one authenticated staging-user transaction.

## Browser acceptance matrix

| Scenario | Evidence |
| --- | --- |
| Foreground | Actual Google receipt; page receives the message and worker records `foreground`, with no duplicate desktop alert. |
| Background | Actual Google receipt while another tab is active; browser creates the expected alert. |
| Replay | Actual second send is received and recorded as duplicate; only one alert exists for that ID. |
| Browser restart/re-subscription | Same disposable regular profile reopened, SDK registration re-established with automation-granted permission, fresh Google push received and alert created; synthetic token then deleted and local worker unregistered. This does not test persistence of a human-granted OS permission. |
| Multiple tabs | Live local inbox/count/read synchronization passed; worker deduplication/account validation also remain covered by JS tests. Multi-tab real-account OS behavior remains manual. |
| Permission denied then re-enabled | Permission state regression verifies reconnection instead of a stuck blocked state, and respects explicit opt-out; real system/browser settings interaction remains manual. |
| Notification click/read/navigation | Existing worker and Laravel tests cover protected click, safe route, authorization/CSRF, read state and expired login; a human clicking an actual OS alert was not repeated here. |
| Logout/login/account switching | Existing browser binding/revocation and account-switch tests passed. Interactive system-alert behavior across a real user's logout/login remains manual. |
| Minimized browser | Not verified; no desktop-minimization pass is inferred from a background-tab test. |
| Android app | Deferred by the user; no Flutter files were changed. |

Early transport probes used private/incognito automation contexts, where subscription failed despite a granted Notification API permission. A fresh regular profile registered successfully and was used for the actual Google tests. These harness failures are not presented as Tenantism application defects.

## Tests and validation

- `php artisan test --compact`: **429 passed / 2,321 assertions** on PHP 8.5.
- PHP 8.3 full suite: **429 passed / 2,321 assertions**.
- `npm run test:js`: **37 passed**.
- `npm run build`: passed; existing optional `fontaine` fallback warning only.
- Focused notification/push suite before adding queue coverage: 41 passed / 256 assertions; the new queued-comment regression then passed 17 assertions and is included in the final full suite.
- Opt-in real HTTP queued maintenance-comment simulation: **1 method passed / 17 assertions**, two actual HTTP requests logged by the Firebase member.
- Real Chrome local comment → live inbox/badge → cross-tab read: passed at desktop/mobile widths.
- Real Google transport: five accepted controlled sends, with actual worker receipt and expected display/deduplication outcomes.
- Pint and `git diff --check`: passed.

Regressions: [notification count](../tests/Unit/JavaScript/notification-count.test.js), [permission recovery](../tests/Unit/JavaScript/notification-settings.test.js), [same-second feed ordering](../tests/Feature/NotificationTest.php), [real database queue/maintenance recipient](../tests/Feature/WebPushTest.php), [queued HTTP simulation](../tests/Integration/FcmSimulationTest.php).

## Staging release

Installed 20 application/source/asset files and 14 matching public-root asset files, all verified against checksums. These include six targeted source files plus the compiled build; the FCM worker, sender, outbox, queue architecture, credentials and cron were retained. Blade views were rebuilt. No schema or vendor update was needed for this release.

Verified `.env`, entry points, rewrite files, service worker files and both storage-link targets remained unchanged. Staging push configuration is available, sender project/environment is `tenantism-staging`/`staging`, VAPID is configured, credentials are readable, and the persistent queue remains `database`/`web-push`. All 21 post-release HTTP checks passed, covering health/public pages, worker/config/icons and every deployed build asset. Firebase SDK scripts also returned HTTP 200; no missing icon asset was found. No cron cause was evidenced, so its configuration was not changed.

Private source/asset backup: `/home/u577461158/domains/staging.tenantism.com/rentra-staging/storage/app/backups/pre-notification-fix-20261105`. Existing assets were retained for older open pages. Previous audit deployment and user uploads are preserved. Local reviewed manifest/results: `/private/tmp/tenantism-notification-release-20261105`.

## Screenshots and local review

- [Before desktop](../storage/app/private/notification-diagnosis-20261105/notification-before-desktop.png)
- [Live maintenance comment — desktop](../storage/app/private/notification-diagnosis-20261105/maintenance-comment-live-desktop.png)
- [Live maintenance comment — mobile](../storage/app/private/notification-diagnosis-20261105/maintenance-comment-live-mobile.png)

These are ignored local screenshots of clearly identified sample accounts, not real user data. Synthetic comments remain clearly marked in the isolated preview; existing notification history was not removed.

Use `php bin/local-ui-preview.php serve` for the isolated sample preview at `http://127.0.0.1:8000`. The workstation `.env` points toward staging; ordinary local server/migration/reset commands are not an isolated preview. This preview disables real push/email. The actual-Google diagnostic instead used a separate disposable loopback page/profile and only public Firebase configuration locally; no service-account file was downloaded.

For the user's own Mac, reload `https://staging.tenantism.com/notifications` once to load the new release, confirm desktop alerts are enabled, and inspect **Check delivery** after a fresh incoming event. Existing cron dispatch can add up to roughly one minute of delay; inbox fallback polling is 30 seconds. A focused foreground page deliberately receives an in-app update instead of a duplicate OS popup. A successful local **Test desktop notification** checks browser/OS display only and does not establish Firebase transport. The user's particular Mac subscription/OS settings have not been certified by the disposable-profile test.

## References and tooling

- [Firebase web setup](https://firebase.google.com/docs/cloud-messaging/web/get-started), [receiving web messages](https://firebase.google.com/docs/cloud-messaging/web/receive-messages), [messaging API](https://firebase.google.com/docs/reference/js/messaging): registration and foreground/background distinctions.
- Existing local Postman API Simulation workflow/provider mock was reused; nothing was published or deployed through Postman. The adjacent simulation context preserves historical findings and adds this run's scoped evidence.
