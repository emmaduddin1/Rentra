# Tenantism

Tenantism is a rental property discovery and management platform: a Laravel web portal
and REST API, with a separate Flutter mobile client. The repository retains its earlier
name, **Rentra**.

## Project documentation

The consolidated record was updated on **5 November 2026**:

- **[Complete project documentation](docs/PROJECT-DOCUMENTATION.md)** — features,
  owner/renter journeys, financial/tenancy rules, architecture, data and mobile work.
- **[Development history](docs/PROJECT-HISTORY.md)** — original phases, fixes,
  Firebase rollout, UI work, merges and staging releases from start to this snapshot.
- **[Current status and run guide](docs/CURRENT-STATUS.md)** — deployment state,
  evidence, outstanding work and safe local commands.

The latest merged web release is `f009cce` ([PR #2](https://github.com/emmaduddin1/Rentra/pull/2)).
It was deployed to [staging](https://staging.tenantism.com) on 5 November. This does not
establish equivalent production or complete mobile-device acceptance.

## What is implemented

- Owner/renter authentication, role-aware portals, policies and profiles.
- Property discovery/search, categories, galleries, videos and publication/archival rules.
- Whole-property and floor-by-floor rentals.
- Applications, favorites and eligible owner/renter conversations.
- Invitation-first tenancies, cancellation requests, owner decisions and retained history.
- Monthly records, private proofs, review/freeze/reopen and immutable owner receipts.
- Maintenance, private attachments, comments, history and reimbursement information.
- Owner reports, inline SVG charts, CSV export and audit history.
- In-app notifications, browser/native Firebase pipeline, preferences and safe routing.
- Guided tours and responsive branded dashboards/navigation.

Laravel remains authoritative for permissions, transitions and decimal financial totals.
See the [financial](docs/FINANCIAL-API-CONTRACT.md) and
[tenancy](docs/TENANCY-LIFECYCLE-API.md) contracts before changing those workflows.

## Safe local preview

The existing workstation `.env` is configured for staging. The preview command uses
separate SQLite data, sessions, key and uploads, with email/push disabled.

With dependencies installed, prepare it once:

```bash
npm run build
php bin/local-ui-preview.php prepare
php bin/local-ui-preview.php serve
```

Open **http://127.0.0.1:8000**. For a prepared preview, only the final command is needed;
if it is running already, open the URL. Sample logins and isolation details are in the
[run guide](docs/CURRENT-STATUS.md#3-safe-local-review).

`prepare` retains completed sample data and does not reset an existing database. Do not
run fresh/reset migrations or generic seed/setup commands against a shared environment.

## Stack and checks

- Laravel 13, PHP 8.3+, MySQL; isolated SQLite tests.
- Blade, Tailwind CSS 4, vanilla JavaScript, Vite 8.2.1.
- Vite Node requirement: `^20.19.0 || >=22.12.0`.
- Sanctum `/api/v1` API; separate Flutter/BLoC client in `../rentel`.

```bash
php artisan test --compact
npm run test:js
npm run build
git diff --check
```

Last verified for the merged implementation: **412 Laravel tests / 2,117 assertions**,
**34 JavaScript tests**, and a successful production frontend build. Rerun relevant
checks after code changes; these are dated results.

Current source has 60 API routes; route definitions and deployment acceptance are
separate. Existing [Postman examples](docs/postman/Rentra-API.postman_collection.json)
and [phase notes](docs/PHASE-3-TO-7.md) may describe an older subset.

Deployment requires explicit authorization. Preserve server configuration, credentials,
uploads, storage links, history and the Hostinger entry point. Production is a separate
release decision from staging.
