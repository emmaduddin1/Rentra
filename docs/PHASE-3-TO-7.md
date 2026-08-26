# Phases 3 to 7 - Tenancy, Monthly Records, Maintenance, Reporting, API

Delivered against SRS §16 phases 3 through 7, on top of the foundation in
[PHASE-1.md](PHASE-1.md) and the property discovery work in [PHASE-2.md](PHASE-2.md).

At this point every workflow in SRS §5 runs end to end: property publication → renter
inquiry → renter assignment → monthly submission → owner approval and freeze → maintenance
tracking → reporting → the same operations over a versioned REST API.

## Phase 3 - Tenancy (FR-TEN-001 to FR-TEN-005)

| Action | Rule it owns |
|--------|--------------|
| `AssignRenter` | BR-002 - the property row is locked inside the transaction, so two concurrent assignments cannot both pass the "one active tenancy" check |
| `EndTenancy` | FR-TEN-005 - frees the property to Available or Inactive, never Occupied; the end date cannot precede the start |
| `UpdateTenancy` | FR-TEN-002 - the terms stay editable; the property and renter do not |
| `CreateRenterAccount` | SRS §19 - the owner invites the renter, since renters do not self-register |

Assignment marks the property Occupied, which removes it from the public feed through
BR-001 without any extra bookkeeping. A new renter account gets a generated temporary
password returned **once** to the owner for hand-over - it is never stored in the audit log
(NFR-SEC-008), and the renter can reset it through the normal flow.

FR-TEN-003 gives the owner a renter directory scoped to their own tenancies: there is no
global user list, so BR-010 holds even for people search.

## Phase 4 - Monthly Records (FR-MON-001 to FR-MON-010)

The freeze lifecycle, which is the core of the product:

```
draft ──submit──► pending ──approve──► approved + frozen_at   (BR-006)
  ▲                  │                        │
  └─── reject ───────┘                        │
       (reason required, BR-007)      reopen (reason required, FR-MON-009)
                                              │
                                              └──► pending
```

- **BR-003** is guarded in `CreateMonthlyRecord` *and* by the unique index, so a race loses
  cleanly with a readable message rather than a 500.
- **BR-011** rejects a period outside the tenancy, and a month that has not started yet.
- **BR-012** - the total is always recomputed server-side with `bcadd` from the category
  columns. A client cannot post a total.
- **BR-004/BR-006** - `isEditableByRenter()` is checked in the policy, in the action, and in
  the proof service, so no channel can slip past it.
- Resubmitting after a rejection clears the previous verdict on the record but keeps the
  full history in `audit_logs` (BR-005).

**Proofs (FR-MON-003, NFR-SEC-007).** Files land on the *private* disk with randomised
names. `MonthlyProofDownloadController` is the only way back out, and it runs
`MonthlyRecordPolicy::downloadProof` first - so a proof is readable by the renter who
uploaded it and the owner of that property, and by nobody else. A failed database write
deletes any file already stored rather than orphaning it (SRS §12).

## Phase 5 - Maintenance and notifications (FR-MNT-001 to FR-MNT-005, FR-NOT-001 to FR-NOT-004)

`MaintenanceStatus::canTransitionTo()` - written back in Phase 1 - is finally enforced:
the owner's form offers only the legal transitions, and `ChangeMaintenanceStatus` rejects
anything else cleanly (SRS §12). Completed and cancelled are terminal.

FR-MNT-004's timeline is a single ordered list: a comment is a history row whose status does
not move, so the UI never has to merge two sources. Attachments follow the proof rules
(private disk, authorised download), because issue photos show the inside of someone's home.

Notifications now have a UI (FR-NOT-003) - list, unread filter, mark one or all as read, and
an unread count in the nav. Every workflow event writes one row with an idempotency key
(FR-NOT-004). **Email and push (FR-NOT-002) are still not wired**: `Notifier` writes the
in-app record only, and the queued channels are the natural next increment.

## Phase 6 - Reporting and hardening (FR-RPT-001/003, FR-ADM-004, SRS §13)

`MonthlyReport` is one owner-scoped query behind the table, the totals, the category and
status breakdowns, and the chart series - computed by the database, not in PHP
(NFR-PERF-003). The CSV export streams through a `LazyCollection`, so a long report never
has to fit in memory, and it carries whatever filters are on screen.

The chart is inline SVG with no library and no external request: it prints, it works with
JavaScript off, and every bar keeps a text label with the same numbers repeated in the table
below it (NFR-UX-005).

The audit trail (SRS §13) is filtered to subjects the owner controls plus their own actions,
showing old → new values for each change.

## Phase 7 - REST API (SRS §9.3, NFR-MNT-004)

34 endpoints under `/api/v1`, authenticated with Sanctum personal access tokens. Every one
delegates to the same actions, policies, and form requests as the web portal, so the rules
cannot drift between channels (NFR-MNT-001). Money crosses the wire as decimal **strings**,
never floats (BR-012).

`ListingResource` withholds the address and contact details from unauthenticated callers,
exactly as the web listing page does (SRS §19). `MonthlyRecordResource` exposes `is_frozen`
and a `status_label` of "Frozen", so a Flutter client never has to know that freezing is
stored as `approved` + `frozen_at`. `MaintenanceRequestResource` ships
`allowed_transitions`, so the client can render only the buttons that will work.

Suspended accounts are cut off on both channels: `EnsureUserIsActive` now runs on the api
group too, and deletes the presented token instead of invalidating a session (FR-AUTH-006).

## Decisions taken

| # | Decision | Why |
|---|----------|-----|
| 1 | Reopening returns a record to **Pending**, not to the renter | FR-MON-009 says "editable/pending". Sending it to Pending keeps exactly one path back to the renter - rejection, which already requires a reason - instead of two ways to do the same thing. |
| 2 | A draft monthly record cannot be deleted | The unique index on `(tenancy_id, period_month)` also covers soft-deleted rows, so deleting a draft would permanently block that month. Drafts are edited instead. |
| 3 | Maintenance attachments use the proof disk, not the image disk | FR-MNT-002 calls them optional images, but they show the inside of a home. Private storage plus an authorised route is the safer reading of NFR-SEC-007. |
| 4 | A comment is a status-history row | FR-MNT-004 asks for "comments and status history" in one chronological timeline. One table keeps the ordering honest. |
| 5 | The tenancy's property and renter are immutable | Swapping either would rewrite history that FR-TEN-004 requires be preserved. End the tenancy and create a new one. |
| 6 | Reports export CSV, not PDF | FR-RPT-003 says "CSV or PDF" and is a *Could*. CSV needs no new dependency and opens in the tools owners already use. |
| 7 | The chart is hand-drawn SVG | NFR-PERF-004 and the CSP-free brief both argue against pulling a chart library in for one bar chart. |
| 8 | API token abilities are set to the account's role | A token can never outrank the account that issued it. |

## Fixed along the way

- **`gte` on date strings compares string *length*.** The report's `to`/`from` range guard
  silently passed `2026-01` against `2026-06`. It now uses `after_or_equal`.
- `public/storage` pointed at a misspelled path (fixed in Phase 2, noted here because image
  rendering depends on it).

## Tests

**196 tests, 820 assertions, all green** (`php artisan test`). Phases 3 to 7 add 85:

- **`Owner/TenancyManagementTest`** (11) - assignment, account creation and its
  password-never-audited guarantee, BR-002, cross-owner refusal, role checks on the renter,
  ending and its property-status rules, immutable history, renter-side scoping.
- **`MonthlyRecords/MonthlySubmissionTest`** (13) - draft creation and period normalisation,
  BR-003, BR-011 in both directions, money validation, derived totals, submission and its
  notification, the empty-submission guard, BR-004, BR-006, BR-005, renter scoping.
- **`MonthlyRecords/MonthlyApprovalTest`** (10) - approval and freeze, reject-without-reason,
  the renter seeing the reason, reopen-without-reason, reopen clearing the freeze, invalid
  transitions, cross-owner refusal, idempotent approval.
- **`MonthlyRecords/MonthlyProofTest`** (7) - private-disk placement, MIME and size
  rejection, the per-category cap, refusal on pending/frozen records, and the download
  matrix (renter ✓, owner ✓, other owner ✗, other renter ✗, guest → login).
- **`Maintenance/MaintenanceTest`** (11) - creation with attachments and notification,
  validation, cross-tenancy refusal, ended-tenancy refusal, the status timeline, illegal and
  no-op transitions, renter read-only status, two-way comments, scoping, attachment
  downloads.
- **`NotificationTest`** (6) - per-user scoping, idempotency, individual and bulk read,
  cross-user refusal, the unread filter.
- **`Owner/ReportTest`** (6) - owner-scoped totals, range/property/status filters, the
  rejected impossible range, CSV contents and filter-matching, the audit trail, role lockout.
- **`Api/ApiAuthTest`** (7) - login by email or phone, bad credentials, suspended accounts,
  token death on suspension, unauthenticated refusal, logout revocation, rate limiting.
- **`Api/ApiWorkflowTest`** (11) - feed visibility and contact hiding, area search, 404 for
  hidden listings, property CRUD and scoping, the complete monthly cycle
  (create → duplicate refused → submit → lock → approve/freeze → reopen → reject), approval
  endpoints closed to renters, role-scoped lists, tenancy assign/end with BR-002, the
  maintenance lifecycle, applications, notifications.
- **`ScreenSmokeTest`** (4) - all 24 owner screens, 13 renter tenancy screens, 7 renter search screens,
  and the public pages render.

## Known gaps

These are deliberate, not oversights:

- **FR-NOT-002 (email/push)** - `Notifier` writes in-app rows only. Adding a queued mail
  channel is a change to one class.
- **NFR-PERF-004 (thumbnails)** - originals are stored and size-capped; no derivative
  generation.
- **FR-RPT-003 PDF** - CSV only, see decision 6.
- **Deployment (§16 Phase 6)** - CI runs Pint, migrations, and the suite; no deployment
  pipeline is configured, since the target environment has not been chosen.
- **§18 future enhancements** - payments, e-signatures, maps, chat, tenant verification,
  multi-unit buildings, and Urdu localisation all remain out of scope, as the SRS intends.
