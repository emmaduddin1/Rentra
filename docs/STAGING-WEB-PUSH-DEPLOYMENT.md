# Web push staging deployment — 27–28 October 2026

Status: deployed to **https://staging.tenantism.com**, with push enabled and automatic hPanel scheduler/queue execution verified. Production was not changed.

## Deployed configuration

- Firebase staging project: `tenantism-staging`.
- Migration `2026_10_27_000002_add_web_push_notifications`: applied in **batch 6**.
- Dedicated Firebase sender credential: `/home/u577461158/.config/tenantism/firebase/tenantism-staging-service-account.json`, outside the checkout and public root. Directory mode `0700`, file mode `0600`.
- Staging's existing `.env` was updated only for `PUSH_*` and `FIREBASE_*` settings. A comparison against the pre-deployment backup verified that all existing non-push environment settings were preserved.
- Application default queue remains `database`; push jobs use only the `web-push` queue. Existing database retry interval remains 90 seconds.
- Laravel configuration, routes and views were rebuilt successfully on the server.
- The targeted release deployed 51 application/assets files. SHA-256 comparison found **zero application or public-file mismatches**. Environment files, private keys, dependencies and storage were excluded from the application archive.
- Both staging rewrite files received only the backed-up worker cache-policy addition; existing rewrites were preserved. Worker registration now uses a content-version query to bypass Hostinger CDN's earlier cached response. The actual registered URL returns current worker bytes and `Cache-Control: no-cache, no-store, must-revalidate`.
- Short-lived Firebase OAuth tokens are cached only in worker memory, never the database cache.

## Backups and preserved paths

Verified private backup directory:

```text
/home/u577461158/domains/staging.tenantism.com/rentra-staging/storage/app/backups/pre-web-push-20261027-12a48003
```

It contains the verified compressed database dump, targeted application files including the original server `.env`, public assets/entry point, rewrite-file backups and verification state. The temporary MySQL credentials file was deleted after the dump completed. Database dump size was 24,132 bytes and `gzip -t` passed before migration.

The Hostinger-specific `public_html/index.php` checksum remained unchanged. Both storage links still target:

```text
/home/u577461158/domains/staging.tenantism.com/rentra-staging/storage/app/public
```

No staging database rows, browser registrations or credentials were copied into production. No Flutter files were changed.

## hPanel cron configuration

Hostinger does not expose a `crontab` executable to this SSH account. The staging job was configured through hPanel and verified by its automatic heartbeat.

- Website: `staging.tenantism.com`.
- Type: **Custom**.
- Common setting: **Once per minute**.
- Schedule: `* * * * *` (each of the five fields is `*`).
- Command to run:

```text
/bin/sh /home/u577461158/domains/staging.tenantism.com/rentra-staging/bin/staging-web-push-cron.sh
```

Keep one staging scheduler entry; update an existing staging `artisan schedule:run` entry rather than duplicating it. Production entries remain unchanged.

The shell wrapper enables only `proc_open` for this CLI invocation, preserving the host's other disabled functions. Website PHP settings are unchanged. The PHP wrapper verifies staging paths/domain/Firebase project, acquires a private lock, runs `schedule:run`, then runs `queue:work database --queue=web-push --stop-when-empty --tries=3 --timeout=60 --max-time=50`. It avoids concurrent/duplicate completed runs within a minute.

Private runtime files:

```text
storage/app/private/staging-web-push-cron.json
storage/logs/staging-web-push-cron.log
storage/framework/staging-web-push-cron.lock
```

Multiple automatic executions were observed with `source=hpanel`, `status=complete`, `scheduler_exit=0` and `worker_exit=0`. The final recorded verification saw a completed run at **28 October 2026, 00:00:02–00:00:08 Asia/Karachi**, with a heartbeat age of 19 seconds. These were actual hPanel executions, not a manual scheduler smoke test.

## Verification completed

- Local Laravel suite after click fix: **389 passed, 1,887 assertions**.
- JavaScript suite after click fix: **16 passed**.
- Production Vite build, Pint, PHP/shell syntax and `git diff --check`: passed.
- Hostinger service-account OAuth exchange: passed.
- Hostinger FCM `validate_only` request: **HTTP 200**; no notification delivered.
- Queue smoke test: nonexistent notification ID `0` queued and processed successfully; no business notification created, zero remaining push jobs and zero new failed jobs.
- Final queue state: zero queued push jobs and zero failed push jobs.
- Automatic hPanel scheduler/worker: verified across multiple minute intervals.
- Homepage, login, `/up`, root push worker, public Firebase configuration and current built JS: **HTTP 200**.
- Exact content-versioned worker URL: **HTTP 200**, current byte checksum and no-store cache policy verified.
- Public configuration: enabled, staging project/environment, expected 87-character public VAPID key, no service-account path/private key fields.
- Notification icon and badge: **HTTP 200**, PNG.
- A real published listing image: **HTTP 200**, WebP.
- Exact Firebase 13.1.0 App/Messaging worker CDN URLs: **HTTP 200**.
- Staging migration status: all migrations applied, including web push batch 6.

## Remaining browser verification

The server deployment and automation are complete. On 28 October 2026, the user enabled notifications in Chrome and its active staging browser registration was verified. One privacy-safe configuration test notification was created through the normal database/queued-push flow; FCM accepted it on the first attempt, with zero device failures, zero failed queue jobs and no remaining queued push jobs. The user subsequently clarified that the visible notification was in Tenantism's website bell. Desktop/system display and automatic read on a desktop-alert click are therefore **not yet verified**. The earlier test was manually marked read in preparation for a fresh background-tab test. Foreground/background behavior, multiple tabs, logout/account switching and supported Safari behavior still require the remaining real-browser checklist in [WEB-PUSH-NOTIFICATIONS.md](WEB-PUSH-NOTIFICATIONS.md).

Start at https://staging.tenantism.com/notifications while signed in and select **Enable notifications**. The later configuration test targeted only the account whose Chrome device the user had just enabled; it did not change tenancy, payment or maintenance business records. This does not establish universal closed-browser/OS support.

## Release records and rollback

Local prepared release records remain under `/tmp/tenantism-web-push-release/`: the application/public manifests, SHA-256 map, final archive and `server-verification.json`. These are temporary local artifacts; the private server backups above are the durable rollback reference.

For a code/configuration rollback, disable staging push and restore the backed-up application/public files, server environment and rewrite files. Retain the additive tables and history unless a separately reviewed database rollback is required; restoring a database dump would overwrite any subsequent business changes. Stop/update only the staging hPanel entry if rolling back this wrapper.

Hostinger references: [Cron Jobs setup](https://www.hostinger.com/support/1583465-how-to-set-up-a-cron-job-at-hostinger/), [shell wrapper for special characters](https://www.hostinger.com/support/5646919-how-to-set-up-a-cron-job-with-special-characters-at-hostinger/).


## Chrome click/read follow-up — 28 October 2026

FCM accepted the first real test. The user's screenshot showed the inbox with the test still New; they later clarified that the notification appeared in the website bell, so that screenshot does not establish a desktop-click failure. Review of the original worker nevertheless identified click-path gaps: it only navigated to an opening page, the database read action depended on the subsequent page flow, and it did not support Firebase-wrapped alert data or a failed existing-window navigation.

A targeted fix is now deployed: the click handler fetches a same-origin authenticated/no-store JSON context, obtains its session CSRF token, POSTs the authorized read action, then follows only the server's validated internal route. Existing-window failures open a new window; expired login/read/network failures retain the protected opening-form fallback. Successful reads notify all tabs to reconcile. Stale account bindings cannot issue reads. Displayed alerts remain clickable if push was later disabled.

The patch backup is `storage/app/backups/pre-web-push-click-fix-20261028/`, containing application/controller/worker and public assets. No migration, credential or environment change was made for this fix. All 51 release file checksums match; the automatic cron, queues, preserved server settings, entry point and storage links remain healthy. The new versioned worker bytes/no-store policy and bundle return HTTP 200; guest click-context requests return HTTP 401 without a CSRF token.

The user has been asked to refresh once to activate the new worker, mark the earlier test read, and background the staging tab before a fresh click verification. The updated live click/read behavior remains pending that retest; do not mark this as browser-verified yet.

At **28 October 2026, 01:00:04 Asia/Karachi**, FCM accepted a second privacy-safe test (`notification_id=33`, type `account.push_click_test`) on its first attempt. It targeted only the same confirmed staging account and active Chrome registration. Delivery ledger status is `sent`, error code is null, device failure count is zero, and no push jobs remain queued or failed. The record was still unread immediately after sending. User confirmation of a desktop popup or OS Notification Center entry is pending; FCM acceptance alone does not establish browser display.

## Background display follow-up — 28 October 2026

The user confirmed the second test appeared only in Tenantism's website bell. Desktop display is still unverified. Source inspection found a foreground-classification gap: Firebase 13.1.0 routes messages to any visible client before invoking the background callback, even if that client is behind another application window. The application worker's own callback also previously checked visibility alone. This finding is a possible suppression path, not proof of the cause on the user's browser.

The deployed worker now owns the native push event before Firebase's dispatch and uses both `WindowClient.focused` and `visibilityState` to decide between an in-app update and a desktop alert. Firebase continues to manage registration. Environment, account binding, safe routes, data-only payloads and persistent deduplication remain enforced. All same-origin windows receive reconciliation messages; foreground toasts also require document focus.

The settings page now has **Test desktop notification**, a user-triggered local `showNotification` check that bypasses FCM and the queue and creates no database record. It requires already-granted permission and reports display failures without re-prompting. A local-test click opens only the inbox. User feedback from this check is pending; a resolved display API call alone does not prove OS banner display.

Validation: **20 JavaScript tests passed**, **32 Laravel push-feature tests / 222 assertions passed**, Vite build and diff whitespace checks passed. A targeted 17-file update was deployed after verifying private backups in `storage/app/backups/pre-web-push-display-fix-20261028/`. All 51 release checksums match; HTTP checks verified the current bundle and versioned worker (`60e32d68578f`), with no-store headers. The hPanel heartbeat remains successful; push queues and failed jobs are empty. Existing environment settings, entry point and storage links remain unchanged. No migration, credential or production change was needed.
