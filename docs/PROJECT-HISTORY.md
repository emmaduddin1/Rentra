# Tenantism — development history

**Compiled: 5 November 2026 · Asia/Karachi**

This history combines Git, dated requirements/handoffs and release evidence. Commit dates identify when work was recorded; migration filenames and feature notes do not prove deployment on those exact dates. See [current features](PROJECT-DOCUMENTATION.md) and [current status](CURRENT-STATUS.md) for the resulting behavior and verification boundaries.

## 1. Original specification and seven phases

The original SRS is dated **31 July 2026**. The first recorded repository commit is **26 August 2026**.

| Original phase | Work delivered |
| --- | --- |
| 1 — Foundation | Laravel/MySQL, schema, auth, roles, policies, shared shell, audit/tests |
| 2 — Discovery | Owner properties/media/publication, public listings/search/details, applications, favorites and initial dashboards |
| 3 — Tenancy | Renter directory/accounts, assignment/terms/ending/history; later revised to invitation-first activation |
| 4 — Monthly records | Drafts, private proofs, submit/review/reject/reopen and approved-record freeze |
| 5 — Maintenance/inbox | Requests, transitions, comments, private files, notifications and read/unread UI |
| 6 — Reporting | Owner aggregates, SVG charts, filtered CSV and audit browsing |
| 7 — API | Versioned Sanctum API sharing actions/policies, later expanded for parity and push |

The initial 13-entity foundation expanded into the current 24 model classes and supporting tables. Later lifecycle/financial contracts supersede older phase rules where they differ.

## 2. Recorded Git milestones

| Date | Commit | Milestone |
| --- | --- | --- |
| 26 Aug 2026 | `f20e0c0` | Project initialized |
| 26 Aug | `9fa14c5` | Environment update |
| 26 Aug | `0654ab3` | Workflow and monthly finance changes |
| 27 Aug | `d1da571` | Monthly proof fix |
| 16 Sep | `42f1a1c` | Assignment and branding updates |
| 10 Oct | `aba2b65` | Staging and theme work |
| 12 Oct | `d18c071` | Logos, icons, favicon and staging UI |
| 12 Oct | `27278b9` | Additional requirement/workflow changes |
| 13 Oct | `a45c182` | Features, messaging and UI |
| 14 Oct | `2a917b1` | Styling, APIs, staging and Postman |
| 29 Oct | `210c3e1` | Cancellation corrections and Firebase/browser push recorded |
| 4 Nov | `435ccbd` | Firebase pipeline extended to native devices |
| 4 Nov | `e7b6a09` | UI polish, delivery diagnostics and browser-click fix |
| 4 Nov | `99c3d0b` | [PR #1](https://github.com/emmaduddin1/Rentra/pull/1), `firebase-notification` merged into `main` |
| 5 Nov | `26e5e94` | Dashboards, homepage, pagination and safe local preview |
| 5 Nov | `f009cce` | [PR #2](https://github.com/emmaduddin1/Rentra/pull/2), `ui-ux-modification` merged into `main` |

## 3. Expansion beyond the initial phases

### Properties, floors and media

Parent/child property rows enabled floor-by-floor rentals without duplicating tenancy, accounting or maintenance systems. Exclusive whole/floor modes, parent rent synchronization and archive/restore propagation were added. Media rules became configurable 3–12 photos, primary-image handling and optional validated walkthrough videos. Deletion became explicitly archival, preserving history.

### Tenantism identity and hosting

Rentra was rebranded with Tenantism wordmark/compact SVGs, favicons, icons, loaders and consistent navigation. A dedicated public hero replaced dependence on user-uploaded listing images. Staging was established separately. Broken image links were traced to storage symlinks pointing at a macOS path and repaired to server storage; preserving Hostinger's entry point became an explicit release constraint.

### Marketplace and API growth

Purpose/category/specification/amenity fields, gated sales support, renter lookup, identifier-based assignment, resource parity and application conversations expanded. Later capabilities, favorites, private-download aliases and lifecycle/financial endpoints brought the local API inventory from 34 to 60 routes. Source presence and deployed acceptance remain separate.

## 4. October financial and contract corrections

The 26 October migrations introduced the corrected financial model and expanded tenancy lifecycle, recorded in the 27 October handoff and later October commit.

Financial work separated provider-direct bills from owner income, added contractual/effective-dated utility arrangements, separated approval from payment, added immutable partial receipts and receivable/received/outstanding totals, and introduced maintenance expense/reimbursement fields. Reports/API resources were updated; historical balances and conflict safeguards were preserved.

Tenancy work replaced immediate activation on owner assignment with invitations, renter acceptance/rejection, owner withdrawal and expiry. Transactions, locks, histories and conflict tools were added. Renters gained cancellation requests; owners retained final decisions and explicit publish-again/assign-next choices. Future-start ending became cancellation rather than an invalid date range. Existing financial/maintenance history remained intact.

Guided owner/renter onboarding was also added, with account completion tracking and replay; Flutter parity integrated the relevant contracts and screens.

## 5. Browser Firebase rollout: 27 October–4 November

The existing `tenantism-88cfc` project was assigned to production; `tenantism-staging` was used separately for staging. CLI aliases, public SDK/VAPID settings and private dedicated sender credentials were configured.

Persistent notification rows remained authoritative. An after-commit outbox, queued delivery ledger, HTTP v1 sender, subscriptions, preferences, safe payloads and token cleanup were implemented. Hostinger's automatic once-per-minute hPanel scheduler/queue wrapper was configured and verified.

The initial symptom was that only the website bell updated. Investigation separated:

1. Native browser display from Firebase transport.
2. Chrome/macOS notification presentation settings from application code.
3. FCM acceptance from actual browser receipt.
4. Inbox polling from genuine push, using a local worker diagnostic.
5. Opening the website from persisting the intended notification's read state.

After real receipt/display were confirmed, the worker click flow was simplified to open the protected page immediately. The signed-in window then performs the CSRF-protected read action and authorized redirect; GET remains read-only.

On **4 November**, the user opened uniquely titled test #58 and its database read state was independently confirmed at **19:05:45 Asia/Karachi**. This completed the basic real Chrome staging path, without certifying every browser, restart/account-switch case or business destination.

## 6. Native push and Flutter corrections

Native devices were bound to Sanctum sessions using the existing outbox/sender and an additive nullable session foreign key. Browser payloads stayed data-only; native payloads use generic notification-plus-data and protected navigation.

The native config endpoint initially returned 404 because its release existed only locally. A targeted backend deployment on **3 November** preserved unrelated routes. The normal Android app enabled push and registered a valid staging session-bound device.

Physical TECNO CM6 / Android 16 checks passed foreground, background, tap and process-exit/cold-launch transport using an **isolated local API/database with staging Firebase**. These were not complete deployed rental-screen tests.

Follow-ups addressed stale UNREGISTERED tokens, registration recovery and environment-scoped navigation/provider lifetimes. A separate maintenance-comment crash was reproduced and fixed by correcting dialog/controller lifetime; the user confirmed the phone flow on **4 November**. The latest mobile record reports **64 tests passed, one existing opt-in skip**, clean analysis and a debug APK build/install.

Further deployed Android acceptance and iOS configuration were deferred by the user.

## 7. Web UI releases: 4–5 November

### 4 November

Listing media/cards were standardized; mobile hero/categories compressed; primary/advanced search simplified; notification states/actions clarified; Rent & bills labels, spacing, focus and control sizes improved. Desktop/mobile/long/empty fixtures were reviewed and the targeted staging release deployed.

### 5 November homepage and pagination

Live-refresh Previous/Next links became stable numbered pagination, sharing 20-item boundaries with the initial HTML and preserving unread filters. The homepage search card was extended around category controls; the outer background was raised to the More filters area. Both changes were verified and deployed to staging with a rollback backup.

### 5 November dashboards

The user requested local-only modernization on `ui-ux-modification` and confirmed keeping navy/terracotta. Owner/renter headings, cards, activity lists, property thumbnails, quick actions, mobile content, sidebar accessibility, popovers and reduced-motion effects were refined without changing KPI calculations.

A dedicated isolated local preview was added because the normal workstation `.env` points to staging. It uses sample SQLite data and disabled delivery instead of shared services.

After review, the user explicitly approved the merge, then separately requested staging deployment. The final release installed **31 application/source/build files**, verified **14 public build files**, rebuilt Blade views and preserved the environment, entry points, worker, rewrites and storage links. No migration, sample-data upload or production deployment was part of that release.

## 8. Important corrections to historical notes

| Earlier statement | Current interpretation |
| --- | --- |
| Assignment immediately occupies a unit | Superseded by invitation-first activation |
| Notifications only write rows | Browser/native FCM is implemented; platform acceptance varies |
| Browser click/read unresolved | Basic Chrome path verified on 4 November |
| Dashboard work local only | Accurate for review; user later approved merge and staging on 5 November |
| Flutter favorites absent | Superseded by current source/parity report |
| 13 entities / 34 API routes / 222 tests | Historical; now 24 model classes / 60 API routes / 412 Laravel tests |
| Phase notes mention delivered CI | Current checkout has no tracked GitHub Actions workflows; latest workflow listing was empty |

This records progress through the latest known release, not a finished production/mobile-store rollout. The acceptance/configuration backlog is maintained in [current status](CURRENT-STATUS.md).
