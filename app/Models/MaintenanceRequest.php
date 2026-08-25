<?php

namespace App\Models;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use Database\Factories\MaintenanceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['tenancy_id', 'title', 'description', 'priority', 'status', 'created_by', 'completed_at'])]
class MaintenanceRequest extends Model
{
    /** @use HasFactory<MaintenanceRequestFactory> */
    use HasFactory, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'priority' => MaintenancePriority::class,
            'status' => MaintenanceStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenancy, $this> */
    public function tenancy(): BelongsTo
    {
        return $this->belongsTo(Tenancy::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<MaintenanceAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(MaintenanceAttachment::class);
    }

    /**
     * FR-MNT-004 - chronological timeline of status changes and comments.
     *
     * @return HasMany<MaintenanceStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(MaintenanceStatusHistory::class)->orderBy('changed_at')->orderBy('id');
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    /** @param  Builder<static>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [
            MaintenanceStatus::Pending->value,
            MaintenanceStatus::InProgress->value,
        ]);
    }

    /**
     * BR-010 - owner-scoped maintenance lists.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForOwner(Builder $query, User|int $owner): void
    {
        $ownerId = $owner instanceof User ? $owner->id : $owner;

        $query->whereHas(
            'tenancy.property',
            fn (Builder $property) => $property->where('owner_id', $ownerId)
        );
    }

    /** @param  Builder<static>  $query */
    public function scopeForRenter(Builder $query, User|int $renter): void
    {
        $renterId = $renter instanceof User ? $renter->id : $renter;

        $query->whereHas('tenancy', fn (Builder $tenancy) => $tenancy->where('renter_id', $renterId));
    }
}
