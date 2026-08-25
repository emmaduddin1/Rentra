<?php

namespace App\Actions\Tenancies;

use App\Models\Tenancy;
use App\Services\AuditLogger;
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
            $tenancy->fill($data);
            $tenancy->save();

            if ($tenancy->wasChanged()) {
                $this->auditLogger->recordChanges('tenancy.updated', $tenancy);
            }

            return $tenancy;
        });
    }
}
