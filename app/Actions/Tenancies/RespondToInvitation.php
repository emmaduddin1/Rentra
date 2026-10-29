<?php

namespace App\Actions\Tenancies;

use App\Enums\PropertyStatus;
use App\Enums\TenancyStatus;
use App\Models\Tenancy;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RespondToInvitation
{
    public function __construct(private Notifier $notifier, private AuditLogger $auditLogger) {}

    public function accept(Tenancy $invitation, User $renter): Tenancy
    {
        return DB::transaction(function () use ($invitation, $renter) {
            User::whereKey($renter->id)->lockForUpdate()->firstOrFail();
            $tenancy = Tenancy::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            $property = $tenancy->property()->lockForUpdate()->firstOrFail();

            if ($tenancy->renter_id !== $renter->id || ! $tenancy->isInvitationPending()) {
                throw ValidationException::withMessages(['status' => __('This invitation is no longer available.')]);
            }
            if ($property->activeTenancy()->exists()) {
                throw ValidationException::withMessages(['property' => __('This property is no longer available.')]);
            }
            if (Tenancy::forRenter($renter)->active()->exists()
                || DB::table('tenancy_conflicts')->where('conflict_type', 'renter')->where('conflict_key', $renter->id)->whereNull('resolved_at')->exists()) {
                throw ValidationException::withMessages(['renter' => __('You already have an active tenancy. End your current tenancy before accepting another property.')]);
            }

            try {
                DB::table('tenancy_active_locks')->insert([
                    'tenancy_id' => $tenancy->id, 'renter_id' => $renter->id, 'property_id' => $property->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            } catch (QueryException) {
                throw ValidationException::withMessages(['status' => __('You or this property already has an active tenancy.')]);
            }

            $previous = $tenancy->status;
            $tenancy->forceFill([
                'status' => TenancyStatus::Active,
                'accepted_at' => now(), 'activated_at' => now(), 'status_reason' => 'renter_accepted',
            ])->save();
            DB::table('tenancy_invitation_locks')->where('tenancy_id', $tenancy->id)->delete();
            $property->forceFill(['status' => PropertyStatus::Occupied])->save();
            $tenancy->recordStatusChange($previous, $renter, 'renter_accepted');

            $competing = Tenancy::query()->where('status', TenancyStatus::Invited->value)
                ->whereKeyNot($tenancy->id)
                ->where(fn ($query) => $query->where('renter_id', $renter->id)->orWhere('property_id', $property->id))
                ->lockForUpdate()->get();
            foreach ($competing as $other) {
                $old = $other->status;
                $other->forceFill(['status' => TenancyStatus::Expired, 'status_reason' => 'renter_activated_another_tenancy'])->save();
                DB::table('tenancy_invitation_locks')->where('tenancy_id', $other->id)->delete();
                $other->recordStatusChange($old, $renter, 'renter_activated_another_tenancy');
                $this->notifier->send($other->property->owner, 'tenancy.invitation_expired', __('Tenancy invitation closed'), __('The renter is no longer available.'), route('owner.tenancies.show', $other), ['tenancy_id' => $other->id], 'tenancy.competitor:'.$other->id);
            }

            $this->notifier->send($property->owner, 'tenancy.accepted', __('Tenancy invitation accepted'), __(':renter accepted the tenancy for :property.', ['renter' => $renter->name, 'property' => $property->title]), route('owner.tenancies.show', $tenancy), ['tenancy_id' => $tenancy->id], 'tenancy.accepted:'.$tenancy->id);
            $this->auditLogger->record('tenancy.accepted', $tenancy, ['status' => $previous->value], ['status' => TenancyStatus::Active->value], actor: $renter);

            return $tenancy;
        });
    }

    public function reject(Tenancy $invitation, User $renter, ?string $reason = null): Tenancy
    {
        return $this->close($invitation, $renter, TenancyStatus::Rejected, $reason ?: 'renter_rejected');
    }

    public function withdraw(Tenancy $invitation, User $owner, ?string $reason = null): Tenancy
    {
        return $this->close($invitation, $owner, TenancyStatus::Withdrawn, $reason ?: 'owner_withdrew');
    }

    private function close(Tenancy $invitation, User $actor, TenancyStatus $status, string $reason): Tenancy
    {
        return DB::transaction(function () use ($invitation, $actor, $status, $reason) {
            $tenancy = Tenancy::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if ($tenancy->status !== TenancyStatus::Invited) {
                throw ValidationException::withMessages(['status' => __('This invitation is no longer pending.')]);
            }
            $previous = $tenancy->status;
            $tenancy->forceFill(['status' => $status, 'status_reason' => $reason])->save();
            DB::table('tenancy_invitation_locks')->where('tenancy_id', $tenancy->id)->delete();
            $tenancy->recordStatusChange($previous, $actor, $reason);

            $recipient = $actor->id === $tenancy->renter_id ? $tenancy->property->owner : $tenancy->renter;
            $this->notifier->send($recipient, 'tenancy.'.$status->value, __('Tenancy invitation :status', ['status' => $status->label()]), $reason, $actor->isOwner() ? route('renter.tenancies.show', $tenancy) : route('owner.tenancies.show', $tenancy), ['tenancy_id' => $tenancy->id], 'tenancy.'.$status->value.':'.$tenancy->id);
            $this->auditLogger->record('tenancy.'.$status->value, $tenancy, ['status' => $previous->value], ['status' => $status->value], $reason, $actor);

            return $tenancy;
        });
    }
}
