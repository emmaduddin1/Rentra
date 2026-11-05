# Homepage and notification pagination — 5 November 2026

Deployed to https://staging.tenantism.com at the user's request. Production was not deployed.

## Changes

- The homepage search card contains both the search form and compact property-category links. The outer background card ends at the bottom of the form's More filters area, with the category portion of the foreground card extending below it. Shared grid rows keep that edge aligned when the form expands and across viewport sizes.
- Notification pagination is left aligned and offers Previous, numbered pages, ellipses for larger inboxes and Next. The current page and unavailable boundary actions are explicit. Both the initial Blade rendering and live inbox refresh use the same presentation. Unread filtering survives navigation; a shrunken unread inbox retains a route back to page one.
- The initial notification inbox and its existing live feed now share a 20-item page size, avoiding page-count changes when JavaScript refreshes the initial HTML. Feed defaults and authorization remain unchanged.

## Validation

- Laravel suite: **410 tests passed, 2,101 assertions** for the pagination changes. The subsequent homepage-only layout refinement was verified in real Chrome.
- JavaScript suite: **30 passed**; production Vite build, focused Pint and whitespace checks passed. The existing optional-fontaine build notice remains non-blocking.
- Nine isolated Chrome screenshots cover the homepage at 1440, 390 and 320 pixels, plus first/middle/last notification pages, mobile pagination and server rendering without JavaScript. Keyboard navigation, focus visibility, unread query preservation, live-refresh persistence and 44-pixel pagination targets passed.
- Three additional screenshots were captured from the public staging homepage at 1440, 390 and 320 pixels. In each, the outer background's bottom equals the form's bottom, the foreground card extends below it, and document width equals viewport width. The category strip intentionally scrolls within its own container on mobile.
- Staging homepage, listings, login and health endpoint returned HTTP 200. Served CSS and JavaScript hashes match the local production build.

Screenshots and browser metrics are ignored local artifacts in `storage/app/private/ui-review-20261105-final/`. Files prefixed `staging-` show the real public site; other screenshots are clearly labelled isolated sample data. No preview fixtures or accounts were uploaded to staging.

## Deployment and rollback

The targeted release contains **7 source files and 14 build files**. Ten source/dependency baselines matched before installation. All 21 application files and all 14 public build files match their expected hashes afterward. Blade views were rebuilt.

Verified private rollback backup:

```text
/home/u577461158/domains/staging.tenantism.com/rentra-staging/storage/app/backups/pre-ui-pagination-20261105/
```

The backup includes previous targeted files, referenced build assets, new-path metadata and preserved-state fingerprints. Restore the backed-up application/web files and rebuild Blade views to roll back; retain unrelated assets and user data.

The environment, application and Hostinger entry points, rewrite files, push worker and both storage links retain their preceding fingerprints. No migration, configuration change, business-record write or notification send was performed. Uploads and credentials were excluded from the release. The scheduler reported complete, with zero queued or failed push jobs.

Local release manifests, archive and HTTP checks are in `/private/tmp/tenantism-ui-pagination-release-20261105/`.
