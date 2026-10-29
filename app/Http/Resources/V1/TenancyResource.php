<?php

namespace App\Http\Resources\V1;

use App\Models\Tenancy;
use App\Support\UtilityArrangements;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/**
 * @mixin Tenancy
 */
class TenancyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'agreed_rent' => (string) $this->agreed_rent,
            'deposit' => (string) $this->deposit,
            'billing_day' => $this->billing_day,
            'utility_arrangements_effective_from' => $this->utility_arrangements_effective_from?->toDateString(),
            'utility_arrangements' => collect(UtilityArrangements::utilities())->mapWithKeys(fn (string $utility) => [
                $utility => [
                    'value' => $this->{"{$utility}_arrangement"}->value,
                    'label' => $this->{"{$utility}_arrangement"}->label(),
                ],
            ]),
            'notes' => $this->notes,
            'ended_at' => $this->ended_at?->toIso8601String(),
            'end_reason' => $this->end_reason,
            'invitation_expires_at' => $this->invitation_expires_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'activated_at' => $this->activated_at?->toIso8601String(),
            'status_reason' => $this->status_reason,
            'has_unresolved_conflict' => DB::table('tenancy_conflicts')->whereNull('resolved_at')
                ->where(fn ($query) => $query->where(fn ($q) => $q->where('conflict_type', 'renter')->where('conflict_key', $this->renter_id))
                    ->orWhere(fn ($q) => $q->where('conflict_type', 'property')->where('conflict_key', $this->property_id)))
                ->exists(),
            'can_accept' => $request->user()?->can('acceptInvitation', $this->resource) ?? false,
            'can_reject' => $request->user()?->can('rejectInvitation', $this->resource) ?? false,
            'can_withdraw' => $request->user()?->can('withdrawInvitation', $this->resource) ?? false,
            'can_end' => $request->user()?->can('end', $this->resource) ?? false,
            'can_update' => $request->user()?->can('update', $this->resource) ?? false,
            'can_request_termination' => $request->user()?->can('requestTermination', $this->resource) ?? false,
            'property' => new PropertyResource($this->whenLoaded('property')),
            'renter' => new UserResource($this->whenLoaded('renter')),
            'monthly_records_count' => $this->whenCounted('monthlyRecords'),
            'status_history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($entry) => [
                'previous_status' => $entry->previous_status?->value,
                'new_status' => $entry->new_status->value,
                'changed_by' => $entry->changer?->name,
                'reason' => $entry->reason,
                'metadata' => $entry->metadata,
                'changed_at' => $entry->changed_at->toIso8601String(),
            ])),
            'termination_requests' => $this->whenLoaded('terminationRequests', fn () => $this->terminationRequests->map(fn ($item) => [
                'id' => $item->id, 'status' => $item->status->value,
                'proposed_end_date' => $item->proposed_end_date->toDateString(),
                'counter_end_date' => $item->counter_end_date?->toDateString(),
                'reason' => $item->reason, 'notes' => $item->notes,
                'requested_by' => $item->requester?->name, 'responded_by' => $item->responder?->name,
                'response_notes' => $item->response_notes,
                'can_respond' => $request->user()?->can('respondTermination', [Tenancy::class, $item]) ?? false,
            ])),
        ];
    }
}
