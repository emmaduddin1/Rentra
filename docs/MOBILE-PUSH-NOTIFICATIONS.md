# Native mobile push — 3 November 2026

This source change extends the existing web notification outbox and FCM sender. The targeted mobile push release is deployed to staging and was verified on 3 November 2026. Production is unchanged.

`Notifier` still creates one persistent notification with its existing business idempotency key. The after-commit observer, `SendWebPush` / `DeliverWebPush`, `web-push` queue, recovery scheduler, encrypted `PushSubscription`, shared category preferences and `PushDelivery` ledger are reused. Browser delivery remains data-only. Android/iOS receive generic notification-plus-data payloads through HTTP v1; no service-account credential is exposed to Flutter.

## Authenticated contract

All routes use the existing `/api/v1` Sanctum authentication and API rate limiting.

- GET `/notifications/push-config`: `data.enabled`, `data.environment`, `data.firebase_project_id`. Native availability needs enabled persistent-queue sender credentials; browser-only VAPID values are not required for mobile.
- POST `/notifications/devices`: `token` (FCM registration token), `installation_id` (installation-specific UUID), `platform` (`android` or `ios`), `firebase_project_id` matching server config. Requires a real personal API token, not a cookie-only session. Returns `data.enabled`, opaque `data.subscription_key`, `data.environment`; never returns the raw token.
- DELETE `/notifications/devices`: `installation_id`. Idempotently revokes only the authenticated user's native installation in the configured environment.
- GET `/notifications/{notification}`: existing view policy authorizes the recipient; response wraps the existing resource in `data`, including a safe `mobile_target`. Existing read/list APIs remain the inbox source of truth.

Registration reuses the installation's row, encrypts the token, supports rotation and binds it to the current personal API session. Reassignment changes recipient binding. Conflicting rows/web registrations return 409 so the client obtains a fresh token instead of deleting another subscription. Logout revokes only the current API session's native devices. Delivery excludes expired/deleted/mismatched API sessions and rechecks recipient/token under the same row lock as registration.

The additive migration `2026_11_03_000001_bind_mobile_push_to_api_sessions.php` adds the nullable session foreign key. Existing browser subscriptions and notification history remain intact. Never use migrate:fresh/reset for this rollout.

Native payloads include only generic title/body and notification ID/type, recipient ID, environment, opaque binding and allowlisted entity target. Flutter fetches/authorizes the persistent record and protected resource before routing. Existing application, tenancy, monthly-record/payment/reminder, maintenance, messaging and account events are inherited automatically. Existing category preferences and bounded invalid-token/retry handling apply.

## Configuration and rollout

Keep the current staging project `tenantism-staging`, private sender credential path, `PUSH_NOTIFICATIONS_ENABLED=true`, `PUSH_ENVIRONMENT=staging` and persistent `PUSH_QUEUE_CONNECTION=database`. Preserve all web configuration. Apply the additive migration with the normal backed-up staging deployment, refresh config and restart existing workers. Continue consuming `web-push` with the existing scheduler/recovery/hPanel wrapper; no new mobile queue is required. Production is outside this change's deployment scope.

The Android checkout reuses the existing Firebase app `com.staging.tenantism`. The Flutter API selector must be Staging; project/environment preflight prevents staging Firebase registration against production. iOS delivery requires the real bundle ID, Firebase iOS app/plist, APNs credentials and signing capabilities before device verification.

## Verification

- Full Laravel suite: **401 tests passed / 2,010 assertions**; includes existing auth, rental workflow and web push tests plus 12 mobile feature tests.
- Pint and diff whitespace checks passed for changed files.
- Actual PHP HTTP client against the existing local Postman Firebase stand-in: **1 test / 24 assertions**. Its nine logged requests covered OAuth, successful send, UNREGISTERED and three attempts each for 503 and 429. This tests caller behavior under modeled responses, not Google or an OS.
- Separate physical TECNO CM6 / Android 16 check used this real Laravel sender, the existing staging Firebase credentials, a new temporary SQLite database and a fake local test account. Registration, foreground local presentation, one background system alert, tap callback, confirmed process termination followed by one system alert and cold-launch initial-message handling passed. All isolated device registrations/API sessions were revoked; queue and failed jobs were empty. No real staging/production database was used. The normal rental UI's protected detail navigation is covered by Flutter widget/API tests rather than a deployed staging end-to-end run.
- Flutter: 55 tests passed, one existing unrelated opt-in HTTP test skipped, analyzer clean, normal debug APK built. iOS and two physical devices remain unverified.

Reproduce the provider stand-in with:

```sh
postman simulation run postman/simulations/tenantism-web-push.sim.yaml --port 4900 --output ndjson
TENANTISM_FCM_SIMULATION_BASE=http://127.0.0.1:4900/firebase php artisan test tests/Integration/MobileFcmSimulationTest.php --filter=test_real_mobile_fcm_http_against_local_simulation
```

The simulation uses generated test credentials only. Keep request logs outside Git and stop the local simulator afterward. Current findings and earlier browser evidence are retained in the adjacent Postman context files. The mobile repository contains the full implementation report, sanitized physical-device results and opt-in isolated test harness under `docs/` and `tool/`.

## Missing staging endpoint resolved

Staging's native config endpoint initially returned 404. The client now reports unavailable endpoints accurately (two new regression tests), and the targeted 12-file backend release is deployed after remote baseline review and verified private backups. Existing unrelated server API routes were preserved. The additive session-binding migration is batch 7. Guest config is now 401, mobile/web push are available and health/login return 200.

The user confirmed enabling push in the normal installed Android app; staging independently verified one active session-bound Android device. hPanel cron is complete, the push queue and failed jobs are empty, all twelve installed checksums match and the existing 35 notifications were preserved. No new staging test notification was sent. See [the deployment record](STAGING-MOBILE-PUSH-DEPLOYMENT.md). Flutter now has 55 passing tests and one existing opt-in skip, clean analysis and a rebuilt/installed normal APK.
