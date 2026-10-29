<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['monthly_record_id', 'amount', 'received_on', 'reference', 'notes', 'recorded_by'])]
class MonthlyRecordPayment extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'received_on' => 'date'];
    }

    public function monthlyRecord(): BelongsTo
    {
        return $this->belongsTo(MonthlyRecord::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
