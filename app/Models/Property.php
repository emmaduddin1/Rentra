<?php

namespace App\Models;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\PublicationStatus;
use App\Enums\RentalMode;
use App\Enums\TenancyStatus;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'owner_id', 'parent_id', 'type', 'rental_mode', 'title', 'unit_label', 'floor_number',
    'rent_amount', 'area', 'city', 'address', 'description',
    'contact_name', 'contact_phone', 'contact_email', 'status', 'publication_status', 'published_at',
])]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The building-level fields a floor always shares with its house.
     *
     * A floor sits inside its house, so its type and address cannot diverge;
     * they are copied down on create and kept in sync on update.
     *
     * @var list<string>
     */
    public const INHERITED_FROM_HOUSE = [
        'type', 'area', 'city', 'address', 'contact_name', 'contact_phone', 'contact_email',
    ];

    /**
     * Mirrors the column default, so a listing is an independent unit before it
     * has ever been saved.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'rental_mode' => RentalMode::Whole->value,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => PropertyType::class,
            'rental_mode' => RentalMode::class,
            'floor_number' => 'integer',
            'status' => PropertyStatus::class,
            'publication_status' => PublicationStatus::class,
            'rent_amount' => 'decimal:2',
            'published_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The house this floor belongs to; null on a top-level property.
     *
     * @return BelongsTo<Property, $this>
     */
    public function house(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'parent_id');
    }

    /**
     * FR-PROP-001 - the separately rentable floors of this house.
     *
     * @return HasMany<Property, $this>
     */
    public function floors(): HasMany
    {
        return $this->hasMany(Property::class, 'parent_id')
            ->orderBy('floor_number')
            ->orderBy('id');
    }

    /** @return HasMany<PropertyImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasOne<PropertyImage, $this> */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(PropertyImage::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * FR-PROP-003 - walkthrough clips, kept apart from the gallery.
     *
     * @return HasMany<PropertyVideo, $this>
     */
    public function videos(): HasMany
    {
        return $this->hasMany(PropertyVideo::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<Tenancy, $this> */
    public function tenancies(): HasMany
    {
        return $this->hasMany(Tenancy::class);
    }

    /**
     * BR-002 - at most one active tenancy per property.
     *
     * @return HasOne<Tenancy, $this>
     */
    public function activeTenancy(): HasOne
    {
        return $this->hasOne(Tenancy::class)->where('status', TenancyStatus::Active->value);
    }

    /** @return HasMany<Application, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /** @return HasMany<Favorite, $this> */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /*
    |--------------------------------------------------------------------------
    | State helpers
    |--------------------------------------------------------------------------
    */

    /**
     * BR-001 - only Published + Available properties are visible to renters,
     * and BR-013 keeps a house that is split into floors out of the feed.
     */
    public function isPubliclyVisible(): bool
    {
        return $this->isRentable()
            && $this->publication_status === PublicationStatus::Published
            && $this->status === PropertyStatus::Available;
    }

    /**
     * BR-013 - a house rented floor by floor is a container: it is published,
     * applied for, and rented through its floors, never on its own.
     */
    public function isRentable(): bool
    {
        return ($this->rental_mode ?? RentalMode::Whole)->isRentable();
    }

    public function isSplitIntoFloors(): bool
    {
        return $this->rental_mode === RentalMode::ByFloor;
    }

    public function isFloor(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * The stored title of a floor, so every existing screen that prints
     * $property->title reads "Gulberg Villa - Ground Floor" unchanged.
     */
    public static function composeFloorTitle(self $house, string $unitLabel): string
    {
        return trim($house->title).' - '.trim($unitLabel);
    }

    public function isOccupied(): bool
    {
        return $this->status === PropertyStatus::Occupied;
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * BR-001 - the public listing feed.
     *
     * @param  Builder<static>  $query
     */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->rentable()
            ->where('publication_status', PublicationStatus::Published->value)
            ->where('status', PropertyStatus::Available->value);
    }

    /**
     * BR-013 - everything a renter or a tenancy may point at: independent
     * properties and individual floors, but not the houses that hold floors.
     *
     * @param  Builder<static>  $query
     */
    public function scopeRentable(Builder $query): void
    {
        $query->where('rental_mode', RentalMode::Whole->value);
    }

    /**
     * Houses and independent properties, without the floors nested under them.
     *
     * @param  Builder<static>  $query
     */
    public function scopeTopLevel(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * BR-010 - an owner may only reach their own portfolio.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOwnedBy(Builder $query, User|int $owner): void
    {
        $query->where('owner_id', $owner instanceof User ? $owner->id : $owner);
    }

    /**
     * FR-APP-002 - area search.
     *
     * @param  Builder<static>  $query
     */
    public function scopeInArea(Builder $query, ?string $area): void
    {
        $area = trim((string) $area);

        if ($area === '') {
            return;
        }

        $query->where(function (Builder $query) use ($area) {
            $query->where('area', 'like', "%{$area}%")
                ->orWhere('city', 'like', "%{$area}%");
        });
    }
}
