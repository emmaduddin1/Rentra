<?php

namespace App\Actions\Tenancies;

use App\Enums\TenancyStatus;
use App\Enums\TerminationRequestStatus;
use App\Models\Tenancy;
use App\Models\TenancyTerminationRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestTenancyTermination
{
    public function __construct(private Notifier $notifier, private AuditLogger $auditLogger) {}

    public function handle(Tenancy $tenancy, User $actor, array $data): TenancyTerminationRequest
    {
        if (! $actor->isRenter() || $tenancy->renter_id !== $actor->id) {
            throw ValidationException::withMessages(['status' => __('Only the renter can request contract cancellation.')]);
        }

        if (! $tenancy->isActive()) {
            throw ValidationException::withMessages(['status' => __('Only an active tenancy can be terminated.')]);
        }

        return DB::transaction(function () use ($tenancy, $actor, $data) {
            $locked = Tenancy::whereKey($tenancy->id)->lockForUpdate()->firstOrFail();
            if ($locked->terminationRequests()->whereIn('status', [TerminationRequestStatus::Pending->value, TerminationRequestStatus::Countered->value])->exists()) {
                throw ValidationException::withMessages(['status' => __('A termination request is already awaiting a response.')]);
            }

            $request = $locked->terminationRequests()->create([
                'requested_by' => $actor->id,
                'proposed_end_date' => $data['proposed_end_date'],
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'status' => TerminationRequestStatus::Pending,
            ]);
            $previous = $locked->status;
            $locked->forceFill(['status' => TenancyStatus::TerminationRequested, 'status_reason' => $data['reason']])->save();
            $locked->recordStatusChange($previous, $actor, 'termination_requested', ['request_id' => $request->id]);

            $recipient = $actor->id === $locked->renter_id ? $locked->property->owner : $locked->renter;
            $url = $recipient->isOwner() ? route('owner.tenancies.show', $locked) : route('renter.tenancies.show', $locked);
            $this->notifier->send($recipient, 'tenancy.termination_requested', __('Tenancy termination requested'), $data['reason'], $url, ['tenancy_id' => $locked->id, 'termination_request_id' => $request->id], 'tenancy.termination:'.$request->id);
            $this->auditLogger->record('tenancy.termination_requested', $locked, ['status' => $previous->value], ['status' => TenancyStatus::TerminationRequested->value, 'proposed_end_date' => $data['proposed_end_date']], $data['reason'], $actor);

            return $request;
        });
    }
}
