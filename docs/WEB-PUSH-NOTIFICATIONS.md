# Tenantism web push notifications

Implemented locally on 27 October 2026 and [deployed to staging across 27–28 October](STAGING-WEB-PUSH-DEPLOYMENT.md). Firebase project `tenantism-staging` is active for staging, with `tenantism-88cfc` reserved for production. Staging credentials/VAPID, the additive migration, assets and automatic hPanel scheduler/worker are configured and server-verified. Production and Flutter are unchanged. Actual browser permission, registration and delivery verification remain pending.

## Root cause and existing architecture

Tenantism uses its own `notifications` Eloquent model/table, rather than Laravel's polymorphic database channel. `Notifier::send()` is the sole workflow writer and has a unique idempotency key. Web notification reads are guarded by `NotificationPolicy`; web authentication uses sessions and CSRF middleware. Existing API notification behavior is retained.

Before this change, the Blade notification list was rendered only on a page request. The authenticated header polled **only the count** every 30 seconds and on focus. No device registrations, Firebase integration, push jobs, service worker, or foreground notification receiver existed. Conversations independently polled messages every three seconds and did not create database notifications.

Existing notification writers inspected:

- `app/Actions/Tenancies/AssignRenter.php`, `RespondToInvitation.php`, `RequestTenancyTermination.php`, `RespondToTermination.php`, `EndTenancy.php`.
- `app/Actions/Applications/SubmitApplication.php`, `RespondToApplication.php`.
- `app/Actions/MonthlyRecords/SubmitMonthlyRecord.php`, `ApproveMonthlyRecord.php`, `RejectMonthlyRecord.php`, `ReopenMonthlyRecord.php`.
- `app/Actions/Maintenance/CreateMaintenanceRequest.php`, `AddMaintenanceComment.php`, `ChangeMaintenanceStatus.php`.
- Invitation-expiry processing in `routes/console.php`.

## Implemented event flow

1. An existing business transaction creates a database notification through `Notifier`.
2. `NotificationObserver` records the current server environment and a durable `push_pending` flag. Historical notifications default to false.
3. After commit, the observer queues `SendWebPush` on the independent asynchronous `web-push` queue. Dispatch failures are caught and logged without rolling back the business action; `push:dispatch-pending` recovers undispatched rows.
4. The fan-out job checks configuration, account status, read state, environment and category preferences, then queues one `DeliverWebPush` job per active device. A large number of browsers cannot exhaust one delivery job's timeout.
5. Each device job checks eligibility again, checks token ownership under a row lock, and uses FCM HTTP v1 with a short-lived OAuth access token. PHP OpenSSL signs the service-account JWT; credentials never go through Vite or the database. Short-lived OAuth tokens are reused only in worker memory, independent of Laravel's configured cache store.
6. A unique notification/device/token delivery ledger suppresses successful repeats. Temporary failures retry up to three times with 60/180-second backoff; success on another device is retained. Unregistered tokens and explicitly invalid registration tokens are revoked. Payload/configuration errors do not blindly revoke valid tokens.
7. The browser manager fetches authoritative count/list state, displays an in-app toast and broadcasts invalidation to other tabs. An open conversation refreshes immediately on the push event. Thirty-second polling and focus reconciliation remain available without push permission.
8. The root worker owns the native push event before Firebase's visibility-only dispatch, displays data-only background messages with Tenantism assets and a notification-ID tag, and posts updates to same-origin tabs. It validates environment, route and current installation/account binding, and remembers handled IDs in IndexedDB to suppress replays after dismissal. Only a focused, visible Tenantism window suppresses the system alert; a visible window behind another app still receives a desktop alert. Payloads that could trigger Firebase's automatic notification display are rejected.
9. Clicking first validates the current installation/account binding. The worker fetches the owned notification's protected `/notifications/{id}/open` JSON context (private/no-store), then submits the existing CSRF-protected read POST using the authenticated session. Only after that does it navigate/focus the validated server-side destination and notify other tabs to refresh. It supports direct and Firebase-wrapped alert data, falls back to a new window when an existing window cannot navigate, and uses the protected HTML opening form for expired login/network/read failures. No push payload contains a CSRF token. Cross-site navigation cannot automatically mark the record read. The destination never comes from stored `action_url`.
10. Mark-one, mark-all, reads from another tab, and API reads reconcile against database state. Push itself never marks anything read.

Transport acceptance is not proof of browser delivery. A crash after FCM accepts a message but before the delivery ledger commits can cause a retry; notification IDs, browser storage and tags suppress that replay on the installation. This is not a claim of transport-level exactly-once delivery.

## Files and migration

| Area | Files |
| --- | --- |
| Configuration | `config/push.php`, `.env.example`, `.gitignore` |
| Additive schema | `database/migrations/2026_10_27_000002_add_web_push_notifications.php` |
| Models | `app/Models/PushSubscription.php`, `PushDelivery.php`; push-state casts in `Notification.php` |
| Delivery | `app/Services/Push/{PushConfiguration,NotificationPayload,FcmClient,PushService}.php`, `app/Observers/NotificationObserver.php`, `app/Jobs/{SendWebPush,DeliverWebPush}.php` |
| Event hooks | `app/Providers/AppServiceProvider.php`, `app/Actions/MonthlyRecords/RecordMonthlyPayment.php`, `app/Actions/Renters/CreateRenterAccount.php` |
| HTTP | `app/Http/Controllers/PushSubscriptionController.php`, `NotificationController.php`, `Auth/AuthenticatedSessionController.php`, `routes/web.php` |
| Commands | `app/Console/Commands/{CleanupWebPush,DispatchPendingWebPush,SendPaymentReminders}.php`, `routes/console.php` |
| Frontend | `public/.htaccess` (worker-only cache policy), `resources/js/notifications.js`, `resources/js/notifications/payload.js`, `resources/js/app.js`, `public/firebase-messaging-sw.js`, `package.json`, `package-lock.json` |
| Views | `resources/views/components/{web-notifications,notification-settings}.blade.php`, `components/layouts/app.blade.php`, `components/nav/authenticated-app-bar.blade.php`, `notifications/{index,open}.blade.php` |
| Tests | `tests/Feature/WebPushTest.php`, `tests/Unit/JavaScript/notifications.test.js`, `tests/Integration/FcmSimulationTest.php` |
| HTTP simulation | `postman/mocks/firebase-web-push/`, `postman/simulations/tenantism-web-push.sim.yaml` and its context document |

The migration creates:

- `push_subscriptions`: user, encrypted token, token SHA-256 digest, platform, installation UUID, browser/device labels, environment, enablement, usage/success/failure timestamps, failure count and revocation time. Unique environment/token and environment/installation constraints prevent duplicate active registrations. Tokens and installation identifiers are hidden from model serialization.
- `web_notification_preferences`: one row per user, with tenancy/messages/payments/maintenance/general booleans. Missing preferences default to enabled; they affect web push, not the database inbox.
- `push_deliveries`: unique notification/subscription/token identity, attempt count, outcome, sanitized error code and acceptance time.
- Additive notification fields: `push_pending`, `push_environment`, `push_queued_at`.

Token rotation replaces the old installation token. An installation changing accounts is reassigned with a new opaque binding. Registration, revocation and logout serialize requests in the same session to prevent a late registration reviving a logged-out browser. Logout revokes only that browser's registration server-side; JavaScript also clears the worker binding, stops receivers across tabs and removes the FCM token. An in-flight alert for a previous login fails the binding check.

## Web endpoints

All modifying endpoints below use the `web` middleware, session authentication and CSRF protection. Registration/revocation are limited to 20 requests/minute; preferences to 30/minute. No endpoint lists or returns device tokens.

| Method | Path | Contract |
| --- | --- | --- |
| POST | `/push-subscriptions` | `token`, UUID `installation_id`, optional `browser`, optional `device_name`, boolean `notifications_enabled`; returns enablement and opaque worker binding only. Server sets platform and environment. |
| DELETE | `/push-subscriptions/current` | UUID `installation_id`; revokes only a registration owned by the current user in this environment. |
| PATCH | `/notification-preferences` | Optional boolean `tenancy`, `messages`, `payments`, `maintenance`, `general` fields. |
| GET | `/notifications/feed` | Current user's paginated inbox plus authoritative unread count; accepts `page` and `filter=unread`. Private/no-store response. |
| GET | `/notifications/unread-count` | Authoritative unread count; existing `/notifications/count` remains compatible. |
| POST | `/notifications/{notification}/read` | Authorizes owner, marks read idempotently; JSON callers receive safe route/count. HTML callers redirect. |
| POST | `/notifications/read-all` | Marks current user's unread rows read; returns JSON or redirects for ordinary forms. |
| GET | `/notifications/{notification}/open` | Authorizes the database notification and renders its protected opening form. |
| GET | `/push-config.js` | Public Firebase web configuration, public VAPID key, availability and environment only; no server credentials. No-store. |

The bell opens a live recent-notification panel with mark-all and a link to the full inbox/settings. The full inbox offers browser enable/disable, category preferences and individual read controls. Permission is requested only by the Enable button after the supplied explanation. Denial produces browser-settings guidance without repeated prompts. Unsupported/configuration/temporary-failure states leave the regular inbox available.

The **Test desktop notification** button calls the active worker registration's native `showNotification` directly after checking that permission is already granted. It does not send through Firebase, create a database notification, or change read state. This separates local browser/OS display checks from FCM transport. Clicking the local test opens only `/notifications`. A successful browser API call is reported as “Test sent”, not proof that the OS actually displayed a banner; Chrome's system notification settings and Do Not Disturb may still suppress it.

## Connected notifications and deep links

Every new database notification is observed, so the existing events are covered without duplicating business actions:

| Types | Category / generated destination |
| --- | --- |
| `tenancy.invited`, `accepted`, `rejected`, `withdrawn`, `invitation_expired`, `termination_requested`, all existing `termination_*` responses, `ended`, `cancelled` | Tenancy / recipient role's tenancy detail |
| `application.submitted`, `application.status_changed` | Tenancy / owner application detail or renter applications list |
| `message.received` | Messages / conversation detail |
| `monthly_record.submitted`, `approved`, `rejected`, `reopened`; `payment.recorded`, `rent.reminder` | Payments / recipient role's monthly record detail |
| `maintenance.created`, `maintenance.commented`, `maintenance.status_changed` | Maintenance / recipient role's maintenance detail |
| `account.created` and other account/general notifications using `Notifier` | General / notifications inbox |

Chat notifications are newly created from the shared message model, covering both existing web and API message creation. The hook checks messaging eligibility and notifies only the other participant. Message text is always “You received a new message.” Payment receipts notify the renter; new renter accounts receive a generic welcome notification. Existing tenancy and maintenance actions already select the other party.

`notifications:payment-reminders` adds a once-per-week reminder for approved records with outstanding owner receivables whose month has ended and whose tenancy remains active/termination-requested/in-notice. This follows the existing financial overdue boundary and excludes direct-provider utility amounts from owner receivables. It is scheduled Mondays at 09:00 in the application's configured scheduler timezone. Repeated runs in a week do not duplicate a record's reminder.

`NotificationPayload` derives paths from controlled type families and numeric entity IDs. Unknown types/malformed IDs fall back to `/notifications`. Existing destination policies still authorize protected content. Push includes only notification ID, type, generic title/body, relative route, entity category/ID, creation time, environment and an opaque installation binding. No names, phone numbers, CNIC, amounts, private message text, proofs, attachments or server credentials are copied into FCM payloads.

## Required environment variables

Populate the server's own environment file. None are Vite variables. `FIREBASE_CREDENTIALS` is an absolute path to a private service-account JSON file, not inline JSON.

```dotenv
PUSH_NOTIFICATIONS_ENABLED=false
PUSH_ENVIRONMENT=staging
PUSH_QUEUE_CONNECTION=database
FIREBASE_PROJECT_ID=
FIREBASE_CREDENTIALS=
FIREBASE_WEB_API_KEY=
FIREBASE_WEB_AUTH_DOMAIN=
FIREBASE_WEB_PROJECT_ID=
FIREBASE_WEB_STORAGE_BUCKET=
FIREBASE_WEB_MESSAGING_SENDER_ID=
FIREBASE_WEB_APP_ID=
FIREBASE_WEB_VAPID_PUBLIC_KEY=
```

Only `FIREBASE_WEB_*` and the public VAPID key are exposed to browsers. The service-account project, server project and web project must match. Staging/production application environments must match `PUSH_ENVIRONMENT`; otherwise push fails closed. A synchronous queue is rejected. Public configuration can report unavailable while the rest of the application continues working.

Use **separate Firebase projects and service accounts for staging and production**. Keep each private JSON outside the checkout and public web root with restricted file permissions. Never copy token tables or staging credentials to production. Staging titles carry `[STAGING]`; jobs, subscriptions, notification outbox records and cleanup are filtered by server environment.

Firebase JS is pinned to 13.1.0 in Vite and the worker CDN imports. Update both together. Only App/Messaging are imported into browser bundles. A gRPC override patches the transitive package pulled in by Firebase's unused Firestore dependency. The repository's pre-existing dev-tool audit findings for Vite, concurrently/shell-quote and source-map-js were not broadly upgraded as part of this feature; the dependency audit still reports four existing advisories (two high, two critical).

## Firebase Console steps requiring manual access

The current staging web app, public VAPID key and dedicated service account are already configured, with server authentication verified; see [FIREBASE-CONNECTION.md](FIREBASE-CONNECTION.md). The steps below remain the setup reference for another environment or credential replacement.

1. Create/select the dedicated **Tenantism staging** Firebase project. Use a separate production project later.
2. Register a Web app under Project settings → General. Copy its public configuration into the web variables above.
3. Enable the Firebase Cloud Messaging HTTP v1 API for that project. Under Project settings → Cloud Messaging → Web Push certificates, generate/import a Web Push key pair and copy the **public** VAPID key.
4. Under Project settings → Service accounts, generate the appropriate private service-account JSON. Place it securely on the intended server and configure its absolute path. Do not paste it into chat, the database, Vite configuration or Git. Give the account permission to send FCM messages for this project.
5. Confirm the server can reach `oauth2.googleapis.com` and `fcm.googleapis.com`; browsers must reach Firebase installation/registration endpoints and the pinned `www.gstatic.com` worker scripts over HTTPS.
6. After an explicitly authorized deployment, database backup and migration, configure the worker/cron below, enable push and rebuild Laravel's configuration cache. The web public config is supplied at runtime; VAPID/server changes do not require exposing a secret at build time.
7. Use an actual signed-in staging user and the Enable button to register a real browser. Firebase Console notification-composer messages are not the application test path: this worker intentionally rejects auto-display notification payloads. Trigger the real business workflows instead.

## Queue, scheduler and cleanup

The application default queue configuration is unchanged. Push uses `PUSH_QUEUE_CONNECTION` (default `database`) and the dedicated `web-push` queue; this avoids inline delivery even if the application's default connection is synchronous.

With a supervised worker available, run the usual worker under the application's server account:

```bash
php artisan queue:work database --queue=web-push --tries=3 --timeout=60
```

For Hostinger shared hosting, a cron-compatible alternative is:

```bash
cd /home/u577461158/domains/staging.tenantism.com/rentra-staging
php artisan queue:work database --queue=web-push --stop-when-empty --tries=3 --timeout=60 --max-time=50
```

Run that every minute with the host's PHP binary. Protect concurrent invocations with `flock` if available, using an environment-specific lock file in the application's storage directory. Ensure the queue connection's `retry_after` exceeds the worker timeout (the unchanged default is 90 seconds). Delayed retries are picked up by later cron invocations. Cron adds up to roughly one minute before delivery; use a permanent worker when immediate dispatch is required.

Keep the existing once-per-minute `php artisan schedule:run` cron. Added scheduled commands are:

- `push:dispatch-pending`: every minute; recovers queue-dispatch failures after a 20-minute lease, expires outbox rows older than a day, and never replays pre-feature historical rows.
- `push:cleanup`: daily; revokes registrations unused for 90 days and removes tokens revoked over 30 days ago, only in the current environment.
- `notifications:payment-reminders`: weekly as described above.

The current staging hPanel cron, CLI-only process-spawning accommodation and automatic heartbeat verification are recorded in [STAGING-WEB-PUSH-DEPLOYMENT.md](STAGING-WEB-PUSH-DEPLOYMENT.md). The generic commands above remain a reference for other hosts. Existing Hostinger entry-point, storage-link, `.env`, upload, dependency and deployment constraints in `PROJECT-HANDOFF-2026-10-27.md` still apply. The root `public/firebase-messaging-sw.js` must be copied to the Hostinger public web root when deployment is authorized; never overwrite its custom `index.php`. Serve the worker as JavaScript without a login redirect or long-lived CDN cache. `/push-config.js` must resolve through Laravel in that same origin.

Diagnostics use database notification/subscription IDs, environment, status and sanitized error codes. No full tokens, private keys, assertions or message bodies appear in normal push logs. `last_success_at` means FCM accepted the send, not that a person saw it.

## Local verification

Verification completed locally:

- Full Laravel suite: **389 passed, 1,887 assertions**, including private click-context authorization and memory-only OAuth caching.
- JavaScript suite: **20 passed**, covering native push routing, route/binding validation, background deduplication, focused and unfocused visible windows, delivery without open tabs, logout, CSRF-protected click/read, wrapped alerts, local-test clicks, failed-navigation/new-window fallback, expired login, unsafe redirects and compatibility fallback. The focused Laravel push-feature suite also passed: **32 tests, 222 assertions**.
- Real local HTTP FCM simulation: **1 passed, 16 assertions**; OAuth plus successful, unregistered, quota and transient responses were observed in the simulation log.
- Production Vite build: passed; the existing optional-fontaine fallback notice remains non-blocking.
- Routes: **162** application routes inspected, with authenticated web middleware and throttles on the new modifying endpoints.
- Scheduler listing: passed with an isolated local cache/database.
- Migration application, rollback, reapplication and status: passed on disposable SQLite.
- Pint and `git diff --check`: passed.

Run the ordinary checks:

```bash
php artisan test
npm run test:js
npm run build
php artisan route:list --except-vendor
php artisan migrate:status
```

The configured local MySQL connection was unavailable during implementation. Migration application, status, rollback of this migration and reapplication were instead checked against a disposable SQLite database, never staging/production. Backend tests use isolated SQLite databases and real commits for after-commit assertions. The opt-in real HTTP simulation is separate from the normal test suite:

```bash
postman simulation run postman/simulations/tenantism-web-push.sim.yaml --port 4900 --output ndjson
TENANTISM_FCM_SIMULATION_BASE=http://127.0.0.1:4900/firebase php artisan test tests/Integration/FcmSimulationTest.php --filter=test_real_fcm_http
```

This sends generated test credentials/tokens over real local HTTP and covers OAuth, success, unregistered tokens, 503 and 429 with bounded retries. The mock derives from Firebase's documented HTTP v1/OAuth/error contracts. No Postman account was connected, so no owning-workspace mock was retrieved or published. Simulation logs stay outside Git and contain only disposable test requests. The simulation cannot establish actual Google credential validity, browser push delivery, permissions, OS behavior or staging/production Firebase isolation.

## Manual staging checklist — server deployment complete; browser checks pending

All items below remain **unverified in real browsers**:

- [ ] Chrome, foreground Tenantism: one toast, immediate badge/list update, no duplicate system alert.
- [ ] Chrome, another tab/background: one system alert and synchronized badge/list on return.
- [ ] Chrome, Tenantism tab closed: worker displays alert; click opens the authorized target and marks the database notification read.
- [ ] Browser restart/closed-browser delivery on supported OS/browser configurations; distinguish a normal background-capable browser from force quit/background processing disabled.
- [ ] Multiple Tenantism tabs: one alert/toast where possible, no double counting, mark-one/mark-all reconciles in every tab.
- [ ] Permission granted, dismissed and denied; no permission prompt during page load.
- [ ] Browser settings block/re-enable notifications; inbox remains usable and Enable recovers.
- [ ] Disable on this browser persists across reload; category preferences persist across devices.
- [ ] Logout/login as another user: only current registration is revoked, no old-user alert or token exposure; delayed old-user payloads are ignored.
- [ ] Same user on two browsers: both receive once; one browser's invalid token does not prevent the other delivery.
- [ ] Notification click while logged out: authenticate, authorize and then continue safely. Also check the Continue fallback on browsers without Fetch Metadata.
- [ ] Message received, already-open conversation refresh, correct recipient and safe message text.
- [ ] Tenancy invite/accept/reject/withdraw, termination responses and ending route correctly.
- [ ] Maintenance creation/status/comment and monthly submission/approval/rejection route correctly.
- [ ] Recorded payment and overdue reminder; payment preference suppresses push only.
- [ ] Invalid/deleted FCM token revokes once; temporary service failures retry with backoff; database notification/business action survives.
- [ ] No staging subscription receives a production job, and no production subscription receives a staging job; verify separate Firebase project IDs/service accounts and `[STAGING]` titles.
- [ ] Supported macOS Safari; supported iPhone/iPad Home Screen web app; browser denial, focus, click and restart behavior on real devices.
- [ ] Root worker and its public-config route return 200 JavaScript in the deployed environment, including a fresh install and worker update.

Safari support is conditional. iOS/iPadOS Web Push requires a supported Home Screen web app and permission requested through a user gesture; do not claim universal Safari or closed-browser delivery.

Primary references: [Firebase web setup](https://firebase.google.com/docs/cloud-messaging/web/get-started), [foreground/background delivery](https://firebase.google.com/docs/cloud-messaging/web/receive-messages), [HTTP v1 and OAuth](https://firebase.google.com/docs/cloud-messaging/send/v1-api), [FCM error codes](https://firebase.google.com/docs/cloud-messaging/error-codes), [Firebase supported environments](https://firebase.google.com/docs/web/environments-js-sdk), [Apple Web Push requirements](https://developer.apple.com/documentation/usernotifications/sending-web-push-notifications-in-web-apps-and-browsers).
