<?php

namespace App\Actions\Tenancies;

use App\Enums\PropertyStatus;
use App\Enums\TenancyStatus;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS 5.1 step 6 / FR-TEN-001 - assign a renter to a property.
 *
 * BR-002 is enforced here inside a transaction with a row lock, because a
 * partial unique index on "one active tenancy per property" is not portable
 * between MySQL and the SQLite test database (Phase 1 decision 6).
 */
class AssignRenter
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{start_date: string, end_date?: string|null, agreed_rent: string|float, deposit?: string|float|null, billing_day?: int|null, notes?: string|null}  $data
     */
    public function handle(User $owner, Property $property, User $renter, array $data): Tenancy
    {
        if (! $renter->isRenter()) {
            throw ValidationException::withMessages([
                'renter_id' => __('That account is not a renter account.'),
            ]);
        }

        return DB::transaction(function () use ($owner, $property, $renter, $data) {
            // NFR-REL-002 - lock the property so two concurrent assignments
            // cannot both pass the BR-002 check.
            $locked = Property::whereKey($property->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->activeTenancy()->exists()) {
                throw ValidationException::withMessages([
                    'property_id' => __('This property already has an active tenancy.'),
                ]);
            }

            $tenancy = Tenancy::create([
                'property_id' => $locked->id,
                'renter_id' => $renter->id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'agreed_rent' => $data['agreed_rent'],
                'deposit' => $data['deposit'] ?? 0,
                'billing_day' => $data['billing_day'] ?? 1,
                'notes' => $data['notes'] ?? null,
                'status' => TenancyStatus::Active,
            ]);

            // 5.1 step 6 - assigning a renter marks the property occupied.
            $locked->status = PropertyStatus::Occupied;
            $locked->save();

            $property->setRawAttributes($locked->getAttributes(), sync: true);

            $this->notifier->send(
                recipient: $renter,
                type: 'tenancy.assigned',
                title: __('You have been assigned to :property', ['property' => $locked->title]),
                body: __('Your tenancy starts on :date.', ['date' => $tenancy->start_date->format('d M Y')]),
                actionUrl: route('renter.tenancies.show', $tenancy),
                data: ['tenancy_id' => $tenancy->id, 'property_id' => $locked->id],
                idempotencyKey: 'tenancy.assigned:'.$tenancy->id,
            );

            // BR-008 - assignment is an audited action.
            $this->auditLogger->record(
                action: 'tenancy.assigned',
                subject: $tenancy,
                newValues: [
                    'property_id' => $locked->id,
                    'renter_id' => $renter->id,
                    'start_date' => (string) $tenancy->start_date->toDateString(),
                    'agreed_rent' => (string) $tenancy->agreed_rent,
                ],
                actor: $owner,
            );

            return $tenancy;
        });
    }
}
