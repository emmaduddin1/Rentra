# Mobile push staging deployment — 3 November 2026

Status: **deployed to https://staging.tenantism.com**. The user confirmed **Push notifications enabled** in the normal Android app. Staging independently verified one active Android subscription linked to a valid Sanctum personal API session. Production was not changed.

## Cause and change

The installed app's connection-related error was caused by HTTP 404 for `/api/v1/notifications/push-config`: the native release had only existed locally. Real Android transport tests had used an isolated local API. Flutter now distinguishes missing endpoints from retryable errors without claiming the device lacks connectivity.

Twelve reviewed files were deployed: native push controller; API auth/notification controllers and notification resource; subscription model; notification observer; FCM client, payload, configuration and delivery service; additive session-binding migration; and API routes. Existing push-file baselines matched local HEAD. The server's routes were older than the local full parity routes, so only the four mobile notification routes were merged into the server route file. No unrelated mobile API routes/features, dependencies, browser assets or public entry point were replaced.

Migration `2026_11_03_000001_bind_mobile_push_to_api_sessions` is applied in **batch 7**. It adds a nullable personal-access-token foreign key and preserves existing subscription/history rows. No other migration, database reset or history deletion was run.

## Backups and preservation

Private verified backup:

`/home/u577461158/domains/staging.tenantism.com/rentra-staging/storage/app/backups/pre-mobile-push-20261103-120014`

It contains the compressed staging database dump (27,298 bytes, decompressed length verified), original targeted files, original environment and cached config/routes, Hostinger entry point, before-state metadata, migration/cache logs and release manifest. The temporary MySQL client credentials file was removed immediately after the dump. Access is private under the existing staging account.

Both the environment file and Hostinger public entry point retained their SHA-256 checksums. Public/application storage links still point to staging's existing `storage/app/public`. Credentials, uploads, vendor dependencies, browser assets and web PHP settings were preserved. Only this deployment CLI invocation enabled `proc_open`, following the existing staging wrapper's restricted-function list. The existing push-worker lock prevented overlap during the backup/migration/source update.

Source writes used atomic per-file replacement after migrating the additive column. Staging config/routes were rebuilt. The existing short-lived `web-push` worker/scheduler runs fresh code through the same hPanel wrapper; no second mobile queue or scheduler was added.

Reviewed archive/manifest and sanitized verification are retained at:

`storage/app/private/mobile-push-release-20261103/`

Local preparation files remain at `/private/tmp/tenantism-mobile-push-release-20261103/`. SSH access uses the existing key with macOS `UseKeychain=yes`; passwords and private keys must not be recorded in chat/docs.

## Verification

- All **12 installed file hashes match** the reviewed payload.
- Additive migration batch 7 and session column verified.
- Guest native config: **HTTP 401**, replacing the original 404. Config/register/delete/show routes are present under Sanctum authentication; registration/unregistration retain API throttles.
- Staging `/up` and `/login`: **HTTP 200**.
- Staging native sender available and browser push still enabled with the expected staging Firebase project; public config contains no private-key material.
- Automatic hPanel heartbeat: **complete**, observed 18 seconds old during final verification.
- Push queue: **0**; failed jobs: **0**.
- Existing notifications: **35 before and after**. User/property/tenancy/monthly-record/maintenance counts were unchanged by deployment. Device registration adds only its subscription metadata. No test notification was sent to staging accounts in this follow-up.
- User confirmed enabled state in the updated normal app. Server verified **one active Android subscription bound to a valid API session**.
- Flutter: **55 passed, one existing unrelated opt-in skip**; analyzer clean; normal debug APK built and installed. New regressions cover missing routes and server errors while preserving the rental session.
- Laravel full suite previously passed **401 tests / 2,010 assertions** for the same backend implementation. Frontend Vite build passed before deployment; its assets were not part of this mobile-only release.

Physical foreground/background delivery and cold-launch transport were previously verified with real staging Firebase on TECNO CM6 / Android 16 against an isolated local API. This follow-up verifies actual deployed registration; it does not claim that every rental event/deep-link workflow has been exercised end to end on deployed staging. iOS/APNs and two physical devices still require their documented acceptance checks.

## Rollback

Restore only backed-up application files and compatible config/routes if required. Preserve the additive nullable column, existing subscriptions and notification history. Do not restore the database over later business activity. Keep the existing environment, credentials, uploads and Hostinger entry point. Production remains outside this release.
