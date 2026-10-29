# Tenantism financial API contract

Laravel is the source of truth for all financial calculations. Web and mobile clients must display the returned decimal strings and must not recompute owner receivables by summing every recorded bill.

## Monthly records

`GET /api/v1/monthly-records/{id}` exposes:

- `rent_due`, `rent_received`
- `utility_arrangements`: one entry per utility with `value`, `label`, and `amount`
- `utilities_payable_to_owner`
- `direct_utility_bills` (tracking only; excluded from owner income)
- `unclassified_utility_bills` (legacy data requiring review; excluded)
- `owner_payable_charges`, `discounts`, `adjustments`
- `maintenance_expenses` (tracking only)
- `reimbursements_to_owner`
- `total_receivable`, `total_received`, `outstanding_receivable`
- `status` for submission/review and `payment_status` for payment state
- `payments`, the immutable partial-payment history

All money values are decimal strings. `total_amount` remains temporarily for compatibility, equals `total_receivable`, and is marked by `total_amount_deprecated: true`.

Utility arrangement values are `provider_direct`, `owner`, `included_in_rent`, `not_applicable`, and `unclassified`. Direct, included, not-applicable, and unclassified amounts never increase owner receivables.

Record an owner receipt with `POST /api/v1/monthly-records/{id}/payments`:

```json
{
  "amount": "40000.00",
  "received_on": "2026-10-26",
  "reference": "BANK-123",
  "notes": "First installment"
}
```

## Property and tenancy arrangements

Property and tenancy resources expose `utility_arrangements`. Property values are defaults. A new tenancy copies the reviewed values into its contractual snapshot. A later tenancy change requires `utility_arrangements_effective_from` plus `change_reason`; existing monthly records are not rewritten.

## Maintenance

Maintenance resources expose `amount`, `paid_by`, `responsible_party`, `reimbursement_required`, `reimbursement_recipient`, `reimbursement_amount`, `reimbursement_status`, and `expense_notes`. Owners can correct these using `PATCH /api/v1/maintenance-requests/{id}/financials`.

Maintenance is never a utility. Only a reimbursement owed to the owner can be entered in a monthly record's `reimbursements_to_owner` and included in that record's receivable.
