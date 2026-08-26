# Phase 1 - Foundation

Delivered against SRS §16 Phase 1: *Laravel project, MySQL, authentication, roles,
policies, shared layout, CI/test foundation* - plus the complete database schema from
SRS §8, so later phases only add behaviour, not tables.

## What is in place

### Database (SRS §8)

All 13 entities exist with their relationships, indexes, and integrity constraints:
`users`, `properties`, `property_images`, `tenancies`, `monthly_records`,
`monthly_proofs`, `maintenance_requests`, `maintenance_attachments`,
`maintenance_status_history`, `applications`, `favorites`, `notifications`,
`audit_logs`.

Enforced at the database level:

- unique `(tenancy_id, period_month)` on `monthly_records` (BR-003)
- unique `(renter_id, property_id)` on `favorites` (§8.3)
- unique `users.email` and `users.phone` (FR-AUTH-001)
- `DECIMAL(14,2)` for every money column (BR-012)
- foreign keys that cascade so no orphan images, proofs, attachments, or history rows survive
- soft deletes on users, properties, tenancies, monthly records, and maintenance requests (BR-009)

### Domain layer

`app/Enums` holds the state vocabulary from SRS §9.1: `UserRole`, `UserStatus`,
`PropertyType`, `PropertyStatus`, `PublicationStatus`, `TenancyStatus`,
`MonthlyRecordStatus`, `PaymentCategory`, `MaintenancePriority`, `MaintenanceStatus`,
`ApplicationStatus`. `MaintenanceStatus::canTransitionTo()` already encodes the legal
transitions so Phase 5 rejects invalid ones cleanly (SRS §12).

Models carry the query scopes that later phases and the API will share:
`Property::publiclyVisible()` (BR-001), `Property::inArea()` (FR-APP-002),
`*::forOwner()` / `*::forRenter()` (BR-010), `MonthlyRecord::forPeriod()` and
`normalizePeriod()` (SRS §12).

### Authentication (SRS §6.1)

Hand-rolled on the framework's own primitives - no Breeze or Fortify, so there is no
scaffolding to unpick later:

- register (FR-AUTH-001), login/logout (FR-AUTH-002), password reset (FR-AUTH-003)
- login accepts either the email or the phone on the account
- role-based routing to `/owner` and `/renter` (FR-AUTH-004)
- profile and password management (FR-AUTH-005)
- suspension (FR-AUTH-006): a suspended account cannot log in, an existing session is
  terminated on its next request, and `Gate::before` denies every policy check
- rate limiting on login, registration, and password reset (NFR-SEC-006)

### Authorization (SRS §3.1, BR-010, NFR-SEC-004)

Policies exist for every protected entity - `Property`, `Tenancy`, `MonthlyRecord`,
`MaintenanceRequest`, `Application`, `Favorite`, `Notification` - and already encode the
business rules the later phases enforce: pending records are not renter-editable
(BR-004), approved records are frozen (BR-006), only the property owner approves or
reopens (FR-MON-006/009), only publicly visible listings accept applications
(FR-APP-001/005).

### UI shell (SRS §9.1, §11)

Responsive Tailwind 4 layouts (`x-layouts.app`, `x-layouts.guest`), role-aware
navigation with a no-JavaScript mobile menu, and shared components for badges, stat
cards, money, flash messages, and form fields with inline validation errors. Friendly
403 and 404 pages (SRS §12). Status badges always carry a text label, never colour alone
(NFR-UX-005).

### Audit and testing

`App\Services\AuditLogger` writes to `audit_logs` and strips secrets before storing
(BR-008, NFR-SEC-008). It is already wired into registration and profile changes.

63 tests cover registration, login (including phone login, suspension, and throttling),
password reset, role routing, profile and upload validation, the schema constraints, the
domain rules, and cross-owner/cross-renter policy denial. CI runs Pint, migrations, and
the suite (`.github/workflows/tests.yml`).

## Decisions taken

| # | Decision | Why |
|---|----------|-----|
| 1 | `users.email` and `users.phone` are both nullable and unique | FR-AUTH-001 allows registration by email *or* phone, and §8.3 anticipates "unique index on users.email where email is used". At least one identifier is required by validation. |
| 2 | Password reset is email-only | The framework's reset flow is mail-based. An account with no email cannot self-reset; SMS reset is a Phase 6+ item. |
| 3 | "Frozen" is `status = approved` plus a `frozen_at` timestamp, not a status value | Matches the §8.1 field list (`status` and `frozen_at` are separate columns) while §9.1 still shows "Frozen" in the UI via `displayStatusLabel()`. |
| 4 | `notifications` follows the SRS shape (`user_id`, `title`, `body`, `data_json`), not Laravel's polymorphic notifications table | §8.1 specifies the columns. Consequence: the framework's `database` notification channel is not used; the relation is `User::inAppNotifications()` so the mail channel keeps working. A nullable unique `idempotency_key` supports FR-NOT-004. |
| 5 | Status columns are strings cast to PHP backed enums, not SQL `ENUM` | §8.3 permits "validated enums or constrained string values"; strings stay portable between MySQL and the SQLite test database and avoid a migration for every new state. |
| 6 | BR-002 (one active tenancy per property) is enforced in the assignment service and the policy, not by a partial unique index | Partial/filtered unique indexes are not portable across MySQL and SQLite. The Phase 3 service will do the check inside a transaction (NFR-REL-002). |
| 7 | Self-registration is limited to renters and owners | SRS §19 - the two roles the system has. Configurable in `config/rental.php`. |
| 8 | `Model::preventLazyLoading` is **not** enabled | Policies traverse `record -> tenancy -> property` during authorisation, which is legitimate lazy loading. Index and dashboard queries eager-load explicitly instead (NFR-PERF-003). |
| 9 | Totals are summed with `bcadd`, never floats | BR-012. |

## Open items for the next phases

- **Database engine**: the full schema is verified on MySQL 8 (`rent-managemnet`) and on
  the SQLite test database. Note for future migrations: MySQL caps identifier names at 64
  characters, so composite indexes on long table names need an explicit short name - see
  `msh_request_changed_at_index` in the maintenance status history migration.
- **Storage**: run `php artisan storage:link`. Proof files must land on the private disk
  and be served through an authorised route (NFR-SEC-007); Phase 4 adds that route.
- **Notifications**: `Notification` model and table exist; the dispatch service, queued
  mail, and the read/unread UI arrive with Phase 5 (FR-NOT-001 to FR-NOT-004).
- **Deferred by design**: property CRUD and the public listing feed (Phase 2), tenancy
  assignment (Phase 3), monthly submission and approval (Phase 4), maintenance workflow
  (Phase 5), reporting and exports (Phase 6), versioned REST API (Phase 7).
