<?php

namespace App\Actions\Maintenance;

use App\Models\MaintenanceRequest;
use App\Models\MaintenanceStatusHistory;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;

/**
 * SRS FR-MNT-004 - owner and renter share one chronological timeline of
 * comments and status changes.
 *
 * A comment is a history row whose status does not move, so the timeline stays
 * a single ordered list instead of two lists the UI has to merge.
 */
class AddMaintenanceComment
{
    public function __construct(private readonly Notifier $notifier) {}

    public function handle(MaintenanceRequest $request, User $author, string $comment): MaintenanceStatusHistory
    {
        return DB::transaction(function () use ($request, $author, $comment) {
            $entry = $request->statusHistory()->create([
                'from_status' => $request->status,
                'to_status' => $request->status,
                'changed_by' => $author->id,
                'comment' => $comment,
                'changed_at' => now(),
            ]);

            $owner = $request->tenancy->property->owner;
            $recipient = $author->is($owner) ? $request->tenancy->renter : $owner;

            $this->notifier->send(
                recipient: $recipient,
                type: 'maintenance.commented',
                title: __('New comment on :title', ['title' => $request->title]),
                body: $comment,
                actionUrl: $recipient->isOwner()
                    ? route('owner.maintenance.show', $request)
                    : route('renter.maintenance.show', $request),
                data: ['maintenance_request_id' => $request->id],
                idempotencyKey: 'maintenance.comment:'.$entry->id,
            );

            $request->unsetRelation('statusHistory');

            return $entry;
        });
    }
}
