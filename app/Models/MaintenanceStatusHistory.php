<?php

namespace App\Models;

use App\Enums\MaintenanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BR-008 - every maintenance status change is timestamped and audited.
 */
#[Fillable([
    'maintenance_request_id', 'from_status', 'to_status', 'changed_by', 'comment', 'changed_at',
])]
class MaintenanceStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'maintenance_status_history';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_status' => MaintenanceStatus::class,
            'to_status' => MaintenanceStatus::class,
            'changed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<MaintenanceRequest, $this> */
    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
