# Tenantism tenancy lifecycle API

The backend is the source of truth for invitation and termination eligibility. Mobile clients must render the returned status and `can_*` flags instead of activating or ending tenancies locally.

## Statuses

`invited`, `accepted`, `active`, `rejected`, `withdrawn`, `expired`, `termination_requested`, `notice_period`, `ended`, and `cancelled`.

Only `active`, `termination_requested`, and `notice_period` occupy a property and permit financial activity. An `invited` tenancy is not an occupied property.

## Endpoints

- `POST /api/v1/tenancies` — owner sends an invitation.
- `GET /api/v1/tenancies` — role-scoped invitations, active tenancy, and history.
- `GET /api/v1/tenancies-active`, `/api/v1/tenancy-invitations`, and `/api/v1/tenancy-history` — dedicated role-scoped lists for mobile screens.
- `GET /api/v1/tenancies/{id}` — terms, eligibility flags, status history, and termination requests.
- `POST /api/v1/tenancies/{id}/accept` — invited renter accepts; race-safe server checks run inside a transaction.
- `POST /api/v1/tenancies/{id}/reject` — invited renter rejects.
- `POST /api/v1/tenancies/{id}/withdraw` — owner withdraws a pending invitation.
- `POST /api/v1/tenancies/{id}/termination-requests` — the renter requests cancellation. Body: `proposed_end_date`, `reason`, optional `notes`, and `confirmed: true`.
- `POST /api/v1/termination-requests/{id}/respond` — the owner sends `decision: accept|counter|dispute`. `counter` is the owner's final alternative end date; `dispute` declines the request.
- `POST /api/v1/tenancies/{id}/end` — the owner ends the contract today. Body: `reason`, `next_step: publish|assign`, and `confirmed: true`. `publish` republishes the available property; `assign` leaves it as a draft ready for a new invitation.

Acceptance automatically expires competing invitations with reason `renter_activated_another_tenancy`. A notice-period tenancy stays occupied and financially active through its accepted end date. Historical records and outstanding balances are preserved after ending.

Existing duplicate active data is listed with `php artisan tenancies:conflicts` and repaired by an authorized server administrator with `php artisan tenancies:reconcile {validTenancyId} --reason="..."`. Reconciliation never deletes tenancy or financial history.
