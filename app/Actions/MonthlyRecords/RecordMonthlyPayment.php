<?php

namespace App\Actions\MonthlyRecords;

use App\Models\MonthlyRecord;
use App\Models\MonthlyRecordPayment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecordMonthlyPayment
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(MonthlyRecord $record, User $owner, array $data): MonthlyRecordPayment
    {
        return DB::transaction(function () use ($record, $owner, $data) {
            $record->refreshForUpdate();
            if (! $owner->isOwner() || $record->tenancy->ownerId() !== $owner->id) {
                throw new AuthorizationException;
            }
            if (! empty($data['idempotency_key'])) {
                $existing = $record->payments()->where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    $same = bccomp((string) $existing->amount, (string) $data['amount'], 2) === 0
                        && $existing->received_on->toDateString() === Carbon::parse($data['received_on'])->toDateString()
                        && (string) $existing->reference === (string) ($data['reference'] ?? null)
                        && (string) $existing->notes === (string) ($data['notes'] ?? null);
                    abort_unless($same, 409, __('This payment request was already used with different details.'));

                    return $existing;
                }
            }
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
