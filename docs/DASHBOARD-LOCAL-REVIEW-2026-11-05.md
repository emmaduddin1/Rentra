# Dashboard modernization — local review, 5 November 2026

Review branch: `ui-ux-modification`. The dashboard was implemented and reviewed locally. On 5 November, the user approved committing, pushing and merging the reviewed branch into `main`; dashboard deployment still requires separate approval. No dashboard files were synchronized to Hostinger. The branch also retains the preceding homepage and notification-pagination work described in `UI-PAGINATION-2026-11-05.md`.

## Review locally

The isolated preview is prepared and running at **http://127.0.0.1:8000**.

To start it again from the project directory:

```bash
php bin/local-ui-preview.php serve
```

Sample accounts use the normal login form:

| Account | Email | Password |
|---|---|---|
| Owner | `owner@tenantism.test` | `Tenantism-local-2026` |
| Renter with an active tenancy | `renter@tenantism.test` | `Tenantism-local-2026` |
| Owner with an empty portfolio | `empty-owner@tenantism.test` | `Tenantism-local-2026` |
| Renter without a tenancy | `empty-renter@tenantism.test` | `Tenantism-local-2026` |

These credentials belong only to disposable local sample accounts. The preview displays a persistent sample-data label.

The existing `.env` points to staging and was left untouched. The preview entry point overrides the environment before Laravel boots, uses its own SQLite database, application key, sessions, compiled views and upload disks under ignored `storage/app/private/local-ui-preview/`, and disables email and push delivery. It listens only on loopback. It does not launch a queue worker or use a shared database.

For a fresh local checkout with dependencies already installed, prepare the separate preview once:

```bash
npm run build
php bin/local-ui-preview.php prepare
php bin/local-ui-preview.php serve
```

`prepare` migrates only a newly created, dedicated SQLite file and creates clearly identified sample data. It retains an already prepared preview and refuses to reset an existing unfinished database. No existing database is reset. Normal `php artisan serve` would use the existing staging configuration, so use the preview command for this review.

The production frontend build is ready, so a separate Vite process is not needed to review these changes. Rebuild with `npm run build` after further frontend edits.

## Improvements

- Preserved Tenantism's navy/terracotta palette, logo, Instrument Sans typography and role-based menu organization, as the user confirmed.
- Added a clearer main dashboard heading, role/date context, consistent spacing and prominent existing actions. Each dashboard now has one main H1; the compact top bar uses a separate navigation title.
- Refined shared KPI cards with aligned icon containers, prominent values, soft borders/shadows and restrained hover effects. The smallest mobile widths prioritize readable labels over decorative icons.
- Owner dashboard retains all existing scoped KPIs, conflict warnings, recent applications, property states and actions. Recent properties use their actual thumbnail or a consistent fallback. Long titles wrap, and mobile status badges sit below the text. Review submissions and Reports remain direct shortcuts.
- Renter dashboard presents the existing tenancy details and current monthly record in matching panels. The displayed amount and record status still come directly from the existing model. Header shortcuts reflect whether an active, non-conflicting tenancy exists; renters without one get search/application actions.
- Sidebar collapse remains persistent but also works when browser storage is blocked. Collapsed links keep accessible names. Mobile drawer controls support Enter/Space, expanded-state announcements, focus containment, Escape and focus restoration; background content is inert while the mobile drawer is open.
- Notification and profile popovers support Escape, outside-click dismissal and mutual exclusion. The notification panel fits a 320px viewport. Existing notification hooks and explicit permission actions are retained.
- Section entrance and hover animations use CSS only and honor reduced motion. No frontend dependency was added.

The dashboard controllers, routes, permissions, financial calculations and notification delivery services were not changed for this modernization. Financial reporting remains in Reports. Neither original dashboard contained charts or owner financial totals; no chart data or monetary metrics were invented. The existing Reports SVG chart remains unchanged.

## Files changed for this work

- `resources/views/owner/dashboard.blade.php`
- `resources/views/renter/dashboard.blade.php`
- `resources/views/components/layouts/app.blade.php`
- `resources/views/components/page-header.blade.php`
- `resources/views/components/stat-card.blade.php`
- `resources/views/components/nav/authenticated-app-bar.blade.php`
- `resources/views/components/nav/sidebar.blade.php`
- `resources/views/components/nav/link.blade.php`
- `resources/css/app.css`
- `resources/js/app.js`
- `resources/js/portal-navigation.js`
- `bin/local-ui-preview.php`
- `tests/Feature/DashboardPresentationTest.php`
- `tests/Unit/JavaScript/portal-navigation.test.js`
- This review document.

Other pending files shown by Git belong to the preceding homepage/pagination changes. The unrelated `.DS_Store` modification was retained.

## Verification

- **412 Laravel tests passed / 2,117 assertions.** Existing suites cover role routing, authorization, tenancy lifecycle, property management, monthly records, financial rules and notifications.
- **34 JavaScript tests passed.** Four new navigation regression tests cover blocked storage, collapse persistence, keyboard drawer behavior, focus restoration and desktop/mobile switching. Two new feature tests cover tenancy-dependent renter actions and preservation of the actual monthly record amount/status/destinations.
- Production Vite build, focused Pint and whitespace checks passed. The pre-existing optional-fontaine build notice remains non-blocking.
- **36 real Chrome captures** cover populated/empty owner and renter dashboards at 1440, 1024, 768, 390 and 320px; long property titles; thumbnail fallback; collapsed sidebar; mobile drawer; notification/account menus; property forms; tenancy screens; monthly records; maintenance; Reports; and the notification inbox.
- Reviewed captures have no horizontal document overflow, broken rendered images or unlabelled checked text/select/textarea inputs. The mobile navigation keyboard path, notification Escape/focus behavior, menu exclusivity/outside dismissal and collapse persistence passed.
- Reduced-motion emulation reports no dashboard entrance animation and zero button transition duration.
- A normal authenticated, CSRF-protected empty property submission returned HTTP 422 with validation errors; it did not create a property. Other UI browser checks only navigated/read the isolated sample application.

Screenshots and browser metrics are ignored local artifacts in:

```text
storage/app/private/dashboard-review-20261105/
```

Useful captures: `owner-dashboard-1440.png`, `renter-dashboard-1440.png`, `owner-dashboard-390.png`, `renter-dashboard-390.png`, `owner-recent-mobile.png`, `owner-mobile-notifications.png`, and the `empty-*-dashboard-*.png` files. Browser harness: `/private/tmp/tenantism-dashboard-browser-20261105.mjs`.

## Remaining review

The reference video mentioned in the brief was not attached in this turn, so the work follows the written requirements and existing product. The application has no existing dark-mode toggle/theme; this change preserves its light theme. Safari, physical touch devices and screen-reader acceptance were not separately tested. These browser checks do not establish live push delivery, which is deliberately disabled in the preview. Deployment requires the user's explicit approval.
