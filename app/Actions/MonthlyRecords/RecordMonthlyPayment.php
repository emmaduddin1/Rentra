<?php

namespace App\Actions\MonthlyRecords;

use App\Models\MonthlyRecord;
use App\Models\MonthlyRecordPayment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;

class RecordMonthlyPayment
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(MonthlyRecord $record, User $owner, array $data): MonthlyRecordPayment
    {
        return DB::transaction(function () use ($record, $owner, $data) {
            $payment = $record->payments()->create([...$data, 'recorded_by' => $owner->id]);
            $record->syncFinancials();
            $record->save();

            $this->auditLogger->record(
                action: 'monthly_record.payment_recorded',
                subject: $record,
                newValues: ['payment_id' => $payment->id, 'amount' => $payment->amount, 'received_on' => $payment->received_on->toDateString()],
                actor: $owner,
            );

            app(Notifier::class)->send($record->tenancy->renter, 'payment.recorded',
                'Payment recorded', 'A payment has been recorded.', data: ['monthly_record_id' => $record->id],
                idempotencyKey: 'payment.recorded:'.$payment->id);

            return $payment;
        });
    }
}
