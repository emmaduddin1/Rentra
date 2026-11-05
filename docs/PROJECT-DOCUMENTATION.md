# Tenantism — complete project documentation

**Snapshot: 5 November 2026 · Asia/Karachi · Laravel source: `f009cce`**

Tenantism is a rental property discovery and management product with a Laravel web portal, a versioned REST API and a separate Flutter mobile application. It covers publishing properties, receiving applications, accepting tenancies, recording rent and bills, reviewing payment evidence, handling maintenance and preserving the resulting history.

The original project/repository name was **Rentra**. The product is now branded **Tenantism**; the Laravel directory and repository retain the earlier name.

This document consolidates current source, requirements, phase notes, handoffs, Git history and recorded verification. Implemented, deployed and fully accepted are different states; see the linked status guide for their boundaries.

## Documentation map

| Document | Purpose |
| --- | --- |
| **This document** | Complete feature inventory, workflows, architecture and important rules |
| [Development history](PROJECT-HISTORY.md) | Work added and corrected from the original phases through the latest release |
| [Current status and run guide](CURRENT-STATUS.md) | Deployment, verification, outstanding work and safe local commands |

## Contents

1. [Project snapshot](#1-project-snapshot)
2. [Users and rental journey](#2-users-and-rental-journey)
3. [Implemented features](#3-implemented-features)
4. [Financial rules](#4-financial-rules)
5. [Tenancy lifecycle](#5-tenancy-lifecycle)
6. [Notifications and Firebase](#6-notifications-and-firebase)
7. [Flutter application](#7-flutter-application)
8. [Architecture and data](#8-architecture-and-data)
9. [Security and design](#9-security-and-design)
10. [Supporting records](#10-supporting-records)

## 1. Project snapshot

| Item | Implementation / recorded state |
| --- | --- |
| Product | Tenantism |
| Laravel checkout | `/Users/waleed/rentra project/Rentra` |
| Backend repository | [emmaduddin1/Rentra](https://github.com/emmaduddin1/Rentra) |
| Flutter checkout | `/Users/waleed/rentra project/rentel` |
| Mobile repository | [waleed52531/rentel](https://github.com/waleed52531/rentel) |
| Backend | Laravel 13, locked at `13.30.0`; PHP requirement `8.3+` |
| Web frontend | Blade, Tailwind CSS 4, vanilla JavaScript, Vite `8.2.1` |
| Node requirement | Installed Vite declares `^20.19.0 || >=22.12.0` |
| Database | MySQL for the application; isolated SQLite for tests/local UI preview |
| Authentication | Laravel sessions on web; Sanctum personal access tokens for protected APIs |
| Mobile | Flutter/Dart, Material 3, BLoC/Cubit, HTTP client and secure session storage |
| Push | Firebase Cloud Messaging HTTP v1; web Firebase SDK `13.1.0` |
| Default currency | PKR, displayed as Rs; configurable |
| Staging | https://staging.tenantism.com |
| Production domain | https://tenantism.com; latest-release parity is not established here |
| Latest web release | `main`, `f009cce`, PR #2; merged and deployed to staging on 5 November |

The inspected source contains **166 non-vendor routes**, including **60 API routes**, **24 model classes**, **32 action classes** and **8 policies**. These are local source counts at this snapshot, not proof of complete API acceptance in every deployed environment.

## 2. Users and rental journey

The current `UserRole` enum has **Owner** and **Renter**. Guests use public discovery. Server-side conflict tools are available to authorized operators; a separate administrator web portal is not implemented by that enum.

| User | Main capabilities |
| --- | --- |
| Guest | Browse/search visible listings, open public details, register, log in and request password reset; protected address/contact information remains restricted |
| Owner | Manage owned properties/floors, publication, applications, invitations, tenancy terms/end decisions, record reviews, receipts, maintenance, reports and audit history |
| Renter | Search, apply, save, message eligible owners, respond to invitations, request cancellation, manage monthly drafts/proofs, submit records, report maintenance and view history |

A renter keeps the same account while searching and after taking a tenancy. Login accepts email or phone identifiers; profile/password management, suspension enforcement, logout and role-aware dashboard routing are implemented. Password reset is mail-based, not an established SMS recovery service.

### Normal end-to-end journey

1. Owner creates an eligible listing, adds media and publishes it.
2. Renter searches, opens details and submits an inquiry/application.
3. Owner reviews it; eligible participants can communicate through the existing conversation workflow.
4. Owner invites a new or existing renter. Assignment creates an invitation, not an active tenancy.
5. Renter accepts/rejects. Successful acceptance passes transactional concurrency checks, activates the tenancy and occupies the unit.
6. Renter creates an eligible monthly record, adds rent/bill information and private evidence, then submits it.
7. Owner approves/freezes, rejects with a reason, or reopens with a reason when authorized.
8. Owner receipts are recorded separately; receivable, received, outstanding and review status remain distinct.
9. Maintenance requests retain their own comments, statuses, attachments and expense/reimbursement information.
10. Renter cancellation is a request. The owner decides or directly ends the contract with an explicit next step for the property.
11. Financial, tenancy, maintenance, payment and audit history remain available after ending.

## 3. Implemented features

### Public website and property discovery

- Branded landing page with a static hero independent of listing uploads.
- Location/area/city, property category/type and budget search; supported size/unit filters on results.
- Compact property-category links with counts.
- More filters disclosure, automatic expansion for selected advanced values/errors, clear-filter behavior and query-preserving pagination.
- Shared listing cards: fixed 4:3 media area, portrait/landscape cropping, missing-image fallback, readable long content and aligned prices.
- Property details, media galleries, available specifications, amenities and authenticated contact information.
- Rent/Buy metadata and discovery behind the property-sales flag. `FEATURE_PROPERTY_SALES` defaults to false; capabilities expose environment feature availability.

Sales support should not be mistaken for a completed property-sale conveyancing or checkout product.

### Owner property and media management

- Create, view and edit owned properties; search/filter the inventory.
- Separate publication and availability/occupancy states; guarded publish/unpublish.
- Archive/soft-delete, view deleted items and restore as unpublished rather than silently public.
- Validated image upload, ordering, primary-image selection and automatic replacement of a deleted primary.
- Configurable **3–12 photo** policy, with existing-listing handling.
- Optional walkthrough videos: defaults of **3 files**, **200 MB each**, subject to hosting/PHP limits.
- Occupancy checks before archiving a property or a parent with rented floors.

### Floor-by-floor houses

- Whole-property and floor-by-floor modes are exclusive.
- Each floor is a child property with its own rent, media, publication, applications and tenancy.
- Building/location/contact fields inherit from the parent; parent rent is synchronized from relevant floors.
- A parent container is not itself a public rentable unit.
- Rental-mode changes, archive/restore propagation and occupied-floor guards are implemented.

### Applications, favorites and messaging

- Renter inquiries, duplicate-open-application protection, owner responses and permitted withdrawal.
- Saved-listing list and save/remove behavior, including current API support.
- Authorized application-related owner/renter conversations and message sending.
- Web polling, unread indicators, duplicate suppression and a bounded message scroll area.
- Generic notifications to the other eligible participant without exposing complete private messages.

### Tenancies and renter management

- Invitations for new/existing renter accounts, identifier resolution and owner renter selection.
- The owner's rental history directory is scoped to their records. Separately, the owner-authorized API lookup supports finding active renter accounts for invitations; that does not grant access to other owners' rental records.
- Renter accept/reject, owner withdrawal, expiry and competing-invitation closure.
- Agreed rent, deposit, billing day, dates and contractual utility arrangements.
- Status history, locks, conflict detection/reconciliation and audit entries.
- Cancellation requests, owner decisions, notice periods, ending and future-start cancellation handling.

### Monthly records, proofs and receipts

- One record per tenancy/month, with tenancy-period and future-month checks.
- Draft editing, submit/pending review, approve/freeze, reject and reasoned reopening.
- Private evidence upload/download with participant authorization.
- Utility arrangements, owner charges, discounts, adjustments and owner-directed reimbursements.
- Server-owned decimal totals, payment state and immutable partial receipt history.
- Historical records retained when terms change or a tenancy ends.

This records receipts/accounting. An integrated card/bank payment-processing gateway is not evidenced as implemented.

### Maintenance

- Renter requests with priorities, private attachments and participant access.
- Valid owner status transitions, comments and ordered history.
- Amount, payer, responsible party, reimbursement direction/amount/status and expense notes.
- Authorized owner financial corrections and retained history after tenancy ending.

### Reporting and audit

- Owner-scoped period/property/renter/status filters and corrected financial summaries.
- Inline SVG chart from real reporting data, readable labels and a corresponding table.
- Filtered streaming CSV export.
- Audit entries for important changes, actors, reasons and old/new values; secret fields are excluded by the audit service.

### Dashboards and onboarding

- Owner properties, active renters, available/occupied units, review queue, maintenance and application counts.
- Recent owner applications/properties, real thumbnails/fallbacks and direct review/report shortcuts.
- Renter tenancy, current monthly record, maintenance count, application/favorite counters and recent listings.
- Empty/conflict states and tenancy-dependent actions.
- Owner/renter product tours, completion tracking and replay.
- Responsive shared sidebar/header, property search, profile/account menu, unread indicators and role-aware logo navigation.

## 4. Financial rules

Laravel is authoritative. Mobile/web clients display server-returned decimals rather than adding all recorded bills together.

| Concept | Treatment |
| --- | --- |
| Rent | Contractual record amount |
| Owner-payable utilities | Included only under the relevant contractual arrangement |
| Provider-direct bills | Tracked; excluded from owner income |
| Included/not-applicable utilities | Do not add a second owner charge |
| Unclassified legacy utilities | Require review; excluded from owner receivables |
| Charges, discounts, adjustments | Explicit supported additions/reductions, calculated by the backend |
| Maintenance expense | Separate from utilities and rent |
| Reimbursement | Only an owner-directed reimbursement belongs in the relevant owner receivable |
| `total_receivable` | Amount owed to owner under the record's rules |
| `total_received` | Recorded owner receipts |
| `outstanding_receivable` | Remaining amount under the backend calculation |
| Submission vs payment status | Separate review/freeze and payment concepts |

Money uses fixed-precision storage and server-side decimal arithmetic; display formatting is separate. The compatibility `total_amount` equals `total_receivable` and is marked deprecated by the API.

Property utility values are defaults. A tenancy retains a contractual snapshot; effective-dated changes require a reason and do not silently rewrite existing monthly records. See [the financial contract](FINANCIAL-API-CONTRACT.md).

## 5. Tenancy lifecycle

The vocabulary is `invited`, `accepted`, `active`, `rejected`, `withdrawn`, `expired`, `termination_requested`, `notice_period`, `ended` and `cancelled`. Current normal acceptance transitions an eligible invitation directly to `active`; an enum entry alone is not a separate mandatory user step.

- At most one active tenancy per renter and one active primary tenancy per rentable unit.
- `active`, `termination_requested` and `notice_period` occupy the property and are financially active, subject to period eligibility.
- Invitations do not occupy units. Acceptance uses transactions, row locks and dedicated lock tables, and expires competing invitations.
- Renters request cancellation; owners make the final decision or directly end a contract.
- Notice-period occupancy/financial activity continues through its accepted end date.
- Closing a future-start contract is a cancellation, not an invalid end date before the start.
- Ending requires an explicit publish-again or assign-next-renter choice; the underlying end operation leaves the property unpublished until that choice is applied.
- Reconciliation blocks affected new activity while preserving historical records and balances.

Payloads, eligibility flags and operator commands are in [the lifecycle API contract](TENANCY-LIFECYCLE-API.md).

## 6. Notifications and Firebase

### Implemented delivery path

Business action → persistent `Notifier` row → after-commit observer/outbox → `SendWebPush` / `DeliverWebPush` and delivery ledger → persistent `web-push` queue → FCM HTTP v1 → registered browser/native device → authorized click/read flow.

Database notifications remain authoritative. Push failure must not undo the business action. FCM acceptance alone does not prove receipt, and a bell updated by polling is not evidence of push delivery.

Browser features include explicit permission requests; a root service worker; foreground updates/toasts; background native display; branded icons; account/environment binding; safe relative routes; deduplication; cross-tab reconciliation; fallback polling; token renewal/revocation; category preferences; and invalid/stale token handling.

Settings separate browser delivery from topics/inbox, show consistent states/actions, offer a local desktop display test and a receipt diagnostic, and never automatically prompt for permission. The inbox supports unread filtering, mark one/all and stable numbered pagination.

The current click path opens the protected notification page immediately. Its signed-in window performs the authorized CSRF-protected read action and follows the server-approved destination. GET remains read-only.

| Environment | Firebase project | Recorded use |
| --- | --- | --- |
| Staging | `tenantism-staging` | Configured and used by verified staging work |
| Production | `tenantism-88cfc` | Assigned/reserved production project; equivalent live rollout not established |

CLI aliases, public SDK configuration and staging VAPID setup are documented. Sender credentials remain outside public assets/Git. Registrations are environment-isolated; staging titles include `[STAGING]`.

The **basic real Chrome staging path** was verified on 4 November: FCM receipt, desktop presentation, opening Tenantism and automatic persisted read for test #58. Broader acceptance remains in [current status](CURRENT-STATUS.md). General business-event email delivery is not established by this FCM work; password reset is a separate mail workflow.

## 7. Flutter application

The separate `rentel` checkout was inspected read-only at local commit `c32f085`; it was not modified for this documentation task.

- Material 3 owner/renter screens, BLoC/Cubit, shared API-backed repositories and centralized HTTP/multipart handling.
- Paging, loading/empty/retry states, Laravel 422 parsing and unauthorized-session handling.
- Secure token/session restoration through `/me`; temporary server/network failure does not automatically discard the cached session.
- Environment selection, saved preferences, branding/splash/loaders, light/dark theme code and localization helpers including English/Urdu strings.
- Supported properties/media, discovery, applications, favorites, messaging and tours.
- Invitation/lifecycle actions, contractual terms, monthly financial fields/receipts, private document access, maintenance and history.
- FCM/native Android alerts, token lifecycle, environment/session binding, inbox synchronization and protected notification navigation.

Native devices bind to real Sanctum sessions and share the backend's notification pipeline. Logout/session expiry/account changes invalidate stale bindings; payloads contain generic notification content, not private proofs/messages.

Android staging registration is deployed. Physical transport checks used an **isolated local API/database with staging Firebase**; complete deployed rental-event/deep-link acceptance remains separate. A maintenance-comment dialog crash was fixed and the user confirmed the corrected phone flow.

iOS push remains deferred pending a real bundle ID, Firebase app/plist, APNs/signing setup and physical-device validation. Source targets/entitlements do not establish delivery. No mobile-store publication is established here.

Older mobile README claims that favorites are absent are superseded by current source and the parity report. Client features must still match the API deployed to the selected environment.

## 8. Architecture and data

| Layer/path | Responsibility |
| --- | --- |
| `routes/web.php`, `routes/api.php` | Public/session web and versioned token/role API endpoints |
| `routes/console.php` | Scheduled work and operator commands |
| `app/Http/Controllers`, `Requests`, `Resources/V1` | HTTP orchestration, validation and JSON representations |
| `app/Actions` | Reusable business writes shared by web and API |
| `app/Policies` | Record-level authorization |
| `app/Models`, `app/Enums` | Relationships, scopes and domain vocabulary |
| `app/Services` | Media/private files, reporting, audit and push |
| `app/Observers`, `app/Jobs` | Outbox and asynchronous delivery |
| `resources/views`, `css`, `js` | Screens, design system and browser behavior |
| `database/migrations`, `tests` | Schema/constraints and automated verification |

| Data group | Main entities/infrastructure |
| --- | --- |
| Accounts | Users, Sanctum tokens |
| Marketplace | Properties/parent-floor relationships, categories, amenities, images/videos |
| Interest/communication | Applications, favorites, conversations/messages |
| Tenancies | Contracts, utility/status histories, termination requests, active/invitation locks, conflicts |
| Accounting | Monthly records, private proofs, immutable receipt/payment records |
| Maintenance | Requests, private attachments, history |
| Notifications | Inbox rows, subscriptions, delivery ledger, topic preferences |
| Operations | Audit logs, queue jobs and scheduler infrastructure |

The API includes auth/capabilities, listings, favorites, sessions, renter lookup, properties, applications, conversations, tenancy lifecycle, monthly records/proofs/receipts, maintenance and notifications/devices. Web/API capabilities are not assumed identical: inspect actual routes/contracts before enabling a client action.

## 9. Security and design

Implemented controls include session/CSRF protection, Sanctum tokens, role middleware, policies, resource scoping, suspension checks, throttling, private authorized downloads, upload validation, server-calculated money, soft deletion, audit history, transactions, idempotency and concurrency locks.

Push uses encrypted registration tokens, private sender credentials, bounded retry/permanent revocation, generic content and allowlisted destinations. Staging/production data and registrations remain separate. Deployments preserve environment, uploads, dependencies, storage links and Hostinger entry points unless a reviewed change explicitly requires otherwise. These controls are not an independent security/compliance certification.

The current web identity is **navy `#1A273A`**, **terracotta `#C24D2C`**, warm off-white and Instrument Sans. The user explicitly retained this instead of the blue/brown values in an earlier brief. Wordmark/compact mark, favicons, branded loader and notification icons are integrated.

Recent UX work covers consistent listing cards, compact mobile search/categories, clearer Rent & bills labels, separated notification settings, polished dashboards, long content, keyboard menus/drawers, collapsed-link labels, checked 44px controls and reduced motion. Existing KPI definitions remain intact. Reports uses actual chart data; invented dashboard totals/charts were not added. Web retains its light theme; Flutter's native theme support is separate.

## 10. Supporting records

| Topic | Documentation |
| --- | --- |
| Original requirements/phases | [SRS](SRS.md), [Phase 1](PHASE-1.md), [Phase 2](PHASE-2.md), [Phases 3–7](PHASE-3-TO-7.md) |
| Earlier handoff | [27 October](PROJECT-HANDOFF-2026-10-27.md) |
| Floors/media/retention | [Floors](MULTI-FLOOR-HOUSES.md), [media/deletion](LISTING-MEDIA-AND-DELETION.md) |
| Financial/lifecycle contracts | [Finance](FINANCIAL-API-CONTRACT.md), [tenancies](TENANCY-LIFECYCLE-API.md) |
| Firebase/browser | [Assignments](FIREBASE-CONNECTION.md), [web push](WEB-PUSH-NOTIFICATIONS.md), [deployment history](STAGING-WEB-PUSH-DEPLOYMENT.md) |
| Native push | [Implementation](MOBILE-PUSH-NOTIFICATIONS.md), [staging rollout](STAGING-MOBILE-PUSH-DEPLOYMENT.md) |
| Device acceptance | [Outstanding evidence](OUTSTANDING-2026-11-04.md) |
| UI work | [4 Nov polish](UI-POLISH-2026-11-04.md), [5 Nov homepage/pagination](UI-PAGINATION-2026-11-05.md), [dashboard review](DASHBOARD-LOCAL-REVIEW-2026-11-05.md) |
| API examples | [Postman collection](postman/Rentra-API.postman_collection.json); compare with current routes |
| Older hosting guidance | [Deployment guide](DEPLOYMENT-TENANTISM.md); instructions are not deployment authorization |

The sibling Flutter checkout additionally contains `docs/DEVELOPMENT_HANDOFF.md`, `MOBILE_PARITY_REPORT.md`, `MOBILE_PUSH_NOTIFICATIONS.md` and `NOTIFICATION_NAVIGATION_FIX.md`. Current code and later dated evidence take precedence over superseded historical prose.
