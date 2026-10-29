<?php

use App\Actions\Tenancies\EndTenancy;
use App\Enums\PropertyStatus;
use App\Enums\TenancyStatus;
use App\Models\Tenancy;
use App\Services\Notifier;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tenancies:expire-invitations', function () {
    $notifier = app(Notifier::class);
    Tenancy::query()->where('status', TenancyStatus::Invited->value)
        ->where('invitation_expires_at', '<=', now())->with(['renter', 'property.owner'])->chunkById(100, function ($invitations) use ($notifier) {
            foreach ($invitations as $tenancy) {
                DB::transaction(function () use ($tenancy, $notifier) {
                    $locked = Tenancy::whereKey($tenancy->id)->lockForUpdate()->first();
                    if (! $locked || $locked->status !== TenancyStatus::Invited) {
                        return;
                    }
                    $previous = $locked->status;
                    $locked->forceFill(['status' => TenancyStatus::Expired, 'status_reason' => 'invitation_expired'])->save();
                    DB::table('tenancy_invitation_locks')->where('tenancy_id', $locked->id)->delete();
                    $locked->recordStatusChange($previous, null, 'invitation_expired');
                    $notifier->send($locked->renter, 'tenancy.invitation_expired', __('Tenancy invitation expired'), $locked->property->title, route('renter.tenancies.show', $locked), ['tenancy_id' => $locked->id], 'tenancy.expired.renter:'.$locked->id);
                    $notifier->send($locked->property->owner, 'tenancy.invitation_expired', __('Tenancy invitation expired'), $locked->renter->name, route('owner.tenancies.show', $locked), ['tenancy_id' => $locked->id], 'tenancy.expired.owner:'.$locked->id);
                });
            }
        });
    $this->info('Expired tenancy invitations processed.');
})->purpose('Expire unanswered tenancy invitations safely');

Artisan::command('tenancies:finalize-notice-periods', function (EndTenancy $endTenancy) {
    Tenancy::query()->where('status', TenancyStatus::NoticePeriod->value)
        ->whereDate('end_date', '<=', today())->each(fn (Tenancy $tenancy) => $endTenancy->handle(
            $tenancy, null, $tenancy->end_date, PropertyStatus::Available, 'notice_period_completed'
        ));
    $this->info('Completed notice periods processed.');
})->purpose('End tenancies whose accepted notice period has completed');

Artisan::command('tenancies:conflicts', function () {
    $rows = DB::table('tenancy_conflicts')->whereNull('resolved_at')->get(['conflict_type', 'conflict_key', 'tenancy_ids']);
    $this->table(['Type', 'Renter/property ID', 'Tenancy IDs'], $rows->map(fn ($row) => [
        $row->conflict_type, $row->conflict_key, $row->tenancy_ids,
    ])->all());
})->purpose('List active-tenancy conflicts requiring administrator reconciliation');

Artisan::command('tenancies:reconcile {tenancy} {--reason=}', function () {
    $reason = trim((string) $this->option('reason'));
    if ($reason === '') {
        $this->error('A mandatory --reason is required.');

        return 1;
    }

    $valid = Tenancy::findOrFail((int) $this->argument('tenancy'));
    if (! $valid->status->occupiesProperty()) {
        $this->error('The selected tenancy is not active.');

        return 1;
    }

    DB::transaction(function () use ($valid, $reason) {
        $conflicts = Tenancy::query()->active()->whereKeyNot($valid->id)
            ->where(fn ($query) => $query->where('renter_id', $valid->renter_id)->orWhere('property_id', $valid->property_id))
            ->lockForUpdate()->get();
        foreach ($conflicts as $conflict) {
            $previous = $conflict->status;
            $conflict->forceFill(['status' => TenancyStatus::Cancelled, 'status_reason' => $reason, 'ended_at' => now(), 'end_reason' => $reason])->save();
            $conflict->recordStatusChange($previous, null, 'administrator_reconciliation', ['valid_tenancy_id' => $valid->id]);
            DB::table('tenancy_active_locks')->where('tenancy_id', $conflict->id)->delete();
            if (! $conflict->property->activeTenancy()->exists()) {
                $conflict->property->forceFill(['status' => PropertyStatus::Available])->save();
            }
        }
        DB::table('tenancy_active_locks')->where('tenancy_id', $valid->id)->delete();
        DB::table('tenancy_active_locks')->insert([
            'tenancy_id' => $valid->id, 'renter_id' => $valid->renter_id, 'property_id' => $valid->property_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('tenancy_conflicts')->whereNull('resolved_at')
            ->where(fn ($query) => $query->where(fn ($q) => $q->where('conflict_type', 'renter')->where('conflict_key', $valid->renter_id))
                ->orWhere(fn ($q) => $q->where('conflict_type', 'property')->where('conflict_key', $valid->property_id)))
            ->update(['resolved_at' => now(), 'resolution_reason' => $reason, 'updated_at' => now()]);
    });

    $this->info('Conflict reconciled; no records were deleted.');

    return 0;
})->purpose('Choose the valid active tenancy and preserve/cancel conflicting history');

Schedule::command('tenancies:expire-invitations')->hourly()->withoutOverlapping();
Schedule::command('tenancies:finalize-notice-periods')->dailyAt('00:15')->withoutOverlapping();

Schedule::command('push:dispatch-pending')->everyMinute()->withoutOverlapping();
Schedule::command('push:cleanup')->daily()->withoutOverlapping();

Schedule::command('notifications:payment-reminders')->weeklyOn(1, '09:00')->withoutOverlapping();
