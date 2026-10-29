<?php

namespace App\Actions\Tenancies;

use App\Enums\PropertyStatus;
use App\Enums\PublicationStatus;
use App\Enums\TenancyStatus;
use App\Enums\TerminationRequestStatus;
use App\Models\Tenancy;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS FR-TEN-005 - ending a tenancy frees the property, and the owner chooses
 * whether it becomes Available again or goes Inactive.
 *
 * FR-TEN-004 - the tenancy row and everything hanging off it (monthly records,
 * maintenance) stays, so history survives the move-out.
 */
class EndTenancy
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(
        Tenancy $tenancy,
        ?User $actor,
        Carbon|string $endDate,
        PropertyStatus $propertyStatus = PropertyStatus::Available,
        ?string $reason = null,
    ): Tenancy {
        if (! $tenancy->isActive()) {
            throw ValidationException::withMessages([
                'status' => __('This tenancy has already ended.'),
            ]);
        }

        $endDate = $endDate instanceof Carbon ? $endDate : Carbon::parse($endDate);

        // A contract can already be marked active/notice-period even though its
        // agreed start date is still ahead. Closing it now is a cancellation,
        // not an impossible end date before the start date.
        $finalStatus = $endDate->lt($tenancy->start_date)
            ? TenancyStatus::Cancelled
            : TenancyStatus::Ended;

        if ($propertyStatus === PropertyStatus::Occupied) {
            throw ValidationException::withMessages([
                'property_status' => __('A property cannot stay occupied once its tenancy ends.'),
            ]);
        }

        return DB::transaction(function () use ($tenancy, $actor, $endDate, $propertyStatus, $reason, $finalStatus) {
            $tenancy->terminationRequests()
                ->whereIn('status', [TerminationRequestStatus::Pending->value, TerminationRequestStatus::Countered->value])
                ->update([
                    'status' => TerminationRequestStatus::Accepted->value,
                    'responded_by' => $actor?->id,
                    'response_notes' => $reason,
                    'responded_at' => now(),
                    'updated_at' => now(),
                ]);

            $previous = $tenancy->status;
            $tenancy->fill([
                'status' => $finalStatus,
                'end_date' => $finalStatus === TenancyStatus::Ended ? $endDate->toDateString() : null,
                'ended_at' => now(),
                'end_reason' => $reason,
            ])->save();

            DB::table('tenancy_active_locks')->where('tenancy_id', $tenancy->id)->delete();
            $this->resolveSatisfiedConflicts($tenancy);
            $statusReason = $finalStatus === TenancyStatus::Cancelled ? 'contract_cancelled_before_start' : 'tenancy_ended';
            $tenancy->recordStatusChange($previous, $actor, $reason ?: $statusReason);

            $property = $tenancy->property;
            $hasAnotherActiveTenancy = $property->activeTenancy()->exists();
            $property->status = $hasAnotherActiveTenancy ? PropertyStatus::Occupied : $propertyStatus;
            if (! $hasAnotherActiveTenancy) {
                // Ending a contract must not silently put the old listing back
                // in the public feed. The owner explicitly chooses what is next.
                $property->publication_status = PublicationStatus::Draft;
                $property->published_at = null;
            }
            $property->save();

            $this->notifier->send(
                recipient: $tenancy->renter,
                type: 'tenancy.'.$finalStatus->value,
                title: $finalStatus === TenancyStatus::Cancelled
                    ? __('Your contract at :property was cancelled', ['property' => $property->title])
                    : __('Your tenancy at :property has ended', ['property' => $property->title]),
                body: $reason,
                actionUrl: route('renter.tenancies.show', $tenancy),
                data: ['tenancy_id' => $tenancy->id],
                idempotencyKey: 'tenancy.'.$finalStatus->value.':'.$tenancy->id,
            );

            if ($actor === null || $actor->id !== $property->owner_id) {
                $this->notifier->send(
                    recipient: $property->owner,
                    type: 'tenancy.'.$finalStatus->value,
                    title: $finalStatus === TenancyStatus::Cancelled
                        ? __('Contract cancelled at :property', ['property' => $property->title])
                        : __('Tenancy ended at :property', ['property' => $property->title]),
                    body: $reason,
                    actionUrl: route('owner.tenancies.show', $tenancy),
                    data: ['tenancy_id' => $tenancy->id],
                    idempotencyKey: 'tenancy.'.$finalStatus->value.'.owner:'.$tenancy->id,
                );
            }

            $this->auditLogger->record(
                action: 'tenancy.'.$finalStatus->value,
                subject: $tenancy,
                oldValues: ['status' => $previous->value],
                newValues: [
                    'status' => $finalStatus->value,
                    'end_date' => $finalStatus === TenancyStatus::Ended ? $endDate->toDateString() : null,
                    'property_status' => $propertyStatus->value,
                    'publication_status' => $property->publication_status->value,
                ],
                reason: $reason,
                actor: $actor,
            );

            return $tenancy;
        });
    }

    /**
     * An explicit contract closure can safely resolve an imported duplicate:
     * the owner chose which record to close, so the sole remaining active row
     * is deterministic rather than selected by the system.
     */
    private function resolveSatisfiedConflicts(Tenancy $closedTenancy): void
    {
        $conflicts = DB::table('tenancy_conflicts')
            ->whereNull('resolved_at')
            ->where(function ($query) use ($closedTenancy) {
                $query->where(fn ($item) => $item->where('conflict_type', 'renter')->where('conflict_key', $closedTenancy->renter_id))
                    ->orWhere(fn ($item) => $item->where('conflict_type', 'property')->where('conflict_key', $closedTenancy->property_id));
            })
            ->lockForUpdate()
            ->get();

        foreach ($conflicts as $conflict) {
            $tenancyIds = json_decode($conflict->tenancy_ids, true, flags: JSON_THROW_ON_ERROR);
            $remaining = Tenancy::query()->active()->whereIn('id', $tenancyIds)->lockForUpdate()->get();

            if ($remaining->count() > 1) {
                continue;
            }

            if ($valid = $remaining->first()) {
                DB::table('tenancy_active_locks')->insertOrIgnore([
                    'tenancy_id' => $valid->id,
                    'renter_id' => $valid->renter_id,
                    'property_id' => $valid->property_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('tenancy_conflicts')->where('id', $conflict->id)->update([
                'resolved_at' => now(),
                'resolution_reason' => 'Resolved when tenancy '.$closedTenancy->id.' was explicitly closed by its owner.',
                'updated_at' => now(),
            ]);
        }
    }
}
