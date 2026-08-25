# Phase 2 - Property Discovery

Delivered against SRS §16 Phase 2: *Property CRUD, images, publication, applicant feed,
area search, listing detail* - plus the applicant inquiry flow (FR-APP-005 to FR-APP-007),
which completes the §5.5 applicant journey and the §15 MVP criterion "applicant can search
available listings by area, open details, and submit an application/inquiry".

## What is in place

### Property management (SRS §5.1, FR-PROP-001 to FR-PROP-005)

Full CRUD for an owner's portfolio, with every write going through an action so the Phase 7
API reuses it rather than duplicating it (NFR-MNT-001):

| Action | Rule it owns |
|--------|--------------|
| `CreateProperty` | property + gallery written in one transaction (NFR-REL-002) |
| `UpdateProperty` | delegates status and publication to the actions that own their rules |
| `PublishProperty` | publication state and the `published_at` stamp (FR-PROP-004) |
| `ChangePropertyStatus` | BR-002 - a property with an active tenancy stays Occupied |
| `ArchiveProperty` | BR-009 - soft delete, and never leaves the listing published |
| `RestoreProperty` | an archived listing returns as a **draft**, never straight to public |

The list view filters by search term, status, publication, and archived/active, with
`withCount` for images and applications so counts cost one query, not N (NFR-PERF-003).

### Images (FR-PROP-003, NFR-SEC-005)

`PropertyImageService` owns placement, ordering, and the "exactly one primary" invariant:
the first image a property ever receives becomes primary, deleting the primary promotes the
next one, and files are removed from disk when their row goes. If the database write fails,
already-stored files are deleted rather than orphaned. Uploads are validated by extension,
MIME type, size, and per-property count - the cap lives in `config/rental.php`.

### Applicant feed (FR-APP-001 to FR-APP-004)

`Property::publiclyVisible()` gates the feed, the detail page, applications, and favourites,
so BR-001 is enforced in one place. Area search matches area *or* city; type and rent-range
filters are there too. Anything not publicly visible returns **404** to everyone except its
owner, who gets a clearly-labelled preview - no existence is disclosed (SRS §12). The street
address and owner contact details are hidden from guests (SRS §19,
`listings.require_login_for_contact`).

### Applications and saved listings (FR-APP-005 to FR-APP-007)

Applicants submit an inquiry from the listing page; owners review it and set the status the
applicant sees. A second *open* inquiry for the same property is refused with a clear message
instead of creating a duplicate row (SRS §12). Withdrawal is the applicant's alone - the
owner cannot set `Withdrawn`, and a withdrawn application cannot be answered. Withdrawn rows
are kept, so the owner's history stays complete (BR-009).

### Notifications (partial - FR-NOT-001, FR-NOT-004)

`Notifier` writes the in-app `notifications` row for application submitted / status changed,
with an idempotency key so an event never notifies twice. This is deliberately only the
*record*: the queued email and push channels (FR-NOT-002) and the read/unread UI
(FR-NOT-003) remain Phase 5 work. Writing the rows now means no event history is lost in the
meantime.

### Dashboards

FR-ADM-003 recent lists now exist for the data that exists: recent applications and recent
properties on the owner dashboard, recent applications and saved listings on the applicant
dashboard. Monthly submissions and maintenance join them in Phases 4 and 5; charts
(FR-ADM-004) are Phase 6.

## Decisions taken

| # | Decision | Why |
|---|----------|-----|
| 1 | Publishing an **occupied** property is allowed | BR-001 already hides it from the feed. Forcing the owner to republish after every tenancy ends would be busywork; the UI states "appears in the feed once its status is Available". |
| 2 | A hidden listing returns 404, not 403 | SRS §12 requires that unauthorised access does not disclose whether another user's record exists. For a public feed, "not published" and "does not exist" must look identical. |
| 3 | Archiving forces publication back to Draft and status to Inactive | So restoring a listing months later cannot silently push it back into the public feed. |
| 4 | Business-rule violations are thrown as `ValidationException` | They render inline next to the field on the web, and the same actions return a 422 with the same messages once the API lands (SRS §12, §9.3). |
| 5 | Applications are part of Phase 2, not a later phase | §16 does not name them in any phase, but §5.5 and the §15 MVP criteria put them in the applicant journey, and the owner dashboard already counted them. |
| 6 | Duplicate applications are blocked on *open* status only | Pending and Reviewed block a resubmission; a rejected or withdrawn applicant may apply again if circumstances change. |
| 7 | A saved listing can always be *removed*, even after it is unpublished | Adding requires a visible listing; removing must not become impossible because the owner changed something. |
| 8 | Search and application endpoints are rate limited | NFR-SEC-006 names search and contact endpoints explicitly. |

## Fixed along the way

`public/storage` pointed at `…/rent-managemnet/storage/app/public` - a misspelled path that
does not exist, so every uploaded image would have 404'd. The symlink was recreated against
the real project path.

## Tests

111 tests, 411 assertions, all green (`php artisan test`). Phase 2 adds 48:

- **`Owner/PropertyManagementTest`** - creation with images, the primary-image rule, money
  validation (negative, malformed, oversized), required fields, appending images through the
  edit form, cross-owner 403 on update/edit/show, portfolio scoping, publish/unpublish,
  archive/restore, the active-tenancy guard on archiving and status changes, audit rows, and
  role lockout.
- **`Owner/PropertyImageTest`** - upload ordering, MIME and size rejection, the per-property
  cap, changing the primary, primary promotion on delete (including file removal), an image
  id from another property returning 404, and cross-owner 403.
- **`Listings/ListingFeedTest`** - BR-001 visibility across draft/occupied/inactive, area and
  city search, type and rent filters, the FR-APP-003 card summary, 404 for hidden listings
  with an owner preview, guest contact/address hiding, and the empty state.
- **`Applications/ApplicationTest`** - submission with notification and audit, message
  validation, the duplicate guard, applying to a hidden listing, role lockout, owner review
  and applicant visibility, cross-owner and cross-applicant scoping, withdrawal rules.
- **`Favorites/FavoriteTest`** - toggle on/off, per-applicant scoping, the hidden-listing
  asymmetry, role lockout, and the unique constraint.
- **`ScreenSmokeTest`** - every Phase 2 screen renders for the role that owns it.

## Open items for the next phases

- **Notification delivery**: rows are written, but nothing sends or displays them yet
  (Phase 5).
- **Image derivatives**: NFR-PERF-004 wants thumbnails served separately from originals.
  Originals are stored and size-capped; resizing is Phase 6 hardening.
- **Property status vs tenancy**: `ChangePropertyStatus` refuses to free an occupied
  property, but nothing yet *creates* the active tenancy that makes it occupied - that is
  FR-TEN-001 in Phase 3.
- **Deferred by design**: tenancy assignment (Phase 3), monthly submission and approval
  (Phase 4), maintenance workflow (Phase 5), reporting and exports (Phase 6), versioned REST
  API (Phase 7).
