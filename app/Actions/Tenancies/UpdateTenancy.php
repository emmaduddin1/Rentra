<?php

namespace App\Actions\Tenancies;

use App\Models\Tenancy;
use App\Services\AuditLogger;
use App\Support\UtilityArrangements;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SRS FR-TEN-002 - the agreed terms of a tenancy stay editable by its owner.
 *
 * The property and the renter are deliberately not editable: reassigning either
 * would rewrite history, so the tenancy is ended and a new one created instead.
 */
class UpdateTenancy
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Tenancy $tenancy, array $data): Tenancy
    {
        return DB::transaction(function () use ($tenancy, $data) {
            $effectiveFrom = $data['utility_arrangements_effective_from'] ?? null;
            $changeReason = $data['change_reason'] ?? null;
            unset($data['change_reason']);

            if ($effectiveFrom) {
                $arrangements = array_intersect_key($data, array_flip(UtilityArrangements::columns()));
                $tenancy->utilityArrangementHistory()->updateOrCreate(
                    ['effective_from' => $effectiveFrom],
                    [...$arrangements, 'changed_by' => auth()->id(), 'change_reason' => $changeReason],
                );

                // Future contractual changes belong in history until their
                // effective month. They must not rewrite the current snapshot.
                if (Carbon::parse($effectiveFrom)->isFuture()) {
                    foreach (UtilityArrangements::columns() as $column) {
                        unset($data[$column]);
                    }
                    unset($data['utility_arrangements_effective_from']);
                }
            } else {
                // An arrangement change is never implicit: the owner must give
                // an effective date so prior monthly records remain explainable.
                foreach (UtilityArrangements::columns() as $column) {
                    unset($data[$column]);
                }
                unset($data['utility_arrangements_effective_from']);
            }

            $tenancy->fill($data);
            $tenancy->save();

            if ($tenancy->wasChanged()) {
                $this->auditLogger->recordChanges('tenancy.updated', $tenancy);
            }

            return $tenancy;
        });
    }
}
