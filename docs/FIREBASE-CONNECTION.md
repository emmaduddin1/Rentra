# Tenantism Firebase environment assignments

Configured and checked through Firebase CLI on 27 October 2026.

| Environment | Firebase project | CLI alias | Web app ID |
| --- | --- | --- | --- |
| Staging | `tenantism-staging` | `staging` (active) | `1:634809958910:web:66bbc122c1e062624400af` |
| Production | `tenantism-88cfc` | `production` | `1:847876029638:web:48325f8de280f9d7a0abc7` |

The staging project is named **Tenantism-Staging**. Its existing web app is currently labelled **Tenantism-Sttaging** in Firebase Console; that display-label spelling does not affect the connection. The existing app was reused, and no duplicate web app was created.

The reserved production project's existing web app still has the earlier **Tenantism Staging Web** Console label. It is a display label only and can be renamed when preparing production configuration.

## Local configuration

- `firebase.json` marks the CLI workspace root; no deployment targets are configured.
- `.firebaserc` holds both project aliases. The active CLI target is `staging`.
- The ignored local `.env` uses staging's public SDK configuration, project ID, web app ID and messaging sender ID (`634809958910`). `PUSH_ENVIRONMENT=staging` and `PUSH_NOTIFICATIONS_ENABLED=true`.
- The staging public VAPID key supplied from Firebase Console is configured locally. Its uncompressed P-256 encoding was validated (65 bytes / 87 base64url characters).
- A dedicated staging service account is configured through `FIREBASE_CREDENTIALS`. Its private key is outside the repository at `~/.config/tenantism/firebase/tenantism-staging-service-account.json`, with file permission `0600` and containing-directory permission `0700`.
- The previous production Firebase settings remain in the ignored, private local reference `storage/app/firebase/production-reference.env`.
- Laravel's configuration cache was cleared and its loaded project/app/environment settings were verified.

The Laravel application and push configuration have now been deployed to the Hostinger staging website; see [STAGING-WEB-PUSH-DEPLOYMENT.md](STAGING-WEB-PUSH-DEPLOYMENT.md). No Firebase Hosting/Functions or production deployment was performed. No Flutter app was created or modified, and the reserved production project's remote configuration was not changed.

Inspect staging explicitly:

```bash
firebase use staging
firebase apps:list WEB --project staging
firebase apps:sdkconfig WEB 1:634809958910:web:66bbc122c1e062624400af --project staging
```

Read-only inspection of production uses an explicit target:

```bash
firebase apps:list WEB --project production
firebase apps:sdkconfig WEB 1:847876029638:web:48325f8de280f9d7a0abc7 --project production
```

Keep credentials, VAPID configuration and browser registrations separate between projects. Do not select production as the active default for staging development.

## Staging server credentials and verification

- Account: `tenantism-web-push@tenantism-staging.iam.gserviceaccount.com`.
- Project role: `roles/firebasecloudmessaging.admin`, granted only in `tenantism-staging`. No Owner, Editor or general Firebase Admin role was granted to this sender.
- FCM and FCM Registration APIs were already enabled. The IAM API was enabled to create this dedicated account and its key.
- Provisioning reused the existing signed-in Firebase CLI account for Google API authentication; Laravel uses the separate service-account key, never the user's CLI login tokens.
- Laravel's real `FcmClient` successfully exchanged the service-account assertion for an OAuth access token. FCM's `validate_only` send request then returned **HTTP 200**. One initial permission-propagation response resolved on retry. No notification was delivered by this check.
- Laravel reports its staging push configuration available; its public configuration excludes the service-account path and key material.
- PHPUnit explicitly disables push, uses the `testing` push environment and clears the real credential path, so tests cannot inherit the enabled local staging credentials.

The validation-only check establishes server authentication and FCM send permission. It does not establish a real browser registration, VAPID association or browser/OS delivery; those require the browser checklist.

## Staging activation completed

- The staging credential was securely transferred to `/home/u577461158/.config/tenantism/firebase/tenantism-staging-service-account.json` with mode `0600`, outside the deployed checkout and public root.
- Staging's database was backed up, the additive migration applied in batch 6, and the application/worker/assets deployed. Existing non-push server settings, storage links and Hostinger entry point were verified unchanged.
- Server OAuth and FCM validation-only permission passed with HTTP 200. The `web-push` queue processed its no-op smoke test successfully.
- A once-per-minute hPanel job now runs the private staging scheduler/worker wrapper; multiple automatic heartbeat entries confirmed successful scheduler and worker exits.
- The user enabled notifications in Chrome; its active registration was verified on staging. A real queued configuration test was accepted by FCM on its first attempt. The user subsequently clarified that it appeared in Tenantism's website bell, so desktop/system display is **not yet verified**. The earlier test was manually marked read; automatic read on a desktop-alert click remains pending a fresh background-tab test. The rest of the [browser checklist](WEB-PUSH-NOTIFICATIONS.md) remains pending.

Production remains separate and unchanged. Deployment details, backups and cron settings are in [STAGING-WEB-PUSH-DEPLOYMENT.md](STAGING-WEB-PUSH-DEPLOYMENT.md).

No Firebase login tokens or private service-account material belong in this document, `.firebaserc`, `firebase.json`, Vite variables or Git.
