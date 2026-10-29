<?php

namespace App\Models;

use App\Enums\TerminationRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenancy_id', 'requested_by', 'proposed_end_date', 'reason', 'notes', 'status', 'responded_by', 'counter_end_date', 'response_notes', 'responded_at'])]
class TenancyTerminationRequest extends Model
{
    protected $table = 'tenancy_termination_requests';

    protected function casts(): array
    {
        return [
            'proposed_end_date' => 'date', 'counter_end_date' => 'date',
            'responded_at' => 'datetime', 'status' => TerminationRequestStatus::class,
        ];
    }

    public function tenancy(): BelongsTo
    {
        return $this->belongsTo(Tenancy::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
