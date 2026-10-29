<?php

namespace App\Models;

use App\Enums\TenancyStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenancy_id', 'previous_status', 'new_status', 'changed_by', 'reason', 'metadata', 'changed_at'])]
class TenancyStatusHistory extends Model
{
    protected function casts(): array
    {
        return [
            'previous_status' => TenancyStatus::class,
            'new_status' => TenancyStatus::class,
            'metadata' => 'array',
            'changed_at' => 'datetime',
        ];
    }

    public function tenancy(): BelongsTo
    {
        return $this->belongsTo(Tenancy::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
