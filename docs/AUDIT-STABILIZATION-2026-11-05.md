# Tenantism web audit and stabilization — 5 November 2026

Status: audit fixes validated and deployed to staging. Production, Git push and merge were not performed.

Baseline: `main` at `f009cce`. Pre-existing local changes are README/project documentation and `.DS_Store`; application source matched that baseline. The user's latest chat explicitly authorizes staging deployment after completion, superseding the attached brief's local-only deployment limit. Production and Git push/merge remain outside scope.

## Initial prioritized findings, before application edits

| ID | Severity | Evidence and reproduction | Planned disposition |
| --- | --- | --- | --- |
| A01 | P1 | `UpdateMonthlyRecord`, `ApproveMonthlyRecord`, `RejectMonthlyRecord`, `ReopenMonthlyRecord`, `SubmitMonthlyRecord` inspect mutable state before their transactions and do not reload/lock the record. A stale draft changed rent from 50,000 to 1 after another writer froze it; a stale rejection changed an approved record to rejected while retaining `frozen_at`. | Serialize mutable record/evidence/receipt operations and revalidate fresh state; regress stale interleavings and MySQL concurrency. |
| A02 | P1 | `RequestTenancyTermination` checks the caller's model before locking, but does not recheck active status on the locked row. Reproduced ending a contract then submitting the older active instance: tenancy became `termination_requested` while its property remained `available`. `EndTenancy` also checks/uses an unlocked caller model. | Lock/reload and validate lifecycle state inside transactions, retaining existing rules/history. |
| A03 | P1 | `MonthlyProofService` checks editability/counts before storing/writing and deletes using an unlocked relation. Approval/submission can race evidence changes, and concurrent attachments can bypass count bounds. | Share the parent record lock and verify fresh state before evidence mutation; preserve existing storage cleanup rules. |
| A04 | P1 | `RecordMonthlyPayment` creates receipts/recomputes cached aggregates without locking/reloading its parent. Concurrent or stale-model receipts can leave ledger totals inconsistent with the current record. | Serialize on the record; cover aggregate/history correctness and retry behavior. |
| A05 | P2 | `Owner/ReportController::export` passes renter/property text directly to `fputcsv`; spreadsheet formula prefixes are not neutralized. | Reproduce with isolated exported user text and sanitize only spreadsheet-dangerous text if confirmed. |
| A06 | P2 | No tracked `.github/workflows` file exists; the historical test counts are not a current pull-request gate. | Add non-deploying isolated Laravel/JS/build CI, including an appropriate MySQL verification path. |
| A07 | P2 | Local and deployed API parity and complete authenticated renter lookup acceptance are not established by route counts. The API lookup and web dropdown currently select active renter accounts, but pagination/fields/privacy require focused comparison. | Verify locally with authenticated isolated accounts, inspect staging routes read-only and distinguish remaining live acceptance gaps. |
| A08 | P3 | Listing images use original validated uploads with CSS crops; no derivative generation is evidenced. | Assess performance benefit without replacing existing uploads or inventing an image pipeline. |
| A09 | P1 | Current registry audits flag CommonMark/Symfony runtime dependencies and concurrently/shell-quote/source-map-js/Vite tooling. Locked Symfony 8.1 packages require PHP >=8.4.1 although the project declares PHP ^8.3. No exploit against this application is inferred from advisory presence alone. | Resolve maintained compatible patches with a PHP 8.3 platform constraint; retain Firebase version/protocol and verify dependency/build/application checks. |

No P0 issue is confirmed at this point. Suspicions are not treated as vulnerabilities without evidence. Existing invitation acceptance already checks expiry through `Tenancy::isInvitationPending`; push queue exceptions are already isolated by the observer; private files already have participant policies. These working safeguards will not be rebuilt.

## Baseline checks actually run

- `php artisan test --compact`: 412 passed / 2,117 assertions.
- `npm run test:js`: 34 passed.
- Read-only source review: lifecycle, accounting, policies, requests, notifications/jobs, private media, renter lookup, routes and test configuration.
- Isolated SQLite reproduction script: `/private/tmp/tenantism-audit-reproduce-20261105.php`; no shared data or delivery used.
- Initial Composer audit found 10 advisories across CommonMark/Symfony. Initial npm audit found tooling advisories. Both final registry audits report zero advisories after compatible updates.

## Verification boundaries

SQLite reproductions establish stale-model failures but do not establish MySQL row-lock behavior. A dedicated fresh MySQL instance is planned. No physical Android/iOS device or live recipient push is assumed available. Staging records will not be seeded/reset or used as disposable test data. Real authenticated staging acceptance will be reported separately from local tests, route presence and source hashes.

## Confirmed fixes and implementation evidence

- **A01 / A03 / A04 — financial transactions:** `MonthlyRecord::refreshForUpdate()` reloads current attributes and clears cached relations on the same caller object inside the transaction. Update, submit, approve, reject, reopen, evidence changes and receipt creation share this parent lock. Status checks now use the locked state. Approval remains idempotent; rejected/reopened records retain their existing rules. Proof deletion waits for the outer commit, preserving the file if the database rolls back.
- **A02 — contract state:** ending, termination requests and term updates reload/lock the tenancy. Ending locks the property before changing availability. Responses lock tenancy before termination request, matching closure order. Assignment locks and rechecks the active renter before the property. Expiry remains enforced by the existing `isInvitationPending()` implementation. Historical contracts, snapshots and receipts are retained.
- **A04 — receipt retry contract:** optional UUID `idempotency_key` is scoped to a monthly record and backed by a unique index. An identical retry returns the same receipt without repeating totals, audit or notification. Reusing the key with different amount/date/reference/notes returns 409. The web form supplies a key. Existing clients omitting the key retain their prior behavior and must adopt it to get retry deduplication. This records receipts; it does not add a gateway. See `docs/FINANCIAL-API-CONTRACT.md`.
- **A05 — CSV injection:** reproduced formula-valued property/area/renter text in an isolated owner export. `Owner/ReportController::spreadsheetText()` prefixes dangerous text, including leading whitespace/control characters; amount cells remain numeric. CSV escaping is explicit. This protects this export on import; spreadsheet software can alter protections on re-save, so it is not a universal spreadsheet sandbox.
- **A09 — dependencies and PHP support:** lock resolution now targets PHP 8.3. CommonMark is patched and Symfony uses the compatible maintained 7.4 branch instead of requiring PHP 8.4.1 through 8.1 packages. Laravel remains 13.30.0 and Firebase remains 13.1.0. npm tooling patches stay inside existing declared ranges. Composer and npm audit both report zero known advisories at verification time; this is not proof of absence of every vulnerability.
- **A10 (P1, additionally reproduced) — gallery integrity:** deleting an old non-primary image after it became primary left zero primary images. `PropertyImageService` now serializes attach/delete/primary mutations on the property, reloads deleted image state, rechecks count limits under the lock and defers physical deletion until commit. Existing uploads/URLs and validation rules are preserved.
- **A11 (P1, additionally reproduced) — monthly period after closure:** an old tenancy instance could create a record after another writer moved its end into the preceding month. `CreateMonthlyRecord` now locks/reloads the tenancy before checking its period and taking its utility snapshot. The period uniqueness index and existing duplicate handling remain.
- **A06 — CI:** `.github/workflows/checks.yml` runs Laravel on PHP 8.3/8.5, JS/build on Node 24, and a dedicated MySQL 8.4 service for coordinated concurrency plus the full suite. It triggers on pull requests/manual runs only, has read-only repository permission, and needs no production secrets. It does not deploy. The workflow has not run on GitHub because no push/merge was authorized.

- **A12 (P1, MySQL reproduction) — competing invitations:** two renters accepting the same property caused a real 1213 deadlock because each locked its invitation before waiting on the shared property; the winner then needed the other's invitation to expire it. `RespondToInvitation::accept` now locks the property before the invitation, retaining the renter guard and active uniqueness constraint. The transaction has three bounded deadlock attempts. Unique-constraint handling is narrowed to that exception so unrelated database failures are not mislabeled as ordinary invitation conflicts. The coordinated checker proves one active contract/lock for both same-renter and same-property contenders.

## Financial and security review

Backend BCMath calculations remain authoritative. Provider-direct bills remain separate from owner receivables; discounts/adjustments retain their subtraction behavior; received amounts derive from the receipt ledger and outstanding balances stay clamped at zero. Maintenance expenses and reimbursements retain their established reporting rules. Approval/freezing remains distinct from receiving money. Existing financial-model and reporting tests exercise these boundaries and owner isolation, including receipts against retained historical records.

Reviewed session/Sanctum authentication, active-account middleware, role and record policies, protected download controllers, upload MIME/size checks, validation allowlists, notification destinations, encrypted token storage, environment scoping and audit logs. No confirmed P0 was found. The review does not constitute a penetration-test certification. No production credentials were edited, printed or sent to a simulator.

## API and Flutter compatibility

The local route inventory contains **60 API routes**. The initial read-only staging inventory contained **55**. Missing routes were favorites GET/POST, bearer-authenticated monthly-proof and maintenance-attachment downloads, and product-tour completion. Source review also found related staging resource gaps: API download URLs still pointed to session-only web routes; tenancy capability flags and the current user's tour timestamp were missing. Their dependencies were compared before release preparation. Existing request/response shapes and authorization are retained; additions bring staging up to the existing local contract. Public listings remain public while a supplied bearer token is resolved with active-account rules.

`RenterApiTest::token_authenticated_lookup_matches_the_complete_web_dropdown_without_rental_history` uses a real generated Sanctum token against isolated data. More than one API page is traversed and compared in order with the full web Assign Renter dropdown, including id/name/email/phone. Suspended renters and owners are excluded. Active renters with a contract can still be found for invitations, while existing acceptance rules prevent conflicting activation. The lookup returns basic assignment details, not other owners' tenancy histories or another user's tour timestamp. Unauthenticated access is 401, a renter's lookup is 403, and reading the other owner's tenancy is 403.

Local authenticated acceptance, staging route presence and live authenticated acceptance are distinct. No real staging login token was manufactured and no disposable user/tenancy/payment records were created there. Full authenticated Flutter acceptance on staging remains manual.

## Notification review and simulation evidence

Persistent events, observer after-commit/outbox recovery, recipient mapping, delivery ledger, per-device deduplication, bounded retries, invalid-token revocation, preferences, token binding, account switching, logout and protected click destinations are covered by the existing tests. Queue exceptions preserve committed business and notification rows. This pipeline was retained.

Used the existing local Firebase stand-in through the Postman API Simulation workflow. The real browser sender integration passed **16 assertions** and native sender integration **24 assertions**. The simulator recorded **18 actual HTTP requests**: two OAuth successes, two successful sends, two unregistered-token responses, six 503 and six 429 responses. Generated RSA credentials and isolated databases were used. Nothing was published and no real recipient was notified. This proves caller behavior against modeled responses, not Google IAM, browser permission or OS delivery.

A MySQL run revealed an existing test assumption that registrations are returned in insertion order. SQL did not guarantee that order. The partial-retry test now identifies the device actually sent on the first attempt and proves that it stays at one attempt while the retry reaches two, independent of query order. Sender behavior was unchanged.

## Verification commands and outcomes

Final command results and deployment evidence are recorded below. Baseline and interim failed reproductions are retained above to explain why changes were made; they are not final passes.

- PHP 8.5 and PHP 8.3: full isolated SQLite suites.
- Dedicated socket-only MySQL **26.7.0** at `/private/tmp/tenantism-audit-mysql-isolated-20261105`: full suite and coordinated competing PHP processes. This is the installed workstation version, not MySQL 8.4. CI adds 8.4 coverage when run remotely.
- `bin/check-mysql-concurrency.php` refuses ordinary `.env` databases. It permits only the specifically named temporary audit socket/database or the dedicated GitHub Actions service. It rebuilds only that isolated database, uses explicit lock/barrier coordination, asserts workers wait on locks, then checks committed balances/history.
- `npm run test:js`, `npm run build`, Composer validation, final registry audits, Pint and `git diff --check`.
- Browser: **47 local screenshots** across 320, 390, 768, 1024 and 1440px where applicable. Owner/renter dashboards and empty states, sidebar keyboard opening/Escape/focus restoration, exclusive menus, mobile notification popover bounds, reduced motion, representative owner/renter forms, reports, inbox, homepage/listings/login and the receipt form with its rendered UUID. A further **9 staging screenshots** cover the public homepage/listings/login after deployment. Automated checks cover overflow, broken images, field labels and validation. Representative screenshots were visually inspected. Brand and design were preserved; no redesign/dark mode was introduced.

Screenshots are local preview fixtures with a visible sample-data banner and delivery disabled:

- [Mobile homepage](../storage/app/private/audit-review-20261105/home-390.png)
- [Mobile owner dashboard](../storage/app/private/dashboard-review-20261105/owner-dashboard-390.png)
- [Desktop reports](../storage/app/private/dashboard-review-20261105/owner-owner-reports.png)
- [Notifications](../storage/app/private/dashboard-review-20261105/owner-notifications.png)

The screenshot directories are ignored review artifacts, not public app uploads. This is a targeted accessibility review, not a full WCAG certification or a complete screen-reader/contrast audit.

## Remaining verification and optional work

- **Browser/device acceptance:** granted/denied OS permission behavior, foreground/background real FCM receipt, alert click and automatic mark-as-read, multi-tab live delivery, account switching/logout and restart/re-subscription need interactive browser/device verification. Automated feature/JS and simulated-HTTP evidence does not replace this. Prior user-confirmed Chrome/macOS delivery is historical evidence, not a newly repeated audit test.
- **Android/iOS:** physical Android checks and iOS bundle/signing setup remain deferred as requested earlier. No physical-device success is claimed.
- **Authenticated staging/Flutter:** exercise complete owner/renter flows with authorized real test accounts after deployment. Do not use live user records as disposable fixtures.
- **A08/P3 images:** consistent crops/fallbacks and MIME/size/count validation are retained. CSS cropping still transfers original images. Read-only staging metadata reports 57 images totaling 6,234,930 bytes (about 109 KB average), with a largest image of 1,892,045 bytes. Responsive derivatives are most valuable for those larger originals; a blanket backfill is lower priority at the current average. Adoption should include bandwidth/visual measurements, backfill/storage policy and quality review. No existing upload is replaced and no unmeasured derivative architecture is introduced.
- Frontend build reports an optional `fontaine` optimized-font-fallback warning. It succeeds and generated Instrument Sans assets render. No font/brand change was made to silence an optional optimization notice.
- New CI configuration needs its first GitHub run. The local MySQL version differs from the CI service and not every possible concurrent interleaving can be established by the exercised scenarios.
- Production remains outside scope; release approval should include these manual gaps and operational backup/restore verification.

## Safe local review

The workstation `.env` points toward staging. Use `php bin/local-ui-preview.php serve`, then open `http://127.0.0.1:8000`. The dedicated preview uses local fixtures/SQLite with email and push disabled. Do not run ordinary destructive Artisan commands against the workstation environment. Existing preview data was preserved. The new `php bin/local-ui-preview.php migrate` command verifies the dedicated preview path and applies pending migrations without resetting fixtures; it was used successfully for the receipt column. For a fresh checkout, run `php bin/local-ui-preview.php prepare` first, then `migrate` and `serve`.

Safe repeat checks: `php artisan test --compact`, `npm run test:js`, `npm run build`, `git diff --check`. The test runner sets isolated SQLite and disables real push. Do not invoke the MySQL checker without its explicit dedicated instance variables.

## Primary references

- [Symfony security advisories](https://github.com/symfony/symfony/security/advisories/GHSA-3h27-hjv7-fqjx), [Vite advisory](https://github.com/vitejs/vite/security/advisories/GHSA-rq7h-c2jc-7f22): dependency findings were established with the installed lockfile audits; these sources describe the relevant upstream advisories, not a demonstrated application exploit.
- [OWASP CSV injection](https://community.owasp.org/attacks/CSV_Injection): formula prefixes and spreadsheet re-save caveats.
- [Checkout releases](https://github.com/actions/checkout/releases), [Node setup](https://github.com/actions/setup-node/blob/main/README.md), [PHP setup](https://github.com/shivammathur/setup-php): non-deploying CI configuration.

## Final finding disposition

| Finding | Severity | Disposition |
| --- | --- | --- |
| A01 stale monthly state | P1 | Fixed; regression and MySQL suite passed |
| A02 stale contract lifecycle | P1 | Fixed; closure/request concurrency passed |
| A03 private evidence races/rollback | P1 | Fixed; frozen evidence and rollback regressions passed |
| A04 receipts/aggregate/retries | P1 | Fixed; optional retry contract, simultaneous keyed receipts passed |
| A05 spreadsheet formulas | P2 | Fixed; export regression passed |
| A06 missing CI | P2 | Non-deploying workflow added; first GitHub execution pending |
| A07 API acceptance/deployment gaps | P2 | Routes/resources deployed; local token parity passed; authenticated live acceptance remains manual |
| A08 original image transfer sizes | P3 | Measured and documented; derivatives optional |
| A09 advisory/declared PHP mismatch | P1 | Compatible patched lockfiles deployed; PHP 8.3 and registry checks passed |
| A10 stale primary/file deletion | P1 | Fixed; promotion and rollback regression passed |
| A11 stale monthly period/snapshot | P1 | Fixed; post-closure period regression passed |
| A12 competing acceptance deadlock | P1 | Reproduced on MySQL, fixed; one-renter/one-property concurrency passed |

No confirmed P0 or unfixed confirmed P1 remains in the investigated findings. The manual acceptance boundaries below still apply.

Changed regression sources: [stale-state/receipts/evidence](../tests/Feature/StabilizationTest.php), [renter parity](../tests/Feature/Api/RenterApiTest.php), [gallery](../tests/Feature/Owner/PropertyImageTest.php), [CSV](../tests/Feature/Owner/ReportTest.php), [MySQL concurrency](../bin/check-mysql-concurrency.php). Runtime changes are in the corresponding existing [monthly actions](../app/Actions/MonthlyRecords), [tenancy actions](../app/Actions/Tenancies), [proof service](../app/Services/MonthlyProofService.php), [image service](../app/Services/PropertyImageService.php), [report controller](../app/Http/Controllers/Owner/ReportController.php), and [additive migration](../database/migrations/2026_11_05_000001_add_receipt_idempotency_key.php). [CI](../.github/workflows/checks.yml) and the [safe preview](../bin/local-ui-preview.php) are local development tooling.

## Final results

| Check | Actual result |
| --- | --- |
| PHP 8.5.10 + SQLite | **427 passed / 2,300 assertions** |
| PHP 8.3.33 + SQLite | **427 passed / 2,300 assertions** |
| Isolated MySQL 26.7.0 + PHP 8.5 | **427 passed / 2,300 assertions** |
| Coordinated MySQL checker | **4 scenarios passed, 7 worker processes**: duplicate receipt retries against updated financials; request vs closure; same-renter invitations; same-property invitations |
| Real HTTP Firebase stand-in | **2 targeted integration methods passed / 40 assertions**, 18 logged member requests |
| JavaScript | **34 passed** |
| Production frontend build | **Passed**, optional font fallback warning only |
| Composer strict validation / Pint / diff whitespace | **Passed** |
| Composer audit / npm audit | **Zero known advisories** at verification |
| Browser review | **47 local screenshots + 9 staging screenshots**, automated responsive/label/image checks passed |
| Staging HTTP smoke | **12/12 expected statuses**, including 200 public/health and 401 protected endpoints |
| API route parity | **60 local / 60 staging**, exact method, URI, action and middleware comparison; no missing or extra routes |

Fifteen additional Laravel tests were added relative to the 412-test baseline: 12 stabilization cases plus renter lookup parity, gallery regression and CSV regression. Two existing test assertions were made database-portable (decimal SUM representation and unspecified registration ordering). The explicit MySQL checker is separately opt-in and included in CI; it is not counted as a PHPUnit test.

## Staging release and readiness

Deployed **45 application/asset files, 14 matching web-root assets and 6,442 production dependency files**. Verified every installed file against the release manifest. The only schema change was `2026_11_05_000001_add_receipt_idempotency_key`, a nullable column with a scoped unique index. No reset/seed, destructive migration or user-content replacement was used. Maintenance mode was removed successfully. Config/routes/views/package metadata were rebuilt in fresh PHP processes.

Verified preserved hashes for `.env`, Firebase credentials, both Hostinger/Laravel entrypoints, rewrite files and Firebase service workers; both storage links retained their targets. Existing media was untouched. The scheduler uses the existing lock and configuration. The subsequent heartbeat at 16:33 UTC completed successfully, with zero queued push jobs and zero failed push jobs. No live notification test was triggered by this audit.

Private backup: `/home/u577461158/domains/staging.tenantism.com/rentra-staging/storage/app/backups/pre-audit-stabilization-20261105`. It contains the verified compressed database snapshot, replaced code/assets/cache metadata and the previous vendor tree. The release helper also includes code/vendor rollback handling; a failed deployment would retain the harmless nullable schema addition. Restore drills and destructive DB rollback were not performed on staging.

The deployed artifact is the validated working tree based on `f009cce`, not a new Git commit. Application source changes, tests, CI and this report remain locally reviewable on `main`. Pre-existing project documentation and `.DS_Store` changes were retained. No Git push/merge or production deployment occurred.

**Readiness:** the tested audit fixes are installed and verified on staging. Production sign-off still needs the interactive browser/device and authenticated staging/Flutter acceptance listed above, plus the first remote CI run. Passing automated checks is not a claim that those manual scenarios have been completed.

Local evidence logs/manifests are under `/private/tmp/tenantism-audit-*`. The Postman CLI was 1.74.0; `postman status` is unavailable in that version, as documented in the existing simulation context. No cloud publication or Postman workspace mutation was performed. Screenshots are under the ignored `storage/app/private/audit-review-20261105`, `dashboard-review-20261105` and `audit-staging-review-20261105` directories. These temporary artifacts supplement this Markdown report; they are not required at runtime.
