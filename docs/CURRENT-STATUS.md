# Tenantism — current status and run guide

**As of 5 November 2026 · Asia/Karachi**

See [complete project documentation](PROJECT-DOCUMENTATION.md) for features and architecture, and [development history](PROJECT-HISTORY.md) for the chronological record. These are last recorded checks, not continuous live monitoring.

## 1. Release and environment status

| Area | Recorded state |
| --- | --- |
| Laravel | `main`, `f009cce`; [PR #2](https://github.com/emmaduddin1/Rentra/pull/2), 5 November |
| Latest web UI | Dashboards/homepage/pagination deployed to staging after explicit approval on 5 November |
| Staging | https://staging.tenantism.com |
| Production | https://tenantism.com; no production deployment in the recent recorded releases; latest-release parity unverified |
| Staging Firebase | `tenantism-staging`, configured browser/native backend |
| Production Firebase | `tenantism-88cfc`, separate assigned project; equivalent rollout not established |
| Flutter source inspected | Separate `rentel` checkout, local commit `c32f085` |
| Android | Staging registration and isolated physical transport evidence; further deployed acceptance deferred |
| iOS | Push configuration/signing/device validation deferred |

## 2. Verification evidence

| Check | Result and boundary |
| --- | --- |
| Laravel suite | **412 passed / 2,117 assertions**, 5 November, merged source |
| JavaScript suite | **34 passed**, including navigation, worker, state and pagination coverage |
| Production Vite build | Passed; optional-fontaine notice remains non-blocking |
| Focused Pint / whitespace | Passed |
| Dashboard Chrome review | **36 isolated captures**, populated/empty roles and related screens, 320/390/768/1024/1440px |
| Navigation | Keyboard drawer/Escape/focus restoration, collapse persistence, menu dismissal/exclusivity and reduced motion passed |
| Form validation | CSRF-authenticated empty property submission returned 422 in the isolated preview; no property created |
| Latest staging installation | 31 application/source/build checks and 14 public-build checks; zero mismatches |
| Public HTTP checks | Home/listings/login/health 200; protected dashboard/inbox routes retained login redirects; exact current assets served |
| Live public Chrome checks | Homepage 1440/390/320px, no document overflow, correct background/search-card alignment |
| Scheduler/queue at release | hPanel complete; zero queued/failed push jobs |
| Real Chrome push | Basic receipt, desktop display, click and automatic read verified on 4 November |
| Flutter last reported result | **64 passed, 1 existing opt-in skip**, clean analyzer/debug build/install, 4 November; not rerun for this documentation task |
| Physical Android | Isolated local API + staging Firebase transport passed; normal staging registration confirmed; full deployed workflow acceptance remains |

Authenticated dashboard captures used isolated local sample accounts. Post-deployment staging checks verified public rendering, protected redirects and installed file/asset hashes; they did not replay every workflow in a real user's account.

## 3. Safe local review

The workstation `.env` is configured for staging. Use the isolated preview instead of an ordinary server against that configuration:

```bash
cd "/Users/waleed/rentra project/Rentra"
php bin/local-ui-preview.php serve
```

Open **http://127.0.0.1:8000**. If this preview is already running, open the URL rather than starting a second process on the same port.

With dependencies installed, prepare an uninitialized preview once:

```bash
npm run build
php bin/local-ui-preview.php prepare
php bin/local-ui-preview.php serve
```

`prepare` creates/migrates only a fresh dedicated SQLite file. It retains a completed preview and refuses to reset an unfinished existing database. Key, sessions, media, views and database live under ignored `storage/app/private/local-ui-preview/`. Email and push are disabled; `.env` is untouched. No shared database reset/seed is required.

| Sample account | Login |
| --- | --- |
| Owner | `owner@tenantism.test` |
| Renter with tenancy | `renter@tenantism.test` |
| Empty owner | `empty-owner@tenantism.test` |
| Renter without tenancy | `empty-renter@tenantism.test` |

All use `Tenantism-local-2026`. These are disposable local credentials, not staging/production credentials. A visible label identifies the preview's sample data.

### Requirements and checks

- PHP `8.3+`, Laravel-required extensions and the financial code's decimal-arithmetic support.
- Installed Composer/frontend dependencies; MySQL for ordinary application use, isolated SQLite for tests/preview.
- Vite 8.2.1 Node requirement: `^20.19.0 || >=22.12.0`.
- The prepared preview serves the production build; a separate Vite process is unnecessary. Run `npm run build` after frontend edits.

```bash
php artisan test --compact
npm run test:js
npm run build
git diff --check
```

PHPUnit disables real push and uses its isolated database. Do not substitute a shared database or run fresh/reset migrations against one. Install dependencies only when necessary.

Flutter has a separate API selector/build configuration. Its API can default to production while its current Firebase app is staging. Select and verify **Staging** with matching Firebase configuration before native testing; registration checks enforce that separation.

## 4. Staging operations

Application and Hostinger public root:

```text
/home/u577461158/domains/staging.tenantism.com/rentra-staging
/home/u577461158/domains/staging.tenantism.com/public_html
```

Releases use explicit manifests, source-baseline checks, private backups, checksums and targeted updates. The latest dashboard release required no migration/configuration change and did not upload the local preview command, sample accounts or database.

Preserve server environment/application key, credentials, records, notification history, uploads/private files, dependencies, Hostinger `public_html/index.php`, rewrite/worker policies and both storage links unless a separately reviewed change requires otherwise. Both links must target the staging application's `storage/app/public`; copied macOS symlinks previously broke images.

### Scheduled work

The existing once-per-minute hPanel entry runs `bin/staging-web-push-cron.sh` and its guarded PHP wrapper. It runs scheduled work and drains the database `web-push` queue with execution limits/locking, rather than requiring a permanent shell daemon.

| Task | Source schedule |
| --- | --- |
| Invitation expiry | Hourly |
| Notice-period finalization | Daily 00:15 |
| Pending push outbox dispatch | Every minute |
| Stale registration cleanup | Daily |
| Payment reminders | Monday 09:00 |

Schedules are in `routes/console.php`; wall-clock execution follows the deployed Laravel timezone/configuration. Device delivery also depends on registration, preferences, permissions, connectivity and OS behavior.

### Rollback references

| Release | Private backup under staging application |
| --- | --- |
| Dashboard, 5 Nov | `storage/app/backups/pre-dashboard-ui-20261105/` |
| Homepage/pagination, 5 Nov | `storage/app/backups/pre-ui-pagination-20261105/` |
| Browser click fix, 4 Nov | `storage/app/backups/pre-click-navigation-20261104/` |

Restore relevant application/public files and rebuild views for a UI rollback. Do not overwrite newer database activity, uploads, credentials or entry points. Historical hosting commands are reference material, not deployment/restore authorization.

## 5. Outstanding and deferred work

| Area | Remaining work / boundary |
| --- | --- |
| Browser acceptance | Safari, restart, multiple tabs/browsers, permission denial, logout/account switching and each protected business-event destination still need documented real-device checks |
| Android | Reconnect phone; verify fresh registration, deployed rental events, protected navigation, account changes and multiple devices; deferred by user |
| iOS | Real bundle ID, Firebase iOS app/plist, APNs, signing/capabilities and physical signed-device acceptance; deferred by user |
| Mobile API parity | Audit deployed endpoints/authenticated results against current source. Earlier mobile rollout preserved unrelated older server routes; local routes do not prove complete staging parity |
| Production | Separate explicit approval, configuration/Firebase review, backups and acceptance; staging success is not production approval |
| Listing content | Staging retains test copy and an unrelated promotional image. Owner-approved content replacement remains; preserve existing records/uploads |
| Broader UX | Physical touch, Safari and screen-reader review were not established by desktop Chrome emulation |
| Web dark mode | Not currently implemented; native Flutter theme code is separate |
| Event email / SMS | Password-reset mail exists; general business-event email and SMS recovery are not established by current evidence |
| Image derivatives | Fixed crops/upload limits exist; assess optimized generated derivatives against the older performance requirement before claiming completion |
| CI | No tracked GitHub Actions workflows in this checkout; last remote listing empty. Runnable tests exist, but a current CI gate is not established |
| API examples | Older collections/READMEs have stale counts or omissions; compare with current routes/contracts |
| Store releases | Debug APK build/install is documented; production Android/iOS store release/signing acceptance is not established |

These distinguish implemented workflows from acceptance, configuration and deliberately deferred work.

## 6. Evidence and maintenance

Current code/contracts and later dated evidence take precedence over conflicting historical notes. Preserve phase/handoff files as history; update this status when deployments or acceptance change.

Private local artifacts include:

- `storage/app/private/dashboard-review-20261105/browser-report.json` and screenshots: authenticated sample-data review.
- `storage/app/private/dashboard-review-20261105/staging-deployment.json`: recorded deployment checks and limits.
- `storage/app/private/ui-review-20261105-final/`: preceding homepage/pagination review.
- `/private/tmp/tenantism-dashboard-release-20261105/`: temporary manifests/archive/HTTP checks, not guaranteed durable.

The sibling Flutter documentation contains the native parity, device and comment-crash evidence. No private keys, tokens, database passwords, account passphrases or production user data are required in this documentation set. This documentation task modifies local Markdown only; it does not deploy or certify a new live health check.
