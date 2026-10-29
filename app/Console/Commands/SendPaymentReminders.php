<?php

namespace App\Console\Commands;

use App\Models\MonthlyRecord;
use App\Services\Notifier;
use Illuminate\Console\Command;

class SendPaymentReminders extends Command
{
    protected $signature = 'notifications:payment-reminders';

    protected $description = 'Notify renters once per week for approved, overdue, outstanding records on active tenancies';

    public function handle(Notifier $notifier): int
    {
        // MonthlyRecord uses the end of the record month as the overdue boundary.
        MonthlyRecord::where('status', 'approved')->where('outstanding_receivable', '>', 0)
            ->whereDate('period_month', '<', now()->startOfMonth())
            ->whereHas('tenancy', fn ($query) => $query->whereIn('status', ['active', 'termination_requested', 'notice_period']))
            ->with('tenancy.renter')->eachById(function ($record) use ($notifier) {
                if ($record->tenancy->renter?->isActive()) {
                    $notifier->send($record->tenancy->renter, 'rent.reminder', 'Payment reminder',
                        'Please review your outstanding monthly record.', data: ['monthly_record_id' => $record->id],
                        idempotencyKey: 'rent.reminder:'.$record->id.':'.now()->format('o-W'));
                }
            });

        return self::SUCCESS;
    }
}
