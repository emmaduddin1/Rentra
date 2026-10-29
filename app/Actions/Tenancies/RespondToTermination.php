<?php

namespace App\Actions\Tenancies;

use App\Enums\PropertyStatus;
use App\Enums\TenancyStatus;
use App\Enums\TerminationRequestStatus;
use App\Models\TenancyTerminationRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RespondToTermination
{
    public function __construct(private EndTenancy $endTenancy, private Notifier $notifier, private AuditLogger $auditLogger) {}

    public function handle(TenancyTerminationRequest $request, User $actor, array $data): TenancyTerminationRequest
    {
        return DB::transaction(function () use ($request, $actor, $data) {
            $locked = TenancyTerminationRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $tenancy = $locked->tenancy()->lockForUpdate()->firstOrFail();
            if ($actor->id !== $tenancy->ownerId()
                || $locked->requested_by !== $tenancy->renter_id
                || ! in_array($locked->status, [TerminationRequestStatus::Pending, TerminationRequestStatus::Countered], true)) {
                throw ValidationException::withMessages(['status' => __('You cannot respond to this termination request.')]);
            }

            $decision = $data['decision'];
            if ($decision === 'dispute') {
                $locked->forceFill([
                    'status' => TerminationRequestStatus::Disputed,
                    'responded_by' => $actor->id, 'response_notes' => $data['response_notes'], 'responded_at' => now(),
                ])->save();
                $previous = $tenancy->status;
                $tenancy->forceFill(['status' => TenancyStatus::Active, 'status_reason' => 'termination_request_declined'])->save();
                $tenancy->recordStatusChange($previous, $actor, 'termination_request_declined', ['termination_request_id' => $locked->id]);
            } else {
                $endDate = $decision === 'counter' ? Carbon::parse($data['counter_end_date']) : $locked->proposed_end_date;
                $locked->forceFill([
                    'status' => TerminationRequestStatus::Accepted,
                    'counter_end_date' => $decision === 'counter' ? $endDate : null,
                    'responded_by' => $actor->id,
                    'response_notes' => $data['response_notes'] ?? null,
                    'responded_at' => now(),
                ])->save();
                if ($endDate->isFuture()) {
                    $previous = $tenancy->status;
                    $tenancy->forceFill(['status' => TenancyStatus::NoticePeriod, 'end_date' => $endDate, 'status_reason' => 'termination_date_accepted'])->save();
                    $tenancy->recordStatusChange($previous, $actor, 'termination_date_accepted', ['termination_request_id' => $locked->id]);
                } else {
                    $this->endTenancy->handle($tenancy, $actor, $endDate, PropertyStatus::Available, $locked->reason);
                }
            }

            $recipient = $tenancy->renter;
            $url = route('renter.tenancies.show', $tenancy);
            $this->notifier->send($recipient, 'tenancy.termination_'.$decision, __('Termination request updated'), $data['response_notes'] ?? null, $url, ['tenancy_id' => $tenancy->id, 'termination_request_id' => $locked->id], 'tenancy.termination.'.$decision.':'.$locked->id.':'.$actor->id);
            $this->auditLogger->record('tenancy.termination_'.$decision, $tenancy, null, ['termination_request_id' => $locked->id, 'decision' => $decision], $data['response_notes'] ?? null, $actor);

            return $locked;
        });
    }
}
